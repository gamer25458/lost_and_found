<?php
// get_admin_id.php — returns the primary admin user ID for messaging

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$adminRole = 'admin';
$stmt = $mysqli->prepare('SELECT id FROM users WHERE role = ? LIMIT 1');
$stmt->bind_param('s', $adminRole);
$stmt->execute();
$row      = $stmt->get_result()->fetch_assoc();
$stmt->close();

$admin_id = $row ? (int) $row['id'] : null;

echo json_encode(['admin_id' => $admin_id]);
