<?php
session_start();
require_once 'config.php';

if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . (!empty($_SESSION['is_admin']) ? '../admin.php' : '../dashboard.html'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.html');
    exit();
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

try {
    if ($email === '' || $password === '') {
        throw new Exception('Email and password are required.');
    }

    $stmt = $mysqli->prepare('SELECT id, full_name, email, password, role, gender FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || !password_verify($password, $user['password'])) {
        throw new Exception('Invalid email or password.');
    }

    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['id'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['username']  = $user['full_name'];
    $_SESSION['gender']    = $user['gender'] ?? null;
    $_SESSION['logged_in'] = true;
    $_SESSION['is_admin']  = ($user['role'] === 'admin');

    if ($_SESSION['is_admin']) {
        header('Location: ../admin.php');
    } else {
        header('Location: ../dashboard.html');
    }
    exit();

} catch (Exception $e) {
    logError('Login failed for ' . $email, $e);
    header('Location: ../login.html?error=' . urlencode($e->getMessage()));
    exit();
}