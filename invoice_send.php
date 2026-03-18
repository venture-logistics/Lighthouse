<?php
define('INCLUDED_FROM_ANOTHER_SCRIPT', true);

require_once 'includes/config.php';
require_once 'includes/functions.php';

require 'lib/phpmailer/src/Exception.php';
require 'lib/phpmailer/src/PHPMailer.php';
require 'lib/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_login();

// Create database connection
if (!isset($pdo)) {
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
}

function set_flash_message($type, $message)
{
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

// Check if invoice ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_flash_message('error', 'Invalid invoice ID');
    header('Location: invoices.php');
    exit;
}

$invoice_id = (int) $_GET['id'];

// Get invoice details with customer and business information
$stmt = $pdo->prepare("
    SELECT i.*, 
           c.company_name as client_company,
           c.contact_name as client_contact,
           c.email as client_email,
           c.phone as client_phone,
           c.address as client_address,
           c.city as client_city,
           c.postcode as client_postcode,
           b.company_name as business_name,
           b.company_number,
           b.vat_number,
           b.address_line1,
           b.address_line2,
           b.city as business_city,
           b.county,
           b.postcode as business_postcode,
           b.country,
           b.phone as business_phone,
           b.smtp_host,
           b.smtp_port,
           b.smtp_username,
           b.smtp_password,
           b.from_email,
           b.from_name
    FROM invoices i 
    JOIN customers c ON i.customer_id = c.id 
    JOIN business_settings b ON b.id = 1
    WHERE i.id = ?
");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    set_flash_message('error', 'Invoice not found');
    header('Location: invoices.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get invoice items
        $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
        $stmt->execute([$invoice_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate PDF
        require_once 'invoice_pdf.php';
        $pdf_content = generate_invoice_pdf($invoice, $items, true);

        $to = $_POST['email'];
        $subject = $_POST['subject'];
        $message = $_POST['message'];

        // Setup PHPMailer
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $invoice['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $invoice['smtp_username'];
        $mail->Password = $invoice['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 60;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ]
        ];

        // Email content
        $mail->setFrom($invoice['from_email'], $invoice['from_name']);
        $mail->addAddress($to);
        $mail->addReplyTo($invoice['from_email'], $invoice['from_name']);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = nl2br(htmlspecialchars($message));
        $mail->AltBody = $message;

        // Attach PDF
        $mail->addStringAttachment(
            $pdf_content,
            "Invoice-" . $invoice['invoice_number'] . ".pdf",
            'base64',
            'application/pdf'
        );

        $mail->send();

        // Update invoice status
        $stmt = $pdo->prepare("UPDATE invoices SET updated_at = NOW(), status = 'sent' WHERE id = ?");
        $stmt->execute([$invoice_id]);

        set_flash_message('success', 'Invoice ' . $invoice['invoice_number'] . ' sent successfully to ' . $to);
        header('Location: invoice_view.php?id=' . $invoice_id);
        exit;

    } catch (Exception $e) {
        set_flash_message('error', 'Failed to send invoice: ' . $e->getMessage());
        error_log("Invoice sending error - Invoice ID: $invoice_id - Error: " . $e->getMessage());
        header('Location: invoice_send.php?id=' . $invoice_id);
        exit;
    }
}

$page_title = 'Send Invoice ' . $invoice['invoice_number'];
require_once 'includes/header.php';
require_once 'includes/menu.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1 class="mb-4">Send Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="invoice_send.php?id=<?php echo $invoice_id; ?>">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">

                        <div class="mb-3">
                            <label for="from_email" class="form-label">From Email</label>
                            <input type="email" class="form-control" id="from_email" name="from_email"
                                value="<?php echo htmlspecialchars($invoice['from_email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Send To</label>
                            <input type="email" class="form-control" id="email" name="email"
                                value="<?php echo htmlspecialchars($invoice['client_email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject"
                                value="Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> from <?php echo htmlspecialchars($invoice['business_name']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required>Dear <?php echo htmlspecialchars($invoice['client_contact']); ?>,

Please find attached invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?> for <?php echo format_currency($invoice['total']); ?>.

Payment is due by: <?php echo format_date($invoice['due_date']); ?>

Thank you for your business!

Best regards,
<?php echo htmlspecialchars($invoice['business_name']); ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="invoice_view.php?id=<?php echo $invoice_id; ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="generate_pdf" class="btn btn-info">Preview PDF</button>
                            <button type="submit" class="btn btn-primary">Send Invoice</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>