<?php
// assign_item.php — admin assigns themselves to handle an item

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

$data      = json_decode(file_get_contents('php://input'), true);
$item_id   = isset($data['item_id'])   ? (int) $data['item_id']   : 0;
$item_type = isset($data['item_type']) ? trim($data['item_type']) : '';

// Strict type check — third argument true prevents type coercion
if ($item_id <= 0 || !in_array($item_type, ['lost', 'found'], true)) {
    echo json_encode(['success' => false, 'error' => 'Invalid data.']);
    exit();
}

$admin_id = (int) $_SESSION['user_id'];

// Check if this item is already being handled
$handlingStatus = 'handling';
$check = $mysqli->prepare(
    'SELECT id FROM admin_assignments
     WHERE item_id = ? AND item_type = ? AND status = ?'
);
$check->bind_param('iss', $item_id, $item_type, $handlingStatus);
$check->execute();
$alreadyAssigned = $check->get_result()->fetch_assoc();
$check->close();

if ($alreadyAssigned) {
    echo json_encode(['success' => false, 'error' => 'Item already being handled.']);
    exit();
}

$stmt = $mysqli->prepare(
    'INSERT INTO admin_assignments (admin_id, item_id, item_type) VALUES (?, ?, ?)'
);
$stmt->bind_param('iis', $admin_id, $item_id, $item_type);

if ($stmt->execute()) {
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    $stmt->close();
    echo json_encode(['success' => false, 'error' => 'Assignment failed.']);
}
