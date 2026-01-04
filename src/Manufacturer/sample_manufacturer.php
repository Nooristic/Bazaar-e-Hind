<?php
session_start();

// Security check
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.html");
    exit();
}

$manufacturer_id = $_SESSION['user_id'];   // ← this is correct (your users table uses user_id)

// DB Connection
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* ==================== HANDLE ACTIONS ==================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $sample_id = (int)$_POST['request_id'];
    $action    = $_POST['action'];

    if ($action === 'approve') {
        $sql = "UPDATE sample_requests SET status = 'accepted', updated_at = NOW() 
                WHERE sample_id = ? AND manufacturer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $sample_id, $manufacturer_id);
        $stmt->execute();

        // Audit log
        $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) 
                               VALUES (?, 'SAMPLE_APPROVED', ?, 'Sample approved', ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log->bind_param("iis", $manufacturer_id, $sample_id, $ip);
        $log->execute();
    }

    elseif ($action === 'reject') {
        $reason = $_POST['reject_reason'] ?? 'No reason given';
        $sql = "UPDATE sample_requests 
                SET status = 'rejected', manufacturer_notes = ?, updated_at = NOW() 
                WHERE sample_id = ? AND manufacturer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $reason, $sample_id, $manufacturer_id);
        $stmt->execute();
    }

    elseif ($action === 'dispatch') {
        $courier = $_POST['courier'];
        $track   = $_POST['tracking_url'] ?? '';
        $est     = $_POST['est_date'];

        $sql = "UPDATE sample_requests 
                SET status = 'shipped',
                    courier_details = ?,
                    tracking_url = ?,
                    estimated_dispatch_date = ?,
                    dispatched_at = NOW(),
                    updated_at = NOW()
                WHERE sample_id = ? AND manufacturer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssii", $courier, $track, $est, $sample_id, $manufacturer_id);
        $stmt->execute();
    }

    header("Location: sample_manufacturer.php");
    exit();
}

/* ==================== FETCH REQUESTS ==================== */
$sql = "SELECT 
            sr.sample_id,
            COALESCE(sr.request_code, CONCAT('SREQ-', sr.sample_id)) AS req_code,
            u.company_name AS wholesaler,
            f.name AS fabric_name,
            sr.quantity,
            sr.custom_notes AS notes,
            sr.status,
            sr.courier_details,
            sr.tracking_url,
            sr.estimated_dispatch_date
        FROM sample_requests sr
        JOIN users u ON sr.wholesaler_id = u.user_id          -- ← fixed
        JOIN fabrics f ON sr.fabric_id = f.fabric_id
        WHERE sr.manufacturer_id = ?
        ORDER BY sr.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $manufacturer_id);
