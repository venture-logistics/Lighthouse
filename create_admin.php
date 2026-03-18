<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $error = null;

    // Check if passwords match
    if ($_POST['password'] !== $_POST['password_confirm']) {
        $error = "Passwords do not match!";
    } else {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Hash the password
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            // Prepare and execute the insert
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
            $stmt->execute([$_POST['username'], $_POST['email'], $hashed_password]);

            // Create a lock file to prevent further access
            file_put_contents('adduser.lock', 'Installation complete');

            // Redirect to login page
            header('Location: login.php');
            exit;

        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Check if already locked
if (file_exists('adduser.lock')) {
    header('Location: login.php');
    exit;
}

$page_title = 'Create Admin';
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
                                <h4 class="page-title">Create Admin</h4>
                                <div class="">
                                    <ol class="breadcrumb mb-0">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item active">Create Admin</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" name="password" class="form-control" required 
                                    minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" 
                                    title="Must contain at least one number and one uppercase and lowercase letter, and at least 8 or more characters">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" name="password_confirm" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">
                                    Password must contain:
                                    <ul>
                                        <li>At least 8 characters</li>
                                        <li>At least one uppercase letter</li>
                                        <li>At least one lowercase letter</li>
                                        <li>At least one number</li>
                                    </ul>
                                </small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add User</button>
                        </form>
                    </div>


                </div>
            </div>
        </div>

<?php require_once 'includes/footer.php'; ?>

    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        var password = document.querySelector('input[name="password"]').value;
        var confirm = document.querySelector('input[name="password_confirm"]').value;
        
        if (password !== confirm) {
            e.preventDefault();
            alert('Passwords do not match!');
        }
    });
    </script>