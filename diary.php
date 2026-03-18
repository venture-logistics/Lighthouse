<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

// Admin only for add/edit/delete
$is_admin = ($_SESSION['role'] === 'admin');

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_admin) {
    $action = $_POST['action'] ?? '';

    // --- ADD ---
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $entry_date = trim($_POST['entry_date'] ?? '');
        $entry_time = trim($_POST['entry_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($title))
            $errors[] = 'Title is required.';
        if (empty($entry_date))
            $errors[] = 'Date is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO diary (title, entry_date, entry_time, notes, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $title,
                $entry_date,
                $entry_time ?: null,
                $notes ?: null,
                $_SESSION['user_id']
            ]);
            $success = 'Diary entry added successfully.';
        }
    }

    // --- EDIT ---
    if ($action === 'edit') {
        $id = (int) ($_POST['entry_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $entry_date = trim($_POST['entry_date'] ?? '');
        $entry_time = trim($_POST['entry_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($title))
            $errors[] = 'Title is required.';
        if (empty($entry_date))
            $errors[] = 'Date is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                UPDATE diary SET title = ?, entry_date = ?, entry_time = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $title,
                $entry_date,
                $entry_time ?: null,
                $notes ?: null,
                $id
            ]);
            $success = 'Diary entry updated successfully.';
        }
    }

    // --- DELETE ---
    if ($action === 'delete') {
        $id = (int) ($_POST['entry_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM diary WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Diary entry deleted successfully.';
    }
}

// Fetch all diary entries ordered by date
$entries = $pdo->query("
    SELECT d.*, u.username AS created_by_name
    FROM diary d
    JOIN users u ON d.created_by = u.id
    ORDER BY d.entry_date ASC, d.entry_time ASC
")->fetchAll();

$page_title = 'Diary';
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
                        <h4 class="page-title">Diary</h4>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Diary</li>
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

            <!-- Diary Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">All Entries</h4>
                            <?php if ($is_admin): ?>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                                    <i class="fas fa-plus me-1"></i> Add Entry
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body pt-0">
                            <?php if (empty($entries)): ?>
                                <p class="text-muted mt-3">No diary entries found.</p>
                            <?php else: ?>
                                <table class="table table-striped table-hover mt-3">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Title</th>
                                            <th>Notes</th>
                                            <th>Created By</th>
                                            <?php if ($is_admin): ?><th>Actions</th><?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($entries as $entry): ?>
                                            <?php
                                            $is_today = ($entry['entry_date'] === date('Y-m-d'));
                                            $is_past = ($entry['entry_date'] < date('Y-m-d'));
                                            $row_class = $is_today ? 'table-warning' : ($is_past ? 'text-muted' : '');
                                            ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td><?php echo date('d-m-Y', strtotime($entry['entry_date'])); ?></td>
                                                <td><?php echo $entry['entry_time'] ? date('H:i', strtotime($entry['entry_time'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($entry['title']); ?></td>
                                                <td><?php echo $entry['notes'] ? nl2br(htmlspecialchars($entry['notes'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($entry['created_by_name']); ?></td>
                                                <?php if ($is_admin): ?>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary me-1 btn-edit-entry"
                                                        data-id="<?php echo $entry['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($entry['title']); ?>"
                                                        data-date="<?php echo $entry['entry_date']; ?>"
                                                        data-time="<?php echo $entry['entry_time'] ?? ''; ?>"
                                                        data-notes="<?php echo htmlspecialchars($entry['notes'] ?? ''); ?>"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editEntryModal">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger btn-delete-entry"
                                                        data-id="<?php echo $entry['id']; ?>"
                                                        data-title="<?php echo htmlspecialchars($entry['title']); ?>"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteEntryModal">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php if ($is_admin): ?>

<!-- ===== ADD ENTRY MODAL ===== -->
<div class="modal fade" id="addEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add Diary Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time <span class="text-muted small">(optional)</span></label>
                        <input type="time" name="entry_time" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== EDIT ENTRY MODAL ===== -->
<div class="modal fade" id="editEntryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="entry_id" id="edit_entry_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Diary Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_entry_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" name="entry_date" id="edit_entry_date" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Time <span class="text-muted small">(optional)</span></label>
                        <input type="time" name="entry_time" id="edit_entry_time" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes <span class="text-muted small">(optional)</span></label>
                        <textarea name="notes" id="edit_entry_notes" class="form-control" rows="4"></textarea>
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
<div class="modal fade" id="deleteEntryModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="entry_id" id="delete_entry_id">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="delete_entry_title"></strong>? This cannot be undone.</p>
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
// Populate edit modal
document.querySelectorAll('.btn-edit-entry').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_entry_id').value    = this.dataset.id;
        document.getElementById('edit_entry_title').value = this.dataset.title;
        document.getElementById('edit_entry_date').value  = this.dataset.date;
        document.getElementById('edit_entry_time').value  = this.dataset.time;
        document.getElementById('edit_entry_notes').value = this.dataset.notes;
    });
});

// Populate delete modal
document.querySelectorAll('.btn-delete-entry').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('delete_entry_id').value          = this.dataset.id;
        document.getElementById('delete_entry_title').textContent = this.dataset.title;
    });
});
</script>

<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>