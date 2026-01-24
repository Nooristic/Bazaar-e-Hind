<?php
session_start();

/* ───────────────── SECURITY ───────────────── */
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: /trial_project/src/login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

/* DB */
$conn = new mysqli('localhost', 'root', '', 'bazaar_e_hind');
if ($conn->connect_error) die("DB Error");

/* ───────────────── AJAX HANDLER ───────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = $_POST;

    if (($data['csrf'] ?? '') !== $csrf) {
        echo json_encode(['success'=>false,'message'=>'Invalid CSRF']);
        exit;
    }

    $action = $data['action'] ?? '';
    $id = (int)($data['id'] ?? 0);
    $ip = $_SERVER['REMOTE_ADDR'];

    $conn->begin_transaction();

    try {
        switch ($action) {

            /* ───── FABRICS ───── */
            case 'approve_listing':
                $stmt = $conn->prepare(
                    "UPDATE fabrics SET status='approved', moderated_by=?, moderated_at=NOW() WHERE fabric_id=?"
                );
                $stmt->bind_param('ii', $admin_id, $id);
                $stmt->execute();
                break;

            case 'reject_listing':
                $stmt = $conn->prepare(
                    "UPDATE fabrics SET status='rejected', moderated_by=?, moderated_at=NOW(), rejected_reason='Rejected by admin' WHERE fabric_id=?"
                );
                $stmt->bind_param('ii', $admin_id, $id);
                $stmt->execute();
                break;

            case 'flag_listing':
                $stmt = $conn->prepare(
                    "UPDATE fabrics SET status='flagged', moderated_by=?, moderated_at=NOW(), moderation_notes='Flagged by admin' WHERE fabric_id=?"
                );
                $stmt->bind_param('ii', $admin_id, $id);
                $stmt->execute();
                break;

            /* ───── FORUM POSTS ───── */
            case 'delete_post':
                $stmt = $conn->prepare(
                    "UPDATE forum_posts SET status='deleted', moderated_by=?, moderated_at=NOW(), moderation_reason='Deleted by admin' WHERE post_id=?"
                );
                $stmt->bind_param('ii', $admin_id, $id);
                $stmt->execute();
                break;

            case 'ignore_post':
                $stmt = $conn->prepare(
                    "UPDATE forum_posts SET status='active', moderated_by=?, moderated_at=NOW() WHERE post_id=?"
                );
                $stmt->bind_param('ii', $admin_id, $id);
                $stmt->execute();
                break;

            case 'edit_post':
                $content = trim($data['content']);
                $stmt = $conn->prepare(
                    "UPDATE forum_posts SET content=?, status='active', moderated_by=?, moderated_at=NOW(), moderation_reason='Edited by admin' WHERE post_id=?"
                );
                $stmt->bind_param('sii', $content, $admin_id, $id);
                $stmt->execute();
                break;
        }

        /* AUDIT LOG */
        $stmt = $conn->prepare(
            "INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address)
             VALUES (?, ?, ?, 'Admin moderation action', ?)"
        );
        $stmt->bind_param('isis', $admin_id, $action, $id, $ip);
        $stmt->execute();

        $conn->commit();
        echo json_encode(['success'=>true]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'message'=>'DB Error']);
    }
    exit;
}

/* ───────────────── FETCH DATA ───────────────── */
$listings = $conn->query("
    SELECT f.fabric_id, f.name, f.price, f.image_urls, f.status, u.username
    FROM fabrics f
    JOIN users u ON f.user_id = u.user_id
    WHERE f.status IN ('pending_approval','flagged')
");

$posts = $conn->query("
    SELECT p.post_id, p.content, p.moderation_reason, u.username
    FROM forum_posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.status='flagged'
");
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Moderation</title>
<link rel="stylesheet" href="../css_all_pages.css">
<style>
.container{max-width:1280px;margin:2.5rem auto;padding:0 2rem}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem}
.card{background:#fff;border-radius:14px;padding:1.3rem;box-shadow:0 4px 14px rgba(180,140,60,.15)}
.actions button{padding:7px 16px;border-radius:12px;border:none;cursor:pointer}
.approve{background:#c8e6c9}
.reject{background:#ffcdd2}
.flag{background:#fff3cd}
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

<h2>Fabric Listings</h2>
<div class="cards-grid">
<?php while($r=$listings->fetch_assoc()): ?>
<div class="card" id="listing-<?= $r['fabric_id'] ?>">
<h3><?= htmlspecialchars($r['name']) ?></h3>
<p>₹<?= htmlspecialchars($r['price']) ?></p>
<p>Seller: <?= htmlspecialchars($r['username']) ?></p>
<div class="actions">
<button class="approve" onclick="act('approve_listing',<?= $r['fabric_id']?>)">Approve</button>
<button class="reject" onclick="act('reject_listing',<?= $r['fabric_id']?>)">Reject</button>
<button class="flag" onclick="act('flag_listing',<?= $r['fabric_id']?>)">Flag</button>
</div>
</div>
<?php endwhile; ?>
</div>

<h2>Flagged Posts</h2>
<div class="cards-grid">
<?php while($p=$posts->fetch_assoc()): ?>
<div class="card" id="post-<?= $p['post_id'] ?>">
<p><?= htmlspecialchars(substr($p['content'],0,120)) ?>…</p>
<div class="actions">
<button class="approve" onclick="act('ignore_post',<?= $p['post_id']?>)">Ignore</button>
<button class="reject" onclick="act('delete_post',<?= $p['post_id']?>)">Delete</button>
</div>
</div>
<?php endwhile; ?>
</div>

</div>

<script>
const csrf="<?= $csrf ?>";
function act(action,id){
fetch('moderation.php',{
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:`action=${action}&id=${id}&csrf=${csrf}`
}).then(r=>r.json()).then(d=>{
if(d.success) document.getElementById(`${action.includes('post')?'post':'listing'}-${id}`).remove();
});
}
</script>
</body>
</html>
<?php $conn->close(); ?>
