<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Check if ID is provided
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

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $sql = "UPDATE customers SET 
                company_name = :company_name,
                contact_name = :contact_name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                postcode = :postcode,
                notes = :notes
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_name' => $_POST['company_name'],
            'contact_name' => $_POST['contact_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address'],
            'city' => $_POST['city'],
            'postcode' => $_POST['postcode'],
            'notes' => $_POST['notes'],
            'id' => $customer_id
        ]);

        $_SESSION['message'] = 'Customer updated successfully';
        header('Location: customers.php');
        exit();
    }

    // Get existing customer data
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new Exception('Customer not found');
    }

} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: customers.php');
    exit();
}

$page_title = 'Edit Customer';
require_once 'includes/header.php';
require_once 'includes/menu.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Edit Customer</h1>
                <div>
                    <a href="customer_view.php?id=<?php echo $customer_id; ?>" 
                       class="btn btn-outline-primary me-2">View Details</a>
                    <a href="invoice_create.php?customer_id=<?php echo $customer_id; ?>" 
                       class="btn btn-success">New Invoice</a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" 
                                       value="<?php echo htmlspecialchars($customer['company_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="contact_name" class="form-label">Contact Name</label>
                                <input type="text" class="form-control" id="contact_name" name="contact_name"
                                       value="<?php echo htmlspecialchars($customer['contact_name']); ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($customer['email']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($customer['phone']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" 
                                    rows="2"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?php echo htmlspecialchars($customer['city']); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="postcode" class="form-label">Postcode</label>
                                <input type="text" class="form-control" id="postcode" name="postcode"
                                       value="<?php echo htmlspecialchars($customer['postcode']); ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" 
                                    rows="3"><?php echo htmlspecialchars($customer['notes']); ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="customers.php" class="btn btn-outline-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Customer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>