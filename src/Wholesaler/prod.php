<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) die("Connection failed");
$mysqli->set_charset("utf8mb4");
$wholesaler_id = $_SESSION['user_id'];
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
        f.color_options,
        f.image_urls,
        f.visibility_type,
        u.company_name AS manufacturer_name
    FROM fabrics f
    JOIN users u
      ON u.user_id = f.user_id

    LEFT JOIN fabric_exclusive_access fe
      ON fe.fabric_id = f.fabric_id
      AND fe.is_active = 1
      AND CURDATE() BETWEEN fe.start_date AND fe.end_date

    WHERE f.status = 'approved'
      AND f.is_active = 1
      AND (
          f.visibility_type = 'public'
          OR fe.wholesaler_id = ?
      )

    ORDER BY f.created_at DESC
");

$stmt->bind_param("i", $wholesaler_id);
$stmt->execute();

/* ✅ THIS LINE WAS MISSING */
$result = $stmt->get_result();
$fabrics = $result->fetch_all(MYSQLI_ASSOC);

$stmt->close();

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
.modal-content {
  background:#fffbe6;  padding:30px;width:520px;max-height:80vh;overflow-y:auto;border-radius:20px;position:relative;
}
.modal-content img{width:100%;height:200px;object-fit:cover;border-radius:12px;margin-bottom:15px}
.close{position:absolute;top:12px;right:16px;font-size:24px;cursor:pointer}
.request-sample-btn {
  margin-top:20px;
  padding:12px 28px;
  border-radius:24px;
  background:#6d4c1e;     /* rich brown */
  color:#fffbe6;
  border:none;
  font-weight:bold;
  font-size:1rem;
  cursor:pointer;
  transition:all .25s ease;
}

.request-sample-btn:hover {
  background:#8d6e3f;     /* lighter hover */
  transform:translateY(-1px);
}
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
      <span style="
        background:#c0392b;
        color:white;
        border-radius:50%;
        padding:2px 8px;
        font-size:0.75rem;
        margin-left:6px;
      ">
        <?= $cartCount ?>
      </span>
    <?php endif; ?>
  </a>
</div>
<div class="products-grid">
<?php foreach ($fabrics as $f):
  $images = json_decode($f['image_urls'] ?? '[]', true);
  $first = !empty($images) ? '/trial_project/'.$images[0] : '/trial_project/assests/fabric_1.png';
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
  data-colors='<?= htmlspecialchars($f['color_options']) ?>'
  data-image="<?= $first ?>"
>
  <div class="product-img"><img src="<?= $first ?>"></div>
  <div class="product-title"><?= htmlspecialchars($f['name']) ?></div>
  <div style="text-align:center;color:#8d6e3f;font-size:.85rem">
    by <?= htmlspecialchars($f['manufacturer_name']) ?>
  </div>
  <div style="text-align:center;font-weight:bold">₹<?= number_format($f['price'],2) ?></div>
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
    <p><strong>Colors:</strong> <span id="modalColors"></span></p>
   
<button onclick="requestSample()" class="request-sample-btn">Request Sample</button>
    <p id="sampleMsg" style="display:none">Sample request sent successfully.</p>
    <hr style="margin:18px 0">

<div style="display:flex;gap:12px;align-items:center;justify-content:center">
  <label style="font-weight:bold">Qty:</label>
  <input
    type="number"
    id="cartQty"
    min="1"
    value="1"
    style="
      width:90px;
      padding:8px;
      border-radius:10px;
      border:1px solid #c7a76c;
    "
  >
</div>

<button id="addCartBtn" onclick="addToCart()" type="button" style="
  margin-top:18px;
  padding:14px 30px;
  border-radius:26px;
  background:#c7a76c;
  border:none;
  font-weight:bold;
  font-size:1rem;
  cursor:pointer;
  color:#3e2723;
">
  Add to Cart
</button>

<p id="cartMsg" style="
  margin-top:12px;
  font-size:0.9rem;
  display:none;
"></p>

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

  document.getElementById('modalImage').src = card.dataset.image;
  document.getElementById('modalName').textContent = card.dataset.name;
  document.getElementById('modalManufacturer').textContent = card.dataset.manufacturer;
  document.getElementById('modalPrice').textContent = card.dataset.price;
  document.getElementById('modalDescription').textContent = card.dataset.description;
  document.getElementById('modalComposition').textContent = card.dataset.composition;
  document.getElementById('modalGSM').textContent = card.dataset.gsm;
  document.getElementById('modalMOQ').textContent = card.dataset.moq;
  document.getElementById('modalVisibility').textContent = card.dataset.visibility;
  document.getElementById('modalColors').textContent = card.dataset.colors;

  const qtyInput = document.getElementById('cartQty');
  qtyInput.min = currentMOQ;
  qtyInput.value = currentMOQ;

  document.getElementById('cartMsg').style.display = 'none';
  document.getElementById('fabricModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('fabricModal').style.display = 'none';
}

function addToCart() {
  const qty = parseInt(document.getElementById('cartQty').value);
  const msg = document.getElementById('cartMsg');

  if (qty < currentMOQ) {
    msg.textContent = `Minimum order quantity is ${currentMOQ}`;
    msg.style.color = '#721c24';
    msg.style.display = 'block';
    return;
  }

  fetch('add_to_cart.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body:
      'fabric_id=' + currentFabricId +
      '&manufacturer_id=' + currentManufacturerId +
      '&quantity=' + qty
  })
  .then(res => res.json())   // 🔥 IMPORTANT
  .then(data => {
    if (data.status === 'ok') {
      msg.textContent = 'Added to cart successfully';
      msg.style.color = '#155724';
      msg.style.display = 'block';

      // ✅ Update cart badge
      const badge = document.querySelector('.cart-link span');
      if (badge) {
        badge.textContent = data.cartCount;
      }
    } else {
      msg.textContent = 'Something went wrong';
      msg.style.color = '#721c24';
      msg.style.display = 'block';
    }
  })
  .catch(() => {
    msg.textContent = 'Network error';
    msg.style.color = '#721c24';
    msg.style.display = 'block';
  });
}
function requestSample() {
  const msg = document.getElementById('sampleMsg');

  fetch('request_sample.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body:
      'fabric_id=' + currentFabricId +
      '&manufacturer_id=' + currentManufacturerId +
      '&quantity=1'
  })
  .then(res => res.json())
  .then(data => {
    msg.style.display = 'block';

    if (data.status === 'ok') {
      msg.textContent = '✅ Sample request sent successfully';
      msg.style.color = '#155724';
    } else {
      msg.textContent = '❌ ' + data.msg;
      msg.style.color = '#721c24';
    }
  })
  .catch(() => {
    msg.style.display = 'block';
    msg.textContent = '❌ Network error';
    msg.style.color = '#721c24';
  });
}
</script>

<?php $mysqli->close();
?>
</body>
</html>
