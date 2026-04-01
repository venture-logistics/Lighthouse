<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sole_trader_settings.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // Sanitise inputs
    $utr_number          = trim($_POST['utr_number'] ?? '');
    $nino                = strtoupper(trim($_POST['nino'] ?? ''));
    $accounting_method   = $_POST['accounting_method'] ?? 'cash';
    $business_start_date = $_POST['business_start_date'] ?? null;
    $mtd_itsa_enrolled   = isset($_POST['mtd_itsa_enrolled']) ? 1 : 0;

    // Validate accounting method is one of the allowed values
    if (!in_array($accounting_method, ['cash', 'accruals'])) {
        $accounting_method = 'cash';
    }

    // Empty string to null for date
    if (empty($business_start_date)) {
        $business_start_date = null;
    }

    // Upsert into sole_trader_settings
    $stmt = $pdo->prepare("
        INSERT INTO sole_trader_settings 
            (user_id, utr_number, nino, accounting_method, business_start_date, mtd_itsa_enrolled)
        VALUES 
            (:user_id, :utr_number, :nino, :accounting_method, :business_start_date, :mtd_itsa_enrolled)
        ON DUPLICATE KEY UPDATE
            utr_number          = VALUES(utr_number),
            nino                = VALUES(nino),
            accounting_method   = VALUES(accounting_method),
            business_start_date = VALUES(business_start_date),
            mtd_itsa_enrolled   = VALUES(mtd_itsa_enrolled),
            updated_at          = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        ':user_id'            => $user_id,
        ':utr_number'         => $utr_number ?: null,
        ':nino'               => $nino ?: null,
        ':accounting_method'  => $accounting_method,
        ':business_start_date'=> $business_start_date,
        ':mtd_itsa_enrolled'  => $mtd_itsa_enrolled,
    ]);

    $_SESSION['message'] = 'Self Assessment settings saved successfully.';

} catch (PDOException $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
}

header('Location: sole_trader_settings.php');
exit;