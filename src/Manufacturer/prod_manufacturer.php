<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$username = strtoupper(htmlspecialchars($_SESSION['username']));

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) die("Connection failed");
$mysqli->set_charset("utf8mb4");

$filter = (isset($_GET['filter']) && $_GET['filter'] === 'exclusive') ? 'restricted' : 'public';
$visibility_sql = ($filter === 'restricted')
    ? "visibility_type = 'restricted'"
    : "visibility_type IN ('public', 'restricted')";

$stmt = $mysqli->prepare("
    SELECT
        fabric_id,
        name,
        description,
        composition,
        gsm,
        moq,
        color_options,
        image_urls,
        visibility_type,
        status
    FROM fabrics
    WHERE user_id = ?
      AND is_active = 1
      AND $visibility_sql
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$fabrics = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Product/Fabric Management</title>

<!-- base is OK because we use ABSOLUTE image URLs -->
<base href="../../src/Manufacturer/">

<style>
:root { --bazaar-bg: rgba(255, 245, 235, 0.92); }
body { background: transparent; font-family: 'Georgia', serif; margin:0; padding:0; color:#3e2723; }
#bg-video { position:fixed; top:0; left:0; width:100vw; height:100vh; object-fit:cover; z-index:-1; }

.header {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    padding: 18px 24px;
    background: var(--bazaar-bg);
    border-bottom: 2px solid #e0c68c;
}
.back-link { color:#6d4c1e; text-decoration:none; font-size:1.2rem; font-weight:bold; }
.header-title { font-size:2rem; font-weight:bold; color:#5d3a1a; }

.top-bar {
    display:flex; gap:18px; padding:22px 38px 0;
    background:var(--bazaar-bg);
}
.tab-btn, .add-btn {
    padding:7px 22px; border-radius:18px;
    border:2px solid #c7a76c;
    background:var(--bazaar-bg);
    font-size:1.1rem; cursor:pointer;
}
.add-btn {
        margin-left: auto;  
    padding:7px 22px; border-radius:18px;
    border:2px solid #c7a76c;
    background:var(--bazaar-bg);
    font-size:1.1rem; cursor:pointer;
}
.tab-btn.active, .tab-btn:hover,
.add-btn:hover { background:#ffe7b3; border-color:#a67c52; }

.action-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.edit-btn, .delete-btn {
    padding: 6px 14px;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: bold;
    border: none;
    cursor: pointer;
    text-decoration: none;
}

.edit-btn {
    background: #dbeafe;
    color: #1e40af;
}

.edit-btn:hover {
    background: #bfdbfe;
}

.delete-btn {
    background: #fee2e2;
    color: #991b1b;
}

.delete-btn:hover {
    background: #fecaca;
}


.products-grid {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
    gap:30px; padding:38px;
}
.product-card {
    background:var(--bazaar-bg);
    border-radius:12px;
    border:2px solid #e0c68c;
    box-shadow:0 2px 12px rgba(180,140,60,.08);
}
.product-img {
    width:90%; height:110px;
    margin:18px auto 10px;
    overflow:hidden; border-radius:8px;
}
.product-img img { width:100%; height:100%; object-fit:cover; }

.product-title { text-align:center; font-weight:bold; color:#6d4c1e; }
.product-desc { text-align:center; font-size:.95rem; color:#8d6e3f; height:40px; overflow:hidden; }

.status { padding:4px 12px; border-radius:20px; font-size:.8rem; font-weight:bold; margin:8px auto; display:inline-block; }
.pending { background:#fff3cd; color:#856404; }
.approved { background:#d4edda; color:#155724; }
.rejected { background:#f8d7da; color:#721c24; }

.empty { text-align:center; padding:80px; font-size:1.4rem; color:#8d6e3f; }
.product-details {
    font-size: 0.9rem;
    color: #6d4c1e;
    padding: 0 14px;
    line-height: 1.4;
}

.product-details div {
    margin: 3px 0;
}

.product-details span {
    font-weight: bold;
    color: #5d3a1a;
}

</style>
</head>

<body>

<!-- background video -->
<video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
</video>
<div class="header">
    <a href="bazaar-homepage.php" class="back-link">← Home</a>
    <span class="header-title" style="text-align:center;">
        Product/Fabric Management
    </span>
    <div></div>
</div>
<div class="top-bar">
    <button class="tab-btn <?= $filter === 'public' ? 'active' : '' ?>"
        onclick="location.href='prod_manufacturer.php'">All Products</button>

    <button class="tab-btn <?= $filter === 'restricted' ? 'active' : '' ?>"
        onclick="location.href='prod_manufacturer.php?filter=exclusive'">Exclusive</button>

    <button class="add-btn" onclick="location.href='add_fabric.php'">+ Add Fabric</button>
</div>

<div class="products-grid">
<?php if (empty($fabrics)): ?>
    <div class="empty">
        No fabrics found.<br><br>
        Click <strong>+ Add Fabric</strong> to add your first fabric.
    </div>
<?php else: ?>
<?php foreach ($fabrics as $f):

    /* ✅ DECODE IMAGE JSON (THIS WAS THE MAIN BUG) */
    $images = json_decode($f['image_urls'] ?? '[]', true);
$first = !empty($images)
    ? '/trial_project/' . $images[0]
    : '/trial_project/assests/fabric_1.png';


    $desc = strlen($f['description']) > 70
        ? substr($f['description'], 0, 70) . '...'
        : $f['description'];

    $status_class = ($f['status'] === 'approved')
        ? 'approved'
        : (($f['status'] === 'rejected') ? 'rejected' : 'pending');
?>
    <div class="product-card">
        <div class="product-img">
            <img src="<?= htmlspecialchars($first) ?>"
                 alt="<?= htmlspecialchars($f['name']) ?>"
                 onerror="this.src='/trial_project/assests/fabric_1.png';">
        </div>

        <div class="product-title"><?= htmlspecialchars($f['name']) ?></div>

<!-- ✅ ADD THIS BLOCK -->
<div class="product-details">
    <div>
        <span>Composition:</span>
        <?= $f['composition'] !== null && $f['composition'] !== ''
            ? htmlspecialchars($f['composition'])
            : '—'; ?>
    </div>

    <div>
        <span>GSM:</span>
        <?= $f['gsm'] !== null
            ? (int)$f['gsm']
            : '—'; ?>
    </div>

    <div>
        <span>MOQ:</span>
        <?= $f['moq'] !== null
            ? (int)$f['moq']
            : '—'; ?>
    </div>

    <div>
        <span>Colors:</span>
        <?php
            $colors = json_decode($f['color_options'] ?? '[]', true);
            echo !empty($colors)
                ? htmlspecialchars(implode(', ', $colors))
                : '—';
        ?>
    </div>

    <div>
        <span>Visibility:</span>
        <?= ucfirst($f['visibility_type']) ?>
    </div>
</div>

<!-- ✅ END DETAILS -->

<div class="status <?= $status_class ?>">
    <?= ucwords(str_replace('_',' ',$f['status'])) ?>
</div>

        <div class="action-buttons">
    <a href="edit_fabric.php?id=<?= $f['fabric_id'] ?>" class="edit-btn">Edit</a>

    <form method="POST" action="delete_fabric.php"
          onsubmit="return confirm('Are you sure you want to delete this fabric?');"
          style="display:inline;">
        <input type="hidden" name="fabric_id" value="<?= $f['fabric_id'] ?>">
        <button type="submit" class="delete-btn">Delete</button>
    </form>
</div>
    </div>
<?php endforeach; ?>
<?php endif; ?>
</div>

</body>
</html>
