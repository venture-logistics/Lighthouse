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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    if (empty($title) || empty($content)) {
        set_flash_message('error', 'Title and content are required');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO notes (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);

            set_flash_message('success', 'Note created successfully');
            header('Location: notes.php');
            exit;
        } catch (PDOException $e) {
            set_flash_message('error', 'Error creating note: ' . $e->getMessage());
        }
    }
}

$page_title = 'Create Note';
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
                        <h4 class="page-title">Add Note</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Add Note</li>
                            </ol>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="15" required></textarea>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="notes.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">Create Note</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/trumbowyg@2.31.0/dist/ui/trumbowyg.min.css">
<script src="https://cdn.jsdelivr.net/npm/trumbowyg@2.31.0/dist/trumbowyg.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/trumbowyg@2/dist/plugins/preformatted/trumbowyg.preformatted.min.js"></script>

<script>
$('#content').trumbowyg({
    btns: [
        ['viewHTML'],
        ['undo', 'redo'],
        ['formatting'],
        ['bold', 'italic', 'underline', 'strikethrough'],
        ['superscript', 'subscript'],
        ['link'],
        ['insertImage'],
        ['justifyLeft', 'justifyCenter', 'justifyRight', 'justifyFull'],
        ['unorderedList', 'orderedList'],
        ['horizontalRule'],
        ['preformatted'],
        ['removeformat'],
        ['fullscreen']
    ],
    autogrow: true,
    removeformatPasted: true,
    urlProtocol: true,
    minimalLinks: true
});
</script>

<style>
    .trumbowyg-button-pane {
        position: sticky !important;
        top: 0 !important;
        z-index: 1000 !important;
        background: #fff !important;
    }
</style>

<?php require_once 'includes/footer.php'; ?>