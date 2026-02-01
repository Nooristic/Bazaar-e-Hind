<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    die("Unauthorized");
}

$wholesaler_id = $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) die("DB Error");
$conn->set_charset("utf8mb4");


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* ===== BAZAAR THEME ===== */
:root {
  --bazaar-bg: rgba(255, 245, 235, 0.92);
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
  z-index: -1;
}

.header {
  background: var(--bazaar-bg);
  border-bottom: 2px solid #e0c68c;
  padding: 18px;
  text-align: center;
  position: relative;
}

.back-link {
  position: absolute;
  left: 24px;
  top: 20px;
  text-decoration: none;
  font-weight: bold;
  color: var(--text);
}

.container {
  padding: 2rem;
}

.section {
  background: var(--bazaar-bg);
  border: 2px solid #e0c68c;
  border-radius: 12px;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.section h2 {
  margin-top: 0;
  color: #6d4c1e;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 1rem;
  border-bottom: 1px solid #e0c68c;
}

th {
  background: #f3e2c3;
}

.make-payment {
  padding: 8px 18px;
  border-radius: 20px;
  border: none;
  background: #ffecb3;
  font-weight: bold;
  cursor: pointer;
}
</style>
</head>

<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<header class="header">
  <a href="home_page.php" class="back-link">← Home</a>
  <h1>Payment Management</h1>
</header>

<div class="container">
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'payment_success'): ?>
  <div id="payment-success" style="
    background:#d4edda;
    color:#155724;
    border:2px solid #c3e6cb;
    padding:14px 20px;
    border-radius:12px;
    margin-bottom:20px;
    font-weight:bold;
    text-align:center;
    transition: opacity 0.5s ease;
  ">
    ✅ Payment successfully completed
  </div>
<?php endif; ?>
<!-- ================= PENDING INVOICES ================= -->
<div class="section">
  <h2>Pending Invoices</h2>

  <table>
    <tr>
      <th>Invoice ID</th>
      <th>Order ID</th>
      <th>Amount</th>
      <th>Status</th>
      <th>Action</th>
    </tr>

<?php
$invoices = $conn->query("
  SELECT invoice_id, order_id, invoice_amount
  FROM invoices
  WHERE wholesaler_id = $wholesaler_id
    AND status = 'unpaid'
  ORDER BY created_at ASC
");

if ($invoices->num_rows === 0) {
    echo "<tr><td colspan='5'>No unpaid invoices</td></tr>";
}

while ($inv = $invoices->fetch_assoc()):
?>
<tr>
  <td>INV-<?= $inv['invoice_id'] ?></td>
  <td>#<?= $inv['order_id'] ?></td>
  <td>₹<?= number_format($inv['invoice_amount']) ?></td>
  <td>Unpaid</td>
  <td>
    <form method="post" action="make_payment.php">
      <input type="hidden" name="invoice_id" value="<?= $inv['invoice_id'] ?>">
      <button class="make-payment">Pay Now</button>
    </form>
    <a href="/trial_project/invoices_pdf/invoice_<?= $inv['invoice_id'] ?>.pdf"
   target="_blank">
   Download Invoice
</a>

  </td>
  
</tr>
<?php endwhile; ?>
  </table>
</div>

<!-- ================= PAYMENT HISTORY ================= -->
<div class="section">
  <h2>Payment History</h2>

  <table class="payment-history-table">
<tr>
  <th>Payment ID</th>
  <th>Date</th>
  <th>Paid To</th>
  <th>Amount</th>
  <th>Mode</th>
  <th>Type</th>
  <th>Pending Dues</th>
  <th>Receipt</th>
</tr>

<?php
$stmt = $conn->prepare("
  SELECT 
    p.payment_id,
    p.paid_at,
    p.amount,
    p.method,
    p.status,
    p.manufacturer_id,
    u.company_name AS manufacturer_name,
    w.company_name AS wholesaler_name,
    i.invoice_amount,
    r.receipt_id
  FROM payments p
  JOIN users u ON u.user_id = p.manufacturer_id
  JOIN users w ON w.user_id = p.wholesaler_id
  LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
  LEFT JOIN receipts r ON r.payment_id = p.payment_id
  WHERE p.wholesaler_id = ?
  ORDER BY p.paid_at DESC
");
$stmt->bind_param("i", $wholesaler_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='8'>No payments found</td></tr>";
}

while ($p = $result->fetch_assoc()):

  $paymentType = 'Normal';
  $pendingDues = 0;

  if ($p['invoice_amount'] !== null) {
      if ($p['amount'] > $p['invoice_amount']) {
          $paymentType = 'Credit';
      }
      $pendingDues = max(0, $p['invoice_amount'] - $p['amount']);
  }
?>
<tr>
  <td>P<?= $p['payment_id'] ?></td>
  <td><?= date('Y-m-d', strtotime($p['paid_at'])) ?></td>
  <td>
    <?= htmlspecialchars($p['manufacturer_name']) ?><br>
    <small>ID: <?= $p['manufacturer_id'] ?></small>
  </td>
  <td>₹<?= number_format($p['amount'], 2) ?></td>
  <td><?= strtoupper($p['method']) ?></td>
  <td><?= $paymentType ?></td>
  <td>₹<?= number_format($pendingDues, 2) ?></td>
  <td>
    <?php if ($p['receipt_id'] && $p['status'] === 'completed'): ?>
      <a href="/trial_project/receipts_pdf/receipt_<?= $p['receipt_id'] ?>.pdf" target="_blank">
        Download
      </a>
    <?php else: ?>
      —
    <?php endif; ?>
  </td>
</tr>
<?php endwhile; ?>
</table>
</div>

</div>

<footer class="header">
  &copy; 2025 Fabric Bazaar. All rights reserved.
</footer>
<script>
  const successBox = document.getElementById('payment-success');
  if (successBox) {
    setTimeout(() => {
      successBox.style.opacity = '0';
      setTimeout(() => successBox.remove(), 500);
    }, 3000);
  }
</script>
</body>
</html>
