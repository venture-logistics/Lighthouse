<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$page_title = 'Chart of Accounts';
require_once 'includes/header.php';
require_once 'includes/menu.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Chart of Accounts</h1>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAccountModal">
            <i class="bi bi-plus-lg"></i> New Account
        </button>
    </div>

    <!-- Filters and Search -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <select class="form-select" id="accountType">
                        <option value="">All Account Types</option>
                        <option value="asset">Assets</option>
                        <option value="liability">Liabilities</option>
                        <option value="equity">Equity</option>
                        <option value="income">Income</option>
                        <option value="expense">Expenses</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchAccount" placeholder="Search accounts...">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-secondary" id="exportAccounts">
                        <i class="bi bi-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Accounts Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th class="text-end">Balance</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // We'll replace this with dynamic data from database
                    $accounts = [
                        ['code' => '1000', 'name' => 'Cash', 'type' => 'Asset', 'balance' => 5000],
                        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'Liability', 'balance' => 1500],
                    ];

                    foreach ($accounts as $account): ?>
                    <tr>
                        <td><?php echo $account['code']; ?></td>
                        <td><?php echo $account['name']; ?></td>
                        <td><span class="badge bg-secondary"><?php echo $account['type']; ?></span></td>
                        <td class="text-end"><?php echo format_money($account['balance']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editAccountModal">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Account Code</label>
                        <input type="text" class="form-control" name="code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Account Type</label>
                        <select class="form-select" name="type" required>
                            <option value="asset">Asset</option>
                            <option value="liability">Liability</option>
                            <option value="equity">Equity</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
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

<?php require_once 'includes/footer.php'; ?>

<!-- Custom JS for this page -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('searchAccount');
    searchInput.addEventListener('input', function(e) {
        const searchText = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });

    // Account type filter
    const typeSelect = document.getElementById('accountType');
    typeSelect.addEventListener('change', function(e) {
        const selectedType = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            if (!selectedType) {
                row.style.display = '';
                return;
            }
            const type = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
            row.style.display = type === selectedType ? '' : 'none';
        });
    });
});
</script>