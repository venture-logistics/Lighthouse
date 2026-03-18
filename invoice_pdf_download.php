<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_login();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('Invalid invoice ID');
}

$invoice_id = (int) $_GET['id'];

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS
    );

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
               b.phone as business_phone
        FROM invoices i 
        JOIN customers c ON i.customer_id = c.id 
        JOIN business_settings b ON b.id = 1
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        die('Invoice not found');
    }

    // Get invoice items
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
    $stmt->execute([$invoice_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate and output PDF
    require_once 'invoice_pdf.php';
    $pdf_content = generate_invoice_pdf($invoice, $items);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Invoice-' . $invoice['invoice_number'] . '.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    echo $pdf_content;

} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}