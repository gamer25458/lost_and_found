<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$sql = "SELECT ii.id AS issued_id, ii.legal_name, ii.id_passport_number, ii.handover_photo_path,
               ii.issued_at,
               fi.item_name, fi.description, fi.category, fi.image_path AS found_image,
               li.item_name AS lost_item_name,
               c.id AS claim_id
        FROM issued_items ii
        JOIN claims c ON c.id = ii.claim_id
        JOIN found_items fi ON fi.id = c.found_item_id
        JOIN lost_items li ON li.id = c.lost_item_id
        WHERE c.user_id = ?
        ORDER BY ii.issued_at DESC";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();

$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = $row;
}
$stmt->close();

echo json_encode($out);
