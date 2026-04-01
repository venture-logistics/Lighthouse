<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$page_title = 'Login';
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
                            <h4 class="page-title">Account Login</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Account Login</li>
                                </ol>
                            </div>                           
                        </div><!--end page-title-box-->

                        <div class="container py-5">
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="mb-0">Login</h4>
                                        </div>
                                        <div class="card-body">
                                            <?php echo display_message(); ?>
                    
                                            <form action="process_login.php" method="post">
                                                <div class="mb-3">
                                                    <label for="username" class="form-label">Username</label>
                                                    <input type="text" class="form-control" id="username" name="username" required>
                                                </div>
                        
                                                <div class="mb-3">
                                                    <label for="password" class="form-label">Password</label>
                                                    <input type="password" class="form-control" id="password" name="password" required>
                                                </div>
                        
                                                <div class="mb-3 form-check">
                                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                                    <label class="form-check-label" for="remember">Remember me</label>
                                                </div>
                        
                                                <button type="submit" class="btn btn-primary">Login</button>
                                            </form>
                                        </div>
                                        <div class="card-footer text-center">
                                            <a href="password_reset.php">Forgot Password?</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php require_once 'includes/footer.php'; ?>