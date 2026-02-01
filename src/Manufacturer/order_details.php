<?php
session_start();

// Fixed line — works on PHP 7.4, 8.0, 8.1, 8.2, 8.3, 8.4
if (empty($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$manufacturer_id = $_SESSION['user_id'];

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    die("Invalid order ID");
}

// Fetch order + wholesaler + shipping address
$sql = "
    SELECT 
        o.*,
        u.company_name AS wholesaler_company,
        u.contact_no AS wholesaler_phone,
        u.email AS wholesaler_email,
        a.address_line,
        a.city,
        a.state,
        a.zip_code,
        a.country
    FROM orders o
    LEFT JOIN users u 
        ON o.wholesaler_id = u.user_id
    LEFT JOIN addresses a 
        ON o.shipping_address_id = a.address_id
    WHERE o.order_id = ?
      AND o.manufacturer_id = ?
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $order_id, $manufacturer_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();

if (!$order) {
    die("Order not found or you don't have access to this order.");
}

// Fetch order items with fabric details
$items_stmt = $mysqli->prepare("
    SELECT 
        oi.quantity, 
        oi.price_at_purchase, 
        f.name AS fabric_name,
        f.image_urls
    FROM order_items oi
    JOIN fabrics f 
        ON oi.fabric_id = f.fabric_id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items = $items_stmt->get_result();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status'])) {
    $allowed_statuses = ['confirmed', 'in_production', 'dispatched', 'delivered'];
    if (in_array($_POST['new_status'], $allowed_statuses)) {
        $update = $mysqli->prepare("UPDATE orders SET status = ? WHERE order_id = ? AND manufacturer_id = ?");
        $update->bind_param("sii", $_POST['new_status'], $order_id, $manufacturer_id);
        $update->execute();
        header("Location: order_details.php?id=$order_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order #<?= htmlspecialchars($order_id) ?> Details</title>
<base href="../../src/Manufacturer/">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
:root{--bazaar-bg:#f5ebe3;--primary:#b88c2b;--border:#e0c68c;--text:#3e2723}
body{margin:0;font-family:'Georgia',serif;background:var(--bazaar-bg);color:var(--text);padding:20px}
#bg-video{position:fixed;top:0;left:0;width:100vw;height:100vh;object-fit:cover;z-index:-1;opacity:0.9}
.container{max-width:1100px;margin:0 auto;background:#fffbe6;border:3px solid var(--border);border-radius:20px;padding:30px;box-shadow:0 8px 30px rgba(0,0,0,0.1)}
.back-link{color:var(--primary);text-decoration:none;font-weight:bold;font-size:1.1rem}
.back-link:hover{text-decoration:underline}
h1{font-size:2.2rem;color:var(--primary);margin:0}
.badge{padding:6px 14px;border-radius:20px;font-size:0.9rem;font-weight:bold}
.status-ordered{background:#fff3cd;color:#856404}
.status-confirmed{background:#d1ecf1;color:#0c5460}
.status-in_production{background:#d4edda;color:#155724}
.status-dispatched{background:#cce5ff;color:#004085}
.status-delivered{background:#e2e3e5;color:#383d41}
table{width:100%;border-collapse:collapse;margin:25px 0}
th{background:#fffbe6;padding:12px;text-align:left;color:#6d4c1e}
td{padding:12px;vertical-align:middle}
.item-img{width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid var(--border)}
.total-row{font-weight:bold;font-size:1.2rem;background:#ffe7b3}
.btn-group{margin:30px 0;text-align:center}
.btn{padding:10px 20px;margin:0 10px;background:var(--primary);color:white;border:none;border-radius:12px;cursor:pointer;font-size:1.1rem;transition:.2s}
.btn:hover{background:#a67c52}
.btn:disabled{background:#ccc;cursor:not-allowed}
</style>
</head>
<body>
<video autoplay muted loop id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="container">
  <a href="order_manufacturer.php" class="back-link">Back to Orders</a>
  <h1>Order #<?= htmlspecialchars($order['order_id']) ?></h1>

  <p><strong>Date:</strong> <?= date('d M Y, h:i A', strtotime($order['order_date'])) ?>
     <span class="badge status-<?= str_replace('_', '-', $order['status']) ?>">
       <?= ucwords(str_replace('_', ' ', $order['status'])) ?>
     </span>
  </p>

  <p><strong>Payment:</strong> <?= ucwords(str_replace('_', ' ', $order['payment_option'])) ?>
     | <strong>Total:</strong> ₹<?= number_format($order['total_amount'], 0) ?></p>

  <hr style="border:1px dashed var(--border);margin:25px 0">

  <h2>Wholesaler</h2>
  <p>
  <strong><?= htmlspecialchars($order['wholesaler_company'] ?? 'N/A') ?></strong><br>
  Phone: <?= htmlspecialchars($order['wholesaler_phone'] ?? '—') ?> |
  Email: <?= htmlspecialchars($order['wholesaler_email'] ?? '—') ?>
</p>
  <h2>Shipping Address</h2>
  <p><?= nl2br(htmlspecialchars($order['address_line'] . "\n" . $order['city'] . ", " . $order['state'] . " " . $order['zip_code'] . "\n" . ($order['country'] ?? 'India'))) ?></p>

  <h2>Order Items</h2>
  <table>
    <thead>
      <tr><th>Image</th><th>Fabric</th><th>Qty</th><th>Unit Price</th><th>Line Total</th></tr>
    </thead>
    <tbody>
      <?php 
      $grand_qty = 0;
      while ($item = $items->fetch_assoc()): 
        $line_total = $item['quantity'] * $item['price_at_purchase'];
        $grand_qty += $item['quantity'];
      ?>
        <tr>
          <?php
$images = json_decode($item['image_urls'], true);
$image = is_array($images) && !empty($images)
    ? $images[0]
    : 'assets/placeholder.jpg';
?>
<td>
  <img src="../../<?= htmlspecialchars($image) ?>" class="item-img" alt="Fabric">
</td>
<td>
  <strong><?= htmlspecialchars($item['fabric_name']) ?></strong>
</td>

          <td><?= number_format($item['quantity']) ?> pcs</td>
          <td>₹<?= number_format($item['price_at_purchase'], 0) ?></td>
          <td>₹<?= number_format($line_total, 0) ?></td>
        </tr>
      <?php endwhile; ?>
      <tr class="total-row">
        <td colspan="2"><strong>Total</strong></td>
        <td><strong><?= number_format($grand_qty) ?> pcs</strong></td>
        <td></td>
        <td><strong>₹<?= number_format($order['total_amount'], 0) ?></strong></td>
      </tr>
    </tbody>
  </table>

  <div class="btn-group">
    <form method="post" style="display:inline">
      <input type="hidden" name="new_status" value="confirmed">
      <button type="submit" class="btn" <?= $order['status'] === 'confirmed' ? 'disabled' : '' ?>>Accept Order</button>
    </form>

    <form method="post" style="display:inline">
      <input type="hidden" name="new_status" value="in_production">
      <button type="submit" class="btn" <?= in_array($order['status'], ['in_production','dispatched','delivered']) ? 'disabled' : '' ?>>Start Production</button>
    </form>

    <form method="post" style="display:inline">
      <input type="hidden" name="new_status" value="dispatched">
      <button type="submit" class="btn" <?= $order['status'] !== 'in_production' ? 'disabled' : '' ?>>Mark Dispatched</button>
    </form>

    <form method="post" style="display:inline">
      <input type="hidden" name="new_status" value="delivered">
      <button type="submit" class="btn" <?= $order['status'] !== 'dispatched' ? 'disabled' : '' ?>>Mark Delivered</button>
    </form>
  </div>
</div>

<?php
$stmt->close();
$items_stmt->close();
$mysqli->close();
?>
</body>
</html>