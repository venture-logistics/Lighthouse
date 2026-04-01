<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);

    $stmt = $pdo->prepare("
        SELECT ba.*, coa.name AS coa_name 
        FROM bank_accounts ba
        LEFT JOIN chart_of_accounts coa ON ba.coa_id = coa.id
        WHERE ba.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $bank_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $bank_accounts = [];
}

$page_title = 'Import Bank Transactions';
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
                            <h4 class="page-title">Import Bank Transactions</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Import Bank Transactions</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <div class="d-flex float-end mb-4">
                            <a href="bank_transactions.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Transactions
                            </a>
                        </div>
                        <div class="clearfix"></div>

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

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Upload Revolut CSV</h5>
                            </div>
                            <div class="card-body">
                                <form action="process_bank_import_expenses.php" method="post" enctype="multipart/form-data">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Bank Account</label>
                                            <select class="form-select" name="bank_account_id" required>
                                                <option value="">-- Select Account --</option>
                                                <?php foreach ($bank_accounts as $account): ?>
                                                    <option value="<?php echo $account['id']; ?>">
                                                        <?php echo htmlspecialchars($account['account_name']); ?>
                                                        (<?php echo htmlspecialchars($account['bank_name']); ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">CSV File</label>
                                            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
                                            <small class="text-muted">
                                                Export from Revolut Business: Accounts → Statement → CSV
                                            </small>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-upload"></i> Import Transactions
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Format reference -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Expected Format (Revolut Business CSV)</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-2">
                                    The following columns are imported. REVERTED transactions are automatically skipped.
                                </p>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered small">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Column</th>
                                                <th>Example</th>
                                                <th>Used for</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr><td>Transaction completed (UTC)</td><td>2025-06-08</td><td>Transaction date</td></tr>
                                            <tr><td>Transaction started (UTC)</td><td>2025-06-06</td><td>Started date</td></tr>
                                            <tr><td>Transaction ID</td><td>6842a450-...</td><td>Duplicate prevention</td></tr>
                                            <tr><td>Transaction status</td><td>COMPLETED</td><td>Skip REVERTED</td></tr>
                                            <tr><td>Transaction type</td><td>CARD_PAYMENT</td><td>Transaction type</td></tr>
                                            <tr><td>Transaction description</td><td>Paypal *discord</td><td>Description</td></tr>
                                            <tr><td>Orig currency</td><td>USD</td><td>Original currency</td></tr>
                                            <tr><td>Orig amount (Orig currency)</td><td>9.99</td><td>Original amount</td></tr>
                                            <tr><td>Amount (Payment currency)</td><td>7.38</td><td>GBP amount</td></tr>
                                            <tr><td>Fee</td><td>0.00</td><td>Revolut fee</td></tr>
                                            <tr><td>Tax name</td><td>20% (VAT on Expenses)</td><td>VAT type</td></tr>
                                            <tr><td>Tax rate</td><td>20%</td><td>VAT rate</td></tr>
                                            <tr><td>Tax amount (Orig currency)</td><td>0.67</td><td>VAT amount</td></tr>
                                            <tr><td>Expense category name</td><td>Website</td><td>Category</td></tr>
                                            <tr><td>Expense category code</td><td>453</td><td>COA mapping</td></tr>
                                            <tr><td>Expense description</td><td>GitHub subscription</td><td>Expense notes</td></tr>
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

<?php require_once 'includes/footer.php'; ?>