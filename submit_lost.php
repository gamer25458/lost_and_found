<?php
session_start();
require 'config.php';
require_once 'helpers.php';

// Must be a logged-in non-admin posting a form
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.html');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../lost.html');
    exit();
}

if (!empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: ../lost.html?error=' . urlencode('Only regular users can report lost items. Admins report found items from the admin panel.'));
    exit();
}

try {
    $user_id        = (int) $_SESSION['user_id'];
    $username       = isset($_SESSION['username']) ? trim($_SESSION['username']) : '';
    $item_name      = trim($_POST['item_name']     ?? '');
    $description    = trim($_POST['description']   ?? '');
    $category       = trim($_POST['category']      ?? '');
    $color          = trim($_POST['color']         ?? '');
    $location_lost  = trim($_POST['location_lost'] ?? '');
    $date_lost      = trim($_POST['date_lost']     ?? '') ?: date('Y-m-d');
    $unique_details = trim($_POST['unique_details'] ?? '');

    if ($item_name === '') {
        throw new Exception('Item name is required.');
    }
    if ($category === '') {
        throw new Exception('Please select a category so we can match found items to your report.');
    }

    // Validate date is not in the future
    if ($date_lost > date('Y-m-d')) {
        throw new Exception('Date lost cannot be in the future.');
    }

    // Generate a simple session ID (user ID + location + date)
    $session_id = md5($user_id . $location_lost . $date_lost);

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

    $lostStatus = 'lost';

    $stmt = $mysqli->prepare(
        'INSERT INTO lost_items
            (user_id, item_name, description, category, color, location_lost, date_lost, session_id,
             image_path, status, resolved, unique_details)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)'
    );
    $stmt->bind_param(
        'issssssssss',
        $user_id, $item_name, $description, $category, $color,
        $location_lost, $date_lost, $session_id, $image_path, $lostStatus, $unique_details
    );
    $stmt->execute();
    $newId = (int) $mysqli->insert_id;
    $stmt->close();

    // Save all photos to lost_item_photos
    if (!empty($photo_paths)) {
        $photoStmt = $mysqli->prepare('INSERT INTO lost_item_photos (lost_item_id, path, sort_order) VALUES (?, ?, ?)');
        foreach ($photo_paths as $i => $path) {
            $photoStmt->bind_param('isi', $newId, $path, $i);
            $photoStmt->execute();
        }
        $photoStmt->close();
    }

    $userDetail = $username !== '' ? ('User: ' . $username) : ('User ID: ' . $user_id);
    notify_all_admins(
        $mysqli,
        'New lost item report',
        $userDetail . ' reported a lost item: ' . $item_name . ' (' . $category . ')',
        $userDetail . ' · Lost: ' . $item_name . ' · ' . $category,
        'admin.php?tab=reported',
        'lost_item',
        $newId
    );

    header('Location: ../items.html?success=1');

} catch (Exception $e) {
    logError('submit_lost', $e);
    header('Location: ../lost.html?error=' . urlencode($e->getMessage()));
}

exit();