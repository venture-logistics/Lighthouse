<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];

    if ($new_password == $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
        $stmt->bindParam(":password", $hashed_password);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        header("Location: login.php");
        exit;
    } else {
        echo "Passwords do not match.";
    }
}

$page_title = 'Password Reset';
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
                            <h4 class="page-title">Password Reset</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Password Reset</li>
                                </ol>
                            </div>                            
                        </div><!--end page-title-box-->

                        <div class="container py-5">
                            <div class="row justify-content-center">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h4 class="mb-0">Reset Password</h4>
                                        </div>
                                        <div class="card-body">
                                            <?php echo display_message(); ?>
                    
                                            <form action="<?php echo $_SERVER["PHP_SELF"]; ?>" method="post">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email Address</label>
                                                    <input type="email" class="form-control" id="email" name="email" required>
                                                </div>
                        
                                                <div class="mb-3">
                                                    <label for="new_password" class="form-label">New Password</label>
                                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                                </div>
                        
                                                <div class="mb-3">
                                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                                </div>
                        
                                                <button type="submit" class="btn btn-primary">Reset Password</button>
                                            </form>
                                        </div>
                                        <div class="card-footer text-center">
                                            <a href="login.php">Back to Login</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                     </div><!--end card-->
                   </div><!--end col-->
                 </div><!--end row-->
               </div><!--end card-body-->
             </div><!--end col-->

<?php
require_once 'includes/footer.php';
?>