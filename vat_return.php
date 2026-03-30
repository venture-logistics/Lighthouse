<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$user_id = $_SESSION['user_id'];

// Get VAT settings
$stmt = $pdo->prepare("SELECT * FROM vat_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
$vat = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vat || !$vat['is_vat_registered']) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'You are not VAT registered. Please update your VAT settings.'];
    header('Location: business_settings.php#vat_registered');
    exit;
}

// ── Quarter helpers ───────────────────────────────────────────────────────────
function get_quarter_months(string $stagger): array {
    return match($stagger) {
        'Jan' => [[1,  31, 'Jan'], [4,  30, 'Apr'], [7,  31, 'Jul'], [10, 31, 'Oct']],
        'Feb' => [[2,  28, 'Feb'], [5,  31, 'May'], [8,  31, 'Aug'], [11, 30, 'Nov']],
        default=> [[3,  31, 'Mar'], [6,  30, 'Jun'], [9,  30, 'Sep'], [12, 31, 'Dec']],
    };
}

function get_current_quarter(string $stagger): array {
    $now        = new DateTime();
    $cur_month  = (int)$now->format('n');
    $cur_year   = (int)$now->format('Y');
    $quarters   = get_quarter_months($stagger);

    // Each quarter = [end_month, end_day, label]
    // Build quarter start/end pairs
    $ranges = [];
    foreach ($quarters as $i => [$end_m, $end_d, $label]) {
        // Start month = previous quarter end month + 1
        $prev        = $quarters[($i + 3) % 4];
        $start_m     = $prev[0] + 1;
        if ($start_m > 12) $start_m -= 12;

        $ranges[] = [
            'start_month' => $start_m,
            'end_month'   => $end_m,
            'end_day'     => $end_d,
            'label'       => $label,
        ];
    }

    // Find which quarter we're currently in
    foreach ($ranges as $q) {
        $sm = $q['start_month'];
        $em = $q['end_month'];

        if ($sm <= $em) {
            $in = $cur_month >= $sm && $cur_month <= $em;
        } else {
            // wraps year e.g. Nov–Jan
            $in = $cur_month >= $sm || $cur_month <= $em;
        }

        if ($in) {
            // Build actual dates
            if ($sm <= $cur_month) {
                $start_year = $cur_year;
            } else {
                $start_year = $cur_year - 1;
            }
            $end_year = ($em < $sm) ? $cur_year : $start_year;
            if ($em >= $sm && $em < $sm) $end_year++;

            // Simpler: end is always current year if end_month <= cur_month
            $end_year   = $cur_year;
            $start_year = ($sm > $em || $sm > $cur_month) ? $cur_year - 1 : $cur_year;

            $start = sprintf('%04d-%02d-01', $start_year, $sm);
            $end   = sprintf('%04d-%02d-%02d', $end_year, $em, $q['end_day']);

            $deadline = (new DateTime($end))->modify('+1 month +7 days')->format('d M Y');

            return [
                'start'    => $start,
                'end'      => $end,
                'label'    => $q['label'],
                'deadline' => $deadline,
            ];
        }
    }

    // Fallback — should never hit
    return [
        'start'    => date('Y-m-01'),
        'end'      => date('Y-m-t'),
        'label'    => date('M'),
        'deadline' => '',
    ];
}

// ── Selected quarter (default = current) ─────────────────────────────────────
$stagger  = $vat['vat_quarter_end'] ?? 'Mar';
$current  = get_current_quarter($stagger);

$quarter_start = $_GET['from'] ?? $current['start'];
$quarter_end   = $_GET['to']   ?? $current['end'];

// Build deadline for selected period
$deadline = (new DateTime($quarter_end))->modify('+1 month +7 days')->format('d M Y');

