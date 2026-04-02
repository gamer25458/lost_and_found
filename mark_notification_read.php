<?php
// mark_notification_read.php — marks a single notification as read

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data    = json_decode(file_get_contents('php://input'), true);
$id      = isset($data['id']) ? (int) $data['id'] : 0;
$user_id = (int) $_SESSION['user_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid notification ID.']);
    exit();
}

// user_id check ensures a user cannot mark another user's notifications as read
$stmt = $mysqli->prepare(
    'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?'
);
$stmt->bind_param('ii', $id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Notification not found or already read.']);
}
