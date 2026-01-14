<?php
session_start();

/* ================= AUTH ================= */
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: /trial_project/src/login.html");
    exit();
}

$admin_id = $_SESSION['user_id'];

/* ================= DB ================= */
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB Connection Failed");
}
$conn->set_charset("utf8mb4");

/* ================= AJAX MODERATION ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    $id     = (int)$_POST['id'];
    $action = $_POST['action'];
    $reason = trim($_POST['reason'] ?? 'No reason provided');
    $ip     = $_SERVER['REMOTE_ADDR'];

    if ($_POST['type'] === 'fabric') {

        if (!in_array($action, ['approve', 'reject'])) {
            echo json_encode(['success' => false]);
            exit();
        }

        $status = ($action === 'approve') ? 'approved' : 'rejected';

        $stmt = $conn->prepare(
            "UPDATE fabrics
             SET status = ?, moderation_notes = ?, moderated_by = ?, moderated_at = NOW()
             WHERE fabric_id = ? AND status = 'pending'"
        );
        $stmt->bind_param("ssii", $status, $reason, $admin_id, $id);
        $stmt->execute();

        $log_type = strtoupper("FABRIC_$action");

    } else {

        $stmt = $conn->prepare(
            "UPDATE forum_posts
             SET status = 'deleted', moderation_reason = ?, moderated_by = ?, moderated_at = NOW()
             WHERE post_id = ?"
        );
        $stmt->bind_param("sii", $reason, $admin_id, $id);
        $stmt->execute();

        $log_type = "POST_DELETED";
    }

    /* ===== AUDIT LOG ===== */
    $desc = "$log_type | ID: $id | Reason: $reason";
    $log = $conn->prepare(
        "INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address)
         VALUES (?, ?, ?, ?, ?)"
    );
    $log->bind_param("isiss", $admin_id, $log_type, $id, $desc, $ip);
    $log->execute();

    echo json_encode(['success' => true]);
    exit();
}

/* ================= FETCH PENDING FABRICS ================= */
$fabrics = $conn->query(
    "SELECT f.fabric_id, f.name, f.image_urls, f.visibility_type,
            u.company_name
     FROM fabrics f
     JOIN users u ON f.user_id = u.user_id
     WHERE f.status = 'pending' AND f.is_active = 1
     ORDER BY f.created_at DESC"
);

/* ================= FETCH FLAGGED POSTS ================= */
$posts = $conn->query(
    "SELECT p.post_id, p.title, LEFT(p.content,100) AS content, u.company_name
     FROM forum_posts p
     JOIN users u ON p.user_id = u.user_id
     WHERE p.status = 'flagged'
     ORDER BY p.created_at DESC"
);

/* ================= AUDIT LOGS ================= */
$logs = $conn->query(
    "SELECT a.timestamp, a.action_type, a.description, u.username
     FROM audit_logs a
     JOIN users u ON a.admin_id = u.user_id
     ORDER BY a.timestamp DESC
     LIMIT 10"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Moderation</title>

<base href="../../src/Admin/">
<link rel="stylesheet" href="../css_all_pages.css">

<style>
/* ===== BACKGROUND VIDEO (keep as is) ===== */
#bg-video {
    position: fixed;
    right: 0;
    bottom: 0;
    min-width: 100%;
    min-height: 100%;
    z-index: -1;
    object-fit: cover;
}

/* ===== MAIN CONTAINER ===== */
.listings-container {
    padding: 40px 5%;
    max-width: 1400px;
    margin: 0 auto;
    color: #fff;
}

/* ===== LARGE BANNER CARDS ===== */
.moderation-banner {
    position: relative;
    height: 280px;                    /* Tall like in screenshot */
    margin-bottom: 35px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.5);
    transition: all 0.35s ease;
    cursor: pointer;
}

.moderation-banner:hover {
    transform: scale(1.02);
    box-shadow: 0 15px 50px rgba(0,0,0,0.6);
}

.moderation-banner::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.25), rgba(0,0,0,0.75));
    z-index: 1;
}

.moderation-banner img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.moderation-banner:hover img {
    transform: scale(1.08);
}

.banner-content {
    position: absolute;
    inset: 0;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
}

.banner-title {
    font-family: 'Marcellus', serif;
    font-size: 3.2rem;
    font-weight: 700;
    color: #fff;
    text-shadow: 0 3px 12px rgba(0,0,0,0.8);
    letter-spacing: 1.5px;
    margin: 0;
}

/* Responsive */
@media (max-width: 768px) {
    .moderation-banner {
        height: 220px;
    }
    .banner-title {
        font-size: 2.4rem;
    }
}
</style>
</head>

<body>

