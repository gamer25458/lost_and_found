<?php
// fetch_conversations.php — fetches all conversations for the admin inbox

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['error' => 'Admins only.']);
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

// Return each distinct user who has exchanged at least one message with the admin,
// along with their unread count and most-recent message snippet.
$stmt = $mysqli->prepare(
    "SELECT DISTINCT u.id, u.full_name, u.email,
        (SELECT COUNT(*)
         FROM messages
         WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0
        ) AS unread_count,
        (SELECT message
         FROM messages
         WHERE (sender_id = u.id AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = u.id)
         ORDER BY created_at DESC LIMIT 1
        ) AS last_message,
        (SELECT created_at
         FROM messages
         WHERE (sender_id = u.id AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = u.id)
         ORDER BY created_at DESC LIMIT 1
        ) AS last_time
     FROM users u
     JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
     WHERE u.id != ?
       AND (m.sender_id = ? OR m.receiver_id = ?)
     ORDER BY last_time DESC"
);

$stmt->bind_param(
    'iiiiiiii',
    $admin_id, $admin_id, $admin_id,
    $admin_id, $admin_id,
    $admin_id, $admin_id, $admin_id
);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}
$stmt->close();

echo json_encode($conversations);
