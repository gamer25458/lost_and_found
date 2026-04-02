<?php
session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized.']);
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['error' => 'Access denied.']);
    exit();
}

$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
if (!in_array($status, ['all', 'pending', 'approved', 'rejected'], true)) {
    echo json_encode(['error' => 'Invalid status filter.']);
    exit();
}

// Build base query — status filter uses a prepared statement, not string interpolation
$baseSql = "SELECT c.id, c.user_id, c.found_item_id, c.lost_item_id, c.status,
               c.proof_description, c.serial_number, c.box_receipt_notes,
               c.device_unlock_password, c.admin_reject_reason,
               c.created_at, c.reviewed_at,
               u.full_name, u.email,
               fi.item_name AS found_name, fi.image_path AS found_image,
               li.item_name AS lost_name,
               (SELECT path FROM claim_photos cp
                WHERE cp.claim_id = c.id
                ORDER BY cp.sort_order ASC, cp.id ASC LIMIT 1) AS thumb
        FROM claims c
        JOIN users u  ON u.id  = c.user_id
        JOIN found_items fi ON fi.id = c.found_item_id
        JOIN lost_items  li ON li.id = c.lost_item_id";

if ($status === 'all') {
    $stmt = $mysqli->prepare($baseSql . ' ORDER BY c.created_at DESC');
    $stmt->execute();
} else {
    $stmt = $mysqli->prepare($baseSql . ' WHERE c.status = ? ORDER BY c.created_at DESC');
    $stmt->bind_param('s', $status);
    $stmt->execute();
}

$result = $stmt->get_result();
if (!$result) {
    $stmt->close();
    echo json_encode(['error' => 'Query failed.']);
    exit();
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

echo json_encode($rows);
