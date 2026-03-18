<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

$page_title = 'Add Customer';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );

        $sql = "INSERT INTO customers (company_name, contact_name, email, phone, address, city, postcode, notes) 
                VALUES (:company_name, :contact_name, :email, :phone, :address, :city, :postcode, :notes)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_name' => $_POST['company_name'],
            'contact_name' => $_POST['contact_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address'],
            'city' => $_POST['city'],
            'postcode' => $_POST['postcode'],
            'notes' => $_POST['notes']
        ]);

        $_SESSION['message'] = 'Customer added successfully';
        header('Location: customers.php');
        exit();

    } catch (PDOException $e) {
        $error = "Error adding customer";
    }
}

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
                            <h4 class="page-title">Add new Customer</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Add Customer</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-body">
                                <form method="post" action="">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="company_name" class="form-label">Company Name</label>
                                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contact_name" class="form-label">Contact Name</label>
                                            <input type="text" class="form-control" id="contact_name" name="contact_name">
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="phone" name="phone">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="10"></textarea>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="city" class="form-label">City</label>
                                            <input type="text" class="form-control" id="city" name="city">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="postcode" class="form-label">Postcode</label>
                                            <input type="text" class="form-control" id="postcode" name="postcode">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="customers.php" class="btn btn-outline-secondary me-2">Cancel</a>
                                        <button type="submit" class="btn btn-primary">Add Customer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>