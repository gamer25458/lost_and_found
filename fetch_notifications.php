<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $mysqli->prepare(
    "SELECT id, title, message, preview, link, ref_type, ref_id, is_read, created_at
     FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 100"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    if (empty($row['title'])) {
        $row['title'] = 'Notification';
    }
    if (empty($row['preview'])) {
        $row['preview'] = mb_substr(strip_tags($row['message']), 0, 120);
    }
    $notifications[] = $row;
}
$stmt->close();

$stmt = $mysqli->prepare(
    'SELECT COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$unread = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

echo json_encode(['notifications' => $notifications, 'unread_count' => $unread]);
