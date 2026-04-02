<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'logged_in' => !empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true,
    'is_admin'  => !empty($_SESSION['is_admin']) && $_SESSION['is_admin'] === true,
    'user_id'   => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
    'name'      => $_SESSION['username'] ?? '',
    'gender'    => $_SESSION['gender'] ?? null,
]);