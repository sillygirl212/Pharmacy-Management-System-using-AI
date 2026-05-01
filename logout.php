<?php
require_once 'config/functions.php';

if (isLoggedIn()) {
    logActivity('User Logout', "User {$_SESSION['user_email']} logged out");
}

// Clear all session data
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// Redirect to home
redirect('index.php');
?>
