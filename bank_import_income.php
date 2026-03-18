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

$page_title = 'Import Income Transactions';
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
                            <h4 class="page-title">Import Income Transactions</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Import Income Transactions</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h1></h1>
                            <a href="bank_transactions.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Transactions
                            </a>
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

                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Upload Revolut Statement CSV</h5>
                            </div>
                            <div class="card-body">
                                <form action="process_bank_import_income.php" method="post" enctype="multipart/form-data">
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
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-upload"></i> Import Income
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Format reference -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Expected Format (Revolut Business Statement CSV)</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small mb-2">
                                    Only <strong>COMPLETED</strong> incoming transactions are imported. 
                                    Outgoing payments and REVERTED rows are automatically skipped.
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
                                            <tr><td>Date completed (UTC)</td><td>2025-05-09</td><td>Transaction date</td></tr>
                                            <tr><td>Date started (UTC)</td><td>2025-05-09</td><td>Started date</td></tr>
                                            <tr><td>ID</td><td>681e252b-...</td><td>Duplicate prevention</td></tr>
                                            <tr><td>Type</td><td>TOPUP</td><td>Transaction type</td></tr>
                                            <tr><td>State</td><td>COMPLETED</td><td>Skip non-completed</td></tr>
                                            <tr><td>Description</td><td>Money added from CUSTOMER NAME</td><td>Description</td></tr>
                                            <tr><td>Reference</td><td>0001</td><td>Payment reference</td></tr>
                                            <tr><td>Payer</td><td>DIRECTOR LOAN</td><td>Who paid</td></tr>
                                            <tr><td>Orig currency</td><td>GBP</td><td>Original currency</td></tr>
                                            <tr><td>Orig amount</td><td>200.00</td><td>Original amount</td></tr>
                                            <tr><td>Amount</td><td>200.00</td><td>GBP amount</td></tr>
                                            <tr><td>Fee</td><td>0.00</td><td>Fee</td></tr>
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