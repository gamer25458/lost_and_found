<?php
// fetch_users.php - returns all registered users for the admin panel

session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode(['error' => 'Access denied. Admins only.']);
    exit();
}

$stmt = $mysqli->prepare(
    "SELECT id, full_name, email, role, created_at 
     FROM users 
     ORDER BY created_at DESC"
);

$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$stmt->close();
echo json_encode($users);
?>