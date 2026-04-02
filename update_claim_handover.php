<?php
// update_claim_handover.php — fallback to mark a claim as completed
// NOTE: issue_handover.php is the primary handover endpoint.
// This file exists only as a lightweight status-only update if needed.

session_start();
require 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Admins only.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit();
}

$data     = json_decode(file_get_contents('php://input'), true);
$claim_id = isset($data['id']) ? (int) $data['id'] : 0;

if ($claim_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid claim ID.']);
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

// Verify the claim exists and is in an approved state before marking complete
$stmt = $mysqli->prepare('SELECT id, status FROM claims WHERE id = ?');
$stmt->bind_param('i', $claim_id);
$stmt->execute();
$claim = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$claim) {
    echo json_encode(['success' => false, 'error' => 'Claim not found.']);
    exit();
}

if ($claim['status'] !== 'approved') {
    echo json_encode(['success' => false, 'error' => 'Only approved claims can be marked as completed.']);
    exit();
}

// NOTE: 'completed' is not in the ENUM in schema.sql (pending/approved/rejected).
// If you want this status, run: ALTER TABLE claims MODIFY status ENUM('pending','approved','rejected','completed')...
// For now we keep the status as 'approved' and just log the admin who handled it.
$stmt = $mysqli->prepare('UPDATE claims SET handled_by = ?, reviewed_at = NOW() WHERE id = ?');
$stmt->bind_param('ii', $admin_id, $claim_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Update failed or no changes made.']);
}