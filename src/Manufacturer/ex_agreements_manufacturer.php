<?php
session_start();

// Security
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$user_id  = $_SESSION['user_id'];
$username = strtoupper(htmlspecialchars($_SESSION['username']));

// DB Connection
$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);
$mysqli->set_charset("utf8mb4");

// ====================== FORM SUBMISSION ======================
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wholesaler_id = (int)($_POST['wholesaler'] ?? 0);
    $fabric_ids    = $_POST['fabrics'] ?? [];
    $terms         = trim($_POST['terms'] ?? '');
    $start_date    = $_POST['start_date'] ?? '';
    $end_date      = $_POST['end_date'] ?? '';

    if (!$wholesaler_id || empty($fabric_ids) || !$terms || !$start_date || !$end_date) {
        $message = "<div class='msg error'>All fields are required.</div>";
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $message = "<div class='msg error'>End date must be after start date.</div>";
    } else {
        $fabric_json = json_encode(array_map('intval', $fabric_ids));

        $stmt = $mysqli->prepare("
            INSERT INTO exclusivity_agreements 
            (manufacturer_id, wholesaler_id, fabric_ids, terms, start_date, end_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending_wholesaler')
        ");
        $stmt->bind_param("iissss", $user_id, $wholesaler_id, $fabric_json, $terms, $start_date, $end_date);

        if ($stmt->execute()) {
            $agreement_id = $mysqli->insert_id;

            // Grant access
            $acc = $mysqli->prepare("INSERT IGNORE INTO fabric_exclusive_access (agreement_id, fabric_id, wholesaler_id) VALUES (?, ?, ?)");
            foreach ($fabric_ids as $fid) {
                $fid = (int)$fid;
                $acc->bind_param("iii", $agreement_id, $fid, $wholesaler_id);
                $acc->execute();
            }
            $acc->close();

            // Mark fabrics as restricted
            if (!empty($fabric_ids)) {
                $placeholders = str_repeat('?,', count($fabric_ids) - 1) . '?';
                $upd = $mysqli->prepare("UPDATE fabrics SET visibility_type = 'restricted' WHERE fabric_id IN ($placeholders) AND user_id = ?");
                $params = array_merge($fabric_ids, [$user_id]);
                $tmp = [];
                foreach ($params as $k => $v) $tmp[$k] = &$params[$k];
                call_user_func_array([$upd, 'bind_param'], array_merge([str_repeat('i', count($fabric_ids)) . 'i'], $tmp));
                $upd->execute();
                $upd->close();
            }

            $message = "<div class='msg success'>Agreement created! Awaiting wholesaler approval.</div>";
        } else {
            $message = "<div class='msg error'>Failed to create agreement.</div>";
        }
        $stmt->close();
    }
}

// ====================== FETCH DATA (100% matching your real schema) ======================
// Wholesalers — using correct column names
$wholesalers = $mysqli->query("
    SELECT user_id, company_name 
    FROM users 
    WHERE role = 'wholesaler' 
      AND account_status = 'active' 
    ORDER BY company_name
");

// My fabrics
$my_fabrics = $mysqli->query("
    SELECT fabric_id, name 
    FROM fabrics 
    WHERE user_id = $user_id AND is_active = 1 
    ORDER BY name
");

// Existing agreements — using company_name and correct status column
$agreements = $mysqli->query("
    SELECT 
        a.agreement_id,
        a.start_date,
        a.end_date,
        a.status,
        u.company_name AS wholesaler_name,
        a.fabric_ids
    FROM exclusivity_agreements a
    JOIN users u ON a.wholesaler_id = u.user_id
    WHERE a.manufacturer_id = $user_id
    ORDER BY a.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bazaar-e-Hind - Exclusivity Agreements</title>
  <base href="../../src/Manufacturer/">

    <link rel="stylesheet" href="../css_all_pages.css">
  
  <style>
    /* Page-specific styles; globals (variables, body, header, links, bg-video) are in css_all_pages.css */
    .container { flex:1; padding:20px 38px; display:flex; gap:40px; align-items:flex-start; }
    .list-section { flex:2; background:#fff; border-radius:12px; padding:20px; box-shadow:0 2px 12px rgba(180,140,60,.08); border:1.5px solid var(--border); }
    .list-title { font-size:1.3rem; font-weight:bold; margin-bottom:15px; color:#6d4c1e; }

    table { width:100%; border-collapse:separate; border-spacing:0 10px; }
    th { background:#fffbe6; padding:12px; text-align:left; color:#6d4c1e; font-weight:bold; }
    td { background:#fff; padding:12px; border-bottom:1px solid #f5ebe3; }
    .status { padding:5px 12px; border-radius:20px; font-size:0.9rem; font-weight:bold; }
    .status.active { background:#d4edda; color:#155724; }
    .status.pending_wholesaler,.status.pending { background:#fff3cd; color:#856404; }
    .status.expired { background:#f8d7da; color:#721c24; }

    .form-section { flex:1.3; background:#fff; border-radius:12px; padding:22px; box-shadow:0 2px 12px rgba(180,140,60,.08); border:1.5px solid var(--border); height:fit-content; }
    .form-title { font-size:1.25rem; font-weight:bold; margin-bottom:18px; color:#6d4c1e; }
    .form-group { margin-bottom:16px; }
    label { display:block; margin-bottom:6px; font-weight:500; }
    select, textarea, input[type=date] { width:100%; padding:10px; border:1.5px solid var(--border); border-radius:8px; background:#fffbe6; }
    select[multiple] { height:120px; }
    .form-row { display:flex; align-items:center; gap:12px; }
    /* submit button and messages moved to css_all_pages.css */

  
    @media (max-width:1000px) { .container { flex-direction:column; } }
  </style>
</head>
<body>

  <video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
  </video>

  <div class="header">
    <a href="bazaar-homepage.php" class="back-link">← Home</a>
    <div class="header-title">Exclusivity Agreements</div>
     <div class="profile-btn">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/></svg>
    </div> 
   </div>

  <div class="container">
    <!-- Existing Agreements -->
    <div class="list-section">
      <div class="list-title">Existing Agreements</div>
      <?= $message ?>

      <table>
        <thead>
          <tr><th>Wholesaler</th><th>Fabrics</th><th>Duration</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if ($agreements->num_rows === 0): ?>
            <tr><td colspan="4" style="text-align:center;padding:50px;color:#888;">
              No exclusivity agreements yet.<br><br>Use the form to create one.
            </td></tr>
          <?php else: ?>
            <?php while ($a = $agreements->fetch_assoc()):
              $fabric_ids = json_decode($a['fabric_ids'], true) ?? [];
              $fabric_list = '—';
              if (!empty($fabric_ids)) {
                  $ids = implode(',', array_map('intval', $fabric_ids));
                  $res = $mysqli->query("SELECT GROUP_CONCAT(name SEPARATOR ', ') AS names FROM fabrics WHERE fabric_id IN ($ids)");
                  $row = $res->fetch_assoc();
                  $fabric_list = htmlspecialchars($row['names'] ?? '—');
              }
              $status_class = str_replace('_', '-', strtolower($a['status']));
            ?>
              <tr>
                <td><strong><?= htmlspecialchars($a['wholesaler_name']) ?></strong></td>
                <td><?= $fabric_list ?></td>
                <td><?= date('d/m/Y', strtotime($a['start_date'])) ?> – <?= date('d/m/Y', strtotime($a['end_date'])) ?></td>
                <td><span class="status <?= $status_class ?>"><?= ucwords(str_replace('_', ' ', $a['status'])) ?></span></td>
              </tr>
            <?php endwhile; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Create New Agreement -->
    <form class="form-section" method="POST">
      <div class="form-title">Create New Agreement</div>

      <div class="form-group">
        <label>Select Wholesaler *</label>
        <select name="wholesaler" required>
          <option value="">-- Choose Wholesaler --</option>
          <?php while ($w = $wholesalers->fetch_assoc()): ?>
            <option value="<?= $w['user_id'] ?>"><?= htmlspecialchars($w['company_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Select Fabrics * <small>(Ctrl/Cmd + click)</small></label>
        <select name="fabrics[]" multiple required>
          <?php 
          $my_fabrics->data_seek(0);
          while ($f = $my_fabrics->fetch_assoc()): ?>
            <option value="<?= $f['fabric_id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Agreement Terms *</label>
        <textarea name="terms" rows="4" placeholder="Write exclusivity terms, penalties, etc." required></textarea>
      </div>

      <div class="form-group">
        <label>Duration *</label>
        <div class="form-row">
          <input type="date" name="start_date" required>
          <span>to</span>
          <input type="date" name="end_date" required>
        </div>
      </div>

      <button type="submit" class="submit-btn">Submit Agreement</button>

      <p style="text-align:center;margin-top:15px;color:#777;font-size:0.9rem;">
        Digital signature integration coming soon
      </p>
    </form>
  </div>
</body>
</html>

<?php $mysqli->close(); ?>