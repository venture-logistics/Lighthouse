<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://jsdelivr.net;");
header_remove("X-Powered-By");

require_once 'includes/config.php';
require_once 'includes/functions.php';

require_once __DIR__ . '/../vendor/owasp/csrf-protector-php/libs/csrf/csrfprotector.php';

// Initialise CSRFProtector library
csrfProtector::init();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT logo_path, company_name FROM business_settings";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $logoPath = $result['logo_path'] ?? null;
    $companyName = $result['company_name'] ?? 'Lighthouse';
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lighthouse - <?php echo $page_title ?? 'Small Business Accounting'; ?></title>

    <link rel="icon" type="image/png" href="<?php echo $logoPath; ?>">

    <meta name="robots" content="noindex, nofollow">

    <!-- Bootstrap 5 CSS -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <link href="assets/css/app.min.css" rel="stylesheet" type="text/css" />

</head>
<body data-sidebar-size="default">