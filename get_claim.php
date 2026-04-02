<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid id.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$sql = "SELECT c.*, fi.item_name AS found_name, fi.description AS found_desc, fi.category AS found_cat,
        fi.image_path AS found_image,
        li.item_name AS lost_name
        FROM claims c
        JOIN found_items fi ON fi.id = c.found_item_id
        JOIN lost_items li ON li.id = c.lost_item_id
        WHERE c.id = ? AND c.user_id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode(['error' => 'Not found.']);
    exit();
}

$stmt = $mysqli->prepare(
    'SELECT id, path, sort_order FROM claim_photos WHERE claim_id = ? ORDER BY sort_order ASC, id ASC'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$photos = [];
while ($p = $result->fetch_assoc()) {
    $photos[] = $p;
}
$stmt->close();

$row['photos'] = $photos;

echo json_encode($row);
