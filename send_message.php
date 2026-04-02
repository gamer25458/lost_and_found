<?php
// send_message.php — sends a message between a user and admin

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

$data        = json_decode(file_get_contents('php://input'), true);
$message     = isset($data['message'])     ? trim($data['message'])     : '';
$receiver_id = isset($data['receiver_id']) ? (int) $data['receiver_id'] : 0;

if ($message === '' || $receiver_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Message and receiver are required.']);
    exit();
}

// Basic length guard — prevents accidental or malicious oversized inserts
if (mb_strlen($message) > 5000) {
    echo json_encode(['success' => false, 'error' => 'Message is too long (max 5000 characters).']);
    exit();
}

$sender_id = (int) $_SESSION['user_id'];

// Prevent a user messaging themselves
if ($sender_id === $receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Cannot send a message to yourself.']);
    exit();
}

$stmt = $mysqli->prepare(
    'INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)'
);
$stmt->bind_param('iis', $sender_id, $receiver_id, $message);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
}
