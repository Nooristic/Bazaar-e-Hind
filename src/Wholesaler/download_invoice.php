<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    exit("Unauthorized");
}

require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;

$invoice_id = (int)($_GET['id'] ?? 0);
$wholesaler_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");

/* Fetch invoice + order */
$invoice = $conn->query("
  SELECT 
    i.invoice_id,
    i.invoice_amount,
    i.created_at,
    o.order_id,
    u.company_name AS manufacturer
  FROM invoices i
  JOIN orders o ON o.order_id = i.order_id
  JOIN users u ON u.user_id = i.manufacturer_id
  WHERE i.invoice_id = $invoice_id
    AND i.wholesaler_id = $wholesaler_id
")->fetch_assoc();

if (!$invoice) {
    exit("Invalid invoice");
}

/* Invoice HTML */
$html = "
<h2>Invoice #INV-{$invoice['invoice_id']}</h2>
<p><strong>Order ID:</strong> {$invoice['order_id']}</p>
<p><strong>Manufacturer:</strong> {$invoice['manufacturer']}</p>
<p><strong>Invoice Date:</strong> {$invoice['created_at']}</p>
<hr>
<h3>Total Amount: ₹".number_format($invoice['invoice_amount'],2)."</h3>
";

/* Generate PDF */
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4');
$dompdf->render();

/* Download */
$dompdf->stream("Invoice_{$invoice_id}.pdf", ["Attachment" => true]);
