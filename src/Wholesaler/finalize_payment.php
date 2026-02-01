<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    die("Unauthorized");
}

if (!isset($_POST['invoice_id'], $_POST['amount'], $_POST['method'])) {
    die("Invalid request");
}

require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;

$invoice_id    = (int) $_POST['invoice_id'];
$requested     = (float) $_POST['amount'];
$method        = trim($_POST['method']);
$wholesaler_id = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) die("DB connection failed");
$conn->set_charset("utf8mb4");

/* ================= TRANSACTION ================= */
$conn->begin_transaction();

try {

    /* ================= FETCH + LOCK INVOICE + ORDER ================= */
    $stmt = $conn->prepare("
        SELECT 
            i.invoice_id,
            i.invoice_amount,
            i.amount_paid,
            i.status AS invoice_status,
            i.order_id,
            o.status AS order_status,
            i.manufacturer_id,
            w.company_name AS wholesaler_name,
            m.company_name AS manufacturer_name
        FROM invoices i
        JOIN orders o ON o.order_id = i.order_id
        JOIN users w ON w.user_id = i.wholesaler_id
        JOIN users m ON m.user_id = i.manufacturer_id
        WHERE i.invoice_id = ?
          AND i.wholesaler_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $invoice_id, $wholesaler_id);
    $stmt->execute();
    $invoice = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$invoice) {
        throw new Exception("Invalid invoice");
    }

    if (!in_array($invoice['invoice_status'], ['unpaid', 'partial'])) {
        throw new Exception("Invoice already settled");
    }

    if (!in_array($invoice['order_status'], ['confirmed','in_production'])) {
        throw new Exception("Payment not allowed at this stage");
    }

    /* ================= PAYMENT CALCULATIONS ================= */
    $remaining   = max(0, $invoice['invoice_amount'] - $invoice['amount_paid']);
    $applied     = min($requested, $remaining);
    $credit      = max(0, $requested - $remaining);
    $newPaid     = $invoice['amount_paid'] + $applied;

    if ($applied <= 0 && $credit <= 0) {
        throw new Exception("Invalid payment amount");
    }

    if ($newPaid >= $invoice['invoice_amount']) {
        $newInvoiceStatus = 'paid';
    } else {
        $newInvoiceStatus = 'partial';
    }

    /* ================= INSERT PAYMENT ================= */
    $pay = $conn->prepare("
        INSERT INTO payments (
            invoice_id,
            order_id,
            wholesaler_id,
            manufacturer_id,
            amount,
            credit_amount,
            method,
            status,
            paid_at
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
    ");
    $pay->bind_param(
        "iiiidds",
        $invoice_id,
        $invoice['order_id'],
        $wholesaler_id,
        $invoice['manufacturer_id'],
        $applied,
        $credit,
        $method
    );
    $pay->execute();
    $payment_id = $conn->insert_id;
    $pay->close();

    /* ================= UPDATE INVOICE ================= */
    $updInv = $conn->prepare("
        UPDATE invoices
        SET amount_paid = ?, status = ?
        WHERE invoice_id = ?
    ");
    $updInv->bind_param("dsi", $newPaid, $newInvoiceStatus, $invoice_id);
    $updInv->execute();
    $updInv->close();

    /* ================= STEP 5: MOVE ORDER ON FIRST PAYMENT ================= */
    if ($invoice['order_status'] === 'confirmed') {
        $updOrder = $conn->prepare("
            UPDATE orders
            SET status = 'in_production'
            WHERE order_id = ?
        ");
        $updOrder->bind_param("i", $invoice['order_id']);
        $updOrder->execute();
        $updOrder->close();
    }

    /* ================= CREATE RECEIPT ================= */
    $rec = $conn->prepare("
        INSERT INTO receipts (payment_id, generated_by, created_at)
        VALUES (?, ?, NOW())
    ");
    $rec->bind_param("ii", $payment_id, $wholesaler_id);
    $rec->execute();
    $receipt_id = $conn->insert_id;
    $rec->close();

    /* ================= GENERATE RECEIPT PDF ================= */
    $dompdf = new Dompdf(['isRemoteEnabled' => true]);

    $html = "
    <h2 style='text-align:center'>PAYMENT RECEIPT</h2>
    <p><strong>Receipt:</strong> RCP-$receipt_id</p>
    <p><strong>Date:</strong> ".date('Y-m-d')."</p>
    <hr>
    <p><strong>Wholesaler:</strong> {$invoice['wholesaler_name']}</p>
    <p><strong>Manufacturer:</strong> {$invoice['manufacturer_name']}</p>
    <hr>
    <p><strong>Invoice:</strong> INV-$invoice_id</p>
    <p><strong>Amount Applied:</strong> ₹".number_format($applied,2)."</p>
    <p><strong>Credit Generated:</strong> ₹".number_format($credit,2)."</p>
    <p><strong>Total Paid:</strong> ₹".number_format($newPaid,2)."</p>
    <p><strong>Invoice Status:</strong> ".strtoupper($newInvoiceStatus)."</p>
    ";

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();

    $dir = $_SERVER['DOCUMENT_ROOT'].'/trial_project/receipts_pdf';
    if (!is_dir($dir)) mkdir($dir,0777,true);
    file_put_contents("$dir/receipt_$receipt_id.pdf", $dompdf->output());

    /* ================= COMMIT ================= */
    $conn->commit();

    header("Location: payment_management.php?msg=payment_success");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Payment failed: ".$e->getMessage());
}
