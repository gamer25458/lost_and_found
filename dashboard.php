<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../login.html');
    exit();
}

// Redirect to the correct root dashboard
header('Location: ../dashboard.html');
exit();
?>