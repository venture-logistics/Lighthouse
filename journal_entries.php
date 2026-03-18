<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Fetch chart of accounts for dropdowns
$coa = $pdo->prepare("SELECT id, code, name, type FROM chart_of_accounts WHERE is_active = 1 ORDER BY code");
$coa->execute([]);
$accounts = $coa->fetchAll();

// Handle POST - save new journal entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_entry'])) {
    $date = clean($_POST['date']);
    $description = clean($_POST['description']);
    $reference = clean($_POST['reference'] ?? '');
    $lines = $_POST['lines'] ?? [];

    $errors = [];

    if (empty($date))
        $errors[] = 'Date is required.';
    if (empty($description))
        $errors[] = 'Description is required.';
    if (count($lines) < 2)
        $errors[] = 'At least two lines are required.';

    $total_debit = 0;
    $total_credit = 0;
    foreach ($lines as $line) {
        if (empty($line['coa_id']) || empty($line['type']) || !isset($line['amount'])) {
            $errors[] = 'All line fields are required.';
            break;
        }
        if ($line['type'] === 'debit')
            $total_debit += (float) $line['amount'];
        if ($line['type'] === 'credit')
            $total_credit += (float) $line['amount'];
    }

    if (empty($errors) && round($total_debit, 2) !== round($total_credit, 2)) {
        $errors[] = 'Debits and credits must balance (currently DR: ' . number_format($total_debit, 2) . ' / CR: ' . number_format($total_credit, 2) . ').';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO journal_entries (date, description, reference) VALUES (?, ?, ?)");
            $stmt->execute([$date, $description, $reference]);
            $entry_id = $pdo->lastInsertId();

            $line_stmt = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, coa_id, type, amount) VALUES (?, ?, ?, ?)");
            foreach ($lines as $line) {
                $line_stmt->execute([$entry_id, $line['coa_id'], $line['type'], (float) $line['amount']]);
            }

            $pdo->commit();
            set_message('Journal entry saved successfully.');
            header('Location: journal_entries.php');
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del = $pdo->prepare("DELETE FROM journal_entries WHERE id = ?");
    $del->execute([$_GET['delete']]);
    set_message('Journal entry deleted.', 'warning');
    header('Location: journal_entries.php');
    exit();
}

// Fetch all journal entries with line totals
$entries_stmt = $pdo->prepare("
    SELECT je.*, 
           SUM(CASE WHEN jel.type = 'debit' THEN jel.amount ELSE 0 END) AS total_debit,
           SUM(CASE WHEN jel.type = 'credit' THEN jel.amount ELSE 0 END) AS total_credit
    FROM journal_entries je
    LEFT JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
    GROUP BY je.id
    ORDER BY je.date DESC, je.created_at DESC
");
$entries_stmt->execute([]);
$entries = $entries_stmt->fetchAll();

// Fetch lines for a single entry if viewing detail
$view_lines = [];
$view_entry = null;
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $ve = $pdo->prepare("SELECT * FROM journal_entries WHERE id = ?");
    $ve->execute([$_GET['view']]);
    $view_entry = $ve->fetch();

    if ($view_entry) {
        $vl = $pdo->prepare("
            SELECT jel.*, ca.code, ca.name 
            FROM journal_entry_lines jel
            JOIN chart_of_accounts ca ON jel.coa_id = ca.id
            WHERE jel.journal_entry_id = ?
        ");
        $vl->execute([$_GET['view']]);
        $view_lines = $vl->fetchAll();
    }
}

$page_title = 'Journal Entries';
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
                            <h4 class="page-title">Journal Entries</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Journal Entries</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                            <i class="bi bi-plus-lg me-1"></i>New Entry
                        </button>

                        <?= display_message() ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $e): ?>
                                        <li><?= htmlspecialchars($e) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- View Entry Detail Modal -->
                        <?php if ($view_entry): ?>
                        <div class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Journal Entry — <?= htmlspecialchars($view_entry['description']) ?></h5>
                                        <a href="journal_entries.php" class="btn-close"></a>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-4"><strong>Date:</strong> <?= format_date($view_entry['date']) ?></div>
                                            <div class="col-md-4"><strong>Reference:</strong> <?= htmlspecialchars($view_entry['reference'] ?? '—') ?></div>
                                        </div>
                                        <table class="table table-bordered table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Account</th>
                                                    <th class="text-end">Debit</th>
                                                    <th class="text-end">Credit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($view_lines as $vl): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($vl['code'] . ' — ' . $vl['name']) ?></td>
                                                    <td class="text-end"><?= $vl['type'] === 'debit' ? number_format($vl['amount'], 2) : '' ?></td>
                                                    <td class="text-end"><?= $vl['type'] === 'credit' ? number_format($vl['amount'], 2) : '' ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="journal_entries.php" class="btn btn-secondary btn-sm">Close</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Entries Table -->
                        <div class="card">
                            <div class="card-body p-0">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Reference</th>
                                            <th class="text-end">Total DR</th>
                                            <th class="text-end">Total CR</th>
                                            <th class="text-end">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($entries)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-4">No journal entries yet.</td></tr>
                                        <?php else: ?>
                                        <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?= format_date($entry['date']) ?></td>
                                            <td><?= htmlspecialchars($entry['description']) ?></td>
                                            <td><?= htmlspecialchars($entry['reference'] ?? '—') ?></td>
                                            <td class="text-end"><?= number_format($entry['total_debit'], 2) ?></td>
                                            <td class="text-end"><?= number_format($entry['total_credit'], 2) ?></td>
                                            <td class="text-end">
                                                <a href="?view=<?= $entry['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="?delete=<?= $entry['id'] ?>" class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Delete this journal entry?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Add Entry Modal -->
                    <div class="modal fade" id="addEntryModal" tabindex="-1">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-journal-plus me-2"></i>New Journal Entry</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">

                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label">Description <span class="text-danger">*</span></label>
                                                <input type="text" name="description" class="form-control" placeholder="e.g. Depreciation of vehicles" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Reference</label>
                                                <input type="text" name="reference" class="form-control" placeholder="e.g. JE-001">
                                            </div>
                                        </div>

                                        <table class="table table-bordered table-sm" id="linesTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:45%">Account</th>
                                                    <th style="width:20%">Type</th>
                                                    <th style="width:25%">Amount</th>
                                                    <th style="width:10%"></th>
                                                </tr>
                                            </thead>
                                            <tbody id="linesBody">
                                                <!-- lines added by JS -->
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="4">
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="addLine()">
                                                            <i class="bi bi-plus"></i> Add Line
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr class="table-light">
                                                    <td colspan="2" class="text-end fw-bold">Totals:</td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <span class="text-muted small">DR:</span>
                                                            <span id="totalDebit" class="fw-bold">0.00</span>
                                                            <span class="text-muted small ms-2">CR:</span>
                                                            <span id="totalCredit" class="fw-bold">0.00</span>
                                                        </div>
                                                    </td>
                                                    <td></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="4">
                                                        <span id="balanceStatus" class="small"></span>
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>

                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="save_entry" class="btn btn-primary btn-sm">
                                            <i class="bi bi-save me-1"></i>Save Entry
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>

