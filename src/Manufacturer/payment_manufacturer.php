<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$manufacturer_id = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bazaar-e-Hind – Manufacturer Payment Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body { font-family: Georgia, serif; color:#3b1f10; margin:0; }
#bg-video { position:fixed; inset:0; width:100%; height:100%; object-fit:cover; z-index:-2; }
body::before { content:''; position:fixed; inset:0; background:rgba(62,20,6,0.45); z-index:-1; }

.header {
  background:#fff4e6;
  padding:20px;
  border-bottom:6px solid #3e1406;
  display:flex;
  align-items:center;
}
.back-link {
  text-decoration:none;
  font-weight:bold;
  color:#8a5b22;
  margin-right:20px;
}
.header-title {
  font-size:28px;
  font-weight:700;
}

.container {
  max-width:1200px;
  margin:30px auto;
  padding:20px;
  background:#fff9ef;
  border-radius:12px;
  border:2px solid #e0c68c;
}

h2 { margin-top:0; color:#6b3e23; }

table {
  width:100%;
  border-collapse:collapse;
}
th, td {
  padding:12px;
  border-bottom:1px solid #e0c68c;
  text-align:left;
}
th {
  background:#f3e2c3;
}

.status {
  padding:6px 12px;
  border-radius:12px;
  font-weight:bold;
  font-size:14px;
}
.status.completed { background:#ccefd0; color:#1c6a2e; }
.status.pending { background:#f5e3b8; color:#946400; }
.status.failed { background:#f6cfcf; color:#a61c1c; }

a.download {
  color:#6b3e23;
  font-weight:bold;
  text-decoration:none;
}
a.download:hover { text-decoration:underline; }
</style>
</head>

<body>

<video autoplay muted loop id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<header class="header">
  <a href="bazaar-homepage.php" class="back-link">← Home</a>
  <span class="header-title">Payment Management</span>
</header>

<div class="container">
<h2>Payments Received</h2>

<table>
<tr>
  <th>Payment ID</th>
  <th>Date</th>
  <th>Paid By (Wholesaler)</th>
  <th>Amount</th>
  <th>Mode</th>
  <th>Type</th>
  <th>Pending Dues</th>
  <th>Status</th>
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
    w.user_id AS wholesaler_id,
    w.company_name AS wholesaler_name,
    i.invoice_amount,
    r.receipt_id
  FROM payments p
  JOIN users w ON w.user_id = p.wholesaler_id
  LEFT JOIN invoices i ON i.invoice_id = p.invoice_id
  LEFT JOIN receipts r ON r.payment_id = p.payment_id
  WHERE p.manufacturer_id = ?
  ORDER BY p.paid_at DESC
");
$stmt->bind_param("i", $manufacturer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<tr><td colspan='9'>No payments received yet</td></tr>";
}

while ($row = $result->fetch_assoc()):

    $invoiceAmount = (float) ($row['invoice_amount'] ?? 0);
    $paymentAmount = (float) $row['amount'];

    $paymentType = ($paymentAmount > $invoiceAmount && $invoiceAmount > 0)
        ? 'Credit'
        : 'Normal';

    $pendingDues = max(0, $invoiceAmount - $paymentAmount);
?>
<tr>
  <td>P<?= $row['payment_id'] ?></td>
  <td><?= date('Y-m-d', strtotime($row['paid_at'])) ?></td>
  <td>
    <?= htmlspecialchars($row['wholesaler_name']) ?><br>
    <small>ID: <?= $row['wholesaler_id'] ?></small>
  </td>
  <td>₹<?= number_format($paymentAmount, 2) ?></td>
  <td><?= strtoupper($row['method']) ?></td>
  <td><?= $paymentType ?></td>
  <td>₹<?= number_format($pendingDues, 2) ?></td>
  <td>
    <span class="status <?= $row['status'] ?>">
      <?= ucfirst($row['status']) ?>
    </span>
  </td>
  <td>
    <?php if ($row['receipt_id'] && $row['status'] === 'completed'): ?>
      <a class="download"
         href="/trial_project/receipts_pdf/receipt_<?= $row['receipt_id'] ?>.pdf"
         target="_blank">
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

</body>
</html>
