<?php
session_start();

// Security - only logged-in wholesalers can access this page
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.html");
    exit();
}

// Database connection
$servername = "localhost";
$username_db = "root";          // ← Change if needed
$password_db = "";              // ← Change if needed
$dbname = "bazaar_e_hind";      // ← Your database name

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Current logged-in wholesaler
$current_user_id = $_SESSION['user_id'] ?? 0;

// Fetch all orders placed by this wholesaler
$sql = "
    SELECT 
        o.order_id,
        o.order_date,
        u.company_name AS manufacturer_name,
        COUNT(oi.item_id) AS item_count,
        o.total_amount,
        o.status,
        i.invoice_id,
        i.status AS invoice_status
    FROM orders o
    JOIN users u ON o.manufacturer_id = u.user_id
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN invoices i ON i.order_id = o.order_id
    WHERE o.wholesaler_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
/* ================= DASHBOARD METRICS ================= */
$total_orders = count($orders);

$manufacturers = [];
$accepted_orders = 0;
$rejected_orders = 0;

foreach ($orders as $o) {
    $manufacturers[$o['manufacturer_name']] = true;

    $status = strtolower($o['status']);

    if (in_array($status, ['confirmed', 'in_production', 'dispatched', 'delivered'])) {
        $accepted_orders++;
    }

    if (in_array($status, ['rejected', 'cancelled'])) {
        $rejected_orders++;
    }
}

$total_manufacturers = count($manufacturers);

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders Page</title>

<style>
/* ===== GENERAL ===== */
body {
  font-family: 'Georgia', serif;
  margin: 0;
  padding: 0;
  background: transparent;
  color: #5a3e2b;
}

/* ===== BACKGROUND VIDEO ===== */
#bg-video {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  object-fit: cover;
  z-index: -1;
  pointer-events: none;
  background: black;
}

/* ===== HEADER ===== */
.blue-header {
  background: rgba(255, 245, 235, 0.96); /* beige */
  color: #512d1a;
  padding: 1.4rem 1rem;
  text-align: center;
  position: relative;
  border-bottom: 2px solid #e0c68c;
}

.blue-header h1 {
  margin: 0;
  font-size: 2.2rem;
  letter-spacing: 0.5px;
}

