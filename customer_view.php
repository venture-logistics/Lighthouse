<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid customer ID';
    header('Location: customers.php');
    exit();
}

$customer_id = $_GET['id'];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

    // Get customer details
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Customer not found');
    }

    // Get recent invoices
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$customer_id]);
    $recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: customers.php');
    exit();
}

$page_title = 'Customer Details';
require_once 'includes/header.php';
require_once 'includes/menu.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><?php echo htmlspecialchars($customer['company_name']); ?></h1>
                <div>
                    <a href="customer_edit.php?id=<?php echo $customer_id; ?>" 
                       class="btn btn-outline-primary me-2">Edit</a>
                    <a href="invoice_create.php?customer_id=<?php echo $customer_id; ?>" 
                       class="btn btn-success">New Invoice</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Contact Information</h5>
                    <dl class="row">
                        <dt class="col-sm-3">Contact Name</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['contact_name']); ?></dd>

                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">
                            <a href="mailto:<?php echo htmlspecialchars($customer['email']); ?>">
                                <?php echo htmlspecialchars($customer['email']); ?>
                            </a>
                        </dd>

                        <dt class="col-sm-3">Phone</dt>
                        <dd class="col-sm-9"><?php echo htmlspecialchars($customer['phone']); ?></dd>

                        <dt class="col-sm-3">Address</dt>
                        <dd class="col-sm-9">
                            <?php echo nl2br(htmlspecialchars($customer['address'])); ?><br>
                            <?php echo htmlspecialchars($customer['city']); ?><br>
                            <?php echo htmlspecialchars($customer['postcode']); ?>
                        </dd>
                    </dl>

                    <?php if ($customer['notes']): ?>
                        <h5 class="card-title mt-4">Notes</h5>
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Invoices Section -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Invoices</h5>
                    <a href="invoices.php?customer_id=<?php echo $customer_id; ?>" 
                       class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_invoices)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No invoices found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                                        <td>£<?php echo number_format($invoice['total'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo get_invoice_status_color($invoice['status']); ?>">
                                                <?php echo htmlspecialchars($invoice['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="invoice_view.php?id=<?php echo $invoice['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Activity timeline will be implemented later</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>