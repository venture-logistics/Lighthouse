<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'version.php';

// Safe fallback in case $updateStatus wasn't set by config.php
if (!isset($updateStatus)) {
    $updateStatus = [
        'update_available' => false,
        'latest_version'   => APP_VERSION,
        'current_version'  => APP_VERSION
    ];
}

// Use update status already fetched by config.php
$update_available = $updateStatus['update_available'];
$latest_version   = $updateStatus['latest_version'];
$current_version  = APP_VERSION; // ← Always use version.php, never the DB

$error = '';
$success = '';
$licence_file = 'includes/licence.json';

// Load existing licence data
if (file_exists($licence_file)) {
    $licence_data = json_decode(file_get_contents($licence_file), true);
} else {
    $licence_data = [
        'valid' => false,
        'expires_at' => null,
        'email' => '',
        'verified_at' => null
    ];
}

// Calculate validity from file
$is_valid = false;
$days_remaining = 0;

if (!empty($licence_data['expires_at'])) {
    $expires_date = new DateTime($licence_data['expires_at']);
    $now = new DateTime();
    $is_valid = ($expires_date > $now);
    $interval = $now->diff($expires_date);
    $days_remaining = $interval->days;
}

// Handle licence verification POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email'] ?? '');
    $purchase_code = trim($_POST['purchase_code'] ?? '');

    if (empty($email) || empty($purchase_code)) {
        $error = "Both email and purchase code are required.";
    } else {
        $api_url = "https://lighthousefinance.io/support/verify-license.php";
        $data = [
            'email' => $email,
            'purchase_code' => $purchase_code,
            'domain' => $_SERVER['HTTP_HOST'],
            'ip' => $_SERVER['SERVER_ADDR'],
            'version' => APP_VERSION
        ];

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            if ($result['valid']) {
                $licence_data = [
                    'valid' => true,
                    'expires_at' => $result['expires_at'],
                    'email' => $email,
                    'verified_at' => date('Y-m-d H:i:s')
                ];

                if (file_put_contents($licence_file, json_encode($licence_data, JSON_PRETTY_PRINT))) {
                    $expires_date = new DateTime($licence_data['expires_at']);
                    $now = new DateTime();
                    $is_valid = ($expires_date > $now);
                    $interval = $now->diff($expires_date);
                    $days_remaining = $interval->days;

                    $_SESSION['licence_valid'] = true;
                    $_SESSION['licence_expires'] = $result['expires_at'];
                    $success = "License validated successfully!";
                } else {
                    $error = "License verified but could not save to file. Check permissions on includes/ directory.";
                }
            } else {
                $error = $result['message'] ?? 'Invalid license';
            }
        } else {
            $error = "Error connecting to license server (HTTP $http_code): $curl_error";
        }
    }
}

// Handle update POST
if (isset($_POST['apply_update']) && $update_available && $download_url) {
    $zip_path = sys_get_temp_dir() . '/lighthouse_update.zip';

    $context = stream_context_create(['http' => ['header' => 'User-Agent: LighthouseApp']]);
    $zip_data = @file_get_contents($download_url, false, $context);

    if ($zip_data === false) {
        $error = "Failed to download update. Please try again.";
    } else {
        file_put_contents($zip_path, $zip_data);

        $zip = new ZipArchive();
        if ($zip->open($zip_path) === true) {

            // Protected files - never overwrite
            $protected = [
                'includes/config.php',
                'includes/functions.php',
                'includes/header.php',
                'includes/invoice_email.php',
                'includes/licence.json',
                'includes/menu.php',
                'includes/topbar.php',
            ];

            // Protected folders - never touch
            $protected_folders = ['lib/', 'assets/', 'uploads/', 'install/'];

            $base_path = __DIR__ . '/';
            $applied = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);

                // Skip directories
                if (substr($filename, -1) === '/') continue;

                // Skip protected folders
                $skip = false;
                foreach ($protected_folders as $folder) {
                    if (strpos($filename, $folder) === 0) {
                        $skip = true;
                        break;
                    }
                }

                // Skip protected files
                if (in_array($filename, $protected)) {
                    $skip = true;
                }

                if ($skip) continue;

                // Extract file
                $target = $base_path . $filename;
                $dir = dirname($target);
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($target, $zip->getFromIndex($i));
                $applied[] = $filename;
            }

            $zip->close();
            unlink($zip_path);
            $success = "Update to v" . $latest_version . " applied successfully! " . count($applied) . " files updated.";

        } else {
            $error = "Failed to open update zip.";
        }
    }
}

