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
$client->disconnect();

$_SESSION['message'] = 'Disconnected from HMRC.';
header('Location: ../business_settings.php');
exit();