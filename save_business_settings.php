<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

    // Handle logo upload
    $logo_path = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024;

        if (!in_array($_FILES['logo']['type'], $allowed_types)) {
            throw new Exception('Invalid file type. Only JPG, PNG and GIF files are allowed.');
        }

        if ($_FILES['logo']['size'] > $max_size) {
            throw new Exception('File is too large. Maximum size is 2MB.');
        }

        $upload_dir = 'uploads/logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $file_name = 'logo_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_path)) {
            $logo_path = $target_path;

            $stmt = $pdo->prepare("SELECT logo_path FROM business_settings WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $old_logo = $stmt->fetchColumn();

            if ($old_logo && file_exists($old_logo)) {
                unlink($old_logo);
            }
        } else {
            throw new Exception('Failed to upload file.');
        }
    }

    // Prepare data for database
    $data = [
        'company_name' => $_POST['company_name'] ?? null,
        'company_number' => $_POST['company_number'] ?? null,
        'vat_number' => $_POST['vat_number'] ?? null,
        'address_line1' => $_POST['address_line1'] ?? null,
        'address_line2' => $_POST['address_line2'] ?? null,
        'city' => $_POST['city'] ?? null,
        'county' => $_POST['county'] ?? null,
        'postcode' => $_POST['postcode'] ?? null,
        'country' => $_POST['country'] ?? 'United Kingdom',
        'phone' => $_POST['phone'] ?? null,
        'website' => $_POST['website'] ?? null,
        'logo_height' => $_POST['logo_height'] ?? 100,
        'primary_color' => $_POST['primary_color'] ?? '#0d6efd',
        'bank_name' => $_POST['bank_name'] ?? null,
        'account_name' => $_POST['account_name'] ?? null,
        'sort_code' => $_POST['sort_code'] ?? null,
        'account_number' => $_POST['account_number'] ?? null,
        'invoice_prefix' => $_POST['invoice_prefix'] ?? 'INV-',
        'invoice_next_number' => $_POST['invoice_next_number'] ?? 1,
        'default_payment_terms' => $_POST['default_payment_terms'] ?? 30,
        'default_tax_rate' => $_POST['default_tax_rate'] ?? 20.00,
        'invoice_notes' => $_POST['invoice_notes'] ?? null,
        'payment_instructions' => $_POST['payment_instructions'] ?? null,
        'smtp_host' => $_POST['smtp_host'] ?? null,
        'smtp_port' => $_POST['smtp_port'] ?? 587,
        'smtp_username' => $_POST['smtp_username'] ?? null,
        'smtp_password' => $_POST['smtp_password'] ?? null,
        'from_email' => $_POST['from_email'] ?? null,
        'from_name' => $_POST['from_name'] ?? null,
        'accounting_period_start' => !empty($_POST['accounting_period_start']) ? $_POST['accounting_period_start'] : null,
        'accounting_period_end' => !empty($_POST['accounting_period_end']) ? $_POST['accounting_period_end'] : null,
        'logo_path' => $logo_path ?? $_POST['existing_logo'] ?? null,
        'user_id' => $_SESSION['user_id']
    ];

    // Check if settings already exist for this user
    $stmt = $pdo->prepare("SELECT id FROM business_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $exists = $stmt->fetch();

    if ($exists) {
        $sql_parts = [];
        foreach ($data as $key => $value) {
            if ($key !== 'user_id') {
                $sql_parts[] = "`$key` = :$key";
            }
        }
        $sql = "UPDATE business_settings SET " . implode(', ', $sql_parts) .
            " WHERE user_id = :user_id";
    } else {
        $columns = implode('`, `', array_keys($data));
        $values = implode(', :', array_keys($data));
        $sql = "INSERT INTO business_settings (`$columns`) VALUES (:$values)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    // -------------------------------------------------------
    // Sync bank details to bank_accounts table
    // -------------------------------------------------------
    $user_id = $_SESSION['user_id'];

    // Get the COA id for Current Account (code 1000)
    $coa_stmt = $pdo->prepare("
        SELECT id FROM chart_of_accounts WHERE code = '1000' LIMIT 1
    ");
    $coa_stmt->execute();
    $coa_id = $coa_stmt->fetchColumn();

    // Check if this user already has a bank account row
    $bank_stmt = $pdo->prepare("
        SELECT id FROM bank_accounts WHERE user_id = ?
    ");
    $bank_stmt->execute([$user_id]);
    $bank_exists = $bank_stmt->fetch();

    if ($bank_exists) {
        // Update existing row
        $stmt = $pdo->prepare("
            UPDATE bank_accounts 
            SET account_name   = ?,
                bank_name      = ?,
                account_number = ?,
                sort_code      = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $_POST['account_name'],
            $_POST['bank_name'],
            $_POST['account_number'],
            $_POST['sort_code'],
            $user_id
        ]);
    } else {
        // Insert new row for this user
        $stmt = $pdo->prepare("
            INSERT INTO bank_accounts 
                (user_id, account_name, bank_name, account_number, sort_code, coa_id)
            VALUES 
                (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $_POST['account_name'],
            $_POST['bank_name'],
            $_POST['account_number'],
            $_POST['sort_code'],
            $coa_id
        ]);
    }
    // -------------------------------------------------------

    $_SESSION['message'] = 'Business settings saved successfully.';

} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: business_settings.php');
exit();