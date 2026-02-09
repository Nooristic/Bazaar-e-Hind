<?php
session_start();

/* ───────────────── SECURITY ───────────────── */
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: /trial_project/src/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

/* ───────────────── CSRF ───────────────── */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* ───────────────── DB ───────────────── */
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) die("Database Error");
$conn->set_charset("utf8mb4");

/* ───────────────── AJAX HANDLER ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (($_POST['csrf'] ?? '') !== $csrf) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);
    $ip     = $_SERVER['REMOTE_ADDR'];

    $conn->begin_transaction();

    try {
        switch ($action) {

            /* ───── FABRICS ───── */
            case 'approve_listing':
                $stmt = $conn->prepare("
                    UPDATE fabrics 
                    SET status='approved', moderated_by=?, moderated_at=NOW() 
                    WHERE fabric_id=?
                ");
                $stmt->bind_param("ii", $admin_id, $id);
                break;

            case 'reject_listing':
                $stmt = $conn->prepare("
                    UPDATE fabrics 
                    SET status='rejected', moderated_by=?, moderated_at=NOW(),
                        rejected_reason='Rejected by admin'
                    WHERE fabric_id=?
                ");
                $stmt->bind_param("ii", $admin_id, $id);
                break;

            case 'flag_listing':
                $stmt = $conn->prepare("
                    UPDATE fabrics 
                    SET status='flagged', moderated_by=?, moderated_at=NOW(),
                        moderation_notes='Flagged by admin'
                    WHERE fabric_id=?
                ");
                $stmt->bind_param("ii", $admin_id, $id);
                break;

            /* ───── POSTS ───── */
            case 'ignore_post':
                $stmt = $conn->prepare("
                    UPDATE forum_posts 
                    SET status='active', moderated_by=?, moderated_at=NOW()
                    WHERE post_id=?
                ");
                $stmt->bind_param("ii", $admin_id, $id);
                break;

            case 'delete_post':
                $stmt = $conn->prepare("
                    UPDATE forum_posts 
                    SET status='deleted', moderated_by=?, moderated_at=NOW(),
                        moderation_reason='Deleted by admin'
                    WHERE post_id=?
                ");
                $stmt->bind_param("ii", $admin_id, $id);
                break;

            default:
                throw new Exception("Invalid action");
        }

        $stmt->execute();

        /* ───── AUDIT LOG ───── */
        $log = $conn->prepare("
            INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address)
            VALUES (?, ?, ?, 'Admin moderation action', ?)
        ");
        $log->bind_param("isis", $admin_id, $action, $id, $ip);
        $log->execute();

        $conn->commit();
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Action failed']);
    }
    exit;
}

/* ───────────────── FETCH DATA ───────────────── */
$listings = $conn->query("
    SELECT f.fabric_id, f.name, f.price, f.status, u.username
    FROM fabrics f
    JOIN users u ON u.user_id = f.user_id
    WHERE f.status IN ('pending_approval','flagged')
");

$posts = $conn->query("
    SELECT p.post_id, p.content, p.moderation_reason, u.username
    FROM forum_posts p
    JOIN users u ON u.user_id = p.user_id
    WHERE p.status='flagged'
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Moderation</title>
<link rel="stylesheet" href="../css_all_pages.css">

<style>
:root{
  --success:#66bb6a;
  --danger:#ef5350;
  --warning:#ffa726;
}

body{
  font-family: Inter, system-ui, sans-serif;
}

.container{
  max-width:1300px;
  margin:3rem auto;
  padding:0 2rem;
}

.section{
  margin-bottom:4rem;
}

.section h2 {
  display: inline-block;
  padding: 0.4rem 1rem;
  margin-bottom: 1.5rem;

  background: rgba(255, 255, 255, 0.85);
  color: #3a2a1f;

  border-radius: 12px;
  font-weight: 600;
  letter-spacing: 0.3px;

  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.cards{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
  gap:1.6rem;
}

.card{
  background:rgba(255,255,255,.95);
  border-radius:16px;
  padding:1.4rem;
  box-shadow:0 6px 18px rgba(0,0,0,.08);
  transition:.25s;
}

.card:hover{
  transform:translateY(-4px);
  box-shadow:0 12px 28px rgba(0,0,0,.15);
}

.meta{
  font-size:.85rem;
  color:#666;
  margin:.4rem 0;
}

.price{
  font-size:1.1rem;
  font-weight:600;
}

.actions{
  display:flex;
  gap:.6rem;
  margin-top:1rem;
}

.actions button{
  flex:1;
  border:none;
  padding:.55rem;
  border-radius:12px;
  font-size:.85rem;
  font-weight:500;
  cursor:pointer;
}

.approve{background:var(--success);color:#fff}
.reject{background:var(--danger);color:#fff}
.flag{background:var(--warning)}
.empty{color:#777;font-size:.95rem}
</style>
</head>

<body>

<video autoplay muted loop id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="header">
  <a href="admin_homepage.php" class="back-link">← Home</a>
  <span class="header-title">Content Moderation</span>
</div>

<div class="container">

<!-- ───────── FABRICS ───────── -->
<div class="section">
<h2>Fabric Listings</h2>

<?php if($listings->num_rows === 0): ?>
<p class="empty">No pending or flagged listings 🎉</p>
<?php endif; ?>

<div class="cards">
<?php while($f = $listings->fetch_assoc()): ?>
<div class="card" id="listing-<?= $f['fabric_id'] ?>">
  <h3><?= htmlspecialchars($f['name']) ?></h3>
  <div class="meta">Seller: <?= htmlspecialchars($f['username']) ?></div>
  <div class="price">₹<?= htmlspecialchars($f['price']) ?></div>

  <div class="actions">
    <button class="approve" onclick="act('approve_listing',<?= $f['fabric_id'] ?>)">Approve</button>
    <button class="reject" onclick="act('reject_listing',<?= $f['fabric_id'] ?>)">Reject</button>
    <button class="flag" onclick="act('flag_listing',<?= $f['fabric_id'] ?>)">Flag</button>
  </div>
</div>
<?php endwhile; ?>
</div>
</div>

<!-- ───────── POSTS ───────── -->
<div class="section">
<h2>Flagged Posts</h2>

<?php if($posts->num_rows === 0): ?>
<p class="empty">No flagged posts 🎉</p>
<?php endif; ?>

<div class="cards">
<?php while($p = $posts->fetch_assoc()): ?>
<div class="card" id="post-<?= $p['post_id'] ?>">
  <div class="meta">By <?= htmlspecialchars($p['username']) ?></div>
  <p><?= nl2br(htmlspecialchars($p['content'])) ?></p>

  <?php if($p['moderation_reason']): ?>
    <div class="meta">Reason: <?= htmlspecialchars($p['moderation_reason']) ?></div>
  <?php endif; ?>

  <div class="actions">
    <button class="approve" onclick="act('ignore_post',<?= $p['post_id'] ?>)">Ignore</button>
    <button class="reject" onclick="act('delete_post',<?= $p['post_id'] ?>)">Delete</button>
  </div>
</div>
<?php endwhile; ?>
</div>
</div>

</div>

<script>
const csrf = "<?= $csrf ?>";

function act(action,id){
  const dangerous = ['reject_listing','delete_post'];
  if (dangerous.includes(action)) {
    if (!confirm('Are you sure? This action cannot be undone.')) return;
  }

  fetch('moderation.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`action=${action}&id=${id}&csrf=${csrf}`
  })
  .then(r=>r.json())
  .then(d=>{
    if(d.success){
      const el = document.getElementById(
        `${action.includes('post')?'post':'listing'}-${id}`
      );
      el.style.opacity = .4;
      setTimeout(()=>el.remove(),300);
    } else {
      alert(d.message || 'Action failed');
    }
  });
}
</script>

</body>
</html>
<?php $conn->close(); ?>
