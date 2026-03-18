<?php
require_once('lib/phpmailer/class.phpmailer.php');
require_once('lib/phpmailer/class.smtp.php');

require_once(__DIR__ . '/../lib/phpmailer/src/Exception.php');
require_once(__DIR__ . '/../lib/phpmailer/src/PHPMailer.php');
require_once(__DIR__ . '/../lib/phpmailer/src/SMTP.php');

// Update namespace usage
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function send_invoice_email($invoice_id, $pdf_path) {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, 
            DB_USER, 
            DB_PASS
        );

function send_invoice_email($invoice_id, $pdf_path)
{
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );

        // Get invoice and settings data
        $stmt = $pdo->prepare("
            SELECT i.*, c.*, b.*
            FROM invoices i
            JOIN customers c ON i.customer_id = c.id
            JOIN business_settings b ON i.user_id = b.user_id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoice_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            throw new Exception('Invoice not found');
        }

        $mail = new PHPMailer(true);

        // SMTP Configuration
        if (!empty($data['smtp_host'])) {
            $mail->isSMTP();
            $mail->Host = $data['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $data['smtp_username'];
            $mail->Password = $data['smtp_password'];
            $mail->SMTPSecure = 'tls';
            $mail->Port = $data['smtp_port'];
        }

        // Email Settings
        $mail->setFrom($data['from_email'], $data['from_name']);
        $mail->addAddress($data['email'], $data['contact_name']);
        $mail->Subject = "Invoice {$data['invoice_number']} from {$data['company_name']}";

        // Email Body
        $body = "<p>Dear {$data['contact_name']},</p>";
        $body .= "<p>Please find attached invoice {$data['invoice_number']} for {$data['company_name']}.</p>";
        $body .= "<p>Invoice Details:<br>";
        $body .= "Amount: £" . number_format($data['total_amount'], 2) . "<br>";
        $body .= "Due Date: " . date('d/m/Y', strtotime($data['due_date'])) . "</p>";

        if (!empty($data['payment_instructions'])) {
            $body .= "<p>Payment Instructions:<br>";
            $body .= nl2br(htmlspecialchars($data['payment_instructions'])) . "</p>";
        }

        $body .= "<p>Thank you for your business.</p>";
        $body .= "<p>Best regards,<br>{$data['company_name']}</p>";

        $mail->isHTML(true);
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

        // Attach PDF
        $mail->addAttachment($pdf_path, "Invoice-{$data['invoice_number']}.pdf");

        // Send email
        if (!$mail->send()) {
            throw new Exception('Email could not be sent. Mailer Error: ' . $mail->ErrorInfo);
        }

        // Update invoice status
        $stmt = $pdo->prepare("UPDATE invoices SET status = 'sent', sent_date = NOW() WHERE id = ?");
        $stmt->execute([$invoice_id]);

        return true;

    } catch (Exception $e) {
        error_log('Email Sending Error: ' . $e->getMessage());
        throw new Exception('Failed to send email: ' . $e->getMessage());
    }
}