// ── VAT Calculation ───────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT 
        bt.amount,
        bt.type,
        bt.transaction_date,
        bt.description,
        bt.tax_amount,
        coa.name     AS coa_name,
        coa.code     AS coa_code,
        coa.type     AS coa_type,
        coa.vat_rate,
        ROUND(
            CASE 
                WHEN bt.tax_amount > 0 
                    THEN bt.tax_amount
                WHEN (bt.tax_amount = 0 OR bt.tax_amount IS NULL) AND coa.vat_rate > 0 
                    THEN bt.amount * coa.vat_rate / (100 + coa.vat_rate)
                ELSE 0 
            END
        , 2) AS vat_amount,
        ROUND(
            CASE 
                WHEN bt.tax_amount > 0 
                    THEN bt.amount - bt.tax_amount
                WHEN (bt.tax_amount = 0 OR bt.tax_amount IS NULL) AND coa.vat_rate > 0 
                    THEN bt.amount / (1 + coa.vat_rate / 100)
                ELSE bt.amount 
            END
        , 2) AS net_amount
    FROM bank_transactions bt
    JOIN chart_of_accounts coa ON bt.coa_id = coa.id
    WHERE bt.user_id = :uid
      AND bt.transaction_date BETWEEN :start AND :end
      AND (coa.vat_rate > 0 OR bt.tax_amount > 0)
    ORDER BY bt.transaction_date ASC
