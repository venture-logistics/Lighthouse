<?php
// bank_transactions.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];

    // Get bank accounts for filter dropdown
    $stmt = $pdo->prepare("
        SELECT id, account_name, bank_name 
        FROM bank_accounts 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get chart of accounts for categorisation dropdown
    $stmt = $pdo->prepare("
        SELECT id, code, name, type 
        FROM chart_of_accounts 
        ORDER BY code ASC
    ");
    $stmt->execute();
    $coa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Filters ───────────────────────────────────────────────────────────────
    $where = ["bt.user_id = :user_id"];
    $params = [':user_id' => $user_id];

    $filter_account = $_GET['account'] ?? '';
    $filter_reconciled = $_GET['reconciled'] ?? '';   // 'reconciled' | 'unreconciled' | ''
    $filter_type = $_GET['type'] ?? '';
    $filter_from = $_GET['from'] ?? '';
    $filter_to = $_GET['to'] ?? '';
    $filter_search = $_GET['search'] ?? '';
    $filter_order = $_GET['order'] ?? 'desc'; // 'desc' | 'asc'

    if ($filter_account) {
        $where[] = "bt.bank_account_id = :account";
        $params[':account'] = $filter_account;
    }

    if ($filter_reconciled === 'reconciled') {
        $where[] = "bt.reconciled = 1";
    } elseif ($filter_reconciled === 'unreconciled') {
        $where[] = "bt.reconciled = 0";
    }

    if ($filter_type) {
        $where[] = "bt.type = :type";
        $params[':type'] = $filter_type;
    }
    if ($filter_from) {
        $where[] = "bt.transaction_date >= :from";
        $params[':from'] = $filter_from;
    }
    if ($filter_to) {
        $where[] = "bt.transaction_date <= :to";
        $params[':to'] = $filter_to;
    }
    if ($filter_search) {
        $where[] = "(bt.description LIKE :search OR bt.expense_description LIKE :search2)";
        $params[':search'] = '%' . $filter_search . '%';
        $params[':search2'] = '%' . $filter_search . '%';
    }

    $where_sql = implode(' AND ', $where);
    $order_dir = $filter_order === 'asc' ? 'ASC' : 'DESC';

    // ── Summary totals ────────────────────────────────────────────────────────
    $summary_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN type = 'debit'  THEN amount ELSE 0 END) as total_debits,
            SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credits,
            SUM(CASE WHEN reconciled = 0  THEN 1 ELSE 0 END)      as unreconciled
        FROM bank_transactions bt
        WHERE {$where_sql}
    ");
    $summary_stmt->execute($params);
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

    // ── Transactions ──────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT 
            bt.*,
            ba.account_name,
            ba.bank_name,
            coa.name     AS coa_name,
            coa.code     AS coa_code,
            coa.vat_rate AS coa_vat_rate
        FROM bank_transactions bt
        LEFT JOIN bank_accounts ba      ON bt.bank_account_id = ba.id
        LEFT JOIN chart_of_accounts coa ON bt.coa_id = coa.id
        WHERE {$where_sql}
        ORDER BY bt.transaction_date {$order_dir}, bt.id {$order_dir}
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $transactions = [];
    $summary = ['total' => 0, 'total_debits' => 0, 'total_credits' => 0, 'unreconciled' => 0];
}

