<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

// Weekly Sales
$stmt_weekly = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN bt.type = 'credit' THEN bt.amount ELSE 0 END) AS weekly_sales
    FROM bank_transactions bt
    WHERE bt.coa_id = 13
    AND bt.user_id = ?
    AND YEARWEEK(bt.transaction_date, 1) = YEARWEEK(NOW(), 1)
");
$stmt_weekly->execute([$user_id]);
$row_weekly = $stmt_weekly->fetch();

// Monthly Sales
$stmt_monthly = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN bt.type = 'credit' THEN bt.amount ELSE 0 END) AS monthly_sales
    FROM bank_transactions bt
    WHERE bt.coa_id = 13
    AND bt.user_id = ?
    AND YEAR(bt.transaction_date) = YEAR(NOW())
    AND MONTH(bt.transaction_date) = MONTH(NOW())
");
$stmt_monthly->execute([$user_id]);
$row_monthly = $stmt_monthly->fetch();

// Yearly Sales
$stmt_yearly = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN bt.type = 'credit' THEN bt.amount ELSE 0 END) AS yearly_sales
    FROM bank_transactions bt
    WHERE bt.coa_id = 13
    AND bt.user_id = ?
    AND YEAR(bt.transaction_date) = YEAR(NOW())
");
$stmt_yearly->execute([$user_id]);
$row_yearly = $stmt_yearly->fetch();

$weekly_sales = $row_weekly['weekly_sales'] ?? 0;
$monthly_sales = $row_monthly['monthly_sales'] ?? 0;
$yearly_sales = $row_yearly['yearly_sales'] ?? 0;

// Last 365 Days Sales
$stmt_last365 = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN bt.type = 'credit' THEN bt.amount ELSE 0 END) AS last365_sales
    FROM bank_transactions bt
    WHERE bt.coa_id = 13
    AND bt.user_id = ?
    AND bt.transaction_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)
");
$stmt_last365->execute([$user_id]);
$row_last365 = $stmt_last365->fetch();
$last365_sales = $row_last365['last365_sales'] ?? 0;

