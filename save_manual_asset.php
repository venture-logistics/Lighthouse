<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];
    $date = $_POST['transaction_date'];
    $description = trim($_POST['description']);
    $amount = floatval($_POST['amount']);
    $dr_coa_id = intval($_POST['dr_coa_id']);
    $notes = trim($_POST['notes'] ?? '');

    $pdo->beginTransaction();


    // DR - debit the asset account (asset increases)
    $stmt = $pdo->prepare("
    INSERT INTO bank_transactions 
        (user_id, bank_account_id, transaction_date, description, amount, type, transaction_type, coa_id, notes, status, reconciled)
    VALUES 
        (?, 1, ?, ?, ?, 'debit', 'ASSET', ?, ?, 'categorised', 1)
    ");
    $stmt->execute([$user_id, $date, $description, $amount, $dr_coa_id, $notes]);

    // CR - only if a funding account was selected
    if (!empty($_POST['cr_coa_id'])) {
        $cr_coa_id = intval($_POST['cr_coa_id']);
        $stmt = $pdo->prepare("
        INSERT INTO bank_transactions 
            (user_id, bank_account_id, transaction_date, description, amount, type, transaction_type, coa_id, notes, status, reconciled)
        VALUES 
            (?, 1, ?, ?, ?, 'credit', 'ASSET', ?, ?, 'categorised', 1)
    ");
        $stmt->execute([$user_id, $date, $description, $amount, $cr_coa_id, $notes]);
    }

    $pdo->commit();

    $_SESSION['message'] = "Asset '{$description}' added successfully.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Failed to add asset: " . $e->getMessage();
}

header('Location: bank_transactions.php');
exit;