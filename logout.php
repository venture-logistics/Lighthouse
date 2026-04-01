<?php
require_once 'includes/config.php';

// Destroy the session
session_unset();
session_destroy();

// Clear remember me cookie if set
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login
header('Location: login.php');
exit;
?>