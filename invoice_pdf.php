<?php
function generate_invoice_pdf($invoice, $items, $return_string = false)
{
    // First include required files and establish DB connection
    require_once 'includes/config.php';
    require_once 'includes/functions.php';

    try {
        // Create database connection
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

        // Get complete business and customer details
        $sql = "SELECT i.*, 
                c.company_name, c.contact_name, c.email, c.address, c.city, c.postcode,
                b.company_name as business_name, b.address_line1, b.address_line2, 
                b.city as business_city, b.county as business_county,
                b.postcode as business_postcode, b.phone as business_phone,
                b.company_number, b.vat_number, b.logo_path
                FROM invoices i
                JOIN customers c ON i.customer_id = c.id
                JOIN business_settings b ON b.id = 1
                WHERE i.id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$invoice['id']]);
        $invoice_details = $stmt->fetch(PDO::FETCH_ASSOC);

        // Merge the new details with existing invoice data
        $invoice = array_merge($invoice, $invoice_details);

        require_once 'lib/tcpdf/tcpdf.php';

        // Create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($invoice['business_name']);
        $pdf->SetTitle('Invoice ' . $invoice['invoice_number']);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Add logo if exists
        if (!empty($invoice['logo_path']) && file_exists($invoice['logo_path'])) {
            $logo_width = 20;
            $pdf->Image($invoice['logo_path'], 15, 15, $logo_width);
            $pdf->Ln(10);
        }

        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, $invoice['business_name'], 0, 1, 'R');
        $pdf->SetFont('helvetica', '', 10);

        $pdf->Cell(0, 6, $invoice['address_line1'], 0, 1, 'R');
        if (!empty($invoice['address_line2'])) {
            $pdf->Cell(0, 6, $invoice['address_line2'], 0, 1, 'R');
        }
        $pdf->Cell(0, 6, $invoice['business_city'], 0, 1, 'R');
        $pdf->Cell(0, 6, $invoice['business_postcode'], 0, 1, 'R');

        if (!empty($invoice['business_phone'])) {
            $pdf->Cell(0, 6, 'Phone: ' . $invoice['business_phone'], 0, 1, 'R');
        }
        if (!empty($invoice['company_number'])) {
            $pdf->Cell(0, 6, 'Company No: ' . $invoice['company_number'], 0, 1, 'R');
        }
        if (!empty($invoice['vat_number']) && $invoice['vat_number'] !== '') {
            $pdf->Cell(0, 6, 'VAT No: ' . $invoice['vat_number'], 0, 1, 'R');
        }
        $pdf->Ln(10);

        // Invoice details (left side)
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->Cell(0, 15, 'INVOICE', 0, 1, 'L');

        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'Invoice Number: ' . $invoice['invoice_number'], 0, 1);
        $pdf->Cell(0, 6, 'Invoice Date: ' . date('d/m/Y', strtotime($invoice['invoice_date'])), 0, 1);
        $pdf->Cell(0, 6, 'Due Date: ' . date('d/m/Y', strtotime($invoice['due_date'])), 0, 1);

        $pdf->Ln(10);

        // Bill to
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Bill To:', 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $invoice['company_name'], 0, 1);
        $pdf->Cell(0, 6, $invoice['contact_name'], 0, 1);
        $pdf->MultiCell(0, 6, $invoice['address'], 0, 'L');
        $pdf->Cell(0, 6, $invoice['city'], 0, 1);
        $pdf->Cell(0, 6, $invoice['postcode'], 0, 1);

        $pdf->Ln(10);

        // Items table
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(90, 8, 'Description', 1, 0, 'L', true);
        $pdf->Cell(25, 8, 'Quantity', 1, 0, 'R', true);
        $pdf->Cell(35, 8, 'Unit Price', 1, 0, 'R', true);
        $pdf->Cell(35, 8, 'Amount', 1, 1, 'R', true);

        $pdf->SetFont('helvetica', '', 10);
        foreach ($items as $item) {
            $pdf->MultiCell(90, 8, $item['description'], 1, 'L', false, 0);
            $pdf->Cell(25, 8, format_money($item['quantity']), 1, 0, 'R');
            $pdf->Cell(35, 8, '£' . format_money($item['unit_price']), 1, 0, 'R');
            $pdf->Cell(35, 8, '£' . format_money($item['amount']), 1, 1, 'R');
        }

        // Totals
        $pdf->Ln(5);
        $pdf->Cell(150, 6, 'Subtotal:', 0, 0, 'R');
        $pdf->Cell(35, 6, '£' . format_money($invoice['subtotal']), 0, 1, 'R');

        if ($invoice['tax_rate'] > 0) {
            $pdf->Cell(150, 6, 'VAT (' . $invoice['tax_rate'] . '%):', 0, 0, 'R');
            $pdf->Cell(35, 6, '£' . format_money($invoice['tax_amount']), 0, 1, 'R');
        }

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(150, 6, 'Total:', 0, 0, 'R');
        $pdf->Cell(35, 6, '£' . format_money($invoice['total']), 0, 1, 'R');

        // Notes
        if (!empty($invoice['notes'])) {
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->Cell(0, 6, 'Notes:', 0, 1);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->MultiCell(0, 6, $invoice['notes'], 0, 'L');
        }

        // Modified output section
        if ($return_string) {
            return $pdf->Output('', 'S');
        } else {
            return $pdf->Output('Invoice-' . $invoice['invoice_number'] . '.pdf', 'I');
        }

    } catch (Exception $e) {
        throw new Exception('PDF Generation Error: ' . $e->getMessage());
    }
} // End of function

// Only run this if the script is called directly (not included)
if (!defined('INCLUDED_FROM_ANOTHER_SCRIPT')) {
    require_once 'includes/config.php';
    require_once 'includes/functions.php';
    require_login();

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        set_message('Invalid invoice ID', 'danger');
        header('Location: invoices.php');
        exit();
    }

    $invoice_id = $_GET['id'];

    try {
        // Database connection
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get invoice details
        $sql = "SELECT i.*, 
                c.company_name, c.contact_name, c.email, c.address, c.city, c.postcode,
                b.company_name as business_name, b.address_line1, b.address_line2, 
                b.city as business_city, b.county as business_county,
                b.postcode as business_postcode, b.phone as business_phone,
                b.company_number, b.vat_number, b.logo_path
                FROM invoices i
                JOIN customers c ON i.customer_id = c.id
                JOIN business_settings b ON b.id = 1
                WHERE i.id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            throw new Exception('Invoice not found');
        }

        // Get invoice items
        $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id");
        $stmt->execute([$invoice_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate and output PDF
        generate_invoice_pdf($invoice, $items, false);

    } catch (Exception $e) {
        set_message('Error generating PDF: ' . $e->getMessage(), 'danger');
        header('Location: invoices.php');
        exit();
    }
}