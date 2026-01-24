<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
$mysqli->set_charset("utf8mb4");

/*
|--------------------------------------------------------------------------
| Fetch agreements for this manufacturer
|--------------------------------------------------------------------------
| NOTE:
| - Manufacturer does NOT deal with fabric logic here
| - Product movement happens only AFTER agreement becomes ACTIVE
*/
$agreements = $mysqli->query("
    SELECT 
        a.agreement_id,
        a.start_date,
        a.end_date,
        a.status,
        u.company_name AS wholesaler_name
    FROM exclusivity_agreements a
    JOIN users u ON u.user_id = a.wholesaler_id
    WHERE a.manufacturer_id = $user_id
      AND a.status != 'rejected'
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
    .status.pending_manufacturer,.status.pending { background:#fff3cd; color:#856404; }
    .status.expired { background:#f8d7da; color:#721c24; }
.status.pending_wholesaler {
  background:#fff3cd;
  color:#856404;
}

.status.declined {
  background:#f8d7da;
  color:#721c24;
}

.status.cancelled {
  background:#f8d7da;
  color:#721c24;
}

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
    <svg viewBox="0 0 24 24">
      <circle cx="12" cy="8" r="5"/>
      <path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/>
    </svg>
  </div>
</div>

<div class="container">

  <!-- ================= EXISTING AGREEMENTS ================= -->
  <div class="list-section">
    <div class="list-title">Existing Agreements</div>

    <table>
      <thead>
        <tr>
          <th>Wholesaler</th>
          <th>Duration</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>

      <tbody>
        <?php if ($agreements->num_rows === 0): ?>
          <tr>
            <td colspan="4" style="text-align:center;padding:40px;color:#888;">
              No exclusivity agreements found.
            </td>
          </tr>
        <?php else: ?>
          <?php while ($a = $agreements->fetch_assoc()):
              $status_class = strtolower($a['status']);
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($a['wholesaler_name']) ?></strong></td>

            <td>
              <?= date('d/m/Y', strtotime($a['start_date'])) ?>
              –
              <?= date('d/m/Y', strtotime($a['end_date'])) ?>
            </td>

            <td>
              <span class="status <?= $status_class ?>">
                <?= ucwords(str_replace('_', ' ', $a['status'])) ?>
              </span>
            </td>

            <td style="display:flex;gap:10px;">
              <a href="view_agreement.php?id=<?= (int)$a['agreement_id'] ?>" class="submit-btn">
                View Agreement
              </a>

              <a href="download_agreement.php?id=<?= (int)$a['agreement_id'] ?>" class="submit-btn">
                Download
              </a>
            </td>
          </tr>
          <?php endwhile; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>

</body>
</html>

<?php $mysqli->close(); ?>