.status-in_production { background-color: #007bff; }
.blue-header .back-link {
  color: #512d1a;
  text-decoration: none;
  font-size: 1.1rem;
  font-weight: bold;
  position: absolute;
  left: 1.2rem;
  top: 50%;
  transform: translateY(-50%);
}
/* ===== TABLE ===== */
.orders-table {
  width: 95%;
  margin: 2rem auto;
  border-collapse: collapse;
  background-color: rgba(255, 255, 255, 0.9);
  border-radius: 8px;
  overflow: hidden;
}

.orders-table th,
.orders-table td {
  padding: 1rem;
  border-bottom: 1px solid #ddd;
}

.orders-table th {
  background-color: #e0c68c;
  color: white;
  text-align: left;
}

.orders-table tr:hover {
  background-color: #f1f1f1;
}

/* ===== STATUS ===== */
.status-badge {
  display: inline-block;
  padding: 0.3rem 0.6rem;
  border-radius: 4px;
  font-size: 0.9rem;
  color: white;
}

.status-ordered { background-color: #ffc107; }
.status-confirmed { background-color: #28a745; }
.status-dispatched { background-color: #17a2b8; }
.status-delivered { background-color: #6c757d; }

/* ===== ACTIONS ===== */
.actions button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  margin: 0.3rem;
  font-family: inherit;
}

.view-invoice {
  background-color: #512d1a;
  color: rgb(226, 189, 164);
}

.pay-remaining {
  background-color: #d497695e;
  color: white;
}

.pay-remaining::after {
  content: " ₹";
}

/* ===== FOOTER ===== */
footer {
  background: rgba(255, 255, 255, 0.9);
  text-align: center;
  padding: 1rem;
  margin-top: 2rem;
}
.view-invoice {
  display: inline-block;
  padding: 0.5rem 1rem;
  background-color: #512d1a;
  color: rgb(226, 189, 164);
  border-radius: 4px;
  text-decoration: none;
}
/* ===== DASHBOARD ===== */
.dashboard {
  max-width: 95%;
  margin: 1.8rem auto 1rem;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1.2rem;
}

.dashboard-card {
  background: rgba(255,255,255,0.92);
  border-radius: 14px;
  padding: 1.4rem;
  text-align: center;
  border: 2px solid #e0c68c;
  box-shadow: 0 10px 26px rgba(0,0,0,.06);
}

.dashboard-card h2 {
  margin: 0;
  font-size: 2rem;
  color: #512d1a;
}

.dashboard-card p {
  margin-top: 6px;
  font-size: 0.95rem;
  font-weight: bold;
  letter-spacing: .3px;
}

.dashboard-card.accepted h2 { color: #2e7d32; }
.dashboard-card.rejected h2 { color: #c62828; }
.status-in_production { background-color: #0d6efd; }
.status-cancelled { background-color: #9e9e9e; }
.status-rejected { background-color: #c62828; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .orders-table thead {
    display: none;
  }

  .orders-table tr {
    display: block;
    margin-bottom: 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
  }

  .orders-table td {
    display: flex;
    justify-content: space-between;
  }

  .orders-table td::before {
    content: attr(data-label);
    font-weight: bold;
  }
}
</style>
</head>

<body>

<!-- ✅ BACKGROUND VIDEO -->
<video id="bg-video" autoplay muted loop playsinline>
  <source src="../../assests/silk video.mp4" type="video/mp4">
  Your browser does not support the video tag.
</video>

<header class="blue-header">
  <a href="home_page.php" class="back-link">← Home</a>
  <h1>Orders</h1>
</header>
<section class="dashboard">

  <div class="dashboard-card">
    <h2><?= $total_orders ?></h2>
    <p>Orders Placed</p>
  </div>

  <div class="dashboard-card">
    <h2><?= $total_manufacturers ?></h2>
    <p>Manufacturers</p>
  </div>

  <div class="dashboard-card accepted">
    <h2><?= $accepted_orders ?></h2>
    <p>Accepted Orders</p>
  </div>

  <div class="dashboard-card rejected">
    <h2><?= $rejected_orders ?></h2>
    <p>Rejected Orders</p>
  </div>

</section>
<table class="orders-table">
  <thead>
    <tr>
      <th>Order ID</th>
      <th>Date</th>
      <th>Manufacturer</th>
      <th>Items</th>
      <th>Total</th>
      <th>Status</th>
      <th>Actions</th>
    </tr>
  </thead>

  <tbody>
    <?php if (empty($orders)): ?>
      <tr>
        <td colspan="7" style="text-align:center; padding:2rem;">
          No orders found.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($orders as $order): ?>
        <tr>
          <td data-label="Order ID">#<?php echo htmlspecialchars($order['order_id']); ?></td>
          <td data-label="Date"><?php echo htmlspecialchars($order['order_date']); ?></td>
          <td data-label="Manufacturer"><?php echo htmlspecialchars($order['manufacturer_name']); ?></td>
          <td data-label="Items"><?php echo $order['item_count']; ?></td>
          <td data-label="Total">₹<?php echo number_format($order['total_amount'], 0); ?></td>
          <td data-label="Status">
            <?php
              $status = strtolower($order['status']);
              $class = 'status-' . $status;
              // You can map more status values if needed
             $displayStatus = ucwords(str_replace('_', ' ', $status));
            ?>
            <span class="status-badge <?php echo $class; ?>">
              <?php echo $displayStatus; ?>
            </span>
          </td>
          <td data-label="Actions" class="actions">

  <?php if (!empty($order['invoice_id'])): ?>
    <a class="view-invoice"
       href="view_invoice.php?invoice_id=<?php echo $order['invoice_id']; ?>">
      View Invoice
    </a>
  <?php endif; ?>

  <?php
    $status = strtolower($order['status']);
    $canPay = in_array($status, [
        'confirmed',
        'in_production',
        'dispatched'
    ]);

    if ($canPay && $order['invoice_status'] !== 'paid'):
  ?>
    <button class="pay-remaining"
            onclick="window.location.href='payment.php?invoice_id=<?php echo $order['invoice_id']; ?>'">
      Pay Remaining
    </button>
  <?php endif; ?>
</td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>

<footer>
  <p>© 2025 Fabric Bazaar. All rights reserved.</p>
</footer>

</body>
</html>