");
$stmt->execute([':uid' => $user_id, ':start' => $quarter_start, ':end' => $quarter_end]);
$vat_txns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// All transactions for Box 6 / Box 7 totals (including zero-rated)
$stmt2 = $pdo->prepare("
    SELECT 
        bt.amount,
        bt.type,
        coa.vat_rate,
        ROUND(bt.amount / (1 + COALESCE(coa.vat_rate,0) / 100), 2) AS net_amount
    FROM bank_transactions bt
    LEFT JOIN chart_of_accounts coa ON bt.coa_id = coa.id
    WHERE bt.user_id = :uid
      AND bt.transaction_date BETWEEN :start AND :end
    ORDER BY bt.transaction_date ASC
");
$stmt2->execute([':uid' => $user_id, ':start' => $quarter_start, ':end' => $quarter_end]);
$all_txns = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// ── HMRC Boxes ────────────────────────────────────────────────────────────────
$box1 = 0; // VAT on sales
$box4 = 0; // VAT on purchases
$sales_txns    = [];
$purchase_txns = [];

foreach ($vat_txns as $tx) {
    if ($tx['type'] === 'credit') {
        $box1 += $tx['vat_amount'];
        $sales_txns[] = $tx;
    } else {
        $box4 += $tx['vat_amount'];
        $purchase_txns[] = $tx;
    }
}

$box3 = $box1;                    // VAT charged (same as box 1 for standard scheme)
$box5 = round($box3 - $box4, 2); // Net VAT payable / reclaimable

$box6 = 0; // Total sales net of VAT
$box7 = 0; // Total purchases net of VAT
foreach ($all_txns as $tx) {
    if ($tx['type'] === 'credit') {
        $box6 += $tx['net_amount'];
    } else {
        $box7 += $tx['net_amount'];
    }
}
$box6 = round($box6, 2);
$box7 = round($box7, 2);

// ── Build available quarters for dropdown ─────────────────────────────────────
function get_all_quarters(string $stagger, string $vat_start): array {
    $quarters   = get_quarter_months($stagger);
    $start_date = new DateTime($vat_start);
    $today      = new DateTime();
    $result     = [];

    // Go back up to 4 years
    for ($y = (int)$today->format('Y') - 3; $y <= (int)$today->format('Y'); $y++) {
        foreach ($quarters as [$end_m, $end_d, $label]) {
            $end   = new DateTime("$y-$end_m-$end_d");
            if ($end < $start_date || $end > $today) continue;

            $prev_q    = $quarters[array_search([$end_m, $end_d, $label], $quarters) === 0
                            ? 3
                            : array_search([$end_m, $end_d, $label], $quarters) - 1];
            $start_m   = $prev_q[0] + 1;
            if ($start_m > 12) $start_m -= 12;
            $start_y   = ($start_m > $end_m) ? $y - 1 : $y;
            $start     = new DateTime("$start_y-$start_m-01");

            $result[] = [
                'label' => $label . ' ' . $y,
                'start' => $start->format('Y-m-d'),
                'end'   => $end->format('Y-m-d'),
            ];
        }
    }

    // Most recent first
    return array_reverse($result);
}

$available_quarters = get_all_quarters($stagger, $vat['vat_period_start'] ?? date('Y-01-01'));

$page_title = 'VAT Return';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid">

            <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                <h4 class="page-title">VAT Return</h4>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">VAT Return</li>
                </ol>
            </div>

            <!-- Quarter selector -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Select Quarter</label>
                            <select name="quarter" class="form-select" id="quarterSelect">
                                <option value="">-- Custom Date Range --</option>
                                <?php foreach ($available_quarters as $q): ?>
                                    <option value="<?php echo $q['start'] . '|' . $q['end']; ?>"
                                        <?php echo ($quarter_start === $q['start'] && $quarter_end === $q['end']) ? 'selected' : ''; ?>>
                                        Quarter ending <?php echo $q['label']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From</label>
                            <input type="date" name="from" id="fromDate" class="form-control"
                                   value="<?php echo $quarter_start; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To</label>
                            <input type="date" name="to" id="toDate" class="form-control"
                                   value="<?php echo $quarter_end; ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-calculator"></i> Calculate
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filing deadline -->
            <div class="alert alert-info d-flex align-items-center mb-4">
                <i class="bi bi-calendar-check fs-4 me-3"></i>
                <div>
                    <strong>Period:</strong>
                    <?php echo date('d M Y', strtotime($quarter_start)); ?> —
                    <?php echo date('d M Y', strtotime($quarter_end)); ?>
                    &nbsp;|&nbsp;
                    <strong>Filing &amp; Payment Deadline:</strong> <?php echo $deadline; ?>
                </div>
            </div>

            <!-- HMRC VAT Return Boxes -->
            <div class="row g-3 mb-4">

                <!-- Box 1 -->
                <div class="col-md-4">
                    <div class="card h-100 border-start border-primary border-3">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Box 1 — VAT on Sales</div>
                            <div class="fs-3 fw-bold text-primary">£<?php echo number_format($box1, 2); ?></div>
                            <div class="text-muted small">VAT charged on your sales</div>
                        </div>
                    </div>
                </div>

                <!-- Box 4 -->
                <div class="col-md-4">
                    <div class="card h-100 border-start border-success border-3">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Box 4 — VAT Reclaimed on Purchases</div>
                            <div class="fs-3 fw-bold text-success">£<?php echo number_format($box4, 2); ?></div>
                            <div class="text-muted small">VAT you can reclaim on purchases</div>
                        </div>
                    </div>
                </div>

                <!-- Box 5 -->
                <div class="col-md-4">
                    <div class="card h-100 border-start border-<?php echo $box5 >= 0 ? 'danger' : 'warning'; ?> border-3">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Box 5 — Net VAT <?php echo $box5 >= 0 ? 'Payable' : 'Reclaimable'; ?></div>
                            <div class="fs-3 fw-bold text-<?php echo $box5 >= 0 ? 'danger' : 'warning'; ?>">
                                £<?php echo number_format(abs($box5), 2); ?>
                            </div>
                            <div class="text-muted small">
                                <?php echo $box5 >= 0 ? 'Amount owed to HMRC' : 'Amount HMRC owes you'; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Box 6 -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Box 6 — Total Sales (ex VAT)</div>
                            <div class="fs-4 fw-bold">£<?php echo number_format($box6, 2); ?></div>
                            <div class="text-muted small">Net value of all sales</div>
                        </div>
                    </div>
                </div>

                <!-- Box 7 -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Box 7 — Total Purchases (ex VAT)</div>
                            <div class="fs-4 fw-bold">£<?php echo number_format($box7, 2); ?></div>
                            <div class="text-muted small">Net value of all purchases</div>
                        </div>
                    </div>
                </div>

                <!-- Box 3 -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1">Box 3 — Total VAT Charged</div>
                            <div class="fs-4 fw-bold">£<?php echo number_format($box3, 2); ?></div>
                            <div class="text-muted small">Same as Box 1 (standard scheme)</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Sales breakdown -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-arrow-up-circle text-primary me-2"></i>
                        Sales with VAT — Box 1 Breakdown
                    </h5>
                    <span class="badge bg-primary"><?php echo count($sales_txns); ?> transactions</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Account</th>
                                <th class="text-end">VAT Rate</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Net</th>
                                <th class="text-end">VAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_txns)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        No VATable sales in this period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_txns as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($tx['transaction_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                        <td>
                                            <span class="small text-muted">
                                                <?php echo htmlspecialchars($tx['coa_code']); ?> —
                                                <?php echo htmlspecialchars($tx['coa_name']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?php echo $tx['vat_rate']; ?>%</td>
                                        <td class="text-end">£<?php echo number_format($tx['amount'], 2); ?></td>
                                        <td class="text-end">£<?php echo number_format($tx['net_amount'], 2); ?></td>
                                        <td class="text-end fw-semibold text-primary">
                                            £<?php echo number_format($tx['vat_amount'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-primary fw-bold">
                                    <td colspan="6" class="text-end">Box 1 Total</td>
                                    <td class="text-end">£<?php echo number_format($box1, 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Purchases breakdown -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-arrow-down-circle text-success me-2"></i>
                        Purchases with VAT — Box 4 Breakdown
                    </h5>
                    <span class="badge bg-success"><?php echo count($purchase_txns); ?> transactions</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Account</th>
                                <th class="text-end">VAT Rate</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Net</th>
                                <th class="text-end">VAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($purchase_txns)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        No VATable purchases in this period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($purchase_txns as $tx): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($tx['transaction_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($tx['description']); ?></td>
                                        <td>
                                            <span class="small text-muted">
                                                <?php echo htmlspecialchars($tx['coa_code']); ?> —
                                                <?php echo htmlspecialchars($tx['coa_name']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end"><?php echo $tx['vat_rate']; ?>%</td>
                                        <td class="text-end">£<?php echo number_format($tx['amount'], 2); ?></td>
                                        <td class="text-end">£<?php echo number_format($tx['net_amount'], 2); ?></td>
                                        <td class="text-end fw-semibold text-success">
                                            £<?php echo number_format($tx['vat_amount'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-success fw-bold">
                                    <td colspan="6" class="text-end">Box 4 Total</td>
                                    <td class="text-end">£<?php echo number_format($box4, 2); ?></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary / Print -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">VAT Return Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td>Box 1 — VAT on sales</td>
                                        <td class="text-end fw-semibold">£<?php echo number_format($box1, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Box 3 — Total VAT charged</td>
                                        <td class="text-end fw-semibold">£<?php echo number_format($box3, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Box 4 — VAT reclaimed</td>
                                        <td class="text-end fw-semibold">£<?php echo number_format($box4, 2); ?></td>
                                    </tr>
                                    <tr class="table-<?php echo $box5 >= 0 ? 'danger' : 'warning'; ?>">
                                        <td><strong>Box 5 — Net VAT <?php echo $box5 >= 0 ? 'Payable' : 'Reclaimable'; ?></strong></td>
                                        <td class="text-end fw-bold">£<?php echo number_format(abs($box5), 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Box 6 — Total sales (ex VAT)</td>
                                        <td class="text-end fw-semibold">£<?php echo number_format($box6, 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Box 7 — Total purchases (ex VAT)</td>
                                        <td class="text-end fw-semibold">£<?php echo number_format($box7, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-md-6 d-flex align-items-center justify-content-center">
                            <div class="text-center">
                                <div class="text-muted mb-1">
                                    <?php echo $box5 >= 0 ? 'Amount to pay HMRC' : 'Amount HMRC owes you'; ?>
                                </div>
                                <div class="display-5 fw-bold text-<?php echo $box5 >= 0 ? 'danger' : 'warning'; ?>">
                                    £<?php echo number_format(abs($box5), 2); ?>
                                </div>
                                <div class="text-muted small mt-1">Due by <?php echo $deadline; ?></div>
                                <button onclick="window.print()" class="btn btn-outline-secondary mt-3">
                                    <i class="bi bi-printer me-1"></i> Print / Save PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Quarter dropdown auto-fills date range
document.getElementById('quarterSelect').addEventListener('change', function () {
    if (!this.value) return;
    const [from, to] = this.value.split('|');
    document.getElementById('fromDate').value = from;
    document.getElementById('toDate').value   = to;
});
</script>

<?php require_once 'includes/footer.php'; ?>