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

$filter = isset($_GET['filter']) && $_GET['filter'] === 'exclusive' ? 'restricted' : 'public';
$visibility_sql = ($filter === 'restricted') ? "visibility_type = 'restricted'" : "visibility_type IN ('public', 'restricted')";

$stmt = $mysqli->prepare("SELECT fabric_id, name, description, image_urls, visibility_type, status FROM fabrics WHERE user_id = ? AND is_active = 1 AND $visibility_sql ORDER BY created_at DESC");
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
  <base href="../../src/Manufacturer/">

  <style>
    :root { --bazaar-bg: rgba(255, 245, 235, 0.92); }
    body { background: transparent; font-family: 'Georgia', serif; margin: 0; padding: 0; color: #3e2723; }
    #bg-video { position: fixed; top: 0; left: 0; min-width: 100vw; min-height: 100vh; width: 100vw; height: 100vh; object-fit: cover; z-index: -1; opacity: 1; pointer-events: none; }
    
    .header { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; gap: 18px; background: var(--bazaar-bg); border-bottom: 2px solid #e0c68c; }
    .back-link { color: #6d4c1e; text-decoration: none; font-size: 1.2rem; font-weight: bold; display: flex; align-items: center; }
    .back-link:hover { color: #a67c52; }
    .header-title { font-size: 2rem; font-weight: bold; letter-spacing: 1px; color: #5d3a1a; }

    .top-bar { display: flex; justify-content: flex-start; align-items: center; gap: 18px; padding: 22px 38px 0 38px; background: var(--bazaar-bg); }
    .tab-btn { padding: 7px 22px; border-radius: 18px; border: 2px solid #c7a76c; background: var(--bazaar-bg); font-size: 1.1rem; cursor: pointer; transition: all 0.2s; }
    .tab-btn.active, .tab-btn:hover { background: #ffe7b3; border-color: #a67c52; font-weight: bold; }
    .add-btn { margin-left: auto; padding: 7px 22px; border-radius: 18px; border: 2px solid #c7a76c; background: #ffe7b3; font-size: 1.1rem; font-weight: bold; color: #6d4c1e; cursor: pointer; }
    .add-btn:hover { background: #ffd180; border-color: #a67c52; }

    .products-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 30px; padding: 38px; margin-top: 0; }
    .product-card { background: var(--bazaar-bg); border-radius: 12px; box-shadow: 0 2px 12px rgba(180,140,60,0.08); padding: 0 0 18px 0; border: 2px solid #e0c68c; transition: all 0.2s; }
    .product-card:hover { box-shadow: 0 6px 24px rgba(180,140,60,0.13); border-color: #c7a76c; }
    .product-img { width: 90%; height: 110px; background: #e0c68c; border-radius: 8px; margin: 18px auto 10px; overflow: hidden; }
    .product-img img { width: 100%; height: 100%; object-fit: cover; }
    .product-title { font-size: 1.1rem; font-weight: bold; margin: 0 12px; color: #6d4c1e; text-align: center; }
    .product-desc { font-size: 0.97rem; color: #8d6e3f; text-align: center; opacity: 0.85; margin: 4px 12px 10px; height: 40px; overflow: hidden; }
    .status { padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: bold; margin: 8px auto; display: inline-block; }
    .pending { background: #fff3cd; color: #856404; }
    .approved { background: #d4edda; color: #155724; }
    .rejected { background: #f8d7da; color: #721c24; }

    .empty { text-align: center; padding: 80px 20px; font-size: 1.4rem; color: #8d6e3f; }
  </style>
</head>
<body>

  <video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
  </video>

  <div class="header">
    <a href="bazaar-homepage.php" class="back-link">← Home</a>
    <span class="header-title">Product/Fabric Management</span>
    <div class="profile-btn">Profile Icon</div>
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
        Click the <strong>+ Add Fabric</strong> button to add your first fabric!
      </div>
    <?php else: ?>
      <?php foreach ($fabrics as $f):
    $images = json_decode($f['image_urls'] ?? '[]', true);
    $first = !empty($images) ? '../uploads/' . basename($images[0]) : '../assests/no-image.jpg';    $desc = strlen($f['description']) > 70 ? substr($f['description'],0,70).'...' : $f['description'];
    $status_class = $f['status']==='approved' ? 'approved' : ($f['status']==='rejected' ? 'rejected' : 'pending');
?>
    <div class="product-card">
      <div class="product-img"><img src="<?= $first ?>" alt="<?= htmlspecialchars($f['name']) ?>"></div>
      <div class="product-title"><?= htmlspecialchars($f['name']) ?></div>
      <div class="product-desc"><?= htmlspecialchars($desc) ?></div>
      <div class="status <?= $status_class ?>"><?= ucwords(str_replace('_',' ',$f['status'])) ?></div>
    </div>
<?php endforeach; ?>
    <?php endif; ?>
  </div>

</body>
</html>