<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

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

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid note ID');
    header('Location: notes.php');
    exit;
}

$note_id = (int) $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ?");
$stmt->execute([$note_id]);
$note = $stmt->fetch();

if (!$note) {
    set_flash_message('error', 'Note not found');
    header('Location: notes.php');
    exit;
}

$page_title = $note['title'];
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col">
            <div class="card mt-5">
                <div class="card-body bg-white text-dark border rounded-2 shadow-md">
                    <h1 class="mb-3"><?php echo htmlspecialchars($note['title']); ?></h1>
                    <div class="text-muted mb-4">
                        <small>Last updated: <?php echo format_date($note['updated_at']); ?></small>
                    </div>
                    <div>
                        <?php echo $note['content']; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>