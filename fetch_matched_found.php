<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    echo json_encode([
        'error' => 'Log in as a regular user (not admin) to see matching found items and submit claims. Open a private window and register a test user.',
    ]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$search  = isset($_GET['search']) ? trim($_GET['search']) : '';
$cat     = isset($_GET['category']) ? trim($_GET['category']) : '';

// Helper functions (same as in fetch_items.php)
function normalize_text($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}0-9]+/u', ' ', $text);
    return trim($text);
}

function tokens_from_text($text) {
    $text = normalize_text($text);
    $tokens = array_filter(array_unique(explode(' ', $text)), fn($word) => $word !== '');
    $stopwords = [
        'the','and','for','with','that','from','your','this','have','where','when','here','there','item','items','lost','found',
        'red','blue','green','yellow','black','white','gray','grey','pink','purple','orange','brown','silver','gold','beige',
        'apple','samsung','sony','dell','hp','lenovo','asus','microsoft','nokia','google','amazon','xiaomi','oneplus','lg','philips',
        'bose','jbl','anker','logitech','canon','nikon','fujifilm','adidas','nike','puma','rebook','levi','guess','zara','h&m'
    ];
    return array_values(array_filter($tokens, fn($word) => !in_array($word, $stopwords, true)));
}

function text_similarity($a, $b) {
    if (!$a || !$b) return 0.0;
    $intersection = count(array_intersect($a, $b));
    return $intersection / max(count($a), count($b));
}

function color_similarity($c1, $c2) {
    if (empty($c1) || empty($c2)) return 0.0;
    $c1 = strtolower(trim($c1));
    $c2 = strtolower(trim($c2));
    if ($c1 === $c2) return 1.0;
    return 0.0;
}

function location_similarity($loc1, $loc2) {
    $loc1 = normalize_text($loc1);
    $loc2 = normalize_text($loc2);
    if ($loc1 === $loc2) return 1.0;
    $words1 = array_filter(explode(' ', $loc1));
    $words2 = array_filter(explode(' ', $loc2));
    $common = array_intersect($words1, $words2);
    if (!empty($common)) return 0.5;
    return 0.0;
}

function date_proximity($date1, $date2) {
    if (!$date1 || !$date2) return 0.0;
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    $diff = abs($d1->diff($d2)->days);
    if ($diff <= 7) return 1.0;
    if ($diff <= 30) return 0.5;
    return 0.0;
}

// 1. Fetch all unresolved lost items for this user
$lostStmt = $mysqli->prepare(
    'SELECT id, item_name, description, category, color, location_lost, date_lost, unique_details
     FROM lost_items
     WHERE user_id = ? AND resolved = 0'
);
$lostStmt->bind_param('i', $user_id);
$lostStmt->execute();
$lostItems = $lostStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$lostStmt->close();

if (empty($lostItems)) {
    echo json_encode([]);
    exit();
}

// Precompute lost data
$lostData = [];
foreach ($lostItems as $lost) {
    $lostData[] = [
        'id'          => $lost['id'],
        'category'    => $lost['category'],
        'name_tokens' => tokens_from_text($lost['item_name']),
        'desc_tokens' => tokens_from_text($lost['description'] ?? ''),
        'color'       => $lost['color'],
        'location'    => $lost['location_lost'] ?? '',
        'date'        => $lost['date_lost'] ?? '',
        'unique_details' => $lost['unique_details'] ?? ''
    ];
}

// 2. Build SQL to fetch candidate found items (same category)
$sql = "SELECT fi.id, fi.item_name, fi.description, fi.category, fi.color,
               fi.location_found AS location, fi.date_found AS date,
               fi.image_path, fi.is_active, fi.approval_hold, fi.unique_details,
               u.full_name AS posted_by_name,
               (SELECT c.id FROM claims c
                WHERE c.found_item_id = fi.id AND c.user_id = ?
                ORDER BY c.id DESC LIMIT 1) AS my_claim_id,
               (SELECT c.status FROM claims c
                WHERE c.found_item_id = fi.id AND c.user_id = ?
                ORDER BY c.id DESC LIMIT 1) AS my_claim_status
        FROM found_items fi
        JOIN users u ON fi.user_id = u.id
        WHERE fi.is_active = 1
        AND fi.category IN (
            SELECT DISTINCT li.category FROM lost_items li
            WHERE li.user_id = ? AND li.resolved = 0
            AND IFNULL(li.category, '') <> ''
        )";

$params = [$user_id, $user_id, $user_id];
$types  = 'iii';

if ($search !== '') {
    $sql     .= ' AND (fi.item_name LIKE ? OR fi.description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types   .= 'ss';
}
if ($cat !== '') {
    $sql     .= ' AND fi.category = ?';
    $params[] = $cat;
    $types   .= 's';
}

$sql .= ' ORDER BY fi.date_found DESC';

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$foundRows = [];
while ($row = $res->fetch_assoc()) {
    $foundRows[] = $row;
}
$stmt->close();

// 3. For each found item, compute best match score against any lost item
$output = [];
$matchThreshold = 40; // minimum score to be considered a match

foreach ($foundRows as $found) {
    $foundNameTokens = tokens_from_text($found['item_name']);
    $foundDescTokens = tokens_from_text($found['description'] ?? '');
    $bestScore = 0;
    $bestLostId = null;

    foreach ($lostData as $lost) {
        if ($lost['category'] !== $found['category']) continue;

        // Weights: category 20, name 30, description 30, color 10, location 5, date 5 = 100
        $categoryScore = 20;

        $nameSim = text_similarity($lost['name_tokens'], $foundNameTokens);
        $nameScore = $nameSim * 30;

        $descSim = text_similarity($lost['desc_tokens'], $foundDescTokens);
        $descScore = $descSim * 30;

        $colorSim = color_similarity($lost['color'], $found['color']);
        $colorScore = $colorSim * 10;

        $locSim = location_similarity($lost['location'], $found['location']);
        $locationScore = $locSim * 5;

        $dateSim = date_proximity($lost['date'], $found['date']);
        $dateScore = $dateSim * 5;

        $totalScore = $categoryScore + $nameScore + $descScore + $colorScore + $locationScore + $dateScore;

        if ($totalScore > $bestScore) {
            $bestScore = $totalScore;
            $bestLostId = $lost['id'];
        }
    }

    if ($bestScore >= $matchThreshold) {
        $found['match_score'] = round($bestScore);
        $found['matched_lost_id'] = $bestLostId;
        $approvedMine = ($found['my_claim_status'] === 'approved');
        $found['claim_blocked'] = ((int) $found['approval_hold'] === 1 && !$approvedMine);
        $output[] = $found;
    }
}

echo json_encode($output);