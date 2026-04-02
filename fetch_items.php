<?php
session_start();
require 'config.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$user_id  = (int) $_SESSION['user_id'];
$is_admin = !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$type     = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'all';
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$mine     = isset($_GET['mine']) && $_GET['mine'] === '1';
$adminAll = isset($_GET['admin']) && $_GET['admin'] === '1';

// ------------------------------------------------------------
// Helper functions (matching user scoring)
// ------------------------------------------------------------
function normalize_text(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}0-9]+/u', ' ', $text);
    return trim($text);
}

function tokens_from_text(string $text): array {
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

function text_similarity(array $a, array $b): float {
    if (!$a || !$b) return 0.0;
    $intersection = count(array_intersect($a, $b));
    return $intersection / max(count($a), count($b));
}

function tokens_from_path(string $path): array {
    $filename = pathinfo($path, PATHINFO_FILENAME);
    return tokens_from_text($filename);
}

function image_similarity(array $a, array $b): float {
    if (!$a || !$b) return 0.0;
    if ($a === $b) return 1.0;
    $intersection = count(array_intersect($a, $b));
    return $intersection / max(count($a), count($b));
}

function color_similarity(?string $c1, ?string $c2): float {
    if (empty($c1) || empty($c2)) return 0.0;
    $c1 = strtolower(trim($c1));
    $c2 = strtolower(trim($c2));
    return ($c1 === $c2) ? 1.0 : 0.0;
}

function location_similarity($loc1, $loc2): float {
    $loc1 = normalize_text($loc1);
    $loc2 = normalize_text($loc2);
    if ($loc1 === $loc2) return 1.0;
    $words1 = array_filter(explode(' ', $loc1));
    $words2 = array_filter(explode(' ', $loc2));
    $common = array_intersect($words1, $words2);
    if (!empty($common)) return 0.5;
    return 0.0;
}

function date_proximity($date1, $date2): float {
    if (!$date1 || !$date2) return 0.0;
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    $diff = abs($d1->diff($d2)->days);
    if ($diff <= 7) return 1.0;
    if ($diff <= 30) return 0.5;
    return 0.0;
}

function description_similarity($desc1, $desc2): float {
    $tokens1 = tokens_from_text($desc1 ?? '');
    $tokens2 = tokens_from_text($desc2 ?? '');
    return text_similarity($tokens1, $tokens2);
}

function compute_match_score(array $source, array $target): int {
    // Category (20)
    $categoryScore = 0;
    if (!empty($source['category']) && !empty($target['category']) && mb_strtolower($source['category']) === mb_strtolower($target['category'])) {
        $categoryScore = 20;
    }

    // Name tokens (25)
    $nameTokens   = tokens_from_text($source['item_name'] ?? '');
    $targetName   = tokens_from_text($target['item_name'] ?? '');
    $nameScore = (int) round(text_similarity($nameTokens, $targetName) * 25);

    // Description (20)
    $descTokens   = tokens_from_text($source['description'] ?? '');
    $targetDesc   = tokens_from_text($target['description'] ?? '');
    $descScore = (int) round(text_similarity($descTokens, $targetDesc) * 20);

    // Color (15)
    $colorScore = (int) round(color_similarity($source['color'] ?? null, $target['color'] ?? null) * 15);

    // Location (15)
    $locScore = (int) round(location_similarity($source['location'] ?? '', $target['location'] ?? '') * 15);

    // Date (5)
    $dateScore = (int) round(date_proximity($source['date'] ?? '', $target['date'] ?? '') * 5);

    $total = $categoryScore + $nameScore + $descScore + $colorScore + $locScore + $dateScore;
    return $total > 10 ? min(100, $total) : 0;
}

// ------------------------------------------------------------
// Validation
// ------------------------------------------------------------
if (!in_array($type, ['all', 'lost', 'found'], true)) {
    echo json_encode(['error' => 'Invalid type.']);
    exit();
}

if ($adminAll && !$is_admin) {
    echo json_encode(['error' => 'Access denied.']);
    exit();
}

// ------------------------------------------------------------
// Regular user "my items" (lost only)
// ------------------------------------------------------------
if ($mine && !$is_admin) {
    $where  = ['li.user_id = ?'];
    $params = [$user_id];
    $types  = 'i';

    if ($search !== '') {
        $where[]  = '(li.item_name LIKE ? OR li.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types   .= 'ss';
    }
    if ($category !== '') {
        $where[]  = 'li.category = ?';
        $params[] = $category;
        $types   .= 's';
    }

    $sql = "SELECT li.id, li.item_name, li.description, li.category, li.color, li.unique_details,
                   li.location_lost AS location, li.date_lost AS date,
                   li.image_path, li.status, li.resolved,
                   u.full_name, u.email, 'lost' AS type,
                   CASE WHEN li.resolved = 1 THEN 1 ELSE 0 END AS has_match
            FROM lost_items li
            JOIN users u ON li.user_id = u.id
            WHERE " . implode(' AND ', $where) . '
            ORDER BY li.date_lost DESC';

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $output = [];
    while ($row = $result->fetch_assoc()) {
        if (empty($row['image_path'])) {
            $row['image_path'] = null;
        }
        $row['has_match'] = (int) $row['has_match'] === 1;
        $output[] = $row;
    }
    $stmt->close();
    echo json_encode($output);
    exit();
}

// ------------------------------------------------------------
// Admin: all lost + all found
// ------------------------------------------------------------
if ($adminAll && $is_admin) {
    $sqlParts = [];
    $params   = [];
    $types    = '';

    // Lost items
    if ($type === 'lost' || $type === 'all') {
        $where = ['li.resolved = 0'];
        $sql   = "SELECT li.id, li.item_name, li.description, li.category, li.color, li.unique_details,
                         li.location_lost AS location, li.date_lost AS date,
                         li.image_path, li.status, li.resolved,
                         NULL AS is_active, NULL AS approval_hold,
                         u.full_name, u.email, 'lost' AS type
                  FROM lost_items li
                  JOIN users u ON li.user_id = u.id";
        if ($search !== '') {
            $where[]  = '(li.item_name LIKE ? OR li.description LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types   .= 'ss';
        }
        if ($category !== '') {
            $where[]  = 'li.category = ?';
            $params[] = $category;
            $types   .= 's';
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sqlParts[] = $sql;
    }

    // Found items
    if ($type === 'found' || $type === 'all') {
        $where = ['fi.is_active = 1'];
        $sql   = "SELECT fi.id, fi.item_name, fi.description, fi.category, fi.color, fi.unique_details,
                         fi.location_found AS location, fi.date_found AS date,
                         fi.image_path, fi.status, NULL AS resolved,
                         fi.is_active, fi.approval_hold,
                         u.full_name, u.email, 'found' AS type
                  FROM found_items fi
                  JOIN users u ON fi.user_id = u.id";
        if ($search !== '') {
            $where[]  = '(fi.item_name LIKE ? OR fi.description LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $types   .= 'ss';
        }
        if ($category !== '') {
            $where[]  = 'fi.category = ?';
            $params[] = $category;
            $types   .= 's';
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sqlParts[] = $sql;
    }

    if (empty($sqlParts)) {
        echo json_encode([]);
        exit();
    }

    $finalSql = 'SELECT * FROM (' . implode(' UNION ALL ', $sqlParts) . ') AS combined ORDER BY date DESC';
    $stmt     = $mysqli->prepare($finalSql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $allLost = [];
    $allFound = [];
    $output   = [];

    while ($row = $res->fetch_assoc()) {
        if (empty($row['image_path'])) {
            $row['image_path'] = null;
        }
        if ($row['type'] === 'lost') {
            $allLost[] = $row;
        } else {
            $allFound[] = $row;
        }
    }

    // For each lost item, compute best match score against found items
    foreach ($allLost as $lostRow) {
        $bestScore = 0;
        $matchCount = 0;
        foreach ($allFound as $foundRow) {
            $score = compute_match_score($lostRow, $foundRow);
            if ($score > 0) {
                $matchCount++;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }
        $lostRow['match_score'] = $bestScore > 0 ? $bestScore : null;
        $lostRow['match_count'] = $matchCount;
        $output[] = $lostRow;
    }

    // For each found item, compute best match score against lost items
    foreach ($allFound as $foundRow) {
        $bestScore = 0;
        $matchCount = 0;
        foreach ($allLost as $lostRow) {
            $score = compute_match_score($foundRow, $lostRow);
            if ($score > 0) {
                $matchCount++;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }
        $foundRow['match_score'] = $bestScore > 0 ? $bestScore : null;
        $foundRow['match_count'] = $matchCount;
        $output[] = $foundRow;
    }

    $stmt->close();
    echo json_encode($output);
    exit();
}

// ------------------------------------------------------------
// Legacy: logged-in user viewing combined (non-admin) – lost only
// ------------------------------------------------------------
$sqlParts = [];
$params   = [];
$types    = '';

if ($type === 'lost' || $type === 'all') {
    $where = ['li.user_id = ?'];
    $params[] = $user_id;
    $types   .= 'i';
    $sql = "SELECT li.id, li.item_name, li.description, li.category, li.color, li.unique_details,
                   li.location_lost AS location, li.date_lost AS date,
                   li.image_path, li.status, u.full_name, u.email, 'lost' AS type
            FROM lost_items li
            JOIN users u ON li.user_id = u.id";
    if ($search !== '') {
        $where[]  = '(li.item_name LIKE ? OR li.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types   .= 'ss';
    }
    if ($category !== '') {
        $where[]  = 'li.category = ?';
        $params[] = $category;
        $types   .= 's';
    }
    $sql .= ' WHERE ' . implode(' AND ', $where);
    $sqlParts[] = $sql;
}

if (($type === 'found' || $type === 'all') && $is_admin) {
    $where = ['fi.user_id = ?'];
    $params[] = $user_id;
    $types   .= 'i';
    $sql = "SELECT fi.id, fi.item_name, fi.description, fi.category, fi.color, fi.unique_details,
                   fi.location_found AS location, fi.date_found AS date,
                   fi.image_path, fi.status, u.full_name, u.email, 'found' AS type
            FROM found_items fi
            JOIN users u ON fi.user_id = u.id";
    if ($search !== '') {
        $where[]  = '(fi.item_name LIKE ? OR fi.description LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
        $types   .= 'ss';
    }
    if ($category !== '') {
        $where[]  = 'fi.category = ?';
        $params[] = $category;
        $types   .= 's';
    }
    $sql .= ' WHERE ' . implode(' AND ', $where);
    $sqlParts[] = $sql;
}

if (empty($sqlParts)) {
    echo json_encode([]);
    exit();
}

$finalSql = 'SELECT * FROM (' . implode(' UNION ALL ', $sqlParts) . ') AS combined ORDER BY date DESC';
$stmt     = $mysqli->prepare($finalSql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$output = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['image_path'])) {
        $row['image_path'] = null;
    }
    $output[] = $row;
}
$stmt->close();
echo json_encode($output);