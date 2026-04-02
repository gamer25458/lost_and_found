<?php
// delete_user.php — admin deletes a user account

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

// Only admins may delete other users
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Admins only.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int) $data['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
    exit();
}

// Prevent an admin from deleting their own account
if ($id === (int) $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'You cannot delete your own account.']);
    exit();
}

// Cascading deletes on lost_items, found_items, claims etc. are handled
// by the ON DELETE CASCADE foreign keys defined in schema.sql.
$stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'User not found or could not be deleted.']);
}
