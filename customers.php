<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$page_title = 'Customers';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';

// Get all customers
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

    $stmt = $pdo->query("SELECT * FROM customers ORDER BY company_name");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Could not retrieve customers";
}
?>

    <div class="page-wrapper">
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">

                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title">Customers</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Customers</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <div class="d-flex float-end mb-4">
                            <a href="customer_add.php" class="btn btn-primary">Add Customer</a>
                        </div>
                        <div class="clearfix"></div>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php else: ?>
                            <div class="card">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Company Name</th>
                                                <th>Contact</th>
                                                <th>Email</th>
                                                <th>Phone</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customers as $customer): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($customer['company_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['contact_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                                    <td>
                                                        <a href="customer_view.php?id=<?php echo $customer['id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">View</a>
                                                        <a href="customer_edit.php?id=<?php echo $customer['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary">Edit</a>
                                                        <a href="invoice_create.php?customer_id=<?php echo $customer['id']; ?>" 
                                                           class="btn btn-sm btn-outline-success">New Invoice</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php require_once 'includes/footer.php'; ?>