<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tx_id = (int) $_POST['transaction_id'];
    $coa_id = !empty($_POST['coa_id']) ? (int) $_POST['coa_id'] : null;
    $status = $_POST['status'] ?? 'uncategorised';
    $notes = $_POST['notes'] ?? null;
    $no_receipt = isset($_POST['no_receipt']) ? 1 : 0;
    $no_receipt_reason = trim($_POST['no_receipt_reason'] ?? '');

    // Verify this transaction belongs to this user
    $stmt = $pdo->prepare("
        SELECT id, transaction_date, receipt_path 
        FROM bank_transactions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$tx_id, $_SESSION['user_id']]);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tx) {
        throw new Exception('Transaction not found.');
    }

    // Validate no-receipt reason if checked
    if ($no_receipt && $no_receipt_reason === '') {
        throw new Exception('Please provide a reason when marking a transaction as no receipt.');
    }

    // If no receipt checked, use reason as notes
    if ($no_receipt) {
        $notes = $no_receipt_reason;
    }

    $receipt_path = $tx['receipt_path']; // keep existing unless replaced

    // ── Handle receipt upload ─────────────────────────────────────────────────
    if (!empty($_FILES['receipt']['name']) && !$no_receipt) {

        $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file = $_FILES['receipt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_exts)) {
            throw new Exception('Invalid file type. Allowed: PDF, JPG, PNG, GIF, WEBP.');
        }

        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size is 10MB.');
        }

        // Build upload path
        $year = date('Y', strtotime($tx['transaction_date']));
        $upload_dir = __DIR__ . '/uploads/receipts/' . $_SESSION['user_id'] . '/' . $year . '/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $full_path = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $full_path)) {
            throw new Exception('Failed to save uploaded file.');
        }

        // Delete old receipt if being replaced
        if ($tx['receipt_path'] && file_exists(__DIR__ . '/' . $tx['receipt_path'])) {
            unlink(__DIR__ . '/' . $tx['receipt_path']);
        }

        $receipt_path = 'uploads/receipts/' . $_SESSION['user_id'] . '/' . $year . '/' . $filename;
    }

    // ── Determine reconciled state ────────────────────────────────────────────
    // Reconciled if: COA set AND (receipt uploaded OR no_receipt acknowledged)
    $reconciled = 0;
    if ($coa_id && ($receipt_path || $no_receipt)) {
        $reconciled = 1;
        $status = 'reconciled';
    }

    // ── Save ──────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        UPDATE bank_transactions 
        SET coa_id       = ?,
            status       = ?,
            notes        = ?,
            receipt_path = ?,
            reconciled   = ?,
            no_receipt   = ?
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([
        $coa_id,
        $status,
        $notes,
        $receipt_path,
        $reconciled,
        $no_receipt,
        $tx_id,
        $_SESSION['user_id']
    ]);

    $_SESSION['message'] = 'Transaction updated successfully.';

} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: bank_transactions.php');
exit();