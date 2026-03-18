<?php
// report_profit_loss.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    // ── Filters ───────────────────────────────────────────────────────────────
    $filter_from = $_GET['from'] ?? '';
    $filter_to = $_GET['to'] ?? '';

    // Build date conditions for each source
    $bt_where = "1=1";
    $je_where = "1=1";
    $params_bt = [];
    $params_je = [];

    if ($filter_from) {
        $bt_where .= " AND bt.transaction_date >= :from_bt";
        $je_where .= " AND je.date >= :from_je";
        $params_bt[':from_bt'] = $filter_from;
        $params_je[':from_je'] = $filter_from;
    }
    if ($filter_to) {
        $bt_where .= " AND bt.transaction_date <= :to_bt";
        $je_where .= " AND je.date <= :to_je";
        $params_bt[':to_bt'] = $filter_to;
        $params_je[':to_je'] = $filter_to;
    }

    // ── Combined query: bank transactions + journal entry lines ───────────────
    $sql = "
        SELECT
            coa.id,
            coa.code,
            coa.name,
            coa.type,
            SUM(CASE WHEN src_type = 'credit' THEN amount ELSE 0 END) AS total_credit,
            SUM(CASE WHEN src_type = 'debit'  THEN amount ELSE 0 END) AS total_debit
        FROM (

            -- Source 1: Bank Transactions
            SELECT
                bt.coa_id,
                bt.type   AS src_type,
                bt.amount AS amount
            FROM bank_transactions bt
            WHERE {$bt_where}

            UNION ALL

            -- Source 2: Journal Entry Lines
            SELECT
                jel.coa_id,
                jel.type   AS src_type,
                jel.amount AS amount
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE {$je_where}

        ) AS combined
        JOIN chart_of_accounts coa ON combined.coa_id = coa.id
        WHERE coa.type IN ('income', 'expense')
          AND coa.is_active = 1
        GROUP BY coa.id, coa.code, coa.name, coa.type
        ORDER BY coa.code ASC
    ";

    // Merge params and execute
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params_bt, $params_je));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Separate into sections ────────────────────────────────────────────────
    $income = [];
    $cos = [];
    $expenses = [];

    foreach ($rows as $row) {
        if ($row['type'] === 'income') {
            $row['net'] = $row['total_credit'] - $row['total_debit']; // ← FLIPPED
            $income[] = $row;
        } elseif ($row['type'] === 'expense') {
            $row['net'] = $row['total_debit'] - $row['total_credit']; // ← stays the same
            if ($row['code'] >= 5000 && $row['code'] <= 5099) {
                $cos[] = $row;
            } else {
                $expenses[] = $row;
            }
        }
    }

    // ── Totals ────────────────────────────────────────────────────────────────
    $total_income = array_sum(array_column($income, 'net'));
    $total_cos = array_sum(array_column($cos, 'net'));
    $gross_profit = $total_income - $total_cos;
    $total_expenses = array_sum(array_column($expenses, 'net'));
    $net_profit = $gross_profit - $total_expenses;

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $income = $cos = $expenses = [];
    $total_income = $total_cos = $gross_profit = $total_expenses = $net_profit = 0;
}

