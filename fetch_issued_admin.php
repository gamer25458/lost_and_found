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

$stmt = $mysqli->prepare(
    "SELECT ii.id, ii.legal_name, ii.id_passport_number,
            ii.handover_photo_path, ii.issued_at,
            u.full_name AS user_account_name, u.email,
            fi.item_name AS found_item_name, fi.description AS found_item_desc,
            fi.category AS found_item_category, fi.location_found,
            fi.date_found, fi.image_path AS found_item_image,
            c.id AS claim_id
     FROM issued_items ii
     JOIN claims c      ON c.id  = ii.claim_id
     JOIN users u       ON u.id  = c.user_id
     JOIN found_items fi ON fi.id = c.found_item_id
     ORDER BY ii.issued_at DESC"
);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

echo json_encode($rows);
