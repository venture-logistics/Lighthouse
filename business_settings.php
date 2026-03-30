<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();


// Get existing settings
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

    $stmt = $pdo->prepare("SELECT * FROM business_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $settings = [];
}

$vat_stmt = $pdo->prepare("SELECT * FROM vat_settings WHERE user_id = ?");
$vat_stmt->execute([$_SESSION['user_id']]);
$vat_settings = $vat_stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Business Settings';
require_once 'includes/header.php';
require_once 'includes/topbar.php';
require_once 'includes/sidebar.php';
?>

    <div class="page-wrapper">

        <!-- Page Content-->
        <div class="page-content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-12">
                        <div class="page-title-box d-md-flex justify-content-md-between align-items-center">
                            <h4 class="page-title fw-bold">Business Settings</h4>
                            <div class="">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a>
                                    </li><!--end nav-item-->
                                    <li class="breadcrumb-item active">Business Settings</li>
                                </ol>
                            </div>
                        </div>

                        <button form="settingsForm" type="submit" class="btn btn-primary mb-3">
                            Save All Settings
                        </button>

                        <?php if (isset($_SESSION['message'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['message'];
                            unset($_SESSION['message']); ?></div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error'];
                            unset($_SESSION['error']); ?></div>
                        <?php endif; ?>

                        <form id="settingsForm" action="save_business_settings.php" method="post" enctype="multipart/form-data">
        
                            <!-- Business Details -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Business Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Company Name *</label>
                                            <input type="text" class="form-control" name="company_name" 
                                                   value="<?php echo htmlspecialchars($settings['company_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Company Number</label>
                                            <input type="text" class="form-control" name="company_number" 
                                                   value="<?php echo htmlspecialchars($settings['company_number'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">VAT Number</label>
                                            <input type="text" class="form-control" name="vat_number" 
                                                   value="<?php echo htmlspecialchars($settings['vat_number'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 1 *</label>
                                            <input type="text" class="form-control" name="address_line1" 
                                                   value="<?php echo htmlspecialchars($settings['address_line1'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Address Line 2</label>
                                            <input type="text" class="form-control" name="address_line2" 
                                                   value="<?php echo htmlspecialchars($settings['address_line2'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">City *</label>
                                            <input type="text" class="form-control" name="city" 
                                                   value="<?php echo htmlspecialchars($settings['city'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">County</label>
                                            <input type="text" class="form-control" name="county" 
                                                   value="<?php echo htmlspecialchars($settings['county'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Postcode *</label>
                                            <input type="text" class="form-control" name="postcode" 
                                                   value="<?php echo htmlspecialchars($settings['postcode'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label">Website</label>
                                            <input type="url" class="form-control" name="website" 
                                                   value="<?php echo htmlspecialchars($settings['website'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Brand Assets -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Brand Assets</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Company Logo</label>
                                            <?php if (!empty($settings['logo_path'])): ?>
                                                <div class="mb-2">
                                                    <img src="<?php echo htmlspecialchars($settings['logo_path']); ?>" 
                                                         alt="Company Logo" class="img-thumbnail" 
                                                         style="max-height: 100px;">
                                                </div>
                                            <?php endif; ?>
                                            <input type="file" class="form-control" name="logo" accept="image/*">
                                            <small class="text-muted">Recommended size: 300x100px. Max file size: 2MB</small>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Logo Height (px)</label>
                                            <input type="number" class="form-control" name="logo_height" 
                                                   value="<?php echo htmlspecialchars($settings['logo_height'] ?? '100'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Brand Color</label>
                                            <input type="color" class="form-control form-control-color w-100" name="primary_color" 
                                                   value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#0d6efd'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bank Details -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Bank Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Bank Name</label>
                                            <input type="text" class="form-control" name="bank_name" 
                                                   value="<?php echo htmlspecialchars($settings['bank_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Account Name</label>
                                            <input type="text" class="form-control" name="account_name" 
                                                   value="<?php echo htmlspecialchars($settings['account_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Sort Code</label>
                                            <input type="text" class="form-control" name="sort_code" 
                                                   value="<?php echo htmlspecialchars($settings['sort_code'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Account Number</label>
                                            <input type="text" class="form-control" name="account_number" 
                                                   value="<?php echo htmlspecialchars($settings['account_number'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Banking Integration -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Banking Integration</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Your Bank Provider</label>
                                            <select class="form-select" name="bank_provider">
                                                <option value="">— Select your bank —</option>
                                                <option value="revolut_business" <?php echo ($settings['bank_provider'] ?? '') === 'revolut_business' ? 'selected' : ''; ?>>
                                                    Revolut Business
                                                </option>
                                                <option value="tide" <?php echo ($settings['bank_provider'] ?? '') === 'tide' ? 'selected' : ''; ?>>
                                                    Tide
                                                </option>
                                                <option value="monzo_business" <?php echo ($settings['bank_provider'] ?? '') === 'monzo_business' ? 'selected' : ''; ?>>
                                                    Monzo Business
                                                </option>
                                                <option value="barclays" <?php echo ($settings['bank_provider'] ?? '') === 'barclays' ? 'selected' : ''; ?>>
                                                    Barclays
                                                </option>
                                                <option value="hsbc" <?php echo ($settings['bank_provider'] ?? '') === 'hsbc' ? 'selected' : ''; ?>>
                                                    HSBC
                                                </option>
                                                <option value="natwest" <?php echo ($settings['bank_provider'] ?? '') === 'natwest' ? 'selected' : ''; ?>>
                                                    NatWest
                                                </option>
                                                <option value="lloyds" <?php echo ($settings['bank_provider'] ?? '') === 'lloyds' ? 'selected' : ''; ?>>
                                                    Lloyds
                                                </option>
                                                <option value="starling" <?php echo ($settings['bank_provider'] ?? '') === 'starling' ? 'selected' : ''; ?>>
                                                    Starling Bank
                                                </option>
                                            </select>
                                            <small class="text-muted">
                                                This tells Lighthouse how to read your bank CSV exports
                                            </small>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-center">
                                            <?php 
                                            $provider = $settings['bank_provider'] ?? '';
                                            if ($provider): ?>
                                                <div class="alert alert-info mb-0 w-100">
                                                    <?php if ($provider === 'revolut_business'): ?>
                                                    Export from: <strong>Revolut Business → Accounts → Export</strong>
                                                    <?php elseif ($provider === 'tide'): ?>
                                                        Export from: <strong>Tide → Transactions → Export CSV</strong>
                                                    <?php elseif ($provider === 'monzo_business'): ?>
                                                        Export from: <strong>Monzo → Statements → Download CSV</strong>
                                                    <?php elseif ($provider === 'starling'): ?>
                                                        Export from: <strong>Starling → Spaces → Download CSV</strong>
                                                    <?php else: ?>
                                                        Export your transactions as CSV from your online banking
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mb-4" id="vat_registered">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">VAT Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox"
                                                       name="is_vat_registered" id="isVatRegistered" value="1"
                                                       <?php echo !empty($vat_settings['is_vat_registered']) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="isVatRegistered">
                                                    <strong>I am VAT Registered</strong>
                                                </label>
                                            </div>
                                        </div>
                            
                                        <div id="vatFields" style="<?php echo empty($vat_settings['is_vat_registered']) ? 'display:none' : ''; ?>">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">VAT Number</label>
                                                    <input type="text" class="form-control" name="vat_number"
                                                           placeholder="GB123456789"
                                                           value="<?php echo htmlspecialchars($vat_settings['vat_number'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">VAT Registration Date</label>
                                                    <input type="date" class="form-control" name="vat_period_start"
                                                           value="<?php echo htmlspecialchars($vat_settings['vat_period_start'] ?? ''); ?>">
                                                    <small class="text-muted">The date you became VAT registered</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">VAT Rate</label>
                                                    <select class="form-select" name="vat_rate">
                                                        <option value="20" <?php echo ($vat_settings['vat_rate'] ?? '20') == '20' ? 'selected' : ''; ?>>
                                                            Standard Rate — 20%
                                                        </option>
                                                        <option value="5" <?php echo ($vat_settings['vat_rate'] ?? '') == '5' ? 'selected' : ''; ?>>
                                                            Reduced Rate — 5%
                                                        </option>
                                                        <option value="0" <?php echo ($vat_settings['vat_rate'] ?? '') == '0' ? 'selected' : ''; ?>>
                                                            Zero Rate — 0%
                                                        </option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">VAT Scheme</label>
                                                    <select class="form-select" name="vat_scheme">
                                                        <option value="standard" <?php echo ($vat_settings['vat_scheme'] ?? 'standard') === 'standard' ? 'selected' : ''; ?>>
                                                            Standard Accounting
                                                        </option>
                                                        <option value="flat_rate" <?php echo ($vat_settings['vat_scheme'] ?? '') === 'flat_rate' ? 'selected' : ''; ?>>
                                                            Flat Rate Scheme
                                                        </option>
                                                        <option value="cash" <?php echo ($vat_settings['vat_scheme'] ?? '') === 'cash' ? 'selected' : ''; ?>>
                                                            Cash Accounting
                                                        </option>
                                                    </select>
                                                    <small class="text-muted">Not sure? Most small businesses use Standard Accounting</small>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">VAT Quarter End Month</label>
                                                    <select class="form-select" name="vat_quarter_end">
                                                        <option value="Jan" <?php echo ($vat_settings['vat_quarter_end'] ?? '') === 'Jan' ? 'selected' : ''; ?>>
                                                            Stagger 1 — Jan / Apr / Jul / Oct
                                                        </option>
                                                        <option value="Feb" <?php echo ($vat_settings['vat_quarter_end'] ?? '') === 'Feb' ? 'selected' : ''; ?>>
                                                            Stagger 2 — Feb / May / Aug / Nov
                                                        </option>
                                                        <option value="Mar" <?php echo ($vat_settings['vat_quarter_end'] ?? 'Mar') === 'Mar' ? 'selected' : ''; ?>>
                                                            Stagger 3 — Mar / Jun / Sep / Dec
                                                        </option>
                                                    </select>
                                                    <small class="text-muted">Check your VAT registration certificate if unsure</small>
                                                </div>
                                                <div class="col-md-6 d-flex align-items-center">
                                                    <div class="alert alert-info mb-0 w-100">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        VAT returns are submitted <strong>quarterly</strong> to HMRC. 
                                                        You have <strong>1 month and 7 days</strong> after each quarter end to file and pay.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Invoice Settings -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Invoice Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Invoice Prefix</label>
                                            <input type="text" class="form-control" name="invoice_prefix" 
                                                   value="<?php echo htmlspecialchars($settings['invoice_prefix'] ?? 'INV-'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Next Invoice Number</label>
                                            <input type="number" class="form-control" name="invoice_next_number" 
                                                   value="<?php echo htmlspecialchars($settings['invoice_next_number'] ?? '1'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Default Payment Terms (Days)</label>
                                            <input type="number" class="form-control" name="default_payment_terms" 
                                                   value="<?php echo htmlspecialchars($settings['default_payment_terms'] ?? '30'); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Default Tax Rate (%)</label>
                                            <input type="number" step="0.01" class="form-control" name="default_tax_rate" 
                                                   value="<?php echo htmlspecialchars($settings['default_tax_rate'] ?? '20.00'); ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Default Invoice Notes</label>
                                            <textarea class="form-control" name="invoice_notes" rows="3"><?php
                                            echo htmlspecialchars($settings['invoice_notes'] ?? '');
                                            ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Payment Instructions</label>
                                            <textarea class="form-control" name="payment_instructions" rows="3"><?php
                                            echo htmlspecialchars($settings['payment_instructions'] ?? '');
                                            ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Settings -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Email Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">SMTP Host</label>
                                            <input type="text" class="form-control" name="smtp_host" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">SMTP Port</label>
                                            <input type="number" class="form-control" name="smtp_port" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">SMTP Username</label>
                                            <input type="text" class="form-control" name="smtp_username" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">SMTP Password</label>
                                            <input type="password" class="form-control" name="smtp_password" 
                                                   value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">From Email</label>
                                            <input type="email" class="form-control" name="from_email" 
                                                   value="<?php echo htmlspecialchars($settings['from_email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">From Name</label>
                                            <input type="text" class="form-control" name="from_name" 
                                                   value="<?php echo htmlspecialchars($settings['from_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Accounting Period -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Accounting Period</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Accounting Period Start</label>
                                            <input type="date" class="form-control" name="accounting_period_start" 
                                                   value="<?php echo htmlspecialchars($settings['accounting_period_start'] ?? ''); ?>">
                                            <small class="text-muted">The date your business accounting year begins</small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Accounting Period End</label>
                                            <input type="date" class="form-control" name="accounting_period_end" 
                                                   value="<?php echo htmlspecialchars($settings['accounting_period_end'] ?? ''); ?>">
                                            <small class="text-muted">The date your business accounting year ends</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<script>
document.getElementById('isVatRegistered').addEventListener('change', function () {
    document.getElementById('vatFields').style.display = this.checked ? 'block' : 'none';
});
</script>    

<?php require_once 'includes/footer.php'; ?>