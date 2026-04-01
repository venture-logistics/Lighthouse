<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';
$preview = null; // Holds generated TB before saving

// -------------------------------------------------------
// Helper: Calculate trial balance lines for a date range
// -------------------------------------------------------
function calculate_trial_balance(PDO $pdo, string $date_from, string $date_to): array
{
    $stmt = $pdo->prepare("
        SELECT
            c.id         AS coa_id,
            c.code       AS coa_code,
            c.name       AS coa_name,
            c.type       AS coa_type,
            COALESCE(SUM(CASE WHEN bt.type = 'debit'  THEN bt.amount ELSE 0 END), 0) AS raw_debits,
            COALESCE(SUM(CASE WHEN bt.type = 'credit' THEN bt.amount ELSE 0 END), 0) AS raw_credits
        FROM chart_of_accounts c
        LEFT JOIN bank_transactions bt
               ON bt.coa_id = c.id
              AND bt.transaction_date BETWEEN :date_from AND :date_to
        WHERE c.is_active = 1
        GROUP BY c.id, c.code, c.name, c.type
        ORDER BY c.code ASC
    ");
    $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
    $rows = $stmt->fetchAll();

    $lines = [];
    foreach ($rows as $row) {
        // Your system stores debits/credits inverted vs standard bookkeeping
        // So we swap: treat 'credit' rows as debits and 'debit' rows as credits
        $effective_debits = $row['raw_credits'];
        $effective_credits = $row['raw_debits'];

        $net = $effective_debits - $effective_credits;

        $debit_normal = in_array($row['coa_type'], ['asset', 'expense']);

        if ($debit_normal) {
            $debit_amount = $net > 0 ? $net : 0;
            $credit_amount = $net < 0 ? abs($net) : 0;
        } else {
            $credit_amount = $net < 0 ? abs($net) : 0;
            $debit_amount = $net > 0 ? $net : 0;
        }

        $lines[] = [
            'coa_id' => $row['coa_id'],
            'coa_code' => $row['coa_code'],
            'coa_name' => $row['coa_name'],
            'coa_type' => $row['coa_type'],
            'debit_amount' => $debit_amount,
            'credit_amount' => $credit_amount,
        ];
    }

    return $lines;
}

// -------------------------------------------------------
// POST handlers
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- GENERATE PREVIEW ---
    if ($action === 'generate') {
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';
        $label = trim($_POST['label'] ?? '');

        if (empty($date_from))
            $errors[] = 'Start date is required.';
        if (empty($date_to))
            $errors[] = 'End date is required.';
        if ($date_to < $date_from)
            $errors[] = 'End date must be on or after start date.';
        if (empty($label))
            $errors[] = 'Label is required (e.g. Year End March 2026).';

        if (empty($errors)) {
            $lines = calculate_trial_balance($pdo, $date_from, $date_to);
            $total_debits = array_sum(array_column($lines, 'debit_amount'));
            $total_credits = array_sum(array_column($lines, 'credit_amount'));

            $preview = [
                'label' => $label,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'lines' => $lines,
                'total_debits' => $total_debits,
                'total_credits' => $total_credits,
                'balanced' => (round($total_debits, 2) === round($total_credits, 2)),
            ];
        }
    }

    // --- SAVE ---
    if ($action === 'save') {
        $date_from = $_POST['date_from'] ?? '';
        $date_to = $_POST['date_to'] ?? '';
        $label = trim($_POST['label'] ?? '');
        $total_debits = (float) ($_POST['total_debits'] ?? 0);
        $total_credits = (float) ($_POST['total_credits'] ?? 0);

        if (empty($date_from) || empty($date_to) || empty($label)) {
            $errors[] = 'Missing required data to save. Please regenerate.';
        }

        if (empty($errors)) {
            // Insert header
            $stmt = $pdo->prepare("
                INSERT INTO trial_balances (label, date_from, date_to, generated_by, total_debits, total_credits)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$label, $date_from, $date_to, $_SESSION['user_id'], $total_debits, $total_credits]);
            $tb_id = $pdo->lastInsertId();

            // Recalculate lines fresh and insert
            $lines = calculate_trial_balance($pdo, $date_from, $date_to);
            $line_stmt = $pdo->prepare("
                INSERT INTO trial_balance_lines
                    (trial_balance_id, coa_id, coa_code, coa_name, coa_type, debit_amount, credit_amount)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($lines as $line) {
                $line_stmt->execute([
                    $tb_id,
                    $line['coa_id'],
                    $line['coa_code'],
                    $line['coa_name'],
                    $line['coa_type'],
                    $line['debit_amount'],
                    $line['credit_amount'],
                ]);
            }

            $success = 'Trial balance saved successfully.';
        }
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $id = (int) ($_POST['tb_id'] ?? 0);
        // Lines deleted by CASCADE
        $stmt = $pdo->prepare("DELETE FROM trial_balances WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Trial balance deleted.';
    }
}

// Fetch saved trial balances
$saved = $pdo->query("
    SELECT tb.*, u.username AS generated_by_name
    FROM trial_balances tb
    JOIN users u ON tb.generated_by = u.id
    ORDER BY tb.generated_at DESC
")->fetchAll();

$page_title = 'Trial Balance';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-sm-12">
                    <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                        <h4 class="page-title">Trial Balance</h4>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Trial Balance</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Generate Form -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Generate Trial Balance</h4>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="generateForm">
                                <input type="hidden" name="action" value="generate">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Label <span class="text-danger">*</span></label>
                                        <input type="text"
                                               name="label"
                                               class="form-control"
                                               placeholder="e.g. Year End March 2026"
                                               value="<?php echo htmlspecialchars($_POST['label'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">From Date <span class="text-danger">*</span></label>
                                        <input type="date"
                                               name="date_from"
                                               class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['date_from'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">To Date <span class="text-danger">*</span></label>
                                        <input type="date"
                                               name="date_to"
                                               class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['date_to'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <div class="col-md-2 d-flex align-items-end">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-calculator me-1"></i> Generate
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <?php if ($preview): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="card-title mb-0"><?php echo htmlspecialchars($preview['label']); ?></h4>
                                <small class="text-muted">
                                    <?php echo date('d M Y', strtotime($preview['date_from'])); ?>
                                    &mdash;
                                    <?php echo date('d M Y', strtotime($preview['date_to'])); ?>
                                </small>
                            </div>
                            <div>
                                <?php if ($preview['balanced']): ?>
                                    <span class="badge bg-success fs-6 me-2">
                                        <i class="fas fa-check-circle me-1"></i> Balanced
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-danger fs-6 me-2">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Out of Balance
                                    </span>
                                <?php endif; ?>
                                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary me-2">
                                    <i class="fas fa-print me-1"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="card-body pt-0">

                            <?php if (!$preview['balanced']): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    This trial balance does not balance. The difference is
                                    <strong>£<?php echo number_format(abs($preview['total_debits'] - $preview['total_credits']), 2); ?></strong>.
                                    This may indicate unposted transactions or transactions without a COA assignment.
                                </div>
                            <?php endif; ?>

                            <table class="table table-striped table-hover mt-3" id="tbPreviewTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Code</th>
                                        <th>Account Name</th>
                                        <th>Type</th>
                                        <th class="text-end">Debit (£)</th>
                                        <th class="text-end">Credit (£)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $type_order = ['asset', 'liability', 'equity', 'income', 'expense'];
                                    $grouped = [];
                                    foreach ($preview['lines'] as $line) {
                                        $grouped[$line['coa_type']][] = $line;
                                    }
                                    foreach ($type_order as $type):
                                        if (empty($grouped[$type]))
                                            continue;
                                        ?>
                                    <tr class="table-secondary">
                                        <td colspan="5" class="fw-bold text-uppercase small">
                                            <?php echo ucfirst($type); ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($grouped[$type] as $line):
                                        $has_activity = ($line['debit_amount'] > 0 || $line['credit_amount'] > 0);
                                        ?>
                                    <tr class="<?php echo !$has_activity ? 'text-muted' : ''; ?>">
                                        <td><?php echo htmlspecialchars($line['coa_code']); ?></td>
                                        <td><?php echo htmlspecialchars($line['coa_name']); ?></td>
                                        <td>
                                            <span class="badge
                                                <?php echo match ($line['coa_type']) {
                                                    'asset' => 'bg-primary',
                                                    'liability' => 'bg-warning text-dark',
                                                    'equity' => 'bg-info text-dark',
                                                    'income' => 'bg-success',
                                                    'expense' => 'bg-danger',
                                                    default => 'bg-secondary'
                                                }; ?>">
                                                <?php echo ucfirst($line['coa_type']); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $line['debit_amount'] > 0
                                                ? number_format($line['debit_amount'], 2)
                                                : '-'; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $line['credit_amount'] > 0
                                                ? number_format($line['credit_amount'], 2)
                                                : '-'; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-dark">
                                    <tr>
                                        <td colspan="3" class="fw-bold text-end">TOTALS</td>
                                        <td class="text-end fw-bold">
                                            £<?php echo number_format($preview['total_debits'], 2); ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            £<?php echo number_format($preview['total_credits'], 2); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Save form - passes data back -->
                            <form method="POST" class="mt-3 no-print">
                                <input type="hidden" name="action"        value="save">
                                <input type="hidden" name="label"         value="<?php echo htmlspecialchars($preview['label']); ?>">
                                <input type="hidden" name="date_from"     value="<?php echo $preview['date_from']; ?>">
                                <input type="hidden" name="date_to"       value="<?php echo $preview['date_to']; ?>">
                                <input type="hidden" name="total_debits"  value="<?php echo $preview['total_debits']; ?>">
                                <input type="hidden" name="total_credits" value="<?php echo $preview['total_credits']; ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-1"></i> Save This Trial Balance
                                </button>
                            </form>

                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Saved Trial Balances -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Saved Trial Balances</h4>
                        </div>
                        <div class="card-body pt-0">
                            <?php if (empty($saved)): ?>
                                <p class="text-muted mt-3">No trial balances saved yet.</p>
                            <?php else: ?>
                                <table class="table table-striped table-hover mt-3">
                                    <thead>
                                        <tr>
                                            <th>Label</th>
                                            <th>Period</th>
                                            <th>Generated By</th>
                                            <th>Generated At</th>
                                            <th class="text-end">Total Debits</th>
                                            <th class="text-end">Total Credits</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($saved as $tb): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($tb['label']); ?></td>
                                            <td>
                                                <?php echo date('d M Y', strtotime($tb['date_from'])); ?>
                                                &mdash;
                                                <?php echo date('d M Y', strtotime($tb['date_to'])); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($tb['generated_by_name']); ?></td>
                                            <td><?php echo date('d M Y H:i', strtotime($tb['generated_at'])); ?></td>
                                            <td class="text-end">£<?php echo number_format($tb['total_debits'], 2); ?></td>
                                            <td class="text-end">£<?php echo number_format($tb['total_credits'], 2); ?></td>
                                            <td>
                                                <?php if (round($tb['total_debits'], 2) === round($tb['total_credits'], 2)): ?>
                                                    <span class="badge bg-success">Balanced</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Out of Balance</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="trial_balance_view.php?id=<?php echo $tb['id']; ?>"
                                                   class="btn btn-sm btn-outline-primary me-1">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <button class="btn btn-sm btn-outline-danger btn-delete-tb"
                                                    data-id="<?php echo $tb['id']; ?>"
                                                    data-label="<?php echo htmlspecialchars($tb['label']); ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteTbModal">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteTbModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="tb_id" id="delete_tb_id">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Trial Balance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_tb_label"></strong>?
                    This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Print styles -->
<style>
@media print {
    .no-print,
    .page-title-box,
    .breadcrumb,
    nav,
    .sidebar,
    .topbar,
    .btn,
    .card:not(:has(#tbPreviewTable)) {
        display: none !important;
    }
    .page-wrapper { margin: 0 !important; padding: 0 !important; }
    .card { box-shadow: none !important; border: none !important; }
    #tbPreviewTable { font-size: 12px; }
}
</style>

<script>
document.querySelectorAll('.btn-delete-tb').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('delete_tb_id').value          = this.dataset.id;
        document.getElementById('delete_tb_label').textContent = this.dataset.label;
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>