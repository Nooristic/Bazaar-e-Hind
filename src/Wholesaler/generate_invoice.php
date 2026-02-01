<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_POST['order_id'])) {
    die("Order ID missing");
}

$wholesaler_id = $_SESSION['user_id'];
$order_id = (int) $_POST['order_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}

/* Fetch order */
$order = $conn->query("
  SELECT manufacturer_id, total_amount
  FROM orders
  WHERE order_id = $order_id
    AND wholesaler_id = $wholesaler_id
")->fetch_assoc();

if (!$order) {
    die("Order not found or unauthorized");
}

/* Prevent duplicate invoice */
$check = $conn->query("
  SELECT invoice_id FROM invoices WHERE order_id = $order_id
");

if ($check->num_rows > 0) {
    header("Location: payment_management.php?msg=invoice_exists");
    exit();
}

/* Insert invoice */
$stmt = $conn->prepare("
  INSERT INTO invoices
  (order_id, wholesaler_id, manufacturer_id, invoice_amount)
  VALUES (?, ?, ?, ?)
");

$stmt->bind_param(
    "iiid",
    $order_id,
    $wholesaler_id,
    $order['manufacturer_id'],
    $order['total_amount']
);

$stmt->execute();

header("Location: payment_management.php?msg=invoice_created");
exit();
