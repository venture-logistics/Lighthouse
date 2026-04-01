<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Get customer if ID provided
$customer = null;
if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );

        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$_GET['customer_id']]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error loading customer details";
    }
}

// Generate next invoice number (format: INV-YYYYMM-XXXX)
function generate_invoice_number()
{
    global $pdo;
    $prefix = 'INV-' . date('Ym') . '-';
    $stmt = $pdo->query("SELECT MAX(invoice_number) as max_number FROM invoices WHERE invoice_number LIKE '$prefix%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['max_number']) {
        $number = intval(substr($result['max_number'], -4)) + 1;
    } else {
        $number = 1;
    }

    return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // Insert invoice
        $sql = "INSERT INTO invoices (
                    customer_id, invoice_number, invoice_date, due_date, 
                    subtotal, tax_rate, tax_amount, total, notes, status
                ) VALUES (
                    :customer_id, :invoice_number, :invoice_date, :due_date,
                    :subtotal, :tax_rate, :tax_amount, :total, :notes, :status
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'customer_id' => $_POST['customer_id'],
            'invoice_number' => $_POST['invoice_number'],
            'invoice_date' => $_POST['invoice_date'],
            'due_date' => $_POST['due_date'],
            'subtotal' => $_POST['subtotal'],
            'tax_rate' => $_POST['tax_rate'],
            'tax_amount' => $_POST['tax_amount'],
            'total' => $_POST['total'],
            'notes' => $_POST['notes'],
            'status' => $_POST['send_now'] ? 'sent' : 'draft'
        ]);

        $invoice_id = $pdo->lastInsertId();

        // Insert invoice items
        $items = json_decode($_POST['invoice_items'], true);
        $sql = "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, amount) 
                VALUES (:invoice_id, :description, :quantity, :unit_price, :amount)";

        $stmt = $pdo->prepare($sql);
        foreach ($items as $item) {
            $stmt->execute([
                'invoice_id' => $invoice_id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'amount' => $item['amount']
            ]);
        }

        $pdo->commit();

        // Handle email sending if requested
        if ($_POST['send_now']) {
            // We'll implement email sending later
            $_SESSION['message'] = 'Invoice created and marked as sent. Email functionality coming soon.';
        } else {
            $_SESSION['message'] = 'Invoice created successfully';
        }

        header('Location: invoice_view.php?id=' . $invoice_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating invoice: " . $e->getMessage();
    }
}

$page_title = 'Create Invoice';
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
                            <h4 class="page-title">Create New Invoice</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Create Invoice</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <form id="invoiceForm" method="post" action="">
                            <div class="row">
                                <!-- Left Column -->
                                <div class="col-md-8">
                                    <!-- Customer Selection -->
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Customer Details</h5>
                                            <?php if ($customer): ?>
                                                <input type="hidden" name="customer_id" value="<?php echo $customer['id']; ?>">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p class="mb-1"><strong><?php echo htmlspecialchars($customer['company_name']); ?></strong></p>
                                                        <p class="mb-1"><?php echo htmlspecialchars($customer['contact_name']); ?></p>
                                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($customer['address'])); ?></p>
                                                        <p class="mb-1"><?php echo htmlspecialchars($customer['city']); ?></p>
                                                        <p class="mb-1"><?php echo htmlspecialchars($customer['postcode']); ?></p>
                                                    </div>
                                                    <div class="col-md-6 text-md-end">
                                                        <a href="customer_select.php" class="btn btn-outline-primary">Change Customer</a>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center py-3">
                                                    <p>No customer selected</p>
                                                    <a href="customer_select.php" class="btn btn-primary">Select Customer</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Invoice Items -->
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Invoice Items</h5>
                                            <table class="table" id="itemsTable">
                                                <thead>
                                                    <tr>
                                                        <th>Description</th>
                                                        <th width="100">Quantity</th>
                                                        <th width="150">Unit Price</th>
                                                        <th width="150">Amount</th>
                                                        <th width="50"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Items will be added here dynamically -->
                                                </tbody>
                                            </table>
                                            <button type="button" class="btn btn-outline-primary" id="addItem">Add Item</button>
                                        </div>
                                    </div>

                                    <!-- Notes -->
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Notes</h5>
                                            <textarea name="notes" class="form-control" rows="3" 
                                                    placeholder="Enter any additional notes..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right Column -->
                                <div class="col-md-4">
                                    <!-- Invoice Details -->
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Invoice Details</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Invoice Number</label>
                                                <input type="text" class="form-control" name="invoice_number" 
                                                       value="<?php echo generate_invoice_number(); ?>" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Invoice Date</label>
                                                <input type="date" class="form-control" name="invoice_date" 
                                                       value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Due Date</label>
                                                <input type="date" class="form-control" name="due_date" 
                                                       value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Totals -->
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Summary</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Subtotal</label>
                                                <input type="text" class="form-control" name="subtotal" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Tax Rate (%)</label>
                                                <input type="number" class="form-control" name="tax_rate" value="20.00">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Tax Amount</label>
                                                <input type="text" class="form-control" name="tax_amount" readonly>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Total</label>
                                                <input type="text" class="form-control" name="total" readonly>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="send_now" id="send_now">
                                                    <label class="form-check-label" for="send_now">
                                                        Send invoice to customer immediately
                                                    </label>
                                                </div>
                                            </div>
                                            <input type="hidden" name="invoice_items" id="invoice_items">
                                            <button type="submit" class="btn btn-primary w-100">Create Invoice</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>



<script>
// Invoice items handling
let items = [];

function updateTotals() {
    let subtotal = 0;
    items.forEach(item => {
        subtotal += parseFloat(item.amount);
    });
    
    const taxRate = parseFloat(document.querySelector('[name="tax_rate"]').value) || 0;
    const taxAmount = subtotal * (taxRate / 100);
    const total = subtotal + taxAmount;

    document.querySelector('[name="subtotal"]').value = subtotal.toFixed(2);
    document.querySelector('[name="tax_amount"]').value = taxAmount.toFixed(2);
    document.querySelector('[name="total"]').value = total.toFixed(2);
    document.getElementById('invoice_items').value = JSON.stringify(items);
}

function addItem() {
    items.push({
        description: '',
        quantity: 1,
        unit_price: 0,
        amount: 0
    });
    renderItems();
}

function removeItem(index) {
    items.splice(index, 1);
    renderItems();
    updateTotals();
}

function updateItem(index, field, value) {
    items[index][field] = value;
    if (field === 'quantity' || field === 'unit_price') {
        items[index].amount = (items[index].quantity * items[index].unit_price).toFixed(2);
    }
    renderItems();
    updateTotals();
}

function renderItems() {
    const tbody = document.querySelector('#itemsTable tbody');
    tbody.innerHTML = '';
    
    items.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" class="form-control" value="${item.description}"
                       onchange="updateItem(${index}, 'description', this.value)">
            </td>
            <td>
                <input type="number" class="form-control" value="${item.quantity}" min="1" step="1"
                       onchange="updateItem(${index}, 'quantity', this.value)">
            </td>
            <td>
                <input type="number" class="form-control" value="${item.unit_price}" min="0" step="0.01"
                       onchange="updateItem(${index}, 'unit_price', this.value)">
            </td>
            <td>
                <input type="text" class="form-control" value="${item.amount}" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeItem(${index})">×</button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

document.getElementById('addItem').addEventListener('click', addItem);
document.querySelector('[name="tax_rate"]').addEventListener('change', updateTotals);

// Add first item automatically
addItem();
</script>

<?php require_once 'includes/footer.php'; ?>