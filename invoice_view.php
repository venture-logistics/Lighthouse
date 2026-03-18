<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid invoice ID';
    header('Location: invoices.php');
    exit();
}

$invoice_id = $_GET['id'];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

    // Get invoice details with customer information
    $sql = "SELECT i.*, c.* 
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            WHERE i.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception('Invoice not found');
    }

    // Get invoice items
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: invoices.php');
    exit();
}

$page_title = 'Invoice ' . $invoice['invoice_number'];
require_once 'includes/header.php';
require_once 'includes/menu.php';
?>


<div class="container py-4">
    <!-- Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <div class="btn-group">
            <a href="invoice_pdf.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-primary">
                <i class="fas fa-download"></i> Download PDF
            </a>
            <?php if ($invoice['status'] == 'draft'): ?>
                <a href="invoice_edit.php?id=<?php echo $invoice_id; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-edit"></i> Edit
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                More Actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="invoice_send.php?id=<?php echo $invoice_id; ?>">
                    <i class="fas fa-envelope"></i> Send to Customer
                </a></li>
                <?php if ($invoice['status'] == 'sent' || $invoice['status'] == 'overdue'): ?>
                    <li><a class="dropdown-item" href="invoice_mark_paid.php?id=<?php echo $invoice_id; ?>">
                        <i class="fas fa-check"></i> Mark as Paid
                    </a></li>
                <?php endif; ?>
                <?php if ($invoice['status'] == 'draft'): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="invoice_delete.php?id=<?php echo $invoice_id; ?>"
                           onclick="return confirm('Are you sure you want to delete this invoice?')">
                        <i class="fas fa-trash"></i> Delete Invoice
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="alert alert-<?php echo get_status_color($invoice['status']); ?> mb-4">
        Status: <strong><?php echo ucfirst($invoice['status']); ?></strong>
        <?php if ($invoice['status'] == 'overdue'): ?>
            - Due date was <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
        <?php endif; ?>
    </div>

    <!-- Invoice Content -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-4">
                <!-- Customer Details -->
                <div class="col-sm-6">
                    <h5 class="mb-3">Bill To:</h5>
                    <h6><?php echo htmlspecialchars($invoice['company_name']); ?></h6>
                    <p class="mb-0"><?php echo htmlspecialchars($invoice['contact_name']); ?></p>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['address'])); ?></p>
                    <p class="mb-0"><?php echo htmlspecialchars($invoice['city']); ?></p>
                    <p class="mb-0"><?php echo htmlspecialchars($invoice['postcode']); ?></p>
                </div>
                
                <!-- Invoice Details -->
                <div class="col-sm-6 text-sm-end">
                    <p class="mb-1">
                        <strong>Invoice Date:</strong> 
                        <?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?>
                    </p>
                    <p class="mb-1">
                        <strong>Due Date:</strong> 
                        <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?>
                    </p>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td class="text-end"><?php echo number_format($item['quantity'], 2); ?></td>
                                <td class="text-end">£<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end">£<?php echo number_format($item['amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal</strong></td>
                            <td class="text-end">£<?php echo number_format($invoice['subtotal'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>VAT (<?php echo $invoice['tax_rate']; ?>%)</strong></td>
                            <td class="text-end">£<?php echo number_format($invoice['tax_amount'], 2); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total</strong></td>
                            <td class="text-end"><strong>£<?php echo number_format($invoice['total'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if ($invoice['notes']): ?>
                <div class="mt-4">
                    <h6>Notes:</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Timeline / Activity -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Invoice History</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Activity timeline coming soon...</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>