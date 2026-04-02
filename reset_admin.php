<?php
/**
 * One-time admin password reset. Open in browser:
 *   http://localhost/lost_and_found/backend/reset_admin.php
 * Then DELETE this file or block access in production.
 */
header('Content-Type: text/html; charset=utf-8');
require 'config.php';

$email     = 'admin@lostandfound.com';
$full_name = 'Administrator';
$new_pass  = 'admin123';

$hashed = password_hash($new_pass, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare(
    'INSERT INTO users (full_name, email, password, role)
     VALUES (?, ?, ?, \'admin\')
     ON DUPLICATE KEY UPDATE password = VALUES(password), full_name = VALUES(full_name), role = \'admin\''
);
$stmt->bind_param('sss', $full_name, $email, $hashed);

if (!$stmt->execute()) {
    echo '<p style="color:red">Database error: ' . htmlspecialchars($mysqli->error) . '</p>';
    $stmt->close();
    exit;
}
$stmt->close();

$check = $mysqli->prepare('SELECT id, email, password FROM users WHERE email = ?');
$check->bind_param('s', $email);
$check->execute();
$row = $check->get_result()->fetch_assoc();
$check->close();

$ok = $row && password_verify($new_pass, $row['password']);

echo '<h2>Admin reset</h2>';
if ($ok) {
    echo '<p style="color:green;font-size:18px"><strong>Success.</strong> Password is set correctly in the database.</p>';
    echo '<p>Log in with:</p><ul>';
    echo '<li><strong>Email:</strong> <code>admin@lostandfound.com</code></li>';
    echo '<li><strong>Password:</strong> <code>admin123</code></li>';
    echo '</ul>';
    echo '<p><a href="../login.html">Go to login</a></p>';
    echo '<p style="color:#c0392b"><small>Delete <code>backend/reset_admin.php</code> after use for security.</small></p>';
} else {
    echo '<p style="color:red">Reset ran but verification failed. Check the <code>users</code> table in phpMyAdmin.</p>';
}
