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

    // Build the query
    $sql = "SELECT i.*, c.company_name 
            FROM invoices i 
            JOIN customers c ON i.customer_id = c.id";
    $params = [];

    // Filter by customer if specified
    if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
        $sql .= " WHERE i.customer_id = ?";
        $params[] = $_GET['customer_id'];
    }

    // Filter by status if specified
    if (isset($_GET['status']) && in_array($_GET['status'], ['draft', 'sent', 'paid', 'overdue', 'cancelled'])) {
        $sql .= empty($params) ? " WHERE" : " AND";
        $sql .= " i.status = ?";
        $params[] = $_GET['status'];
    }

    // Add sorting
    $sql .= " ORDER BY i.invoice_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get totals
    $stmt = $pdo->query("SELECT 
    COUNT(*) as total_invoices,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END), 0) as total_paid,
    COALESCE(SUM(CASE WHEN status = 'overdue' THEN total ELSE 0 END), 0) as total_overdue
    FROM invoices");
    $totals = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = 'Invoices';
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
                            <h4 class="page-title">Invoices</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Invoices</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <!-- Header Section -->
                        <div class="d-flex float-end mb-4">
                            <a href="invoice_create.php" class="btn btn-primary">Create Invoice</a>
                        </div>
                        <div class="clearfix"></div>

                        <!-- Stats Cards -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title text-muted">Total Invoices</h6>
                                        <h3 class="card-text"><?php echo number_format((int) $totals['total_invoices']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-success text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">Total Paid</h6>
                                        <h3 class="card-text">£<?php echo number_format((float) $totals['total_paid'], 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-danger text-white">
                                    <div class="card-body">
                                        <h6 class="card-title">Total Overdue</h6>
                                        <h3 class="card-text">£<?php echo number_format((float) $totals['total_overdue'], 2); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <form method="get" class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select name="status" class="form-select" onchange="this.form.submit()">
                                            <option value="">All Statuses</option>
                                            <option value="draft" <?php echo isset($_GET['status']) && $_GET['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="sent" <?php echo isset($_GET['status']) && $_GET['status'] == 'sent' ? 'selected' : ''; ?>>Sent</option>
                                            <option value="paid" <?php echo isset($_GET['status']) && $_GET['status'] == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                            <option value="overdue" <?php echo isset($_GET['status']) && $_GET['status'] == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                            <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Search</label>
                                        <input type="text" class="form-control" id="searchInput" placeholder="Search invoices...">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Date Range</label>
                                        <div class="input-group">
                                            <input type="date" class="form-control" name="date_from">
                                            <span class="input-group-text">to</span>
                                            <input type="date" class="form-control" name="date_to">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Invoices Table -->
                        <div class="card">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0" id="invoicesTable">
                                    <thead>
                                        <tr>
                                            <th>Invoice #</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Due Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($invoices)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <p class="text-muted mb-0">No invoices found</p>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($invoices as $invoice): ?>
                                                <tr>
                                                    <td>
                                                        <a href="invoice_view.php?id=<?php echo $invoice['id']; ?>">
                                                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($invoice['company_name']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                                                    <td>£<?php echo number_format($invoice['total'], 2); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo get_status_color($invoice['status']); ?>">
                                                            <?php echo ucfirst($invoice['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="invoice_view.php?id=<?php echo $invoice['id']; ?>" 
                                                               class="btn btn-outline-primary">View</a>
                                                            <?php if ($invoice['status'] == 'draft'): ?>
                                                                <a href="invoice_edit.php?id=<?php echo $invoice['id']; ?>" 
                                                                   class="btn btn-outline-secondary">Edit</a>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                                    data-bs-toggle="dropdown"></button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li><a class="dropdown-item" href="invoice_pdf.php?id=<?php echo $invoice['id']; ?>">
                                                                    Download PDF</a></li>
                                                                <li><a class="dropdown-item" href="invoice_send.php?id=<?php echo $invoice['id']; ?>">
                                                                    Send to Customer</a></li>
                                                                <?php if ($invoice['status'] == 'sent' || $invoice['status'] == 'overdue'): ?>
                                                                    <li><a class="dropdown-item" href="invoice_mark_paid.php?id=<?php echo $invoice['id']; ?>">
                                                                        Mark as Paid</a></li>
                                                                <?php endif; ?>
                                                                <?php if ($invoice['status'] == 'draft'): ?>
                                                                    <li><hr class="dropdown-divider"></li>
                                                                    <li><a class="dropdown-item text-danger" href="invoice_delete.php?id=<?php echo $invoice['id']; ?>"
                                                                           onclick="return confirm('Are you sure you want to delete this invoice?')">
                                                                        Delete</a></li>
                                                                <?php endif; ?>
                                                            </ul>
                                                        </div>
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

<script>
// Live search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchText = this.value.toLowerCase();
    const table = document.getElementById('invoicesTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchText) ? '' : 'none';
    }
});

// Helper function for status colors (add to functions.php)
function get_status_color(status) {
    switch(status) {
        case 'paid': return 'success';
        case 'sent': return 'primary';
        case 'draft': return 'secondary';
        case 'overdue': return 'danger';
        case 'cancelled': return 'dark';
        default: return 'secondary';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>