<script>
const accounts = <?= json_encode($accounts) ?>;

function buildAccountOptions() {
    let opts = '<option value="">— Select Account —</option>';
    accounts.forEach(a => {
        opts += `<option value="${a.id}">[${a.code}] ${a.name} (${a.type})</option>`;
    });
    return opts;
}

function addLine() {
    const tbody = document.getElementById('linesBody');
    const idx = tbody.rows.length;
    const tr = document.createElement('tr');
    tr.className = 'line-row';
    tr.innerHTML = `
        <td>
            <select name="lines[${idx}][coa_id]" class="form-select form-select-sm" required>
                ${buildAccountOptions()}
            </select>
        </td>
        <td>
            <select name="lines[${idx}][type]" class="form-select form-select-sm type-select" onchange="recalc()" required>
                <option value="debit">Debit (DR)</option>
                <option value="credit">Credit (CR)</option>
            </select>
        </td>
        <td>
            <input type="number" name="lines[${idx}][amount]" class="form-control form-control-sm amount-input"
                   step="0.01" min="0" placeholder="0.00" oninput="recalc()" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeLine(this)">
                <i class="bi bi-x"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    recalc();
}

function removeLine(btn) {
    btn.closest('tr').remove();
    reindex();
    recalc();
}

function reindex() {
    const rows = document.querySelectorAll('#linesBody tr');
    rows.forEach((row, i) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/\[\d+\]/, `[${i}]`);
        });
    });
}

function recalc() {
    let dr = 0, cr = 0;
    document.querySelectorAll('#linesBody tr').forEach(row => {
        const type   = row.querySelector('.type-select')?.value;
        const amount = parseFloat(row.querySelector('.amount-input')?.value) || 0;
        if (type === 'debit')  dr += amount;
        if (type === 'credit') cr += amount;
    });
    document.getElementById('totalDebit').textContent  = dr.toFixed(2);
    document.getElementById('totalCredit').textContent = cr.toFixed(2);

    const status = document.getElementById('balanceStatus');
    if (dr === 0 && cr === 0) {
        status.innerHTML = '';
    } else if (Math.round(dr * 100) === Math.round(cr * 100)) {
        status.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Balanced</span>';
    } else {
        const diff = Math.abs(dr - cr).toFixed(2);
        status.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Out of balance by ${diff}</span>`;
    }
}

// Start with 2 lines
addLine();
addLine();
// Set second line to credit by default
document.querySelectorAll('.type-select')[1].value = 'credit';
</script>

                        