<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$errors = [];
$success = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- ADD USER ---
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($username))
            $errors[] = 'Username is required.';
        if (empty($email))
            $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Invalid email address.';
        if (!in_array($role, ['admin', 'manager']))
            $errors[] = 'Invalid role.';
        if (!in_array($status, ['active', 'inactive']))
            $errors[] = 'Invalid status.';
        if (empty($password))
            $errors[] = 'Password is required.';
        if ($password !== $confirm)
            $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // Check for duplicate username or email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Username or email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hash, $role, $status]);
                $success = 'User added successfully.';
            }
        }
    }

    // --- EDIT USER ---
    if ($action === 'edit') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($username))
            $errors[] = 'Username is required.';
        if (empty($email))
            $errors[] = 'Email is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            $errors[] = 'Invalid email address.';
        if (!in_array($role, ['admin', 'manager']))
            $errors[] = 'Invalid role.';
        if (!in_array($status, ['active', 'inactive']))
            $errors[] = 'Invalid status.';
        if (empty($password))
            $errors[] = 'Password is required.';
        if ($password !== $confirm)
            $errors[] = 'Passwords do not match.';

        if (empty($errors)) {
            // Check duplicate username or email excluding this user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$username, $email, $id]);
            if ($stmt->rowCount() > 0) {
                $errors[] = 'Username or email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $email, $role, $status, $hash, $id]);
                $success = 'User updated successfully.';
            }
        }
    }

    // --- DELETE USER ---
    if ($action === 'delete') {
        $id = (int) ($_POST['user_id'] ?? 0);

        if ($id === (int) $_SESSION['user_id']) {
            $errors[] = 'You cannot delete your own account.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $success = 'User deleted successfully.';
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT id, username, email, role, status, created_at, last_login FROM users ORDER BY username ASC")->fetchAll();

$page_title = 'User Management';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-sm-12">
                    <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                        <h4 class="page-title">User Management</h4>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">User Management</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?php echo htmlspecialchars($e); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- User Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Users</h4>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus me-1"></i> Add User
                            </button>
                        </div>
                        <div class="card-body pt-0">
                            <table class="table table-striped table-hover mt-3">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-secondary'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $user['status'] === 'active' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $user['created_at'] ? date('d-m-Y', strtotime($user['created_at'])) : '-'; ?></td>
                                        <td><?php echo $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary me-1 btn-edit"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                data-role="<?php echo $user['role']; ?>"
                                                data-status="<?php echo $user['status']; ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editUserModal">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <?php if ($user['id'] !== (int) $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger btn-delete"
                                                data-id="<?php echo $user['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteUserModal">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <?php else: ?>
                                            <button class="btn btn-sm btn-outline-danger" disabled>
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ===== ADD USER MODAL ===== -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addUserForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="">-- Select Role --</option>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="add_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="add_confirm_password" class="form-control" required>
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== EDIT USER MODAL ===== -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit_status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="edit_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" id="edit_confirm_password" class="form-control" required>
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== DELETE CONFIRMATION MODAL ===== -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-header">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_username"></strong>? This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// --- Populate Edit Modal ---
document.querySelectorAll('.btn-edit').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_user_id').value    = this.dataset.id;
        document.getElementById('edit_username').value   = this.dataset.username;
        document.getElementById('edit_email').value      = this.dataset.email;
        document.getElementById('edit_role').value       = this.dataset.role;
        document.getElementById('edit_status').value     = this.dataset.status;
        // Clear passwords on open
        document.getElementById('edit_password').value         = '';
        document.getElementById('edit_confirm_password').value = '';
    });
});

// --- Populate Delete Modal ---
document.querySelectorAll('.btn-delete').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('delete_user_id').value = this.dataset.id;
        document.getElementById('delete_username').textContent = this.dataset.username;
    });
});

// --- Password match validation - Add form ---
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    const pw  = document.getElementById('add_password');
    const cpw = document.getElementById('add_confirm_password');
    if (pw.value !== cpw.value) {
        e.preventDefault();
        cpw.classList.add('is-invalid');
    } else {
        cpw.classList.remove('is-invalid');
    }
});

document.getElementById('add_confirm_password').addEventListener('input', function() {
    const pw  = document.getElementById('add_password').value;
    if (this.value !== pw) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// --- Password match validation - Edit form ---
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const pw  = document.getElementById('edit_password');
    const cpw = document.getElementById('edit_confirm_password');
    if (pw.value !== cpw.value) {
        e.preventDefault();
        cpw.classList.add('is-invalid');
    } else {
        cpw.classList.remove('is-invalid');
    }
});

document.getElementById('edit_confirm_password').addEventListener('input', function() {
    const pw = document.getElementById('edit_password').value;
    if (this.value !== pw) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>