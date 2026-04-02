<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$found_id = isset($_GET['found_id']) ? (int) $_GET['found_id'] : 0;
if ($found_id <= 0) {
    echo json_encode(['error' => 'Invalid found item.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = $mysqli->prepare('SELECT category FROM found_items WHERE id = ? AND is_active = 1');
$stmt->bind_param('i', $found_id);
$stmt->execute();
$f = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$f || ($f['category'] ?? '') === '') {
    echo json_encode(['error' => 'Found item not available.']);
    exit();
}

$cat = $f['category'];

$stmt = $mysqli->prepare(
    'SELECT id, item_name, description, date_lost FROM lost_items
     WHERE user_id = ? AND resolved = 0 AND category = ?'
);
$stmt->bind_param('is', $user_id, $cat);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

echo json_encode(['category' => $cat, 'lost_items' => $rows]);
