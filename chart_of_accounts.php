<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Handle flash messages
$flash = null;
if (isset($_SESSION['flash_message'])) {
    $flash = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// Fetch all accounts grouped by type
$stmt = $pdo->query("
    SELECT * FROM chart_of_accounts 
    ORDER BY 
        FIELD(type, 'asset','liability','equity','income','expense'),
        code ASC
");
$all_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by type
$grouped = [];
foreach ($all_accounts as $account) {
    $grouped[$account['type']][] = $account;
}

$type_labels = [
    'asset'     => ['label' => 'Assets',      'badge' => 'primary'],
    'liability' => ['label' => 'Liabilities', 'badge' => 'danger'],
    'equity'    => ['label' => 'Equity',      'badge' => 'dark'],
    'income'    => ['label' => 'Income',      'badge' => 'success'],
    'expense'   => ['label' => 'Expenses',    'badge' => 'warning'],
];

// HMRC MTD ITSA allowed expense categories
$hmrc_categories = [
    ''                           => '— Not mapped —',
    'costOfGoods'                => 'Cost of Goods / Materials',
    'constructionCosts'          => 'Construction Costs',
    'staffCosts'                 => 'Staff & Employee Costs',
    'travelCosts'                => 'Travel & Subsistence',
    'premisesRunningCosts'       => 'Premises Running Costs',
    'maintenanceCosts'           => 'Repairs & Maintenance',
    'adminCosts'                 => 'Admin & Office Costs',
    'advertisingCosts'           => 'Marketing & Advertising',
    'businessEntertainmentCosts' => 'Business Entertainment',
    'interestOnBankLoans'        => 'Interest on Bank Loans',
    'financeCharges'             => 'Finance Charges & Bank Fees',
    'irrecoverableDebts'         => 'Irrecoverable Debts (Bad Debts)',
    'professionalFees'           => 'Legal & Professional Fees',
    'depreciation'               => 'Depreciation',
    'otherExpenses'              => 'Other Expenses',
];

$page_title = 'Chart of Accounts';
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
                        <h4 class="page-title">Chart of Accounts</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Chart of Accounts</li>
                            </ol>
                        </div>
                    </div>

                    <?php if ($flash): ?>
                    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                        <?php echo htmlspecialchars($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAccountModal">
                            <i class="bi bi-plus-lg"></i> New Account
                        </button>
                    </div>

                    <!-- Search & Filter -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <select class="form-select" id="accountType">
                                        <option value="">All Account Types</option>
                                        <?php foreach ($type_labels as $key => $val): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $val['label']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" class="form-control" id="searchAccount" placeholder="Search by code or name...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Accounts grouped by type -->
                    <?php foreach ($grouped as $type => $accounts): ?>
                    <div class="card mb-3 account-group" data-type="<?php echo $type; ?>">
                        <div class="card-header fw-semibold">
                            <span class="badge bg-<?php echo $type_labels[$type]['badge']; ?> me-2">
                                <?php echo $type_labels[$type]['label']; ?>
                            </span>
                            <?php echo count($accounts); ?> accounts
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Account Name</th>
                                        <th>Description</th>
                                        <?php if ($type === 'expense'): ?>
                                        <th>HMRC Category</th>
                                        <?php endif; ?>
                                        <th class="text-center">Active</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accounts as $account): ?>
                                    <tr class="account-row">
                                        <td><code><?php echo htmlspecialchars($account['code']); ?></code></td>
                                        <td><?php echo htmlspecialchars($account['name']); ?></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($account['description'] ?? ''); ?></td>
                                        <?php if ($type === 'expense'): ?>
                                        <td>
                                            <?php 
                                            $cat = $account['hmrc_category'] ?? '';
                                            if ($cat && isset($hmrc_categories[$cat])): ?>
                                                <span class="badge bg-info text-dark">
                                                    <?php echo htmlspecialchars($hmrc_categories[$cat]); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small">— not mapped —</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                        <td class="text-center">
                                            <?php if ($account['is_active']): ?>
                                                <i class="bi bi-check-circle-fill text-success"></i>
                                            <?php else: ?>
                                                <i class="bi bi-x-circle-fill text-danger"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-primary edit-account-btn"
                                                data-id="<?php echo $account['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($account['code']); ?>"
                                                data-name="<?php echo htmlspecialchars($account['name']); ?>"
                                                data-type="<?php echo $account['type']; ?>"
                                                data-description="<?php echo htmlspecialchars($account['description'] ?? ''); ?>"
                                                data-active="<?php echo $account['is_active']; ?>"
                                                data-vat-rate="<?php echo $account['vat_rate'] ?? 0; ?>"
                                                data-hmrc-category="<?php echo htmlspecialchars($account['hmrc_category'] ?? ''); ?>"
                                                data-bs-toggle="modal" data-bs-target="#editAccountModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if (!$account['is_system']): ?>
                                            <a href="process_account.php?action=delete&id=<?php echo $account['id']; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Delete this account?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="System account">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Account Modal -->
<div class="modal fade" id="newAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_account.php" method="post">
                <input type="hidden" name="action" value="create">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Account Code</label>
                        <input type="text" class="form-control" name="code" required placeholder="e.g. 6500">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select class="form-select" name="type" id="new_type" required>
                            <?php foreach ($type_labels as $key => $val): ?>
                            <option value="<?php echo $key; ?>"><?php echo $val['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default VAT Rate (%)</label>
                        <input type="number" name="vat_rate" id="new_vat_rate" class="form-control"
                               step="0.01" min="0" max="100" value="0" placeholder="e.g. 20">
                        <div class="form-text">Enter 0 for exempt / out of scope</div>
                    </div>
                    <!-- HMRC Category — only shown when type = expense -->
                    <div class="mb-3" id="new_hmrc_category_wrap" style="display:none">
                        <label class="form-label">
                            HMRC MTD Category
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <select class="form-select" name="hmrc_category" id="new_hmrc_category">
                            <?php foreach ($hmrc_categories as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Maps this account to an HMRC category for MTD ITSA quarterly submissions.
                            Leave as "Not mapped" if unsure.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="process_account.php" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Account Code</label>
                        <input type="text" class="form-control" name="code" id="edit_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" class="form-control" name="name" id="edit_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select class="form-select" name="type" id="edit_type">
                            <?php foreach ($type_labels as $key => $val): ?>
                            <option value="<?php echo $key; ?>"><?php echo $val['label']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Default VAT Rate (%)</label>
                        <input type="number" name="vat_rate" id="edit_vat_rate" class="form-control"
                               step="0.01" min="0" max="100" placeholder="e.g. 20">
                        <div class="form-text">Enter 0 for exempt / out of scope</div>
                    </div>
                    <!-- HMRC Category — only shown when type = expense -->
                    <div class="mb-3" id="edit_hmrc_category_wrap" style="display:none">
                        <label class="form-label">
                            HMRC MTD Category
                            <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <select class="form-select" name="hmrc_category" id="edit_hmrc_category">
                            <?php foreach ($hmrc_categories as $val => $label): ?>
                            <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Maps this account to an HMRC category for MTD ITSA quarterly submissions.
                            Leave as "Not mapped" if unsure.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_active"
                                   id="edit_active" value="1">
                            <label class="form-check-label" for="edit_active">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {

    // Search
    document.getElementById('searchAccount').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.account-row').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });

    // Type filter
    document.getElementById('accountType').addEventListener('change', function () {
        const selected = this.value;
        document.querySelectorAll('.account-group').forEach(group => {
            group.style.display = (!selected || group.dataset.type === selected) ? '' : 'none';
        });
    });

    // Show/hide HMRC category in NEW modal based on type selection
    const newType = document.getElementById('new_type');
    const newHmrcWrap = document.getElementById('new_hmrc_category_wrap');

    function toggleNewHmrc() {
        newHmrcWrap.style.display = newType.value === 'expense' ? '' : 'none';
    }
    newType.addEventListener('change', toggleNewHmrc);
    toggleNewHmrc(); // run on load in case expense is default

    // Show/hide HMRC category in EDIT modal based on type selection
    const editType = document.getElementById('edit_type');
    const editHmrcWrap = document.getElementById('edit_hmrc_category_wrap');

    function toggleEditHmrc() {
        editHmrcWrap.style.display = editType.value === 'expense' ? '' : 'none';
    }
    editType.addEventListener('change', toggleEditHmrc);

    // Populate edit modal
    document.querySelectorAll('.edit-account-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('edit_id').value           = this.dataset.id;
            document.getElementById('edit_code').value         = this.dataset.code;
            document.getElementById('edit_name').value         = this.dataset.name;
            document.getElementById('edit_type').value         = this.dataset.type;
            document.getElementById('edit_description').value  = this.dataset.description;
            document.getElementById('edit_active').checked     = this.dataset.active === '1';
            document.getElementById('edit_vat_rate').value     = this.dataset.vatRate || 0;
            document.getElementById('edit_hmrc_category').value = this.dataset.hmrcCategory || '';

            // Show/hide HMRC field based on this account's type
            toggleEditHmrc();
        });
    });

});
</script>