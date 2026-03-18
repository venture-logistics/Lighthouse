<?php
if (file_exists('../install/complete.lock')) {
    die('Installation already complete. Delete the install folder.');
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