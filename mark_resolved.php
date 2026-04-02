<?php
// mark_resolved.php — admin manually marks an item as resolved

session_start();
require 'config.php';
require_once 'helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Admins only.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data      = json_decode(file_get_contents('php://input'), true);
$item_id   = isset($data['item_id'])   ? (int) $data['item_id']      : 0;
$item_type = isset($data['item_type']) ? trim($data['item_type'])     : '';

if ($item_id <= 0 || !in_array($item_type, ['lost', 'found'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data.']);
    exit();
}

// Whitelist table and status — never interpolate user input into SQL
if ($item_type === 'lost') {
    $table      = 'lost_items';
    $new_status = 'found';
} else {
    $table      = 'found_items';
    $new_status = 'claimed';
}

try {
    // Fetch item details using a fully parameterised query
    // (table name is safe — it came from the whitelist above)
    $stmt = $mysqli->prepare("SELECT user_id, item_name FROM {$table} WHERE id = ?");
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Item not found.']);
        exit();
    }

    $owner_id  = (int) $row['user_id'];
    $item_name = $row['item_name'];

    // Update the item status
    $stmt = $mysqli->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $new_status, $item_id);
    $stmt->execute();
    $stmt->close();

    // Notify the item owner using the shared helper so the notification
    // schema (title, preview, link, ref_type, ref_id) is filled correctly.
    if ($item_type === 'lost') {
        $title   = 'Lost item marked as found';
        $message = "Good news! Your lost item \"{$item_name}\" has been marked as found by an admin.";
        $preview = 'Your item was found — tap to view';
        $link    = 'items.html?tab=lost';
    } else {
        $title   = 'Found item marked as claimed';
        $message = "Your found item \"{$item_name}\" has been marked as claimed.";
        $preview = 'Item marked as claimed';
        $link    = 'items.html';
    }

    notify_user($mysqli, $owner_id, $title, $message, $preview, $link, $item_type . '_item', $item_id);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    logError('mark_resolved', $e);
    echo json_encode(['success' => false, 'error' => 'An error occurred. Please try again.']);
}