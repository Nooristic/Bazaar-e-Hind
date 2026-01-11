<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Exclusivity Agreements - Bazaar-e-Hind</title>

<style>
/* ===== FONT & BASE ===== */
body {
  font-family: 'Georgia', serif;
  margin: 0;
  padding: 0;
  background: transparent;
  color: #3e2723;
  min-height: 100vh;
}

/* ===== BACKGROUND VIDEO ===== */
#bg-video {
  position: fixed;
  top: 0;
  left: 0;
  min-width: 100vw;
  min-height: 100vh;
  width: 100vw;
  height: 100vh;
  object-fit: cover;
  z-index: -1;
  pointer-events: none;
}

/* ===== HEADER ===== */
.blue-header {
  background-color: rgba(73, 40, 22, 0.389);
  color: white;
  padding: 1rem;
  text-align: center;
  position: relative;
}

.blue-header .back-link {
  color: white;
  text-decoration: none;
  font-size: 1.2rem;
  position: absolute;
  left: 1rem;
  top: 1rem;
}

/* ===== TABLE ===== */
.agreements-table {
  width: 90%;
  max-width: 1200px;
  margin: 2rem auto;
  border-collapse: collapse;
  background-color: rgba(255, 255, 255, 0.92);
  border-radius: 8px;
  overflow: hidden;
}

.agreements-table th,
.agreements-table td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
}

.agreements-table th {
  background-color: #e0c68c;
  color: white;
}

.agreements-table tr:hover {
  background-color: #f1f1f1;
}

/* ===== STATUS BADGES ===== */
.status-badge {
  display: inline-block;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  font-size: 0.9rem;
  font-weight: 500;
}

.status-pending  { background-color: #ffc107; color: #212529; }
.status-active   { background-color: #28a745; color: white; }
.status-rejected { background-color: #dc3545; color: white; }
.status-expired  { background-color: #6c757d; color: white; }

/* ===== ACTION BUTTONS ===== */
.actions {
  margin: 2rem auto;
  text-align: center;
}

.actions button {
  padding: 0.7rem 1.8rem;
  border: none;
  border-radius: 6px;
  background-color: #c19c55;
  color: white;
  cursor: pointer;
  font-size: 1.1rem;
  font-family: inherit;
  transition: background-color 0.2s;
}

.actions button:hover {
  background-color: #a67c41;
}

.actions button::after {
  content: " ₹";
  font-weight: bold;
}

/* ===== MODAL FORM ===== */
.form-container {
  display: none;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: rgba(224, 198, 140, 0.98);
  padding: 2.5rem;
  border-radius: 10px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.3);
  z-index: 1000;
  width: 90%;
  max-width: 520px;
}

.form-container.active {
  display: block;
}

.form-container h2 {
  margin-top: 0;
  color: #3e2723;
}

.form-container label {
  display: block;
  margin: 1rem 0 0.4rem;
  font-weight: 500;
}

.form-container select,
.form-container textarea {
  width: 100%;
  padding: 0.6rem;
  border: 1px solid #c19c55;
  border-radius: 5px;
  font-family: inherit;
}

.form-container textarea {
  min-height: 100px;
  resize: vertical;
}

.form-container button {
  margin-top: 1.5rem;
  background-color: #8b5e3c;
}

.form-container button:hover {
  background-color: #6d4a30;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .agreements-table thead {
    display: none;
  }
  .agreements-table tr {
    display: block;
    margin-bottom: 1.2rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 0.8rem;
  }
  .agreements-table td {
    display: flex;
    justify-content: space-between;
    padding: 0.6rem 1rem;
  }
  .agreements-table td::before {
    content: attr(data-label);
    font-weight: bold;
    color: #5d4037;
  }
}
</style>
</head>

<body>

<!-- Background Video -->
<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
  Your browser does not support the video tag.
</video>

<?php
session_start();

// Security - only logged-in wholesalers
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['wholesaler', 'both'])) {
    header("Location: ../login.html");
    exit();
}

$wholesaler_id = $_SESSION['user_id'] ?? null;