<!-- BACKGROUND VIDEO -->
<video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="header">
    <a href="admin_dashboard.php" class="back-link">← Dashboard</a>
    <span class="header-title">Listings & Moderation</span>
    <div class="profile-btn">
        <svg viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="5"/>
            <path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/>
        </svg>
    </div>
</div>

<!-- ======= NEW BANNER STRUCTURE STARTS HERE ======= -->
<div class="listings-container">

    <!-- Pending Fabrics Banner -->
    <a href="#pending-fabrics" class="moderation-banner">
        <img src="https://thumbs.dreamstime.com/b/luxurious-flowing-orange-silk-fabric-close-up-smooth-elegantly-draped-illuminated-ai-generated-357557490.jpg" alt="Silk Background">
        <div class="banner-content">
            <h2 class="banner-title">Pending Fabrics</h2>
        </div>
    </a>

    <!-- Flagged Posts Banner -->
    <a href="#flagged-posts" class="moderation-banner">
        <img src="https://thumbs.dreamstime.com/b/luxurious-orange-satin-fabric-backdrop-elegant-folds-smooth-texture-versatile-material-offers-rich-color-perfect-events-396710088.jpg" alt="Silk Background">
        <div class="banner-content">
            <h2 class="banner-title">Flagged Posts</h2>
        </div>
    </a>

    <!-- Recent Admin Actions Banner -->
    <div class="moderation-banner" style="cursor: default;">
        <img src="https://www.shutterstock.com/image-photo/dark-orange-brown-silk-satin-260nw-2213215151.jpg" alt="Silk Background">
        <div class="banner-content">
            <h2 class="banner-title">Recent Admin Actions</h2>
        </div>
    </div>

</div>
<!-- ======= NEW BANNER STRUCTURE ENDS HERE ======= -->
<!-- ===== FABRICS ===== -->
<h2 style="padding-left:38px;">Pending Fabrics</h2>
<div class="admin-grid">
<?php while ($f = $fabrics->fetch_assoc()):
    $imgs = json_decode($f['image_urls'] ?? '[]', true);
    $img  = !empty($imgs) ? '/trial_project/'.$imgs[0] : '/trial_project/assets/fabric_1.png';
?>
<div class="moderation-card" id="f<?= $f['fabric_id'] ?>">
    <div class="moderation-img">
        <img src="<?= htmlspecialchars($img) ?>" onerror="this.src='/trial_project/assets/fabric_1.png';">
    </div>

    <div class="moderation-title"><?= htmlspecialchars($f['name']) ?></div>
    <div class="moderation-meta">
        <?= htmlspecialchars($f['company_name']) ?><br>
        Visibility: <?= ucfirst($f['visibility_type']) ?>
    </div>

    <div class="admin-actions">
        <button class="approve-btn" onclick="moderate('fabric',<?= $f['fabric_id'] ?>,'approve')">Approve</button>
        <button class="reject-btn" onclick="moderate('fabric',<?= $f['fabric_id'] ?>,'reject')">Reject</button>
    </div>
</div>
<?php endwhile; ?>
</div>

<!-- ===== FLAGGED POSTS ===== -->
<h2 style="padding-left:38px;">Flagged Posts</h2>
<div class="admin-grid">
<?php while ($p = $posts->fetch_assoc()): ?>
<div class="moderation-card" id="p<?= $p['post_id'] ?>">
    <div class="moderation-title"><?= htmlspecialchars($p['title']) ?></div>
    <div class="moderation-meta">
        <?= htmlspecialchars($p['company_name']) ?><br>
        <?= htmlspecialchars($p['content']) ?>...
    </div>
    <div class="admin-actions">
        <button class="reject-btn" onclick="moderate('post',<?= $p['post_id'] ?>,'delete')">Delete</button>
    </div>
</div>
<?php endwhile; ?>
</div>

<!-- ===== LOGS ===== -->
<h2 style="padding-left:38px;">Recent Admin Actions</h2>
<ul style="padding:0 38px 50px;">
<?php while ($l = $logs->fetch_assoc()): ?>
    <li>
        <?= $l['timestamp'] ?> —
        <strong><?= $l['action_type'] ?></strong> —
        <?= htmlspecialchars($l['description']) ?>
        (<?= htmlspecialchars($l['username']) ?>)
    </li>
<?php endwhile; ?>
</ul>

<script>
async function moderate(type,id,action){
    const reason = prompt("Reason (optional):","");
    if(!confirm(`Confirm ${action}?`)) return;

    const res = await fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`type=${type}&id=${id}&action=${action}&reason=${encodeURIComponent(reason)}`
    });

    const data = await res.json();
    if(data.success){
        document.getElementById(type[0]+id).remove();
        alert('Action completed');
    }
}
</script>

</body>
</html>

<?php $conn->close(); ?>