$page_title = 'Profit & Loss';
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
                        <h4 class="page-title">Profit &amp; Loss</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Profit &amp; Loss</li>
                            </ol>
                        </div>
                    </div>

                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <span class="text-muted small">
                                <?php if ($filter_from || $filter_to): ?>
                                    <?= $filter_from ? date('d M Y', strtotime($filter_from)) : 'Start' ?>
                                    &mdash;
                                    <?= $filter_to ? date('d M Y', strtotime($filter_to)) : 'Today' ?>
                                <?php else: ?>
                                    All Dates
                                <?php endif; ?>
                            </span>
                        </div>
                        <button class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print / PDF
                        </button>
                    </div>

                    <!-- Alerts -->
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="alert alert-success">
                            <?= $_SESSION['message'];
                            unset($_SESSION['message']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger">
                            <?= $_SESSION['error'];
                            unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Summary Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small">Total Income</div>
                                    <div class="fs-4 fw-bold text-success">
                                        £<?= number_format($total_income, 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small">Gross Profit</div>
                                    <div class="fs-4 fw-bold <?= $gross_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                        £<?= number_format($gross_profit, 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small">Total Expenses</div>
                                    <div class="fs-4 fw-bold text-danger">
                                        £<?= number_format($total_expenses, 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="text-muted small">Net Profit / (Loss)</div>
                                    <div class="fs-4 fw-bold <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?php if ($net_profit < 0): ?>
                                            (£<?= number_format(abs($net_profit), 2) ?>)
                                        <?php else: ?>
                                            £<?= number_format($net_profit, 2) ?>
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
                                           value="<?= htmlspecialchars($filter_from) ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">To Date</label>
                                    <input type="date" name="to" class="form-control form-control-sm"
                                           value="<?= htmlspecialchars($filter_to) ?>">
                                </div>
                                <div class="col-md-3 d-flex gap-2 align-items-end">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-filter"></i> Filter
                                    </button>
                                    <a href="report_profit_loss.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-x"></i> Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- P&L Table -->
                    <div class="card shadow-sm">
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0 align-middle">

                                <!-- INCOME -->
                                <thead class="table-dark">
                                    <tr>
                                        <th colspan="2">Income</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($income)): ?>
                                        <tr><td colspan="3" class="text-muted text-center py-2">No income recorded</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($income as $row): ?>
                                        <tr>
                                            <td class="text-muted small ps-3" width="80"><?= $row['code'] ?></td>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td class="text-end text-success fw-semibold">
                                                £<?= number_format($row['net'], 2) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-success fw-bold">
                                        <td colspan="2" class="ps-3">Total Income</td>
                                        <td class="text-end">£<?= number_format($total_income, 2) ?></td>
                                    </tr>
                                </tfoot>

                                <!-- COST OF SALES -->
                                <thead class="table-dark">
                                    <tr>
                                        <th colspan="2">Cost of Sales</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($cos)): ?>
                                        <tr><td colspan="3" class="text-muted text-center py-2">No cost of sales recorded</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($cos as $row): ?>
                                        <tr>
                                            <td class="text-muted small ps-3" width="80"><?= $row['code'] ?></td>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td class="text-end text-danger fw-semibold">
                                                £<?= number_format($row['net'], 2) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-warning fw-bold">
                                        <td colspan="2" class="ps-3">Gross Profit</td>
                                        <td class="text-end <?= $gross_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?php if ($gross_profit < 0): ?>
                                                (£<?= number_format(abs($gross_profit), 2) ?>)
                                            <?php else: ?>
                                                £<?= number_format($gross_profit, 2) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tfoot>

                                <!-- EXPENSES -->
                                <thead class="table-dark">
                                    <tr>
                                        <th colspan="2">Expenses</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($expenses)): ?>
                                        <tr><td colspan="3" class="text-muted text-center py-2">No expenses recorded</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($expenses as $row): ?>
                                        <tr>
                                            <td class="text-muted small ps-3" width="80"><?= $row['code'] ?></td>
                                            <td><?= htmlspecialchars($row['name']) ?></td>
                                            <td class="text-end text-danger fw-semibold">
                                                £<?= number_format($row['net'], 2) ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-danger fw-bold">
                                        <td colspan="2" class="ps-3">Total Expenses</td>
                                        <td class="text-end text-danger">
                                            £<?= number_format($total_expenses, 2) ?>
                                        </td>
                                    </tr>
                                </tfoot>

                                <!-- NET PROFIT -->
                                <thead>
                                    <tr class="<?= $net_profit >= 0 ? 'table-success' : 'table-danger' ?> fw-bold fs-5">
                                        <td colspan="2">Net <?= $net_profit >= 0 ? 'Profit' : 'Loss' ?></td>
                                        <td class="text-end <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>">
                                            <?php if ($net_profit < 0): ?>
                                                (£<?= number_format(abs($net_profit), 2) ?>)
                                            <?php else: ?>
                                                £<?= number_format($net_profit, 2) ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </thead>

                            </table>
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