// Director Loan Balance — look up COA id by code 2400
$stmt_dla_coa = $pdo->prepare("
    SELECT id FROM chart_of_accounts WHERE code = '2400' AND is_active = 1 LIMIT 1
");
$stmt_dla_coa->execute();
$dla_coa = $stmt_dla_coa->fetch();
$dla_coa_id = $dla_coa['id'] ?? null;

// Director loan balance — bank transactions + journal entries
$stmt_director_loan = $pdo->prepare("
    SELECT 
        SUM(CASE WHEN source = 'bank' AND type = 'credit' THEN amount
                 WHEN source = 'bank' AND type = 'debit'  THEN -amount
                 WHEN source = 'journal' AND type = 'debit'  THEN amount
                 WHEN source = 'journal' AND type = 'credit' THEN -amount
                 ELSE 0 END) AS director_loan_balance
    FROM (
        SELECT 'bank' AS source, type, amount
        FROM bank_transactions
        WHERE coa_id = ? AND user_id = ?

        UNION ALL

        SELECT 'journal' AS source, jel.type, jel.amount
        FROM journal_entry_lines jel
        JOIN journal_entries je ON jel.journal_entry_id = je.id
        WHERE jel.coa_id = ?
    ) AS combined
");
$stmt_director_loan->execute([$dla_coa_id, $user_id, $dla_coa_id]);
$row_director_loan = $stmt_director_loan->fetch();
$director_loan_balance = $row_director_loan['director_loan_balance'] ?? 0;

// Outstanding Invoices — invoices table has no user_id column
$stmt_outstanding_invoices = $pdo->prepare("
    SELECT SUM(total) AS outstanding_invoices_total
    FROM invoices
    WHERE status != 'paid'
");
$stmt_outstanding_invoices->execute();
$row_outstanding_invoices = $stmt_outstanding_invoices->fetch();
$outstanding_invoices_total = $row_outstanding_invoices['outstanding_invoices_total'] ?? 0;

// Top 10 Largest Expenses (Last 365 Days) — expenses are DEBIT
$stmt_largest_expenses = $pdo->prepare("
    SELECT bt.transaction_date, bt.description, bt.amount
    FROM bank_transactions bt
    WHERE bt.type = 'debit'
    AND bt.user_id = ?
    AND bt.transaction_date >= DATE_SUB(NOW(), INTERVAL 365 DAY)
    ORDER BY bt.amount DESC
    LIMIT 10
");
$stmt_largest_expenses->execute([$user_id]);
$row_largest_expenses = $stmt_largest_expenses->fetchAll();

// Latest Notes
$stmt_notes = $pdo->query("
    SELECT id, title, content, created_at, updated_at
    FROM notes 
    ORDER BY updated_at DESC
    LIMIT 5
");
$notes = $stmt_notes->fetchAll();

// Next 7 Days Diary Entries
$stmt_diary = $pdo->prepare("
    SELECT title, entry_date, entry_time, notes
    FROM diary
    WHERE entry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY entry_date ASC, entry_time ASC
");
$stmt_diary->execute();
$diary_entries = $stmt_diary->fetchAll();

// Accounting Period from business settings
$stmt_period = $pdo->prepare("
    SELECT accounting_period_start, accounting_period_end 
    FROM business_settings 
    WHERE user_id = ?
");
$stmt_period->execute([$user_id]);
$period = $stmt_period->fetch();

$period_start = $period['accounting_period_start'] ?? date('Y-01-01');
$period_end = $period['accounting_period_end'] ?? date('Y-12-31');

// P&L by Month for the Accounting Period
$stmt_pl = $pdo->prepare("
    SELECT 
        DATE_FORMAT(bt.transaction_date, '%Y-%m') AS month,
        SUM(CASE WHEN bt.type = 'credit' AND c.type = 'income' THEN bt.amount ELSE 0 END) AS income,
        SUM(CASE WHEN bt.type = 'debit'  AND c.type = 'expense' THEN bt.amount ELSE 0 END) AS expenses
    FROM bank_transactions bt
    JOIN chart_of_accounts c ON c.id = bt.coa_id
    WHERE bt.user_id = ?
    AND bt.transaction_date BETWEEN ? AND ?
    AND c.type IN ('income', 'expense')
    GROUP BY DATE_FORMAT(bt.transaction_date, '%Y-%m')
    ORDER BY month ASC
");
$stmt_pl->execute([$user_id, $period_start, $period_end]);
$pl_rows = $stmt_pl->fetchAll();

// Build full month array for the period (fills gaps with zeros)
$pl_data = [];
$cursor = new DateTime($period_start);
$end = new DateTime($period_end);

while ($cursor <= $end) {
    $key = $cursor->format('Y-m');
    $pl_data[$key] = ['income' => 0, 'expenses' => 0, 'profit' => 0];
    $cursor->modify('+1 month');
}

foreach ($pl_rows as $row) {
    $key = $row['month'];
    if (isset($pl_data[$key])) {
        $pl_data[$key]['income'] = (float) $row['income'];
        $pl_data[$key]['expenses'] = (float) $row['expenses'];
        $pl_data[$key]['profit'] = (float) $row['income'] - (float) $row['expenses'];
    }
}

$pl_labels = array_map(fn($k) => date('M Y', strtotime($k . '-01')), array_keys($pl_data));
$pl_income = array_column($pl_data, 'income');
$pl_expenses = array_column($pl_data, 'expenses');
$pl_profit = array_column($pl_data, 'profit');

if ($dla_coa_id) {
    // All transactions — bank + journal — ordered by date
    $stmt_dla = $pdo->prepare("
        SELECT source, type, amount, transaction_date, description
        FROM (
            SELECT 
                'bank' AS source,
                type,
                amount,
                transaction_date,
                description,
                id
            FROM bank_transactions
            WHERE coa_id = ? AND user_id = ?

            UNION ALL

            SELECT 
                'journal' AS source,
                jel.type,
                jel.amount,
                je.date AS transaction_date,
                je.description,
                jel.id
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE jel.coa_id = ?
        ) AS combined
        ORDER BY transaction_date ASC, id ASC
    ");
    $stmt_dla->execute([$dla_coa_id, $user_id, $dla_coa_id]);
    $dla_transactions = $stmt_dla->fetchAll();

    $dla_running = [];
    $dla_labels = [];
    $running_bal = 0;

    foreach ($dla_transactions as $t) {
        if ($t['source'] === 'bank') {
            $running_bal += ($t['type'] === 'credit') ? $t['amount'] : -$t['amount'];
        } else {
            // journal: debit = director invested, credit = repaid
            $running_bal += ($t['type'] === 'debit') ? $t['amount'] : -$t['amount'];
        }
        $dla_labels[] = date('d M Y', strtotime($t['transaction_date']));
        $dla_running[] = round($running_bal, 2);
    }

    // Monthly movement — bank + journal combined
    $stmt_dla_monthly = $pdo->prepare("
        SELECT
            month,
            SUM(borrowed) AS borrowed,
            SUM(repaid)   AS repaid
        FROM (
            SELECT
                DATE_FORMAT(transaction_date, '%Y-%m') AS month,
                CASE WHEN type = 'credit' THEN amount ELSE 0 END AS borrowed,
                CASE WHEN type = 'debit'  THEN amount ELSE 0 END AS repaid
            FROM bank_transactions
            WHERE coa_id = ? AND user_id = ?

            UNION ALL

            SELECT
                DATE_FORMAT(je.date, '%Y-%m') AS month,
                CASE WHEN jel.type = 'debit'  THEN jel.amount ELSE 0 END AS borrowed,
                CASE WHEN jel.type = 'credit' THEN jel.amount ELSE 0 END AS repaid
            FROM journal_entry_lines jel
            JOIN journal_entries je ON jel.journal_entry_id = je.id
            WHERE jel.coa_id = ?
        ) AS combined
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt_dla_monthly->execute([$dla_coa_id, $user_id, $dla_coa_id]);
    $dla_monthly_rows = $stmt_dla_monthly->fetchAll();

    $dla_month_labels = [];
    $dla_borrowed = [];
    $dla_repaid = [];

    foreach ($dla_monthly_rows as $row) {
        $dla_month_labels[] = date('M Y', strtotime($row['month'] . '-01'));
        $dla_borrowed[] = (float) $row['borrowed'];
        $dla_repaid[] = (float) $row['repaid'];
    }

    $dla_current_balance = end($dla_running) ?: 0;

} else {
    $dla_transactions = [];
    $dla_labels = [];
    $dla_running = [];
    $dla_month_labels = [];
    $dla_borrowed = [];
    $dla_repaid = [];
    $dla_current_balance = 0;
}

// Unreconciled Items Count — scoped to current user
$stmt_unreconciled = $pdo->prepare("
    SELECT COUNT(*) AS unreconciled_count
    FROM bank_transactions
    WHERE reconciled = 0
    AND user_id = ?
");
$stmt_unreconciled->execute([$user_id]);
$row_unreconciled = $stmt_unreconciled->fetch();
$unreconciled_count = $row_unreconciled['unreconciled_count'] ?? 0;

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
                        <h4 class="page-title">Dashboard</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item active"><a href="index.php">Dashboard</a></li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── KPI Cards ─────────────────────────────────────── -->
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="row">

                        <div class="col-md-4">
                            <div class="card bg-welcome-img overflow-hidden">
                                <div class="card-body">
                                    <h5 class="card-title">Weekly Sales</h5>
                                    <h2 class="text-success">£<?php echo number_format($weekly_sales, 2); ?></h2>
                                    <p class="text-muted">Total sales for the current week</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-welcome-img overflow-hidden">
                                <div class="card-body">
                                    <h5 class="card-title">Monthly Sales</h5>
                                    <h2 class="text-success">£<?php echo number_format($monthly_sales, 2); ?></h2>
                                    <p class="text-muted">Total sales for the current month</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-welcome-img overflow-hidden">
                                <div class="card-body">
                                    <h5 class="card-title">Year to Date Sales</h5>
                                    <h2 class="text-success">£<?= number_format($yearly_sales, 2) ?></h2>
                                    <p class="text-muted">Total sales from January 1st to today (<?php echo date('d-m-Y'); ?>)</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-welcome-img overflow-hidden">
                                <div class="card-body">
                                    <h5 class="card-title">Last 365 Days Sales</h5>
                                    <h2 class="text-success">£<?= number_format($last365_sales, 2) ?></h2>
                                    <p class="text-muted">Total sales for the last 365 days</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-welcome-img overflow-hidden">
                                <div class="card-body">
                                    <h5 class="card-title">Director Loan Account Balance</h5>
                                    <?php
                                    // Positive = company owes director | Negative = director owes company
                                    $dla_colour = $director_loan_balance >= 0 ? 'text-danger' : 'text-success';
                                    $dla_label = $director_loan_balance >= 0 ? 'Owed to Director' : 'Owed to Company';
                                    ?>
                                    <h2 class="<?= $dla_colour ?>">
                                        £<?= number_format(abs($director_loan_balance), 2) ?>
                                    </h2>
                                    <p class="text-muted"><?= $dla_label ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card bg-welcome-img overflow-hidden">
                                <div class="card-body">
                                    <h5 class="card-title">Outstanding Invoices</h5>
                                    <h2 class="text-danger">£<?= number_format($outstanding_invoices_total, 2) ?></h2>
                                    <p class="text-muted">Total value of outstanding invoices</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Top Expenses / Notes / Diary ─────────────────── -->
            <div class="row justify-content-center">

                <div class="col-md-12 col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Top Expenses (Last 365 Days)</h4>
                        </div>
                        <div class="card-body pt-0">
                            <?php if (!empty($row_largest_expenses)): ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($row_largest_expenses as $expense): ?>
                                        <tr>
                                            <td><?php echo date('d-m-Y', strtotime($expense['transaction_date'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($expense['description'] ?? ''); ?></td>
                                            <td>£<?php echo number_format($expense['amount'] ?? 0, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted mt-2">No expenses found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Latest Notes</h4>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="notes.php" class="btn btn-sm btn-outline-primary">View All</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body pt-2">
                            <?php if (empty($notes)): ?>
                                <p class="text-muted mt-2">No notes found.</p>
                            <?php else: ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($notes as $note): ?>
                                        <li class="border-bottom py-2">
                                            <a href="note.php?id=<?php echo $note['id']; ?>"
                                               class="text-decoration-none d-flex justify-content-between align-items-center">
                                                <span class="text-dark"><?php echo htmlspecialchars($note['title']); ?></span>
                                                <i class="fas fa-chevron-right text-muted small"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Upcoming Events</h4>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <a href="diary.php" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-calendar me-1"></i> Manage
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body pt-2">
                            <?php if (empty($diary_entries)): ?>
                                <p class="text-muted mt-2">No events in the next 7 days.</p>
                            <?php else: ?>
                                <ul class="list-unstyled mb-0">
                                    <?php foreach ($diary_entries as $de): ?>
                                        <li class="mb-3 border-bottom pb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong><?php echo htmlspecialchars($de['title']); ?></strong>
                                                <span class="badge bg-primary ms-2">
                                                    <?php echo date('d M', strtotime($de['entry_date'])); ?>
                                                </span>
                                            </div>
                                            <?php if ($de['entry_time']): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($de['entry_time'])); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($de['notes']): ?>
                                                <p class="text-muted small mb-0 mt-1">
                                                    <?php echo htmlspecialchars(mb_strimwidth($de['notes'], 0, 60, '...')); ?>
                                                </p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── P&L / DLA / Unreconciled ──────────────────────── -->
            <div class="row justify-content-center">

                <div class="col-md-6 col-lg-3 order-2 order-lg-1">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Profit / Loss</h4>
                            <small class="text-muted">
                                <?php echo date('d M Y', strtotime($period_start)); ?>
                                &ndash;
                                <?php echo date('d M Y', strtotime($period_end)); ?>
                            </small>
                        </div>
                        <div class="card-body">
                            <canvas id="plChart"></canvas>
                            <div class="row text-center mt-3 g-2">
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Income</small>
                                        <strong class="text-success">
                                            £<?php echo number_format(array_sum($pl_income), 2); ?>
                                        </strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Expenses</small>
                                        <strong class="text-danger">
                                            £<?php echo number_format(array_sum($pl_expenses), 2); ?>
                                        </strong>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-2">
                                        <small class="text-muted d-block">Profit</small>
                                        <strong class="<?php echo array_sum($pl_profit) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            £<?php echo number_format(array_sum($pl_profit), 2); ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-12 col-lg-6 order-1 order-lg-2">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Director Loan Account</h4>
                            <span class="badge <?php echo $dla_current_balance >= 0 ? 'bg-danger' : 'bg-success'; ?> fs-6">
                                Balance: £<?php echo number_format(abs($dla_current_balance), 2); ?>
                                <?php echo $dla_current_balance >= 0 ? '— Owed to Director' : '— Owed to Company'; ?>
                            </span>
                        </div>
                        <div class="card-body pt-2">
                            <?php if (empty($dla_transactions)): ?>
                                <p class="text-muted mt-2">No director loan transactions found.</p>
                            <?php else: ?>
                                <ul class="nav nav-tabs mb-3" id="dlaTab" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="dla-balance-tab" data-bs-toggle="tab"
                                            data-bs-target="#dla-balance" type="button">Running Balance</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="dla-monthly-tab" data-bs-toggle="tab"
                                            data-bs-target="#dla-monthly" type="button">Monthly Movement</button>
                                    </li>
                                </ul>
                                <div class="tab-content" id="dlaTabContent">
                                    <div class="tab-pane fade show active" id="dla-balance" role="tabpanel">
                                        <canvas id="dlaBalanceChart"></canvas>
                                    </div>
                                    <div class="tab-pane fade" id="dla-monthly" role="tabpanel">
                                        <canvas id="dlaMonthlyChart"></canvas>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3 order-3 order-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Unreconciled Items</h4>
                        </div>
                        <div class="card-body text-center py-4">
                            <?php if ($unreconciled_count === 0): ?>
                                <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                                <h4 class="mt-3 text-success">All Reconciled</h4>
                                <p class="text-muted">There are no unreconciled transactions.</p>
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle text-warning" style="font-size: 3rem;"></i>
                                <h1 class="mt-3 <?php echo $unreconciled_count > 10 ? 'text-danger' : 'text-warning'; ?>">
                                    <?php echo number_format($unreconciled_count); ?>
                                </h1>
                                <p class="text-muted">
                                    Unreconciled transaction<?php echo $unreconciled_count !== 1 ? 's' : ''; ?>
                                    requiring attention
                                </p>
                                <a href="bank_transactions.php?reconciled=0" class="btn btn-sm btn-outline-warning mt-2">
                                    <i class="fas fa-list me-1"></i> View All
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ── P&L Chart ──────────────────────────────────────────────
const plLabels   = <?php echo json_encode(array_values($pl_labels)); ?>;
const plIncome   = <?php echo json_encode(array_values($pl_income)); ?>;
const plExpenses = <?php echo json_encode(array_values($pl_expenses)); ?>;
const plProfit   = <?php echo json_encode(array_values($pl_profit)); ?>;

new Chart(document.getElementById('plChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: plLabels,
        datasets: [
            {
                label: 'Income',
                data: plIncome,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1,
                order: 2
            },
            {
                label: 'Expenses',
                data: plExpenses,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1,
                order: 2
            },
            {
                label: 'Profit',
                data: plProfit,
                type: 'line',
                borderColor: 'rgba(13, 110, 253, 1)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderWidth: 2,
                pointRadius: 4,
                fill: false,
                tension: 0.3,
                order: 1
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12 } },
            tooltip: {
                callbacks: {
                    label: ctx => ` £${parseFloat(ctx.raw).toLocaleString('en-GB', {minimumFractionDigits: 2})}`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: val => '£' + val.toLocaleString('en-GB') }
            },
            x: { ticks: { maxRotation: 45 } }
        }
    }
});

// ── Director Loan Account Charts ───────────────────────────
<?php if (!empty($dla_transactions)): ?>
const dlaLabels      = <?php echo json_encode($dla_labels); ?>;
const dlaRunning     = <?php echo json_encode($dla_running); ?>;
const dlaMonthLabels = <?php echo json_encode($dla_month_labels); ?>;
const dlaBorrowed    = <?php echo json_encode($dla_borrowed); ?>;
const dlaRepaid      = <?php echo json_encode($dla_repaid); ?>;

// Running balance line chart
new Chart(document.getElementById('dlaBalanceChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: dlaLabels,
        datasets: [{
            label: 'Running Balance',
            data: dlaRunning,
            borderColor: 'rgba(13, 110, 253, 1)',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            borderWidth: 2,
            pointRadius: 3,
            fill: true,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => ` £${parseFloat(ctx.raw).toLocaleString('en-GB', {minimumFractionDigits: 2})}`
                }
            }
        },
        scales: {
            y: { ticks: { callback: val => '£' + val.toLocaleString('en-GB') } },
            x: { ticks: { maxTicksLimit: 10, maxRotation: 45 } }
        }
    }
});

// Monthly movement bar chart
new Chart(document.getElementById('dlaMonthlyChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: dlaMonthLabels,
        datasets: [
            {
                label: 'Borrowed',
                data: dlaBorrowed,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1
            },
            {
                label: 'Repaid',
                data: dlaRepaid,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12 } },
            tooltip: {
                callbacks: {
                    label: ctx => ` £${parseFloat(ctx.raw).toLocaleString('en-GB', {minimumFractionDigits: 2})}`
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: val => '£' + val.toLocaleString('en-GB') }
            },
            x: { ticks: { maxRotation: 45 } }
        }
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>