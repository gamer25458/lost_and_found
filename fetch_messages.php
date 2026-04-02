<?php
// fetch_messages.php — fetches messages between a user and admin

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$user_id  = (int) $_SESSION['user_id'];
$other_id = isset($_GET['with']) ? (int) $_GET['with'] : 0;

if ($other_id <= 0) {
    echo json_encode(['error' => 'Invalid user.']);
    exit();
}

// Mark incoming messages from the other party as read
$stmt = $mysqli->prepare(
    'UPDATE messages SET is_read = 1
     WHERE receiver_id = ? AND sender_id = ? AND is_read = 0'
);
$stmt->bind_param('ii', $user_id, $other_id);
$stmt->execute();
$stmt->close();

// Fetch the full conversation in chronological order
$stmt = $mysqli->prepare(
    'SELECT m.id, m.message, m.is_read, m.created_at,
            m.sender_id, u.full_name AS sender_name
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE (m.sender_id = ? AND m.receiver_id = ?)
        OR (m.sender_id = ? AND m.receiver_id = ?)
     ORDER BY m.created_at ASC'
);
$stmt->bind_param('iiii', $user_id, $other_id, $other_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

echo json_encode($messages);