// Database connection (change credentials as per your setup)
$host = "localhost";
$user = "your_db_user";
$pass = "your_db_password";
$dbname = "bazaar_e_hind";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle new exclusivity request
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_exclusivity') {
    $manufacturer_id = filter_input(INPUT_POST, 'manufacturer_id', FILTER_VALIDATE_INT);
    $fabrics = isset($_POST['fabrics']) ? implode(", ", $_POST['fabrics']) : "";
    $duration_from = trim($_POST['duration_from'] ?? '');
    $duration_to   = trim($_POST['duration_to'] ?? '');
    $terms = trim($_POST['terms'] ?? '');

    if ($manufacturer_id && $fabrics && $duration_from && $duration_to) {
        $stmt = $conn->prepare("
            INSERT INTO exclusivity_agreements 
            (wholesaler_id, manufacturer_id, fabrics, duration_from, duration_to, terms, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->bind_param("iissss", 
            $wholesaler_id, 
            $manufacturer_id, 
            $fabrics, 
            $duration_from, 
            $duration_to, 
            $terms
        );

        if ($stmt->execute()) {
            $message = "Exclusivity request submitted successfully! Waiting for manufacturer approval.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Please fill all required fields.";
    }
}

// Fetch existing agreements
$agreements = [];
$result = $conn->query("
    SELECT 
        ea.id AS agreement_id,
        u.company_name AS manufacturer_name,
        ea.fabrics,
        DATE_FORMAT(ea.duration_from, '%d/%m/%Y') AS duration_from,
        DATE_FORMAT(ea.duration_to, '%d/%m/%Y') AS duration_to,
        ea.status
    FROM exclusivity_agreements ea
    JOIN users u ON ea.manufacturer_id = u.id
    WHERE ea.wholesaler_id = $wholesaler_id
    ORDER BY ea.created_at DESC
");

while ($row = $result->fetch_assoc()) {
    $agreements[] = $row;
}

// Fetch available manufacturers (for dropdown)
$manufacturers = [];
$manuf_result = $conn->query("
    SELECT id, company_name 
    FROM users 
    WHERE role IN ('manufacturer', 'both') 
    AND account_status = 'active'
    ORDER BY company_name
");
while ($m = $manuf_result->fetch_assoc()) {
    $manufacturers[] = $m;
}

$conn->close();
?>

<header class="blue-header">
  <a href="../home.php" class="back-link">← Home</a>
  <h1>Exclusivity Agreements</h1>
</header>

<div class="container">

  <?php if ($message): ?>
  <div style="text-align:center; margin:1.5rem; padding:1rem; background:#fff3cd; border:1px solid #ffeeba; border-radius:6px; color:#856404;">
    <?= htmlspecialchars($message) ?>
  </div>
  <?php endif; ?>

  <table class="agreements-table">
    <thead>
      <tr>
        <th>Agreement ID</th>
        <th>Manufacturer</th>
        <th>Fabrics</th>
        <th>Duration</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($agreements)): ?>
        <tr>
          <td colspan="5" style="text-align:center; padding:2rem;">No exclusivity agreements found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($agreements as $agr): ?>
          <tr>
            <td data-label="Agreement ID">EA<?= str_pad($agr['agreement_id'], 5, '0', STR_PAD_LEFT) ?></td>
            <td data-label="Manufacturer"><?= htmlspecialchars($agr['manufacturer_name']) ?></td>
            <td data-label="Fabrics"><?= htmlspecialchars($agr['fabrics']) ?></td>
            <td data-label="Duration">
              <?= $agr['duration_from'] ?> – <?= $agr['duration_to'] ?>
            </td>
            <td data-label="Status">
              <span class="status-badge status-<?= $agr['status'] ?>">
                <?= ucfirst($agr['status']) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="actions">
    <button id="request-new">Request New Exclusivity</button>
  </div>

  <!-- New Request Modal -->
  <div class="form-container" id="exclusivity-form">
    <h2>Request New Exclusivity</h2>

    <form method="POST" action="">
      <input type="hidden" name="action" value="request_exclusivity">

      <label>Select Manufacturer *</label>
      <select name="manufacturer_id" required>
        <option value="">-- Select Manufacturer --</option>
        <?php foreach ($manufacturers as $m): ?>
          <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['company_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Desired Fabrics (select multiple) *</label>
      <select name="fabrics[]" multiple required size="6">
        <option value="Silk Saffron">Silk Saffron</option>
        <option value="Cotton Blue">Cotton Blue</option>
        <option value="Linen Gold">Linen Gold</option>
        <option value="Velvet Red">Velvet Red</option>
        <option value="Polyester Blend">Polyester Blend</option>
        <!-- You can also fetch this dynamically from fabrics table -->
      </select>

      <label>Proposed Duration *</label>
      <div style="display:flex; gap:1rem;">
        <input type="date" name="duration_from" required>
        <span style="align-self:center;">to</span>
        <input type="date" name="duration_to" required>
      </div>

      <label>Terms & Conditions / Special Requests</label>
      <textarea name="terms" placeholder="Any additional terms, conditions or notes..."></textarea>

      <button type="submit" id="submit-request">Submit Request</button>
    </form>
  </div>
</div>

<footer style="background:rgba(255,255,255,0.92);text-align:center;padding:1.5rem; color:#5d4037; margin-top:3rem;">
  © <?= date("Y") ?> Bazaar-e-Hind. All rights reserved.
</footer>

<script>
document.getElementById('request-new').onclick = function() {
  document.getElementById('exclusivity-form').classList.add('active');
};

document.getElementById('submit-request').onclick = function(e) {
  // Optional: can add client-side validation here if needed
  // e.preventDefault(); // uncomment if you want AJAX instead
};
</script>

</body>
</html>