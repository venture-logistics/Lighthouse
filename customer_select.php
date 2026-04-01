<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

    // Get all customers
    $stmt = $pdo->query("SELECT * FROM customers ORDER BY company_name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not retrieve customers";
}

$page_title = 'Select Customer';
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
                            <h4 class="page-title">Select Customer for New Invoice</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Invoices</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php else: ?>
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <input type="text" class="form-control" id="customerSearch" 
                                               placeholder="Search customers..." autofocus>
                                    </div>
                                    <div class="col-auto">
                                        <a href="customer_add.php" class="btn btn-primary">Add New Customer</a>
                                    </div>
                                </div>
                            </div>
                            <div class="list-group list-group-flush" id="customerList">
                                <?php foreach ($customers as $customer): ?>
                                    <a href="invoice_create.php?customer_id=<?php echo $customer['id']; ?>" 
                                       class="list-group-item list-group-item-action customer-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($customer['company_name']); ?></h5>
                                            <small><?php echo htmlspecialchars($customer['city']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($customer['contact_name']); ?></p>
                                        <small><?php echo htmlspecialchars($customer['email']); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// Live search functionality
document.getElementById('customerSearch').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const customers = document.getElementsByClassName('customer-item');
    
    Array.from(customers).forEach(function(customer) {
        const text = customer.textContent.toLowerCase();
        customer.style.display = text.includes(searchText) ? '' : 'none';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>