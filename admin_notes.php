<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    set_flash_message('error', 'Unauthorized access');
    header('Location: notes.php');
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$stmt = $pdo->query("
    SELECT id, title, created_at, updated_at
    FROM notes 
    ORDER BY title
");
$notes = $stmt->fetchAll();

$page_title = 'Manage Notes';
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
                        <h4 class="page-title">Manage Notes</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Manage Notes</li>
                            </ol>
                        </div>
                    </div>

                    <div class="col d-flex float-end">
                        <a href="note_create.php" class="btn btn-primary mb-3">New Note</a>
                    </div>
                    <div class="clearfix"></div>

                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Created</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($notes as $note): ?>
                                        <tr>
                                            <td>
                                                <a href="note.php?id=<?php echo $note['id']; ?>">
                                                    <?php echo htmlspecialchars($note['title']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo format_date($note['created_at']); ?></td>
                                            <td><?php echo format_date($note['updated_at']); ?></td>
                                            <td>
                                                <a href="note_edit.php?id=<?php echo $note['id']; ?>" 
                                                   class="btn btn-sm btn-primary">Edit</a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteModal<?php echo $note['id']; ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>

                                        <div class="modal fade" id="deleteModal<?php echo $note['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Confirm Delete</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        Are you sure you want to delete "<?php echo htmlspecialchars($note['title']); ?>"?
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <form action="note_delete.php" method="POST" class="d-inline">
                                                            <input type="hidden" name="id" value="<?php echo $note['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (empty($notes)): ?>
                            <div class="text-center text-muted">
                                <p>No notes found.</p>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>