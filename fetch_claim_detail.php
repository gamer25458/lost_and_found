<?php
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

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['error' => 'Invalid id.']);
    exit();
}

$sql = "SELECT c.*, u.full_name, u.email,
        fi.item_name AS found_name, fi.description AS found_desc, fi.category AS found_cat,
        fi.image_path AS found_image, fi.location_found, fi.date_found,
        li.item_name AS lost_name, li.description AS lost_desc, li.category AS lost_cat,
        li.unique_details AS lost_unique_details   -- NEW
        FROM claims c
        JOIN users u ON u.id = c.user_id
        JOIN found_items fi ON fi.id = c.found_item_id
        JOIN lost_items li ON li.id = c.lost_item_id
        WHERE c.id = ?";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $id);
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