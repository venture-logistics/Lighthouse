<?php
// report_balance_sheet.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // ── Filters ───────────────────────────────────────────────────────────────
    $as_at_date = $_GET['date'] ?? date('Y-m-d');


    function getBalances($pdo, $type, $date, $user_id)
    {
        if ($type === 'liability') {
            $bt_debit_sign = -1;
            $bt_credit_sign = 1;
            $je_debit_sign = 1;
            $je_credit_sign = -1;
        } else {
            $bt_debit_sign = 1;
            $bt_credit_sign = 1;
            $je_debit_sign = 1;
            $je_credit_sign = 1;
        }

        $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.code,
            c.name,
            COALESCE(SUM(combined.signed_amount), 0) AS balance
        FROM chart_of_accounts c
        LEFT JOIN (

            SELECT
                bt.coa_id,
                CASE
                    WHEN bt.type = 'debit'  THEN bt.amount * {$bt_debit_sign}
                    WHEN bt.type = 'credit' THEN bt.amount * {$bt_credit_sign}
                END AS signed_amount
            FROM bank_transactions bt
            WHERE bt.user_id = :user_id
              AND bt.transaction_date <= :date_bt

            UNION ALL

            SELECT
                jel.coa_id,
                CASE
                    WHEN jel.type = 'debit'  THEN jel.amount * {$je_debit_sign}
                    WHEN jel.type = 'credit' THEN jel.amount * {$je_credit_sign}
                END AS signed_amount
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE je.date <= :date_je

        ) AS combined ON combined.coa_id = c.id
        WHERE c.type = :type
          AND c.is_active = 1
        GROUP BY c.id, c.code, c.name
        ORDER BY c.code
    ");

        $stmt->execute([
            ':user_id' => $user_id,
            ':date_bt' => $date,
            ':date_je' => $date,
            ':type' => $type,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $assets = getBalances($pdo, 'asset', $as_at_date, $user_id);
    $liabilities = getBalances($pdo, 'liability', $as_at_date, $user_id);
    $equity = getBalances($pdo, 'equity', $as_at_date, $user_id);

    // Fix P&L — income credits positive, expense debits negative
    $pl_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(net_amount), 0) AS net_profit
    FROM (

        SELECT
            CASE
                WHEN c.type = 'income'  AND bt.type = 'credit' THEN  bt.amount
                WHEN c.type = 'income'  AND bt.type = 'debit'  THEN -bt.amount
                WHEN c.type = 'expense' AND bt.type = 'debit'  THEN -bt.amount
                WHEN c.type = 'expense' AND bt.type = 'credit' THEN  bt.amount
                ELSE 0
            END AS net_amount
        FROM bank_transactions bt
        JOIN chart_of_accounts c ON c.id = bt.coa_id
        WHERE bt.user_id = :user_id
          AND bt.transaction_date <= :date_bt
          AND c.type IN ('income', 'expense')

        UNION ALL

        SELECT
            CASE
                WHEN c.type = 'income'  AND jel.type = 'credit' THEN  jel.amount
                WHEN c.type = 'income'  AND jel.type = 'debit'  THEN -jel.amount
                WHEN c.type = 'expense' AND jel.type = 'debit'  THEN -jel.amount
                WHEN c.type = 'expense' AND jel.type = 'credit' THEN  jel.amount
                ELSE 0
            END AS net_amount
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        JOIN chart_of_accounts c ON c.id = jel.coa_id
        WHERE je.date <= :date_je
          AND c.type IN ('income', 'expense')

    ) AS pl_combined
");
    $pl_stmt->execute([
        ':user_id' => $user_id,
        ':date_bt' => $as_at_date,
        ':date_je' => $as_at_date,
    ]);
    $current_period_profit = $pl_stmt->fetchColumn();

    // Fix totals
    $total_assets = array_sum(array_column($assets, 'balance'));
    $total_liabilities = array_sum(array_column($liabilities, 'balance'));
    $total_equity = array_sum(array_column($equity, 'balance')) + $current_period_profit;
    $net_assets = $total_assets - $total_liabilities;
    $difference = abs($net_assets - $total_equity);
    $is_balanced = $difference < 0.01;

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $assets = $liabilities = $equity = [];
    $total_assets = $total_liabilities = $total_equity = $net_assets = $current_period_profit = 0;
    $is_balanced = false;
}

$page_title = 'Balance Sheet';
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
                            <h4 class="page-title">Balance Sheet</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Balance Sheet</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <!-- Page Header -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <span class="text-muted small">
                                    As at <?php echo date('d F Y', strtotime($as_at_date)); ?>
                                </span>
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
                                        <div class="text-muted small">Total Assets</div>
                                        <div class="fs-4 fw-bold text-success">
                                            £<?php echo number_format($total_assets, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Total Liabilities</div>
                                        <div class="fs-4 fw-bold text-danger">
                                            £<?php echo number_format($total_liabilities, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Net Assets</div>
                                        <div class="fs-4 fw-bold text-primary">
                                            £<?php echo number_format($net_assets, 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border-0 shadow-sm h-100">
                                    <div class="card-body">
                                        <div class="text-muted small">Balanced?</div>
                                        <div class="fs-4 fw-bold <?php echo $is_balanced ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo $is_balanced ? '✅ Yes' : '❌ No'; ?>
                                        </div>
                                        <?php if (!$is_balanced): ?>
                                            <div class="text-muted small mt-1">
                                                Difference: £<?php echo number_format($difference, 2); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Filter -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <form method="get" class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label small">As at Date</label>
                                        <input type="date" name="date" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars($as_at_date); ?>">
                                    </div>
                                    <div class="col-md-3 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-filter"></i> Update
                                        </button>
                                        <a href="report_balance_sheet.php" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-x"></i> Clear
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Assets & Liabilities Row -->
                        <div class="row g-4">

                            <!-- ASSETS -->
                            <div class="col-lg-6">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-success text-white">
                                        <strong><i class="bi bi-graph-up"></i> Assets</strong>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0 align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Account</th>
                                                        <th class="text-end">Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($assets as $row): ?>
                                                        <tr>
                                                            <td class="text-muted small"><?php echo $row['code']; ?></td>
                                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                            <td class="text-end text-nowrap fw-semibold
                                                                <?php echo $row['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                                <?php if ($row['balance'] < 0): ?>
                                                                    (£<?php echo number_format(abs($row['balance']), 2); ?>)
                                                                <?php else: ?>
                                                                    £<?php echo number_format($row['balance'], 2); ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-success fw-bold">
                                                    <tr>
                                                        <td colspan="2">Total Assets</td>
                                                        <td class="text-end text-nowrap">
                                                            £<?php echo number_format($total_assets, 2); ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- LIABILITIES & EQUITY -->
                            <div class="col-lg-6 d-flex flex-column gap-4">

                                <!-- Liabilities -->
                                <div class="card shadow-sm">
                                    <div class="card-header bg-danger text-white">
                                        <strong><i class="bi bi-graph-down"></i> Liabilities</strong>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0 align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Account</th>
                                                        <th class="text-end">Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($liabilities as $row): ?>
                                                        <tr>
                                                            <td class="text-muted small"><?php echo $row['code']; ?></td>
                                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                            <td class="text-end text-nowrap fw-semibold
                                                                <?php echo $row['balance'] > 0 ? 'text-danger' : 'text-muted'; ?>">
                                                                <?php if ($row['balance'] < 0): ?>
                                                                    (£<?php echo number_format(abs($row['balance']), 2); ?>)
                                                                <?php else: ?>
                                                                    £<?php echo number_format($row['balance'], 2); ?>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                                <tfoot class="table-danger fw-bold">
                                                    <tr>
                                                        <td colspan="2">Total Liabilities</td>
                                                        <td class="text-end text-nowrap">
                                                            £<?php echo number_format($total_liabilities, 2); ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                                <!-- Equity -->
                                <div class="card shadow-sm">
                                    <div class="card-header bg-primary text-white">
                                        <strong><i class="bi bi-bank"></i> Equity</strong>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm mb-0 align-middle">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Code</th>
                                                        <th>Account</th>
                                                        <th class="text-end">Balance</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($equity as $row): ?>
                                                        <tr>
                                                            <td class="text-muted small"><?php echo $row['code']; ?></td>
                                                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                                                            <td class="text-end text-nowrap fw-semibold text-primary">
                                                                £<?php echo number_format($row['balance'], 2); ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <!-- Current Period Profit -->
                                                    <tr>
                                                        <td class="text-muted small">—</td>
                                                        <td>
                                                            Current Period Profit
                                                            <span class="text-muted small">(P&amp;L)</span>
                                                        </td>
                                                        <td class="text-end text-nowrap fw-semibold
                                                            <?php echo $current_period_profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            <?php if ($current_period_profit < 0): ?>
                                                                (£<?php echo number_format(abs($current_period_profit), 2); ?>)
                                                            <?php else: ?>
                                                                £<?php echo number_format($current_period_profit, 2); ?>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tfoot class="table-primary fw-bold">
                                                    <tr>
                                                        <td colspan="2">Total Equity</td>
                                                        <td class="text-end text-nowrap">
                                                            £<?php echo number_format($total_equity, 2); ?>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Balance Check Footer -->
                        <div class="card shadow-sm mt-4">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-4 border-end">
                                        <div class="text-muted small">Net Assets</div>
                                        <div class="fs-4 fw-bold text-primary">
                                            £<?php echo number_format($net_assets, 2); ?>
                                        </div>
                                        <div class="text-muted small">Assets − Liabilities</div>
                                    </div>
                                    <div class="col-md-4 border-end">
                                        <div class="text-muted small">Total Equity</div>
                                        <div class="fs-4 fw-bold text-primary">
                                            £<?php echo number_format($total_equity, 2); ?>
                                        </div>
                                        <div class="text-muted small">Capital + Retained + P&amp;L</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-muted small">Status</div>
                                        <div class="fs-4 fw-bold <?php echo $is_balanced ? 'text-success' : 'text-danger'; ?>">
                                            <?php if ($is_balanced): ?>
                                                ✅ Balanced
                                            <?php else: ?>
                                                ❌ Out by £<?php echo number_format($difference, 2); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-muted small">Net Assets should equal Equity</div>
                                    </div>
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
    nav, .btn, form { display: none !important; }
    .container { margin: 0 !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>