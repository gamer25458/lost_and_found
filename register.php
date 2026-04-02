<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../register.html');
    exit();
}

if (!empty($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ../dashboard.html');
    exit();
}

try {
    $full_name = trim($_POST['full_name']        ?? '');
    $email     = trim($_POST['email']            ?? '');
    $gender    = isset($_POST['gender']) && in_array($_POST['gender'], ['male','female']) ? $_POST['gender'] : null;
    $password  = trim($_POST['password']         ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');

    if ($full_name === '' || $email === '' || $password === '' || $confirm === '' || !$gender) {
        throw new Exception('All fields including gender are required.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please provide a valid email address.');
    }

    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters.');
    }

    if ($password !== $confirm) {
        throw new Exception('Passwords do not match.');
    }

    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        throw new Exception('An account with that email already exists.');
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $mysqli->prepare('INSERT INTO users (full_name, email, gender, password) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $full_name, $email, $gender, $hashed);
    $stmt->execute();
    $stmt->close();

    header('Location: ../login.html?success=1');
    exit();

} catch (Exception $e) {
    logError('Registration failed', $e);
    header('Location: ../register.html?error=' . urlencode($e->getMessage()));
    exit();
}