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
  <link rel="stylesheet" href="../css_all_pages.css">
  <style>
  .sample-container {
  flex: 1;
  padding: 32px;
  display: flex;
  flex-direction: column;
}

.responsive-table {
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-x: auto;
}

.table-header,
.table-row {
  display: grid;
  grid-template-columns: 120px 1.5fr 1.2fr 120px 1.5fr 140px 260px;
  background: #fff;
  border-radius: 14px;
  border: 1.5px solid var(--border);
  box-shadow: 0 8px 24px rgba(0,0,0,.04);
}

.table-header {
  background: #fff6dc;
  font-weight: 600;
  color: #6d4c1e;
}

.table-cell {
  padding: 14px 16px;
  display: flex;
  align-items: center;
  font-size: .95rem;
}

.table-cell.actions {
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
}

/* Status pills */
.status-pill {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: .85rem;
  font-weight: 600;
  text-align: center;
}

.status-requested { background:#fff1c1; color:#8a6d1d; }
.status-accepted  { background:#e8f5e9; color:#2e7d32; }
.status-rejected  { background:#fdecea; color:#c62828; }
.status-shipped   { background:#e3f2fd; color:#1565c0; }
.status-delivered { background:#ede7f6; color:#4527a0; }

/* Buttons */
.action-btn {
  padding: 8px 14px;
  border-radius: 10px;
  border: 1.5px solid var(--border);
  background: var(--btn);
  cursor: pointer;
  font-weight: 500;
  transition: all .15s ease;
}

.action-btn:hover {
  background: var(--btn-hover);
  transform: translateY(-1px);
}

/* Dispatch panel */
.dispatch-fields {
  display: none;
  flex-direction: column;
  gap: 8px;
  background: #fff8e8;
  padding: 12px;
  border-radius: 10px;
  border: 1px dashed var(--border);
}

.dispatch-fields input {
  padding: 8px 10px;
  border-radius: 8px;
  border: 1px solid var(--border);
  font-size: .9rem;
}

/* Mobile */
@media (max-width: 1100px) {
  .table-header,
  .table-row {
    grid-template-columns: 1fr;
  }

  .table-header {
    display: none;
  }

  .table-cell {
    padding: 10px 14px;
  }

  .table-row {
    gap: 6px;
  }
}
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
          <div class="table-cell">
  <span class="status-pill status-<?= $req['status'] ?>">
    <?= ucfirst($req['status']) ?>
  </span>
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
             <button type="button" class="action-btn" onclick="toggleDispatch(this)">
              Dispatch
              </button>
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
  const panel = btn.nextElementSibling;
  const open = panel.style.display === 'flex';

  panel.style.display = open ? 'none' : 'flex';
  btn.textContent = open ? 'Dispatch' : 'Hide Dispatch';
}

  </script>
</body>
</html>

<?php $conn->close(); ?>