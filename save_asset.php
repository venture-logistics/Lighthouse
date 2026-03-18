<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bank_transactions.php');
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = (int) $_SESSION['user_id'];
    $asset_coa_id = (int) ($_POST['asset_coa_id'] ?? 0);
    $funding_coa_id = (int) ($_POST['funding_coa_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $date = $_POST['transaction_date'] ?? date('Y-m-d');
    $bank_account_id = (int) ($_POST['bank_account_id'] ?? 0) ?: null;
    $notes = trim($_POST['notes'] ?? '');

    // ── Validation ────────────────────────────────────────────────────────────
    if (!$asset_coa_id || !$funding_coa_id || !$description || $amount <= 0) {
        $_SESSION['error'] = 'Please fill in all required fields.';
        header('Location: bank_transactions.php');
        exit;
    }

    // Verify selected account really is an asset
    $check = $pdo->prepare("SELECT type FROM chart_of_accounts WHERE id = ?");
    $check->execute([$asset_coa_id]);
    $coa_type = $check->fetchColumn();

    if ($coa_type !== 'asset') {
        $_SESSION['error'] = 'Selected account is not an asset account.';
        header('Location: bank_transactions.php');
        exit;
    }

    // Fall back to first bank account belonging to this user
    if (!$bank_account_id) {
        $ba_stmt = $pdo->prepare("
            SELECT id FROM bank_accounts WHERE user_id = ? ORDER BY id ASC LIMIT 1
        ");
        $ba_stmt->execute([$user_id]);
        $bank_account_id = (int) $ba_stmt->fetchColumn();

        if (!$bank_account_id) {
            $_SESSION['error'] = 'No bank account found. Please add a bank account first.';
            header('Location: bank_transactions.php');
            exit;
        }
    }

    /*
     * Double-entry asset purchase
     * ─────────────────────────────────────────────────────────
     * DR  Asset account   (type = 'debit',  coa = asset_coa_id)
     * CR  Funding source  (type = 'credit', coa = funding_coa_id)
     *
     * Both rows get a unique generated revolut_tx_id so the
     * unique index is never violated.
     * Both are linked by the same description so they're easy
     * to identify as a pair in the transactions list.
     * ─────────────────────────────────────────────────────────
     */
    $ref = 'ASSET-' . date('YmdHis') . '-' . $user_id;

    $insert = $pdo->prepare("
        INSERT INTO bank_transactions
            (user_id, bank_account_id, revolut_tx_id,
             transaction_date, description, expense_description,
             type, transaction_type,
             orig_currency, orig_amount, amount,
             coa_id, status, notes, reconciled)
        VALUES
            (:user_id, :bank_account_id, :revolut_tx_id,
             :date, :description, :expense_desc,
             :type, 'ASSET',
             'GBP', :amount, :amount,
             :coa_id, 'reconciled', :notes, 1)
    ");

    $pdo->beginTransaction();

    // Row 1 — DR Asset account
    $insert->execute([
        ':user_id' => $user_id,
        ':bank_account_id' => $bank_account_id,
        ':revolut_tx_id' => $ref . '-DR',
        ':date' => $date,
        ':description' => $description,
        ':expense_desc' => 'Asset purchase',
        ':type' => 'debit',
        ':amount' => $amount,
        ':coa_id' => $asset_coa_id,
        ':notes' => $notes,
    ]);

    // Row 2 — CR Funding source
    $insert->execute([
        ':user_id' => $user_id,
        ':bank_account_id' => $bank_account_id,
        ':revolut_tx_id' => $ref . '-CR',
        ':date' => $date,
        ':description' => $description,
        ':expense_desc' => 'Asset funded by: ' . $funding_coa_id,
        ':type' => 'credit',
        ':amount' => $amount,
        ':coa_id' => $funding_coa_id,
        ':notes' => $notes,
    ]);

    $pdo->commit();

    $_SESSION['message'] = 'Asset <strong>' . htmlspecialchars($description) .
        '</strong> recorded successfully (£' . number_format($amount, 2) . ').';

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = 'Failed to save asset: ' . $e->getMessage();
}

header('Location: bank_transactions.php');
exit;