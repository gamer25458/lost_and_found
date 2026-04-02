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

// Approved claims that have not yet been physically issued.
// Uses a prepared statement even though there are no user-supplied params,
// so the query goes through the same execution path as the rest of the codebase.
$approvedStatus = 'approved';

$stmt = $mysqli->prepare(
    "SELECT c.id, c.user_id, c.created_at, c.device_unlock_password,
            u.full_name, u.email,
            fi.item_name AS found_name, fi.category AS found_cat,
            li.item_name AS lost_name
     FROM claims c
     JOIN users u       ON u.id  = c.user_id
     JOIN found_items fi ON fi.id = c.found_item_id
     JOIN lost_items  li ON li.id = c.lost_item_id
     LEFT JOIN issued_items ii ON ii.claim_id = c.id
     WHERE c.status = ? AND ii.id IS NULL
     ORDER BY c.reviewed_at DESC"
);
$stmt->bind_param('s', $approvedStatus);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

echo json_encode($rows);
