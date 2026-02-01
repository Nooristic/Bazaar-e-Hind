<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    die("Unauthorized");
}

if (!isset($_POST['order_id'], $_POST['new_status'])) {
    die("Invalid request");
}

require_once __DIR__ . '/../../vendor/autoload.php';
use Dompdf\Dompdf;

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}

$order_id   = (int) $_POST['order_id'];
$new_status = trim($_POST['new_status']);
$manufacturer_id = (int) $_SESSION['user_id'];

/* ================= VALID TRANSITIONS ================= */
$allowedTransitions = [
    'ordered'       => ['confirmed'],
    'confirmed'     => ['in_production'],
    'in_production' => ['dispatched'],
    'dispatched'    => ['delivered']
];

$conn->begin_transaction();

try {

    /* ================= FETCH & LOCK ORDER ================= */
    $stmt = $conn->prepare("
        SELECT *
        FROM orders
        WHERE order_id = ?
          AND manufacturer_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $order_id, $manufacturer_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception("Order not found");
    }

    $current_status = $order['status'];

    if (
        !isset($allowedTransitions[$current_status]) ||
        !in_array($new_status, $allowedTransitions[$current_status])
    ) {
        throw new Exception("Invalid status transition");
    }

    /* ================= UPDATE ORDER STATUS ================= */
    $upd = $conn->prepare("
        UPDATE orders
        SET status = ?
        WHERE order_id = ?
    ");
    $upd->bind_param("si", $new_status, $order_id);
    $upd->execute();
    $upd->close();

    /* ======================================================
       CREATE INVOICE ONLY WHEN STATUS = CONFIRMED
       ====================================================== */
    if ($new_status === 'confirmed') {

        // Check existing invoice
        $check = $conn->prepare("
            SELECT invoice_id
            FROM invoices
            WHERE order_id = ?
            LIMIT 1
        ");
        $check->bind_param("i", $order_id);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$exists) {

            /* Insert invoice */
            $insert = $conn->prepare("
                INSERT INTO invoices (
                    order_id,
                    wholesaler_id,
                    manufacturer_id,
                    invoice_amount,
                    amount_paid,
                    status,
                    created_at
                )
                VALUES (?, ?, ?, ?, 0, 'unpaid', NOW())
            ");
            $insert->bind_param(
                "iiid",
                $order['order_id'],
                $order['wholesaler_id'],
                $order['manufacturer_id'],
                $order['total_amount']
            );
            $insert->execute();
            $invoice_id = $conn->insert_id;
            $insert->close();

            /* ================= GENERATE INVOICE PDF ================= */
            $dompdf = new Dompdf(['isRemoteEnabled' => true]);

            $html = "
            <style>
                body { font-family: DejaVu Sans, sans-serif; }
                h2 { text-align:center; }
            </style>

            <h2>INVOICE</h2>
            <hr>
            <p><strong>Invoice ID:</strong> INV-$invoice_id</p>
            <p><strong>Order ID:</strong> {$order['order_id']}</p>
            <p><strong>Date:</strong> ".date('Y-m-d')."</p>
            <hr>
            <p><strong>Total Amount:</strong> ₹".number_format($order['total_amount'],2)."</p>
            <p>Status: UNPAID</p>
            ";

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $pdfDir = $_SERVER['DOCUMENT_ROOT'] . '/trial_project/invoices_pdf';
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0777, true);
            }

            file_put_contents(
                $pdfDir . "/invoice_$invoice_id.pdf",
                $dompdf->output()
            );
        }
    }

    $conn->commit();

    header("Location: order_details.php?id=$order_id&msg=success");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}
