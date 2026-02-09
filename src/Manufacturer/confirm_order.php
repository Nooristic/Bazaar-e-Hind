<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    die("Unauthorized");
}

if (!isset($_POST['order_id'])) {
    die("Order ID missing");
}

$manufacturer_id = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}
$conn->set_charset("utf8mb4");

/* ================= TRANSACTION ================= */
$conn->begin_transaction();

try {

    $order_id = (int) $_POST['order_id'];

    /* ================= FETCH + LOCK ORDER ================= */
    $stmt = $conn->prepare("
        SELECT 
            order_id,
            wholesaler_id,
            manufacturer_id,
            status
        FROM orders
        WHERE order_id = ?
          AND manufacturer_id = ?
        FOR UPDATE
    ");
    $stmt->bind_param("ii", $order_id, $manufacturer_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || $order['status'] !== 'ordered') {
        throw new Exception("Invalid order or already confirmed");
    }

    /* ================= CALCULATE TOTAL FROM ITEMS ================= */
    $total = 0;

    $items = $conn->prepare("
        SELECT quantity, price_at_purchase
        FROM order_items
        WHERE order_id = ?
    ");
    $items->bind_param("i", $order_id);
    $items->execute();
    $res = $items->get_result();

    while ($row = $res->fetch_assoc()) {
        $total += $row['quantity'] * $row['price_at_purchase'];
    }
    $items->close();

    if ($total <= 0) {
        throw new Exception("Invalid order total");
    }

    /* ================= CONFIRM ORDER ================= */
    $update = $conn->prepare("
        UPDATE orders
        SET status = 'confirmed',
            total_amount = ?
        WHERE order_id = ?
    ");
    $update->bind_param("di", $total, $order_id);
    $update->execute();
    $update->close();

    /* ================= CHECK EXISTING INVOICE ================= */
    $check = $conn->prepare("
        SELECT invoice_id
        FROM invoices
        WHERE order_id = ?
    ");
    $check->bind_param("i", $order_id);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    if ($exists) {
    // ✅ Invoice already exists, reuse it
    $invoice_id = $exists['invoice_id'];
}

    $check->close();

    /* ================= CREATE INVOICE ================= */
    /* ================= CREATE INVOICE ================= */
/* ================= CREATE INVOICE ================= */
if (!$exists) {
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

    // ✅ USE $total (calculated), NOT $order['total_amount']
    $insert->bind_param(
        "iiid",
        $order['order_id'],
        $order['wholesaler_id'],
        $order['manufacturer_id'],
        $total
    );

    $insert->execute();
    $invoice_id = $conn->insert_id;
    $insert->close();
}
    /* ================= COMMIT ================= */
    $conn->commit();

   header("Location: /trial_project/src/Manufacturer/order_manufacturer.php?invoice_id=$invoice_id");
   exit();


} catch (Exception $e) {
    $conn->rollback();
    die("Error: " . $e->getMessage());
}