$stmt->execute();
$result   = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bazaar-e-Hind - Manufacturer Sample Management</title>
  <base href="../../src/Manufacturer/">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css_all_pages.css">
  <style>
    /* Page-specific sample management styles; global header/body/bg-video/footer live in css_all_pages.css */
    .sample-container{flex:1;padding:32px 32px 24px;display:flex;flex-direction:column;min-width:0}
    .responsive-table{width:100%;display:flex;flex-direction:column;gap:16px;overflow-x:auto}
    .table-row,.table-header{display:flex;align-items:stretch;background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(180,140,60,.05);border:1.5px solid var(--border);min-width:900px}
    .table-header{background:#fffbe6;font-weight:bold;color:#6d4c1e;font-size:1.05rem;border-bottom:2px solid var(--border)}
    .table-cell{flex:1 1 0;padding:14px 10px;display:flex;align-items:center;min-width:110px;box-sizing:border-box;font-size:1rem;word-break:break-word}
    .table-cell.actions{gap:8px;flex-wrap:wrap}
    .action-btn{background:var(--btn);color:#6d4c1e;border:1.5px solid var(--border);border-radius:7px;padding:6px 14px;font-size:1rem;cursor:pointer;transition:background .15s,border .15s}
    .action-btn:hover{background:var(--btn-hover);border-color:var(--primary)}
    .dispatch-fields{display:flex;flex-direction:column;gap:6px;margin-top:8px;font-size:.97rem}
    .dispatch-fields input{padding:8px 10px;border-radius:6px;border:1.2px solid var(--border);background:#fffbe6;font-size:.95rem}
    footer{background:#fffbe6;border-top:2px solid var(--border);padding:16px 0 12px;text-align:center;margin-top:auto}
    .footer-links{display:flex;justify-content:center;gap:32px;flex-wrap:wrap}
    .footer-links a{color:#6d4c1e;text-decoration:none;font-weight:500;transition:color .15s}
    .footer-links a:hover{color:var(--primary)}
    @media(max-width:1050px){.sample-container{padding:18px 2vw}.table-row,.table-header{min-width:700px}}
    @media(max-width:700px){.table-row,.table-header{flex-direction:column;min-width:0}.table-cell{padding:10px 8px;border-bottom:1px solid #f5ebe3}}
  </style>
</head>
<body>
  <video autoplay muted loop id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
  </video>

  <div class="header">
    <a href="bazaar-homepage.php" class="back-link">&#8592; Home</a>
    <span class="header-title">Sample Management</span>
    <div class="profile-btn">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/></svg>
    </div>
</div>

  <main class="sample-container">
    <div class="responsive-table">
      <div class="table-header">
        <div class="table-cell">Request ID</div>
        <div class="table-cell">Wholesaler</div>
        <div class="table-cell">Fabric</div>
        <div class="table-cell">Quantity</div>
        <div class="table-cell">Notes</div>
        <div class="table-cell status">Status</div>
        <div class="table-cell actions">Actions</div>
      </div>

      <?php if (empty($requests)): ?>
        <div class="table-row"><div class="table-cell" style="flex:100%;text-align:center;padding:40px;color:#8b6f47;">No sample requests yet.</div></div>
      <?php else: foreach ($requests as $req): ?>
        <div class="table-row">
          <div class="table-cell"><?= htmlspecialchars($req['req_code']) ?></div>
          <div class="table-cell"><?= htmlspecialchars($req['wholesaler'] ?? '—') ?></div>
          <div class="table-cell"><?= htmlspecialchars($req['fabric_name']) ?></div>
          <div class="table-cell"><?= number_format($req['quantity'], 2) ?> m</div>
          <div class="table-cell"><?= htmlspecialchars($req['notes'] ?? '—') ?></div>
          <div class="table-cell status">
            <select disabled>
              <option <?= $req['status']=='requested'?'selected':'' ?>>Requested</option>
              <option <?= $req['status']=='accepted'?'selected':'' ?>>Accepted</option>
              <option <?= $req['status']=='rejected'?'selected':'' ?>>Rejected</option>
              <option <?= $req['status']=='shipped'?'selected':'' ?>>Shipped</option>
              <option <?= $req['status']=='delivered'?'selected':'' ?>>Delivered</option>
            </select>
          </div>
          <div class="table-cell actions">
            <?php if ($req['status'] === 'requested'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="request_id" value="<?= $req['sample_id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="action-btn">Approve</button>
              </form>
              <form method="POST" style="display:inline;" onsubmit="let r=prompt('Reason for rejection:');if(!r)return false;this.reject_reason.value=r;">
                <input type="hidden" name="request_id" value="<?= $req['sample_id'] ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="reject_reason" value="">
                <button type="submit" class="action-btn">Reject</button>
              </form>
            <?php endif; ?>

            <?php if (in_array($req['status'], ['accepted','shipped'])): ?>
              <button type="button" class="action-btn dispatch-toggle" onclick="toggleDispatch(this)">Dispatch</button>
              <form method="POST" class="dispatch-fields" style="display:none;">
                <input type="hidden" name="request_id" value="<?= $req['sample_id'] ?>">
                <input type="hidden" name="action" value="dispatch">
                <input type="date" name="est_date" required>
                <input type="text" name="courier" placeholder="Courier Name" required>
                <input type="url" name="tracking_url" placeholder="Tracking URL (optional)">
                <button type="submit" class="action-btn" style="margin-top:8px;">Submit Dispatch</button>
              </form>
            <?php endif; ?>

            <?php if ($req['status'] === 'shipped' && $req['tracking_url']): ?>
              <a href="<?= htmlspecialchars($req['tracking_url']) ?>" target="_blank" class="action-btn">Track</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </main>

  <script>
    function toggleDispatch(btn) {
      const form = btn.nextElementSibling;
      if (form && form.classList.contains('dispatch-fields')) {
        form.style.display = (form.style.display === 'none' || !form.style.display) ? 'flex' : 'none';
        btn.textContent = form.style.display === 'none' ? 'Dispatch' : 'Hide';
      }
    }
  </script>
</body>
</html>

<?php $conn->close(); ?>