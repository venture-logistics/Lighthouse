<?php
if (file_exists('../install/complete.lock')) {
    die('Installation already complete. Delete the install folder.');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    $db_pass = $_POST['db_pass'];

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Test database connection
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die('<div class="alert alert-danger">Database connection failed: ' . $e->getMessage() . '</div>');
    }

    // Import SQL file
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === 0) {
        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            die('<div class="alert alert-danger">SQL import failed: ' . $e->getMessage() . '</div>');
        }
    } else {
        die('<div class="alert alert-danger">SQL file upload failed.</div>');
    }

    // Create admin user
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$username, $email, $password]);
    } catch (PDOException $e) {
        die('<div class="alert alert-danger">Failed to create admin user: ' . $e->getMessage() . '</div>');
    }

    // Update config.php with database credentials
    $config_file = '../config.php';
    $config_content = file_get_contents($config_file);

    // Replace empty credentials with new values
    $config_content = preg_replace(
        [
            "/define\('DB_HOST', ''\);/",
            "/define\('DB_USER', ''\);/",
            "/define\('DB_PASS', ''\);/",
            "/define\('DB_NAME', ''\);/"
        ],
        [
            "define('DB_HOST', '$db_host');",
            "define('DB_USER', '$db_user');",
            "define('DB_PASS', '$db_pass');",
            "define('DB_NAME', '$db_name');"
        ],
        $config_content
    );

    // Write updated content back to config.php
    if (!file_put_contents($config_file, $config_content)) {
        die('<div class="alert alert-danger">Failed to update config file. Check file permissions.</div>');
    }

    // Create lock file
    file_put_contents('complete.lock', 'Installation complete - ' . date('Y-m-d H:i:s'));

    // Show success page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Lighthouse Installed</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    </head>
    <body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">Installation Complete!</h4>
                    </div>
                    <div class="card-body">
                        <p>Lighthouse has been installed successfully.</p>
                        <div class="alert alert-warning">
                            <strong>Important:</strong> Please delete the <code>install/</code> folder before using the application.
                        </div>
                        <a href="../login.php" class="btn btn-primary w-100">Go to Lighthouse</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lighthouse Installer</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Lighthouse Installer</h4>
                </div>
                <div class="card-body">
                    <form action="install.php" method="POST" enctype="multipart/form-data">
                        <h5 class="mb-3">Database Details</h5>
                        <div class="mb-3">
                            <label class="form-label">DB Host</label>
                            <input type="text" name="db_host" class="form-control" value="localhost" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">DB Name</label>
                            <input type="text" name="db_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">DB Username</label>
                            <input type="text" name="db_user" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">DB Password</label>
                            <input type="password" name="db_pass" class="form-control">
                        </div>

                        <hr>
                        <h5 class="mb-3">Admin Account</h5>
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
                            <input type="password" name="password" class="form-control" required>
                        </div>

                        <hr>
                        <h5 class="mb-3">Upload SQL File</h5>
                        <div class="mb-3">
                            <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Install Lighthouse</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>