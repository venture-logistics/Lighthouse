<?php
// Get business settings for the current user
function get_business_logo() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, 
            DB_USER, 
            DB_PASS
        );
        
        $stmt = $pdo->prepare("SELECT logo_path, logo_height, company_name FROM business_settings WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

$business = get_business_logo();
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <?php if ($business && !empty($business['logo_path']) && file_exists($business['logo_path'])): ?>
                <img src="<?php echo htmlspecialchars($business['logo_path']); ?>" 
                     alt="<?php echo htmlspecialchars($business['company_name']); ?>"
                     height="<?php echo (int) ($business['logo_height'] ?? 60); ?>"
                     class="navbar-logo">
            <?php else: ?>
                <?php echo htmlspecialchars($business['company_name'] ?? 'Open Accounts'); ?>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="chart_of_accounts.php">Accounts</a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        Transactions
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="customerDropdown">
                        <li><a class="dropdown-item" href="bank_import_expenses.php">Import Expenses</a></li>
                        <li><a class="dropdown-item" href="bank_transactions.php">Transactions</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        Reports
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="customerDropdown">
                        <li><a class="dropdown-item" href="report_directors_loan.php">Director Loan Account</a></li>
                        <li><a class="dropdown-item" href="report_profit_loss.php">Profit / Loss</a></li>
                        <li><a class="dropdown-item" href="balance_sheet.php">Balance Sheet</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        Customers
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="customerDropdown">
                        <li><a class="dropdown-item" href="customers.php">View Customers</a></li>
                        <li><a class="dropdown-item" href="customer_add.php">Add Customer</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="invoices.php">View Invoices</a></li>
                        <li><a class="dropdown-item" href="invoice_create.php">Create Invoice</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="business_settings.php">Settings</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" 
                       data-bs-toggle="dropdown" aria-expanded="false">
                        Guides
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="customerDropdown">
                        <li><a class="dropdown-item" href="documents.php">View Guides</a></li>
                        <li><a class="dropdown-item" href="admin_documents.php">Manage Guides</a></li>
                        <li><a class="dropdown-item" href="document_create.php">Create Guide</a></li>
                    </ul>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>