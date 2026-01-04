<?php
session_start();

// Security
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$manufacturer_id = $_SESSION['user_id'];

// Database Connection
$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");

if ($mysqli->connect_error) {
    die("Connection failed. Please try again later.");
}
$mysqli->set_charset("utf8mb4");

// Tabs
$allowed_tabs = ['new', 'in_progress', 'completed'];
$tab = 'new';
if (isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs)) {
    $tab = $_GET['tab'];
}

// Map tabs to statuses
$status_map = [
    'new'         => ['ordered', 'confirmed'],
    'in_progress' => ['in_production', 'dispatched'],
    'completed'   => ['delivered']
];

$current_statuses = $status_map[$tab];

// Search
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the IN clause properly
$placeholders = implode(',', array_fill(0, count($current_statuses), '?'));

$sql = "SELECT 
            o.order_id,
            o.order_date,
            o.status,
            o.total_amount,
            u.company_name AS wholesaler_name
        FROM orders o
        LEFT JOIN users u ON o.wholesaler_id = u.user_id
        WHERE o.manufacturer_id = ?
          AND o.status IN ($placeholders)";

$params = [$manufacturer_id];
$types  = "i";

foreach ($current_statuses as $status) {
    $params[] = $status;
    $types .= "s";
}

// Search
if ($search !== '') {
    $sql .= " AND (o.order_id LIKE ? OR u.company_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$sql .= " ORDER BY o.order_date DESC LIMIT 100";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Order Management</title>
  <base href="../../src/Manufacturer/">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css_all_pages.css">
</head>
<body>
  <video autoplay muted loop id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
  </video>

  <div class="header">
    <a href="bazaar-homepage.php" class="back-link">← Home</a>
    <span class="header-title">Order Management</span>
    <div class="profile-btn">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/></svg>
    </div>
  </div>

  <div class="main-content">
    <div class="sidebar">
      <form class="search-box" method="GET">
        <input type="text" name="search" placeholder="Search" value="<?=htmlspecialchars($search)?>">
        <input type="hidden" name="tab" value="<?=$tab?>">
        <button type="submit">Search</button>
      </form>

      <div class="filters-title">Filters</div>
      <form class="filters-list" method="GET">
        <label><input type="checkbox" name="active"    <?=isset($_GET['active'])?'checked':''?>> Active</label>
        <label><input type="checkbox" name="price"     <?=isset($_GET['price'])?'checked':''?>> Price</label>
        <label><input type="checkbox" name="shareable" <?=isset($_GET['shareable'])?'checked':''?>> Shareable</label>
        <label><input type="checkbox" name="exclusive" <?=isset($_GET['exclusive'])?'checked':''?>> Exclusive</label>
        <input type="hidden" name="tab" value="<?=$tab?>">
        <button type="submit" style="margin-top:12px;padding:6px 12px;background:var(--primary);color:white;border:none;border-radius:8px;cursor:pointer;">Apply Filters</button>
      </form>
    </div>

    <div class="order-section">
      <div class="order-tabs">
        <div class="order-tabs">
  <a href="?tab=new"         class="order-tab <?=$tab==='new'?'active':''?>">New</a>
  <a href="?tab=in_progress" class="order-tab <?=$tab==='in_progress'?'active':''?>">In Progress</a>
  <a href="?tab=completed"   class="order-tab <?=$tab==='completed'?'active':''?>">Completed</a>
</div>
      </div>

      <table class="order-table">
        <thead>
          <tr>
            <th><input type="checkbox"></th>
            <th>Order ID</th>
            <th>Wholesaler</th>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>View</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr>
                <td><input type="checkbox"></td>
                <td>#<?= $row['order_id'] ?></td>
                <td><?= htmlspecialchars($row['wholesaler_name'] ?? 'Unknown') ?></td>
                <td><?= date('d M Y', strtotime($row['order_date'])) ?></td>
                <td>₹<?= number_format($row['total_amount'], 0) ?></td>
                <td>
                  <span style="padding:4px 10px;border-radius:12px;font-size:0.85rem;background:#<?=$row['status']=='delivered'?'d4edda':($row['status']=='in_production'||$row['status']=='dispatched'?'fff3cd':'f8d7da')?>;color:#<?=$row['status']=='delivered'?'155724':($row['status']=='in_production'||$row['status']=='dispatched'?'856404':'721c24')?>">
                    <?= ucwords(str_replace('_', ' ', $row['status'])) ?>
                  </span>
                </td>
                <td><a href="order_details.php?id=<?= $row['order_id'] ?>" class="view-btn">View</a></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align:center;padding:50px;color:#999;font-size:1.1rem;">No orders found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php
  if ($stmt) $stmt->close();
  $mysqli->close();
  ?>
</body>
</html>