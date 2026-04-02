<?php
session_start();
require 'config.php';
require_once 'helpers.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../found.html');
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: ../found.html?error=' . urlencode('Only administrators can report found items.'));
    exit();
}

try {
    $user_id        = (int) $_SESSION['user_id'];
    $item_name      = trim($_POST['item_name']      ?? '');
    $description    = trim($_POST['description']    ?? '');
    $category       = trim($_POST['category']       ?? '');
    $color          = trim($_POST['color']          ?? '');
    $location_found = trim($_POST['location_found'] ?? '');
    $date_found     = trim($_POST['date_found']     ?? '') ?: date('Y-m-d');
    $unique_details = trim($_POST['unique_details'] ?? '');

    if ($item_name === '') {
        throw new Exception('Item name is required.');
    }
    if ($category === '') {
        throw new Exception('Please select a category so users with matching lost reports can see this item.');
    }

    if ($date_found > date('Y-m-d')) {
        throw new Exception('Date found cannot be in the future.');
    }

    // Image upload handling (same as before)
    $image_path = null;
    $photo_paths = [];
    $main_photo_set = false;
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif'];
        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        foreach ($_FILES['images']['tmp_name'] as $idx => $tmp) {
            if ($_FILES['images']['error'][$idx] !== UPLOAD_ERR_OK) continue;
            if (getimagesize($tmp) === false) continue;
            $origName = $_FILES['images']['name'][$idx];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExt, true)) continue;
            $baseName = pathinfo($origName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
            $filename = $safeBase . '_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
            if (!move_uploaded_file($tmp, $uploadDir . '/' . $filename)) continue;
            $relPath = 'uploads/' . $filename;
            $photo_paths[] = $relPath;
            if (!$main_photo_set) {
                $image_path = $relPath;
                $main_photo_set = true;
            }
        }
    }

    $foundStatus   = 'found';
    $isActive      = 1;
    $approvalHold  = 0;

    $stmt = $mysqli->prepare(
        'INSERT INTO found_items
            (user_id, item_name, description, category, color, location_found, date_found,
             image_path, status, is_active, approval_hold, unique_details)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'issssssssiis',
        $user_id, $item_name, $description, $category, $color,
        $location_found, $date_found, $image_path,
        $foundStatus, $isActive, $approvalHold, $unique_details
    );
    $stmt->execute();
    $foundId = (int) $mysqli->insert_id;
    $stmt->close();

    // Save all photos to found_item_photos
    if (!empty($photo_paths)) {
        $photoStmt = $mysqli->prepare('INSERT INTO found_item_photos (found_item_id, path, sort_order) VALUES (?, ?, ?)');
        foreach ($photo_paths as $i => $path) {
            $photoStmt->bind_param('isi', $foundId, $path, $i);
            $photoStmt->execute();
        }
        $photoStmt->close();
    }

    // --- Matching and notification logic ---
    // We need to find users with unresolved lost items in the same category,
    // compute a match score using the same logic as fetch_matched_found.php,
    // and notify those with score >= 40.

    // Helper functions (copy the same tokenization and similarity functions used above)
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

    // Fetch all users with unresolved lost items in the same category
    $lostStmt = $mysqli->prepare(
        "SELECT l.id AS lost_id, l.user_id, l.item_name, l.description, l.category, l.color, l.location_lost, l.date_lost,
                u.full_name
         FROM lost_items l
         JOIN users u ON l.user_id = u.id
         WHERE l.category = ? AND l.resolved = 0"
    );
    $lostStmt->bind_param('s', $category);
    $lostStmt->execute();
    $lostItems = $lostStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $lostStmt->close();

    // Prepare found item tokens once
    $foundNameTokens = tokens_from_text($item_name);
    $foundDescTokens = tokens_from_text($description);

    $matchThreshold = 40;
    $notifiedUsers = []; // to avoid duplicate notifications per user (though each lost item is separate)

    foreach ($lostItems as $lost) {
        $lostNameTokens = tokens_from_text($lost['item_name']);
        $lostDescTokens = tokens_from_text($lost['description'] ?? '');

        $categoryScore = 20; // same category

        $nameSim = text_similarity($lostNameTokens, $foundNameTokens);
        $nameScore = $nameSim * 30;

        $descSim = text_similarity($lostDescTokens, $foundDescTokens);
        $descScore = $descSim * 30;

        $colorSim = color_similarity($lost['color'], $color);
        $colorScore = $colorSim * 10;

        $locSim = location_similarity($lost['location_lost'], $location_found);
        $locationScore = $locSim * 5;

        $dateSim = date_proximity($lost['date_lost'], $date_found);
        $dateScore = $dateSim * 5;

        $totalScore = $categoryScore + $nameScore + $descScore + $colorScore + $locationScore + $dateScore;

        if ($totalScore >= $matchThreshold) {
            // Notify this user
            $userLink = 'items.html?tab=found';
            notify_user(
                $mysqli,
                (int) $lost['user_id'],
                'Possible match: item found',
                'An item was found that may match your lost report: ' . $item_name . '. Open Found items to review.',
                'Found: ' . $item_name . ' · ' . $category . ' (Match: ' . round($totalScore) . '%)',
                $userLink,
                'found_item',
                $foundId
            );
            $notifiedUsers[] = $lost['user_id'];
        }
    }

    header('Location: ../admin.php?tab=reported&success=1');

} catch (Exception $e) {
    logError('submit_found', $e);
    header('Location: ../found.html?error=' . urlencode($e->getMessage()));
}

exit();