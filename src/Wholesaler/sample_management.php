<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}

$wholesaler_id = (int) $_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) die("DB Error");
$conn->set_charset("utf8mb4");

/* ================= FETCH SAMPLE REQUESTS ================= */
$stmt = $conn->prepare("
    SELECT
        sr.sample_id,
        sr.quantity,
        sr.status,
        sr.created_at,
        sr.tracking_number,
        sr.tracking_url,
        f.name AS fabric_name,
        u.company_name AS manufacturer_name
    FROM sample_requests sr
    JOIN fabrics f ON f.fabric_id = sr.fabric_id
    JOIN users u ON u.user_id = sr.manufacturer_id
    WHERE sr.wholesaler_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->bind_param("i", $wholesaler_id);
$stmt->execute();
$result = $stmt->get_result();
$samples = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

/* ================= DASHBOARD METRICS ================= */
$total_requests = count($samples);

$manufacturers = [];
$accepted = 0;
$rejected = 0;

foreach ($samples as $s) {
    $manufacturers[$s['manufacturer_name']] = true;

    if ($s['status'] === 'accepted') $accepted++;
    if ($s['status'] === 'rejected') $rejected++;
}

$total_manufacturers = count($manufacturers);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Sample Requests</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
body {
  font-family: Georgia, serif;
  background: transparent;
  color: #3e2723;
  margin: 0;
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
  background: rgba(255,245,235,0.95);
  padding: 18px;
  border-bottom: 2px solid #e0c68c;
  text-align: center;
  position: relative;
}

.back-link {
  position: absolute;
  left: 20px;
  top: 18px;
  text-decoration: none;
  font-weight: bold;
  color: #3e2723;
}

.container {
  max-width: 1100px;
  margin: 40px auto;
  background: rgba(255,245,235,0.95);
  border-radius: 14px;
  border: 2px solid #e0c68c;
  padding: 20px;
}
.dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 18px;
  margin: 30px auto 20px;
  max-width: 1100px;
  padding: 0 20px;
}

.dash-card {
  background: rgba(255,245,235,0.95);
  border: 2px solid #e0c68c;
  border-radius: 14px;
  padding: 18px;
  text-align: center;
  box-shadow: 0 8px 24px rgba(0,0,0,.05);
}

.dash-card h2 {
  margin: 0;
  font-size: 2rem;
  color: #6d4c1e;
}

.dash-card p {
  margin-top: 6px;
  font-size: 0.95rem;
  font-weight: bold;
  color: #3e2723;
  letter-spacing: .3px;
}

.dash-card.accepted h2 { color: #2e7d32; }
.dash-card.rejected h2 { color: #c62828; }

table {
  width: 100%;
  border-collapse: collapse;
}

th, td {
  padding: 14px;
  border-bottom: 1px solid #e0c68c;
  text-align: left;
}

th {
  background: #f3e2c3;
}

.status {
  padding: 6px 14px;
  border-radius: 20px;
  font-weight: bold;
  font-size: 0.85rem;
  text-transform: capitalize;
}

.status.requested { background: #ffe7b3; }
.status.accepted  { background: #c8e6c9; }
.status.rejected  { background: #ffcdd2; }
.status.shipped   { background: #bbdefb; }
.status.delivered { background: #d1c4e9; }

a.track {
  font-weight: bold;
  color: #6d4c1e;
  text-decoration: none;
}
</style>
</head>

<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<header class="header">
  <a href="home_page.php" class="back-link">← Home</a>
  <h1>My Sample Requests</h1>
</header>
<section class="dashboard">

  <div class="dash-card">
    <h2><?= $total_requests ?></h2>
    <p>Requests Sent</p>
  </div>

  <div class="dash-card">
    <h2><?= $total_manufacturers ?></h2>
    <p>Manufacturers Requested</p>
  </div>

  <div class="dash-card accepted">
    <h2><?= $accepted ?></h2>
    <p>Accepted Requests</p>
  </div>

  <div class="dash-card rejected">
    <h2><?= $rejected ?></h2>
    <p>Rejected Requests</p>
  </div>

</section>
<div class="container">
<table>
<tr>
  <th>Sample ID</th>
  <th>Fabric</th>
  <th>Manufacturer</th>
  <th>Quantity</th>
  <th>Status</th>
  <th>Requested On</th>
  <th>Tracking</th>
</tr>

<?php if (empty($samples)): ?>
<tr>
  <td colspan="7" style="text-align:center; padding:30px;">
    No sample requests found.
  </td>
</tr>
<?php else: ?>
<?php foreach ($samples as $s): ?>
<tr>
  <td>S<?= $s['sample_id'] ?></td>
  <td><?= htmlspecialchars($s['fabric_name']) ?></td>
  <td><?= htmlspecialchars($s['manufacturer_name']) ?></td>
  <td><?= number_format($s['quantity'], 2) ?></td>
  <td>
    <span class="status <?= $s['status'] ?>">
      <?= ucfirst($s['status']) ?>
    </span>
  </td>
  <td><?= date('Y-m-d', strtotime($s['created_at'])) ?></td>
  <td>
    <?php if ($s['tracking_url']): ?>
      <a class="track" href="<?= htmlspecialchars($s['tracking_url']) ?>" target="_blank">
        Track
      </a>
    <?php else: ?>
      —
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
<?php endif; ?>

</table>
</div>

</body>
</html>
