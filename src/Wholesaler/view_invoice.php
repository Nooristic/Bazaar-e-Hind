<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_GET['invoice_id'])) {
    die("Invoice ID missing");
}

$invoice_id = (int) $_GET['invoice_id'];
$wholesaler_id = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}

/* Fetch invoice with ownership check */
$stmt = $conn->prepare("
    SELECT 
        i.invoice_id,
        i.invoice_amount,
        i.status AS invoice_status,
        i.created_at,
        o.order_id,
        o.order_date,
        u.company_name AS manufacturer_name
    FROM invoices i
    JOIN orders o ON o.order_id = i.order_id
    JOIN users u ON u.user_id = i.manufacturer_id
    WHERE i.invoice_id = ?
      AND i.wholesaler_id = ?
");

$stmt->bind_param("ii", $invoice_id, $wholesaler_id);
$stmt->execute();
$result = $stmt->get_result();
$invoice = $result->fetch_assoc();

if (!$invoice) {
    die("Invoice not found or unauthorized");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice #<?= $invoice['invoice_id'] ?></title>
<style>
body {
  font-family: Georgia, serif;
  background: #f8f1e9;
  padding: 2rem;
  color: #4a2c1a;
}
.invoice-box {
  max-width: 700px;
  margin: auto;
  background: white;
  padding: 2rem;
  border: 2px solid #e0c68c;
  border-radius: 10px;
}
h1 {
  text-align: center;
}
.row {
  margin-bottom: 1rem;
}
.label {
  font-weight: bold;
}
</style>
</head>

<body>

<div class="invoice-box">
  <h1>Invoice</h1>

  <div class="row">
    <span class="label">Invoice ID:</span>
    INV-<?= $invoice['invoice_id'] ?>
  </div>

  <div class="row">
    <span class="label">Order ID:</span>
    #<?= $invoice['order_id'] ?>
  </div>

  <div class="row">
    <span class="label">Manufacturer:</span>
    <?= htmlspecialchars($invoice['manufacturer_name']) ?>
  </div>

  <div class="row">
    <span class="label">Invoice Date:</span>
    <?= date('Y-m-d', strtotime($invoice['created_at'])) ?>
  </div>

  <div class="row">
    <span class="label">Amount:</span>
    ₹<?= number_format($invoice['invoice_amount'], 2) ?>
  </div>

  <div class="row">
    <span class="label">Status:</span>
    <?= ucfirst($invoice['invoice_status']) ?>
  </div>
</div>

</body>
</html>
