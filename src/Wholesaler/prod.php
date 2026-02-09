<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) {
    die("Database connection failed");
}
$mysqli->set_charset("utf8mb4");

$wholesaler_id = (int) $_SESSION['user_id'];

/* ===============================
   FETCH APPROVED & AVAILABLE FABRICS
   (EXCLUSIVITY ENFORCED)
================================ */
$stmt = $mysqli->prepare("
    SELECT DISTINCT
        f.fabric_id,
        f.user_id AS manufacturer_id,
        f.name,
        f.description,
        f.composition,
        f.gsm,
        f.moq,
        f.price,
        f.image_urls,
        f.visibility_type,
        u.company_name AS manufacturer_name
    FROM fabrics f
    JOIN users u ON u.user_id = f.user_id
    WHERE f.status = 'approved'
      AND f.is_active = 1
      AND NOT EXISTS (
          SELECT 1
          FROM exclusivity_agreements ea
          WHERE ea.manufacturer_id = f.user_id
            AND ea.status IN ('pending_manufacturer','active')
            AND (ea.end_date IS NULL OR ea.end_date >= CURDATE())
            AND FIND_IN_SET(
                f.fabric_id,
                REPLACE(REPLACE(ea.fabric_ids,'[',''),']','')
            ) > 0
      )
    ORDER BY f.created_at DESC
");

$stmt->execute();
$result  = $stmt->get_result();
$fabrics = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ===============================
   CART COUNT
================================ */
$cartCount = 0;
$countStmt = $mysqli->prepare("SELECT COUNT(*) FROM cart WHERE wholesaler_id = ?");
$countStmt->bind_param("i", $wholesaler_id);
$countStmt->execute();
$countStmt->bind_result($cartCount);
$countStmt->fetch();
$countStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Browse Catalog / Fabrics</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="../css_all_pages.css">

<style>
.products-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:30px;padding:40px}
.product-card{background:var(--bazaar-bg);border:2px solid var(--border);border-radius:16px;padding:22px;cursor:pointer}
.product-img{height:150px;overflow:hidden;border-radius:10px;margin-bottom:14px}
.product-img img{width:100%;height:100%;object-fit:cover}
.product-title{text-align:center;font-weight:bold;color:#6d4c1e}

.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;justify-content:center;align-items:center}
.modal-content{background:#fffbe6;padding:30px;width:520px;max-height:80vh;overflow-y:auto;border-radius:20px;position:relative}
.modal-content img{width:100%;height:200px;object-fit:cover;border-radius:12px;margin-bottom:15px}
.close{position:absolute;top:12px;right:16px;font-size:24px;cursor:pointer}

.request-sample-btn{
  margin-top:20px;padding:12px 28px;border-radius:24px;
  background:#6d4c1e;color:#fffbe6;border:none;font-weight:bold;
  cursor:pointer
}
.request-sample-btn:hover{background:#8d6e3f}

#bg-video{pointer-events:none}
.products-grid,.product-card{position:relative;z-index:2}
</style>
</head>

<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="/trial_project/assests/silk video.mp4" type="video/mp4">
</video>

<div class="header">
  <a href="home_page.php" class="back-link">← Home</a>
  <span class="header-title">Browse Catalog / Fabrics</span>

  <a href="cart.php" class="cart-link">
    🛒 Cart
    <?php if ($cartCount > 0): ?>
      <span style="background:#c0392b;color:#fff;border-radius:50%;padding:2px 8px;font-size:.75rem">
        <?= $cartCount ?>
      </span>
    <?php endif; ?>
  </a>
</div>

<div class="products-grid">
<?php foreach ($fabrics as $f):
    $images = json_decode($f['image_urls'] ?? '[]', true);
    $first  = !empty($images)
        ? '/trial_project/' . $images[0]
        : '/trial_project/assests/fabric_1.png';
?>
<div class="product-card"
  onclick="openModal(this)"
  data-fabric-id="<?= $f['fabric_id'] ?>"
  data-manufacturer-id="<?= $f['manufacturer_id'] ?>"
  data-name="<?= htmlspecialchars($f['name']) ?>"
  data-manufacturer="<?= htmlspecialchars($f['manufacturer_name']) ?>"
  data-price="<?= number_format($f['price'],2) ?>"
  data-description="<?= htmlspecialchars($f['description']) ?>"
  data-composition="<?= htmlspecialchars($f['composition']) ?>"
  data-gsm="<?= $f['gsm'] ?>"
  data-moq="<?= $f['moq'] ?>"
  data-visibility="<?= $f['visibility_type'] ?>"
  data-image="<?= $first ?>"
>
  <div class="product-img"><img src="<?= $first ?>"></div>
  <div class="product-title"><?= htmlspecialchars($f['name']) ?></div>
  <div style="text-align:center;color:#8d6e3f;font-size:.85rem">
    by <?= htmlspecialchars($f['manufacturer_name']) ?>
  </div>
  <div style="text-align:center;font-weight:bold">
    ₹<?= number_format($f['price'],2) ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- MODAL -->
<div id="fabricModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeModal()">×</span>

    <img id="modalImage">
    <h2 id="modalName"></h2>
    <p><strong>Manufacturer:</strong> <span id="modalManufacturer"></span></p>
    <p><strong>Price:</strong> ₹<span id="modalPrice"></span></p>
    <p><strong>Description:</strong> <span id="modalDescription"></span></p>
    <p><strong>Composition:</strong> <span id="modalComposition"></span></p>
    <p><strong>GSM:</strong> <span id="modalGSM"></span></p>
    <p><strong>MOQ:</strong> <span id="modalMOQ"></span></p>
    <p><strong>Visibility:</strong> <span id="modalVisibility"></span></p>

    <button onclick="requestSample()" class="request-sample-btn">Request Sample</button>
    <p id="sampleMsg" style="display:none"></p>

    <hr style="margin:18px 0">

    <div style="display:flex;gap:12px;justify-content:center">
      <label><strong>Qty:</strong></label>
      <input type="number" id="cartQty" min="1" value="1"
        style="width:90px;padding:8px;border-radius:10px;border:1px solid #c7a76c">
    </div>

    <button id="addCartBtn" onclick="addToCart()" type="button"
      style="margin-top:18px;padding:14px 30px;border-radius:26px;
      background:#c7a76c;border:none;font-weight:bold;cursor:pointer">
      Add to Cart
    </button>

    <p id="cartMsg" style="margin-top:12px;font-size:.9rem;display:none"></p>
  </div>
</div>

<script>
let currentFabricId = null;
let currentManufacturerId = null;
let currentMOQ = 1;

function openModal(card) {
  currentFabricId = card.dataset.fabricId;
  currentManufacturerId = card.dataset.manufacturerId;
  currentMOQ = parseInt(card.dataset.moq || 1);

  modalImage.src        = card.dataset.image;
  modalName.textContent = card.dataset.name;
  modalManufacturer.textContent = card.dataset.manufacturer;
  modalPrice.textContent = card.dataset.price;
  modalDescription.textContent = card.dataset.description;
  modalComposition.textContent = card.dataset.composition;
  modalGSM.textContent = card.dataset.gsm;
  modalMOQ.textContent = card.dataset.moq;
  modalVisibility.textContent = card.dataset.visibility;

  cartQty.min = currentMOQ;
  cartQty.value = currentMOQ;

  cartMsg.style.display = 'none';
  fabricModal.style.display = 'flex';
}

function closeModal() {
  fabricModal.style.display = 'none';
}

function addToCart() {
  const qty = parseInt(cartQty.value);

  if (qty < currentMOQ) {
    cartMsg.textContent = `Minimum order quantity is ${currentMOQ}`;
    cartMsg.style.display = 'block';
    return;
  }

  fetch('add_to_cart.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `fabric_id=${currentFabricId}&manufacturer_id=${currentManufacturerId}&quantity=${qty}`
  })
  .then(r => r.json())
  .then(d => {
    cartMsg.textContent = d.status === 'ok'
      ? 'Added to cart successfully'
      : d.msg || 'Something went wrong';
    cartMsg.style.display = 'block';
  })
  .catch(() => {
    cartMsg.textContent = 'Network error';
    cartMsg.style.display = 'block';
  });
}

function requestSample() {
  fetch('request_sample.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `fabric_id=${currentFabricId}&manufacturer_id=${currentManufacturerId}&quantity=1`
  })
  .then(r => r.json())
  .then(d => {
    sampleMsg.textContent = d.status === 'ok'
      ? '✅ Sample request sent'
      : '❌ ' + d.msg;
    sampleMsg.style.display = 'block';
  })
  .catch(() => {
    sampleMsg.textContent = '❌ Network error';
    sampleMsg.style.display = 'block';
  });
}
</script>

<?php $mysqli->close(); ?>
</body>
</html>
