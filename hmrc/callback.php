<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/HmrcClient.php';
require_login();

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
    DB_USER,
    DB_PASS
);

$client = new HmrcClient($pdo, $_SESSION['user_id']);

// Validate state to prevent CSRF
if (
    empty($_GET['state']) ||
    empty($_SESSION['hmrc_oauth_state']) ||
    !hash_equals($_SESSION['hmrc_oauth_state'], $_GET['state'])
) {
    $_SESSION['error'] = 'Invalid OAuth state. Please try connecting again.';
    header('Location: ../business_settings.php');
    exit();
}

unset($_SESSION['hmrc_oauth_state']);

// Check HMRC didn't return an error
if (!empty($_GET['error'])) {
    $_SESSION['error'] = 'HMRC authorisation failed: ' . htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    header('Location: ../business_settings.php#vat_registered');
    exit();
}

// Exchange the code for tokens
if (empty($_GET['code'])) {
    $_SESSION['error'] = 'No authorisation code received from HMRC.';
    header('Location: ../business_settings.php#vat_registered');
    exit();
}

$success = $client->exchangeCode($_GET['code']);

if ($success) {
    $_SESSION['message'] = 'Successfully connected to HMRC.';
} else {
    $_SESSION['error'] = 'Failed to exchange authorisation code. Please try again.';
}

header('Location: ../business_settings.php');
exit();