$page_title = 'System Settings';
include 'includes/header.php';
include 'includes/topbar.php';
include 'includes/sidebar.php';
?>

<div class="page-wrapper">
    <div class="page-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-12">
                    <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                        <h4 class="page-title">System Settings &amp; Licensing</h4>
                        <div class="">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Settings</li>
                            </ol>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <!-- License Status Card -->
                    <div class="card shadow-sm border-<?= $is_valid ? 'success' : 'danger' ?> mt-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <i class="bi bi-<?= $is_valid ? 'check-circle-fill text-success' : 'x-circle-fill text-danger' ?>"
                                       style="font-size: 2rem;"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">License <?= $is_valid ? 'Valid' : 'Invalid' ?></h6>
                                    <?php if (!empty($licence_data['expires_at'])): ?>
                                        <div class="text-muted">
                                            <?php if ($is_valid): ?>
                                                Expires in <?= $days_remaining ?> days
                                                (<?= (new DateTime($licence_data['expires_at']))->format('F j, Y') ?>)
                                            <?php else: ?>
                                                Expired on <?= (new DateTime($licence_data['expires_at']))->format('F j, Y') ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($licence_data['email'])): ?>
                                        <div class="text-muted small">
                                            Registered to: <?= htmlspecialchars($licence_data['email']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($licence_data['verified_at'])): ?>
                                        <div class="text-muted small">
                                            Last verified: <?= htmlspecialchars($licence_data['verified_at']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- License Update Form -->
                    <div class="card shadow-sm mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Update License</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Purchase Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?= htmlspecialchars($licence_data['email'] ?? '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="purchase_code" class="form-label">Purchase Code</label>
                                    <input type="text" class="form-control" id="purchase_code" name="purchase_code">
                                </div>
                                <button type="submit" class="btn btn-primary">Verify License</button>
                            </form>
                        </div>
                    </div>

                </div>

                <div class="col-md-4 pt-4">

                    <!-- Server Info Card -->
                    <div class="card">
                        <div class="card-header fs-5">Server Info</div>
                        <div class="card-body">
                            <?php
                            try {
                                $stmt = $pdo->query('SELECT VERSION()');
                                $mysql_version = $stmt->fetchColumn();
                            } catch (Exception $e) {
                                $mysql_version = 'Unknown';
                            }

                            $apache_full = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
                            preg_match('/Apache\/([^\s]+)/', $apache_full, $apache_match);
                            $apache_version = $apache_match[1] ?? $apache_full;

                            $server_items = [
                                ['icon' => 'bi-code-slash', 'label' => 'PHP',    'value' => phpversion()],
                                ['icon' => 'bi-database',   'label' => 'MySQL',  'value' => $mysql_version],
                                ['icon' => 'bi-server',     'label' => 'Apache', 'value' => $apache_version],
                            ];
                            ?>
                            <?php foreach ($server_items as $item): ?>
                                <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary">
                                    <span class="text-muted small">
                                        <i class="bi <?= $item['icon'] ?> me-2"></i><?= $item['label'] ?>
                                    </span>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($item['value']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Version Card -->
                    <div class="card mt-3">
                        <div class="card-header fs-5">Version</div>
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary mb-3">
                                <span class="text-muted small">Installed</span>
                                <span class="badge bg-secondary h6 mb-0"><?= htmlspecialchars($current_version) ?></span>
                            </div>

                            <?php if (!$is_valid): ?>
                                <div class="text-danger small text-center">
                                    <i class="bi bi-x-circle me-1"></i> A valid licence is required to receive updates
                                </div>

                            <?php elseif ($update_available): ?>
                                <div class="d-flex align-items-center justify-content-between py-2 border-bottom border-secondary mb-3">
                                    <span class="text-muted small">Latest</span>
                                    <span class="badge bg-warning text-dark h6 mb-0"><?= htmlspecialchars($latest_version) ?></span>
                                </div>

                                <?php if ($changelog): ?>
                                    <p class="small text-muted mb-3"><?= htmlspecialchars($changelog) ?></p>
                                <?php endif; ?>

                                <form method="post" action="">
                                    <button type="submit" name="apply_update" class="btn btn-warning w-100"
                                        onclick="return confirm('Apply update to v<?= htmlspecialchars($latest_version) ?>?')">
                                        <i class="bi bi-download me-1"></i> Apply Update
                                    </button>
                                </form>

                            <?php else: ?>
                                <div class="text-success small text-center">
                                    <i class="bi bi-check-circle me-1"></i> You're on the latest version
                                </div>

                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>