<?php
session_start();

/* ================= SECURITY ================= */
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    die("Unauthorized");
}

if (!isset($_POST['invoice_id'])) {
    die("Invoice ID missing");
}

$invoice_id    = (int) $_POST['invoice_id'];
$wholesaler_id = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}
$conn->set_charset("utf8mb4");

/* ================= FETCH + VALIDATE INVOICE ================= */
$stmt = $conn->prepare("
    SELECT
        i.invoice_id,
        i.invoice_amount,
        i.status AS invoice_status,
        o.status AS order_status
    FROM invoices i
    JOIN orders o ON o.order_id = i.order_id
    WHERE i.invoice_id = ?
      AND i.wholesaler_id = ?
");
$stmt->bind_param("ii", $invoice_id, $wholesaler_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ================= BUSINESS RULE VALIDATION ================= */
if (
    !$invoice ||
    $invoice['invoice_status'] !== 'unpaid' ||
    !in_array($invoice['order_status'], ['confirmed', 'in_production', 'dispatched'])
) {
    die("Payment not allowed");
}

$amount = (float) $invoice['invoice_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Make Payment</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
:root {
  --bazaar-bg: rgba(255,245,235,0.94);
  --gold: #c7a76c;
  --text: #3e2723;
}

body {
  margin: 0;
  font-family: Georgia, serif;
  color: var(--text);
}

#bg-video {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: -2;
}

body::before {
  content:'';
  position: fixed;
  inset: 0;
  background: rgba(62,20,6,0.5);
  z-index: -1;
}

.header {
  background: var(--bazaar-bg);
  padding: 18px;
  border-bottom: 2px solid #e0c68c;
  text-align: center;
  position: relative;
}

.header a {
  position: absolute;
  left: 24px;
  text-decoration: none;
  font-weight: bold;
  color: var(--text);
}

.payment-container {
  max-width: 520px;
  margin: 60px auto;
  background: var(--bazaar-bg);
  border-radius: 18px;
  border: 2px solid #e0c68c;
  padding: 30px;
  box-shadow: 0 12px 35px rgba(0,0,0,0.2);
}

.payment-container h2 {
  text-align: center;
  color: #6d4c1e;
}

.invoice-summary {
  background: #fff3dc;
  border-radius: 14px;
  padding: 18px;
  margin: 20px 0;
}

.payment-methods {
  display: grid;
  gap: 14px;
}

.payment-methods button {
  padding: 14px;
  border-radius: 28px;
  border: 2px solid var(--gold);
  background: #ffe8b8;
  font-weight: bold;
  cursor: pointer;
  transition: all .25s ease;
}

.payment-methods button:hover {
  background: var(--gold);
  color: #fff;
  transform: translateY(-2px);
}

.note {
  margin-top: 16px;
  font-size: .85rem;
  text-align: center;
  opacity: .85;
}
</style>
</head>

<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<header class="header">
  <a href="payment_management.php">← Back</a>
  <strong>Secure Payment</strong>
</header>

<div class="payment-container">
  <h2>Complete Your Payment</h2>

  <div class="invoice-summary">
    <p><strong>Invoice ID:</strong> INV-<?= $invoice_id ?></p>
    <p><strong>Amount Payable:</strong> ₹<?= number_format($amount, 2) ?></p>
  </div>

  <form method="post" action="process_payment.php">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <input type="hidden" name="amount" value="<?= $amount ?>">

    <div class="payment-methods">
      <button type="submit" name="method" value="upi">Pay via UPI</button>
      <button type="submit" name="method" value="netbanking">Net Banking</button>
      <button type="submit" name="method" value="card">Credit / Debit Card</button>
    </div>
  </form>

  <div class="note">
    This is a simulated payment. No real transaction will occur.
  </div>
</div>

</body>
</html>
