<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $mysqli->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);