$page_title = 'Bank Transactions';
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
                            <h4 class="page-title">Bank Transactions</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Bank Transactions</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="btn-group" role="group" aria-label="Basic example">
                                <a href="bank_import_expenses.php" class="btn btn-primary">
                                    <i class="bi bi-upload"></i> Import Expense CSV
                                </a>
                                <a href="bank_import_income.php" class="btn btn-success">
                                    <i class="bi bi-upload"></i> Import Income CSV
                                </a>
                                <a class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#newTransactionModal">
                                    <i class="bi bi-plus-lg"></i> Manual Transaction
                                </a>
                            </div>
                        </div>

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
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="text-muted small">Total Transactions</div>
                                        <div class="fs-4 fw-bold"><?php echo number_format($summary['total']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="text-muted small">Total Debits</div>
                                        <div class="fs-4 fw-bold text-danger">
                                            £<?php echo number_format($summary['total_debits'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="text-muted small">Total Credits</div>
                                        <div class="fs-4 fw-bold text-success">
                                            £<?php echo number_format((float) $summary['total_credits'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-xl-3">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="text-muted small">Unreconciled</div>
                                        <div class="fs-4 fw-bold text-warning">
                                            <?php echo number_format($summary['unreconciled']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>  

                        <!-- Filters -->
                        <div class="card shadow-sm mb-4">
                            <div class="card-body">
                                <form method="get" class="row g-2 align-items-end">
                                    <div class="col-md-2">
                                        <label class="form-label small">Account</label>
                                        <select name="account" class="form-select form-select-sm">
                                            <option value="">All Accounts</option>
                                            <?php foreach ($bank_accounts as $ba): ?>
                                                <option value="<?php echo $ba['id']; ?>"
                                                    <?php echo $filter_account == $ba['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ba['account_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Reconciled</label>
                                        <select name="reconciled" class="form-select form-select-sm">
                                            <option value="">All</option>
                                            <option value="unreconciled" <?php echo $filter_reconciled === 'unreconciled' ? 'selected' : ''; ?>>Unreconciled</option>
                                            <option value="reconciled"   <?php echo $filter_reconciled === 'reconciled' ? 'selected' : ''; ?>>Reconciled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Type</label>
                                        <select name="type" class="form-select form-select-sm">
                                            <option value="">All Types</option>
                                            <option value="debit"  <?php echo $filter_type === 'debit' ? 'selected' : ''; ?>>Debit</option>
                                            <option value="credit" <?php echo $filter_type === 'credit' ? 'selected' : ''; ?>>Credit</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">From</label>
                                        <input type="date" name="from" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars($filter_from); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">To</label>
                                        <input type="date" name="to" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars($filter_to); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Date Order</label>
                                        <select name="order" class="form-select form-select-sm">
                                            <option value="desc" <?php echo $filter_order === 'desc' ? 'selected' : ''; ?>>Newest First</option>
                                            <option value="asc"  <?php echo $filter_order === 'asc' ? 'selected' : ''; ?>>Oldest First</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Search</label>
                                        <input type="text" name="search" class="form-control form-control-sm"
                                               placeholder="Description..."
                                               value="<?php echo htmlspecialchars($filter_search); ?>">
                                    </div>
                                    <div class="col-12 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-filter"></i> Filter
                                        </button>
                                        <a href="bank_transactions.php" class="btn btn-outline-secondary btn-sm">
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
                                                <th>Type</th>
                                                <th>Category</th>
                                                <th class="text-end">Amount</th>
                                                <th class="text-end">VAT</th>
                                                <th>Account (COA)</th>
                                                <th>Status</th>
                                                <th class="text-center">Receipt</th>
                                                <th class="text-center">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($transactions)): ?>
                                                <tr>
                                                    <td colspan="10" class="text-center text-muted py-4">
                                                        No transactions found.
                                                        <a href="bank_import.php">Import a CSV to get started.</a>
                                                    </td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($transactions as $tx): ?>
                                                    <tr class="<?php echo $tx['reconciled'] ? '' : 'table-warning'; ?>">
                                                        <td class="text-nowrap">
                                                            <?php echo date('d M Y', strtotime($tx['transaction_date'])); ?>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold">
                                                                <?php echo htmlspecialchars($tx['description']); ?>
                                                            </div>
                                                            <?php if ($tx['expense_description']): ?>
                                                                <div class="text-muted small">
                                                                    <?php echo htmlspecialchars($tx['expense_description']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($tx['orig_currency']) && $tx['orig_currency'] !== 'GBP'): ?>
                                                                <div class="text-muted small">
                                                                    <?php echo $tx['orig_currency']; ?>
                                                                    <?php echo number_format(abs($tx['orig_amount']), 2); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $tx['transaction_type'] === 'CARD_PAYMENT' ? 'info' : ($tx['transaction_type'] === 'TRANSFER' ? 'secondary' : 'dark'); ?> text-white">
                                                                <?php echo htmlspecialchars($tx['transaction_type'] ?? ''); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($tx['expense_category']): ?>
                                                                <span class="badge bg-light text-dark border">
                                                                    <?php echo htmlspecialchars($tx['expense_category']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted small">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end text-nowrap">
                                                            <span class="fw-semibold <?php echo $tx['type'] === 'debit' ? 'text-danger' : 'text-success'; ?>">
                                                                <?php echo $tx['type'] === 'debit' ? '-' : '+'; ?>
                                                                £<?php echo number_format($tx['amount'], 2); ?>
                                                            </span>
                                                            <?php if ($tx['fee'] > 0): ?>
                                                                <div class="text-muted small">
                                                                    Fee: £<?php echo number_format($tx['fee'], 2); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>                                                        
                                                        
                                                        <td class="text-end text-nowrap">
                                                            <?php
                                                            $vat_rate = (float)($tx['coa_vat_rate'] ?? 0);
                                                            if ($vat_rate > 0):
                                                                $vat_amount = round(abs($tx['amount']) * $vat_rate / (100 + $vat_rate), 2);
                                                            ?>
                                                                <span class="text-muted small">
                                                                    <?php echo $vat_rate; ?>%<br>
                                                                    £<?php echo number_format($vat_amount, 2); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted small">—</span>
                                                            <?php endif; ?>
                                                        </td>                                                        
                                                        
                                                        <td>
                                                            <?php if ($tx['coa_name']): ?>
                                                                <span class="small">
                                                                    <?php echo htmlspecialchars($tx['coa_code']); ?> —
                                                                    <?php echo htmlspecialchars($tx['coa_name']); ?>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted small">Not assigned</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php
                                                            $badge = match ($tx['status']) {
                                                                'categorised' => 'success',
                                                                'reconciled' => 'primary',
                                                                default => 'warning'
                                                            };
                                                            ?>
                                                            <span class="badge bg-<?php echo $badge; ?>">
                                                                <?php echo ucfirst($tx['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <?php if ($tx['receipt_path']): ?>
                                                                <a href="<?php echo htmlspecialchars($tx['receipt_path']); ?>"
                                                                   target="_blank"
                                                                   class="text-success"
                                                                   title="View Receipt">
                                                                    <i class="bi bi-paperclip fs-5"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-danger" title="No receipt">
                                                                    <i class="bi bi-exclamation-circle fs-5"></i>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-center">
                                                            <button class="btn btn-sm btn-outline-primary"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#categoriseModal"
                                                                    data-id="<?php echo $tx['id']; ?>"
                                                                    data-description="<?php echo htmlspecialchars($tx['description']); ?>"
                                                                    data-amount="<?php echo $tx['amount']; ?>"
                                                                    data-type="<?php echo $tx['type']; ?>"
                                                                    data-coa="<?php echo $tx['coa_id'] ?? ''; ?>"
                                                                    data-status="<?php echo $tx['status']; ?>"
                                                                    data-reconciled="<?php echo $tx['reconciled']; ?>"
                                                                    data-notes="<?php echo htmlspecialchars($tx['notes'] ?? ''); ?>"
                                                                    data-receipt="<?php echo htmlspecialchars($tx['receipt_path'] ?? ''); ?>"
                                                                    data-tax-rate="<?php echo $tx['tax_rate'] ?? 0; ?>">
                                                                <i class="bi bi-tag"></i>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Categorise Modal -->
<div class="modal fade" id="categoriseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Categorise Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="save_transaction_category.php" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="transaction_id" id="modal_tx_id">

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" id="modal_description" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="text" class="form-control" id="modal_amount" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Chart of Accounts</label>
                        <select name="coa_id" class="form-select" id="modal_coa" required>
                            <option value="">-- Select Account --</option>
                            <?php
                            $current_type = '';
                            foreach ($coa_list as $coa):
                                if ($coa['type'] !== $current_type):
                                    if ($current_type !== '')
                                        echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars(ucfirst($coa['type'])) . '">';
                                    $current_type = $coa['type'];
                                endif;
                                ?>
                                <option value="<?php echo $coa['id']; ?>">
                                    <?php echo htmlspecialchars($coa['code'] . ' — ' . $coa['name']); ?>
                                </option>
                            <?php
                            endforeach;
                            if ($current_type !== '')
                                echo '</optgroup>';
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">VAT Rate (%)</label>
                        <input type="number" name="tax_rate" id="modal_tax_rate" 
                               class="form-control" step="0.01" min="0" max="100" value="0">
                        <div class="form-text" id="modal_vat_display"></div>
                    </div>                    

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select" id="modal_status">
                            <option value="uncategorised">Uncategorised</option>
                            <option value="categorised">Categorised</option>
                            <option value="reconciled">Reconciled</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" id="modal_notes" rows="2"></textarea>
                    </div>

                    <!-- Receipt section -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Receipt</label>
                        <div id="modal_receipt_existing" class="mb-2 d-none">
                            <div class="d-flex align-items-center gap-2 p-2 bg-light rounded">
                                <i class="bi bi-paperclip text-success"></i>
                                <a id="modal_receipt_link" href="#" target="_blank" class="small">
                                    View existing receipt
                                </a>
                                <span class="text-muted small ms-auto">Upload below to replace</span>
                            </div>
                        </div>
                        <input type="file"
                               name="receipt"
                               id="modal_receipt"
                               class="form-control"
                               accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
                        <div class="form-text">Accepted: PDF, JPG, PNG, GIF, WEBP</div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Manual Transaction Modal -->
<div class="modal fade" id="newTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Manual Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="save_manual_transaction.php" method="post">
                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Date</label>
                        <input type="date" name="transaction_date" class="form-control" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <input type="text" name="description" class="form-control" 
                               placeholder="e.g. Ford Transit Van" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount (£)</label>
                        <input type="number" name="amount" class="form-control" 
                               step="0.01" min="0.01" placeholder="0.00" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Debit Account (DR)</label>
                        <select name="dr_coa_id" class="form-select" required>
                            <option value="">-- Select Account --</option>
                            <?php
                            $current_type = '';
                            foreach ($coa_list as $coa):
                                if ($coa['type'] !== $current_type):
                                    if ($current_type !== '')
                                        echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars(ucfirst($coa['type'])) . '">';
                                    $current_type = $coa['type'];
                                endif;
                                ?>
                                <option value="<?php echo $coa['id']; ?>">
                                    <?php echo htmlspecialchars($coa['code'] . ' — ' . $coa['name']); ?>
                                </option>
                            <?php
                            endforeach;
                            if ($current_type !== '')
                                echo '</optgroup>';
                            ?>
                        </select>
                        <div class="form-text">What is being purchased / what account increases?</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Credit Account (CR)</label>
                        <select name="cr_coa_id" class="form-select" required>
                            <option value="">-- Select Account --</option>
                            <?php
                            $current_type = '';
                            foreach ($coa_list as $coa):
                                if ($coa['type'] !== $current_type):
                                    if ($current_type !== '')
                                        echo '</optgroup>';
                                    echo '<optgroup label="' . htmlspecialchars(ucfirst($coa['type'])) . '">';
                                    $current_type = $coa['type'];
                                endif;
                                ?>
                                <option value="<?php echo $coa['id']; ?>"
                                    <?php echo $coa['code'] === '2400' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($coa['code'] . ' — ' . $coa['name']); ?>
                                </option>
                            <?php
                            endforeach;
                            if ($current_type !== '')
                                echo '</optgroup>';
                            ?>
                        </select>
                        <div class="form-text">Where is the money coming from? (Default: Directors Loan)</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bank Account</label>
                        <select name="bank_account_id" class="form-select">
                            <option value="">-- None (non-bank transaction) --</option>
                            <?php foreach ($bank_accounts as $ba): ?>
                                <option value="<?php echo $ba['id']; ?>">
                                    <?php echo htmlspecialchars($ba['account_name'] . ' — ' . $ba['bank_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" 
                                  placeholder="Optional notes..."></textarea>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

function updateVatDisplay(rate, gross) {
    const vatAmt = (gross * rate / (100 + rate)).toFixed(2);
    const net    = (gross - vatAmt).toFixed(2);
    const el     = document.getElementById('modal_vat_display');
    el.textContent = rate > 0 
        ? `VAT: £${vatAmt} — Net: £${net}` 
        : 'No VAT';
}

// Wire up VAT input ONCE, outside the modal handler
document.getElementById('modal_tax_rate').addEventListener('input', function () {
    const amount = parseFloat(document.getElementById('modal_tx_amount_raw').value);
    updateVatDisplay(parseFloat(this.value || 0), amount);
});

document.getElementById('categoriseModal').addEventListener('show.bs.modal', function (e) {
    const btn = e.relatedTarget;

    document.getElementById('modal_tx_id').value       = btn.dataset.id;
    document.getElementById('modal_description').value = btn.dataset.description;
    document.getElementById('modal_amount').value      =
        (btn.dataset.type === 'debit' ? '-' : '+') + '£' +
        parseFloat(btn.dataset.amount).toFixed(2);
    document.getElementById('modal_notes').value       = btn.dataset.notes;
    document.getElementById('modal_coa').value         = btn.dataset.coa    || '';
    document.getElementById('modal_status').value      = btn.dataset.status || 'uncategorised';

    const receiptBlock = document.getElementById('modal_receipt_existing');
    const receiptLink  = document.getElementById('modal_receipt_link');
    if (btn.dataset.receipt) {
        receiptBlock.classList.remove('d-none');
        receiptLink.href = btn.dataset.receipt;
    } else {
        receiptBlock.classList.add('d-none');
        receiptLink.href = '#';
    }
    document.getElementById('modal_receipt').value = '';

    // Store raw amount for the VAT input listener to read
    document.getElementById('modal_tx_amount_raw').value = btn.dataset.amount;

    // Pre-fill VAT
    const taxRate = parseFloat(btn.dataset.taxRate || 0);
    document.getElementById('modal_tax_rate').value = taxRate;
    updateVatDisplay(taxRate, parseFloat(btn.dataset.amount));
});

</script>

<?php require_once 'includes/footer.php'; ?>