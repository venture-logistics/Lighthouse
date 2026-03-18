<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── Validate upload ──────────────────────────────────────────────────────
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error.');
    }

    $bank_account_id = (int) $_POST['bank_account_id'];
    if (!$bank_account_id) {
        throw new Exception('No bank account selected.');
    }

    $stmt = $pdo->prepare("SELECT id FROM bank_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$bank_account_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid bank account.');
    }

    // ── Read & parse file ────────────────────────────────────────────────────
    $content = file_get_contents($_FILES['csv_file']['tmp_name']);
    if ($content === false)
        throw new Exception('Could not read file.');

    $firstLine = strtok($content, "\n");
    $separator = substr_count($firstLine, "\t") > substr_count($firstLine, ",") ? "\t" : ",";

    $lines = array_values(array_filter(
        explode("\n", str_replace("\r\n", "\n", $content)),
        fn($l) => trim($l) !== ''
    ));

    if (count($lines) < 2) {
        throw new Exception('CSV file appears to be empty — no data rows found.');
    }

    // ── Parse headers ────────────────────────────────────────────────────────
    $col = [];
    foreach (str_getcsv($lines[0], $separator) as $index => $header) {
        $header = trim($header);
        if (!isset($col[$header]))
            $col[$header] = $index;
    }

    // ── Date parser ──────────────────────────────────────────────────────────
    function parse_date(string $raw): ?string
    {
        $raw = trim($raw);
        if (empty($raw))
            return null;
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $raw);
            if ($dt !== false)
                return $dt->format('Y-m-d');
        }
        return null;
    }

    // ── Validate this is an income CSV ───────────────────────────────────────
    $required = ['Date completed (UTC)', 'ID', 'Type', 'State', 'Description', 'Amount'];
    foreach ($required as $r) {
        if (!isset($col[$r])) {
            throw new Exception("Missing column: {$r}. Are you sure this is a Revolut Business statement export?");
        }
    }

    // ── Prepare insert ───────────────────────────────────────────────────────
    $insert = $pdo->prepare("
        INSERT INTO bank_transactions (
            user_id, bank_account_id, revolut_tx_id,
            transaction_date, started_date, description,
            type, transaction_type, orig_currency, orig_amount,
            amount, fee, status, notes
        ) VALUES (
            :user_id, :bank_account_id, :revolut_tx_id,
            :transaction_date, :started_date, :description,
            :type, :transaction_type, :orig_currency, :orig_amount,
            :amount, :fee, 'uncategorised', :notes
        )
    ");

    $imported = $skipped = $duplicate = 0;

    // ── Process rows ─────────────────────────────────────────────────────────
    for ($i = 1; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i], $separator);
        if (count($row) < 5) {
            $skipped++;
            continue;
        }

        $get = fn(string $name): string =>
            isset($col[$name]) ? trim($row[$col[$name]] ?? '') : '';

        // Skip non-completed
        if ($get('State') !== 'COMPLETED') {
            $skipped++;
            continue;
        }

        // Dates
        $completed_raw = $get('Date completed (UTC)');
        if (empty($completed_raw)) {
            $skipped++;
            continue;
        }

        $transaction_date = parse_date($completed_raw);
        if ($transaction_date === null) {
            error_log("Income import: unparseable date [{$completed_raw}] row $i");
            $skipped++;
            continue;
        }
        $started_date = parse_date($get('Date started (UTC)'));

        // Core fields
        $revolut_tx_id = $get('ID');
        $transaction_type = $get('Type');
        $description = $get('Description');
        $reference = $get('Reference');
        $payer = $get('Payer');

        // Amount — use sign to determine debit/credit
        // Positive = money IN  (credit to bank account)
        // Negative = money OUT (debit  e.g. EXCHANGE)
        $raw_amount = (float) $get('Amount');
        $amount = abs($raw_amount);
        $type = $raw_amount >= 0 ? 'credit' : 'debit';

        $fee = (float) $get('Fee');
        $orig_currency = $get('Orig currency');
        $orig_amount = (float) $get('Orig amount');

        // Build notes from payer + reference
        $notes = implode(' | ', array_filter([
            $payer ? "Payer: {$payer}" : '',
            $reference ? "Ref: {$reference}" : '',
        ])) ?: null;

        // Duplicate check
        if (!empty($revolut_tx_id)) {
            $dup = $pdo->prepare("SELECT id FROM bank_transactions WHERE revolut_tx_id = ? LIMIT 1");
            $dup->execute([$revolut_tx_id]);
            if ($dup->fetch()) {
                $duplicate++;
                continue;
            }
        }

        $insert->execute([
            ':user_id' => $_SESSION['user_id'],
            ':bank_account_id' => $bank_account_id,
            ':revolut_tx_id' => $revolut_tx_id ?: null,
            ':transaction_date' => $transaction_date,
            ':started_date' => $started_date,
            ':description' => $description,
            ':type' => $type,
            ':transaction_type' => $transaction_type ?: null,
            ':orig_currency' => $orig_currency ?: null,
            ':orig_amount' => $orig_amount ?: null,
            ':amount' => $amount,
            ':fee' => $fee,
            ':notes' => $notes,
        ]);

        $imported++;
    }

    $_SESSION['message'] = implode(' ', array_filter([
        "<strong>{$imported}</strong> income transactions imported.",
        $duplicate ? "<strong>{$duplicate}</strong> duplicates skipped." : '',
        $skipped ? "<strong>{$skipped}</strong> non-income/invalid rows skipped." : '',
    ]));

} catch (Exception $e) {
    $_SESSION['error'] = 'Import error: ' . $e->getMessage();
}

header('Location: bank_transactions.php');
exit();