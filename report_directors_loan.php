<?php
// report_directors_loan.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // ── Filters ───────────────────────────────────────────────────────────────
    $filter_from = $_GET['from'] ?? '';
    $filter_to = $_GET['to'] ?? '';
    $filter_order = $_GET['order'] ?? 'asc';
    $order_dir = $filter_order === 'desc' ? 'DESC' : 'ASC';

    $from_sql = $filter_from ? "AND transaction_date >= :from" : "";
    $to_sql = $filter_to ? "AND transaction_date <= :to" : "";
    $je_from_sql = $filter_from ? "AND je.date >= :from" : "";
    $je_to_sql = $filter_to ? "AND je.date <= :to" : "";

    $summary_params = [':user_id' => $user_id];
    if ($filter_from)
        $summary_params[':from'] = $filter_from;
    if ($filter_to)
        $summary_params[':to'] = $filter_to;

    // ── Summary ───────────────────────────────────────────────────────────────
    $summary_stmt = $pdo->prepare("
SELECT
    COUNT(*)                                                        AS total,
    SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END)          AS total_debits,
    SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END)          AS total_credits,
    SUM(CASE WHEN type = 'debit'  THEN amount ELSE -amount END)    AS balance
FROM (

    SELECT
        CASE WHEN type = 'debit' THEN 'credit' ELSE 'debit' END AS type,
        amount,
        transaction_date
    FROM bank_transactions bt
    WHERE bt.user_id = :user_id
      AND bt.coa_id = 10
      {$from_sql}
      {$to_sql}

    UNION ALL

    SELECT jel.type, jel.amount, je.date AS transaction_date
    FROM journal_entry_lines jel
    JOIN journal_entries je ON jel.journal_entry_id = je.id
    WHERE jel.coa_id = 10
      {$je_from_sql}
      {$je_to_sql}

) AS combined
    ");
    $summary_stmt->execute($summary_params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

    $total_debits = $summary['total_debits'] ?? 0;
    $total_credits = $summary['total_credits'] ?? 0;
    $balance = $summary['balance'] ?? 0;

    // ── Transactions ──────────────────────────────────────────────────────────
    $tx_params = [':user_id' => $user_id];
    if ($filter_from)
        $tx_params[':from'] = $filter_from;
    if ($filter_to)
        $tx_params[':to'] = $filter_to;

    $stmt = $pdo->prepare("
        SELECT
            id,
            transaction_date,
            description,
            expense_description,
            type,
            amount,
            notes,
            source,
            account_name,
            bank_name
        FROM (

            SELECT
                bt.id,
                bt.transaction_date,
                bt.description,
                bt.expense_description,
                bt.type,
                bt.amount,
                bt.notes,
                'bank'          AS source,
                ba.account_name AS account_name,
                ba.bank_name    AS bank_name
            FROM bank_transactions bt
            LEFT JOIN bank_accounts ba ON bt.bank_account_id = ba.id
            WHERE bt.user_id = :user_id
              AND bt.coa_id = 10
              {$from_sql}
              {$to_sql}

            UNION ALL

            SELECT
                jel.id,
                je.date        AS transaction_date,
                je.description AS description,
                je.description AS expense_description,
                jel.type,
                jel.amount,
                NULL           AS notes,
                'journal'      AS source,
                NULL           AS account_name,
                NULL           AS bank_name
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE jel.coa_id = 10
              {$je_from_sql}
              {$je_to_sql}

        ) AS combined
        ORDER BY transaction_date {$order_dir}, id {$order_dir}
    ");
    $stmt->execute($tx_params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Running balance ───────────────────────────────────────────────────────
    $running = 0;
    foreach ($transactions as &$tx) {
        if ($tx['source'] === 'bank') {
            // Bank: debit = money OUT = repayment (credit column)
            //       credit = money IN = director invested (debit column)
            if ($tx['type'] === 'debit') {
                $running -= $tx['amount'];
            } else {
                $running += $tx['amount'];
            }
        } else {
            // Journal: standard double-entry
            if ($tx['type'] === 'debit') {
                $running += $tx['amount'];
            } else {
                $running -= $tx['amount'];
            }
        }
        $tx['running_balance'] = $running;
    }
    unset($tx);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $transactions = [];
    $total_debits = 0;
    $total_credits = 0;
    $balance = 0;
    $summary = ['total' => 0];
}

$page_title = 'Directors Loan Account';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">

                    <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                        <h4 class="page-title">Director Loan Account</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Director Loan Account</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <span class="text-muted small">Chart of Accounts: 2400 &mdash; Liability</span>
                        </div>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print / PDF
                        </button>
                    </div>

                    <!-- Alerts -->
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success">
                            <?php echo $_SESSION['message'];
                            unset($_SESSION['message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $_SESSION['error'];
                            unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Summary Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small">Total Transactions</div>
                                    <div class="fs-4 fw-bold">
                                        <?php echo number_format($summary['total'] ?? 0); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small">Director Invested (Debits)</div>
                                    <div class="fs-4 fw-bold text-success">
                                        £<?php echo number_format($total_debits, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small">Company Repaid (Credits)</div>
                                    <div class="fs-4 fw-bold text-danger">
                                        £<?php echo number_format($total_credits, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <div class="text-muted small">Closing Balance</div>
                                    <div class="fs-4 fw-bold <?php echo $balance > 0 ? 'text-success' : ($balance < 0 ? 'text-danger' : 'text-muted'); ?>">
                                        £<?php echo number_format(abs($balance), 2); ?>
                                    </div>
                                    <div class="text-muted small mt-1">
                                        <?php if ($balance > 0): ?>
                                            Company owes you £<?php echo number_format($balance, 2); ?>
                                        <?php elseif ($balance < 0): ?>
                                            You owe the company £<?php echo number_format(abs($balance), 2); ?>
                                        <?php else: ?>
                                            Account is clear
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <form method="get" class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small">From Date</label>
                                    <input type="date" name="from" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($filter_from); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">To Date</label>
                                    <input type="date" name="to" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($filter_to); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Date Order</label>
                                    <select name="order" class="form-select form-select-sm">
                                        <option value="asc"  <?php echo $filter_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                                        <option value="desc" <?php echo $filter_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-filter"></i> Filter
                                    </button>
                                    <a href="report_directors_loan.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x"></i> Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-sm mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Source</th>
                                            <th class="text-end">Invested (DR)</th>
                                            <th class="text-end">Repaid (CR)</th>
                                            <th class="text-end">Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($transactions)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    No Directors Loan transactions found.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($transactions as $tx): ?>
                                                <tr>
                                                    <td class="text-nowrap">
                                                        <?php echo date('d M Y', strtotime($tx['transaction_date'])); ?>
                                                    </td>
                                                    <td>
                                                        <div class="fw-semibold">
                                                            <?php echo htmlspecialchars(
                                                                $tx['expense_description'] ?: $tx['description']
                                                            ); ?>
                                                        </div>
                                                        <?php if ($tx['expense_description'] && $tx['description'] !== $tx['expense_description']): ?>
                                                            <div class="text-muted small">
                                                                <?php echo htmlspecialchars($tx['description']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($tx['notes'])): ?>
                                                            <div class="text-muted small fst-italic">
                                                                <?php echo htmlspecialchars($tx['notes']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-nowrap small">
                                                        <?php if ($tx['source'] === 'journal'): ?>
                                                            <span class="badge bg-info text-dark">Journal</span>
                                                        <?php elseif ($tx['account_name']): ?>
                                                            <?php echo htmlspecialchars($tx['account_name']); ?>
                                                            <span class="text-muted">— <?php echo htmlspecialchars($tx['bank_name']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        <?php $is_invested = ($tx['source'] === 'bank') ? $tx['type'] === 'credit' : $tx['type'] === 'debit'; ?>
                                                        <?php if ($is_invested): ?>
                                                            <span class="fw-semibold text-success">
                                                                £<?php echo number_format($tx['amount'], 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        <?php $is_repaid = ($tx['source'] === 'bank') ? $tx['type'] === 'debit' : $tx['type'] === 'credit'; ?>
                                                        <?php if ($is_repaid): ?>
                                                            <span class="fw-semibold text-danger">
                                                                £<?php echo number_format($tx['amount'], 2); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end text-nowrap">
                                                        <?php if ($tx['running_balance'] > 0): ?>
                                                            <span class="fw-semibold text-success">
                                                                £<?php echo number_format($tx['running_balance'], 2); ?>
                                                            </span>
                                                        <?php elseif ($tx['running_balance'] < 0): ?>
                                                            <span class="fw-semibold text-danger">
                                                                (£<?php echo number_format(abs($tx['running_balance']), 2); ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">£0.00</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                    <?php if (!empty($transactions)): ?>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td colspan="3">Totals</td>
                                            <td class="text-end text-success">
                                                £<?php echo number_format($total_debits, 2); ?>
                                            </td>
                                            <td class="text-end text-danger">
                                                £<?php echo number_format($total_credits, 2); ?>
                                            </td>
                                            <td class="text-end <?php echo $balance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php if ($balance < 0): ?>
                                                    (£<?php echo number_format(abs($balance), 2); ?>)
                                                <?php else: ?>
                                                    £<?php echo number_format($balance, 2); ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    nav, .btn, form, .card-body > .d-flex { display: none !important; }
    .container { margin: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>