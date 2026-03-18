<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bank_transactions.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$date = $_POST['transaction_date'];
$description = trim($_POST['description']);
$amount = (float) $_POST['amount'];
$dr_coa_id = (int) $_POST['dr_coa_id'];
$cr_coa_id = (int) $_POST['cr_coa_id'];
$bank_account_id = !empty($_POST['bank_account_id']) ? (int) $_POST['bank_account_id'] : null;
$notes = trim($_POST['notes']);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO bank_transactions 
            (user_id, bank_account_id, transaction_date, description, 
             amount, type, transaction_type, coa_id, notes, status, reconciled)
        VALUES 
            (?, ?, ?, ?, ?, ?, 'MANUAL', ?, ?, 'categorised', 1)
    ");

    // Debit entry — asset/expense being acquired
    $stmt->execute([
        $user_id,
        $bank_account_id,
        $date,
        $description,
        $amount,
        'debit',
        $dr_coa_id,
        $notes
    ]);

    // Credit entry — where the money/value came from (e.g. Directors Loan)
    $stmt->execute([
        $user_id,
        $bank_account_id,
        $date,
        $description . ' (CR)',
        $amount,
        'credit',
        $cr_coa_id,
        $notes
    ]);

    $pdo->commit();

    $_SESSION['message'] = "Journal entry for '{$description}' saved successfully (DR + CR).";

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error saving transaction: " . $e->getMessage();
}

header('Location: bank_transactions.php');
exit;