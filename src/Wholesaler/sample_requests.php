<?php
session_start();

// Security check - only logged-in wholesalers
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.html");
    exit();
}

// Database connection
$servername = "localhost";
$username_db = "root";          // Change according to your setup
$password_db = "";              // Change according to your setup
$dbname      = "bazaar_e_hind"; // Your database name

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_user_id = $_SESSION['user_id'] ?? 0;

// Basic filtering parameters
$search      = isset($_GET['search'])      ? trim($_GET['search'])      : '';
$composition = isset($_GET['composition']) ? trim($_GET['composition']) : '';
$gsm         = isset($_GET['gsm'])         ? trim($_GET['gsm'])         : '';
$price       = isset($_GET['price'])       ? trim($_GET['price'])       : '';
$color       = isset($_GET['color'])       ? trim($_GET['color'])       : '';

// Build WHERE conditions
$where = ["f.is_active = 1"];
$types = "";
$params = [];

if ($search !== '') {
    $where[] = "(f.name LIKE ? OR f.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($composition !== '' && $composition !== 'Composition') {
    $where[] = "f.composition LIKE ?";
    $params[] = "%$composition%";
    $types .= "s";
}

if ($gsm !== '') {
    $where[] = "f.gsm = ?";
    $params[] = (int)$gsm;
    $types .= "i";
}

if ($color !== '') {
    $where[] = "JSON_CONTAINS(f.color_options, JSON_QUOTE(?))";
    $params[] = $color;
    $types .= "s";
}

// Note: Price filtering is more complex (range/min/max) - keeping it simple here
// You can extend it later with min_price/max_price inputs

$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT 
        f.fabric_id,
        f.name,
        f.gsm,
        f.moq,
        f.composition,
        f.color_options,
        f.image_urls,
        f.visibility_type,
        u.company_name AS manufacturer,
        MIN(fp.price) AS min_price,
        MAX(fp.price) AS max_price
    FROM fabrics f
    LEFT JOIN users u ON f.manufacturer_id = u.user_id
    LEFT JOIN fabric_prices fp ON f.fabric_id = fp.fabric_id
    $where_sql
    GROUP BY f.fabric_id
    ORDER BY f.name ASC
";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$fabrics = [];
while ($row = $result->fetch_assoc()) {
    $row['color_options'] = json_decode($row['color_options'] ?? '[]', true);
    $row['image_urls']    = json_decode($row['image_urls'] ?? '[]', true);
    $fabrics[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Catalog / Fabrics</title>
  <base href="../../src/Wholesaler/">

  <style>
    /* ===== THEME VARIABLES ===== */
    :root {
      --bazaar-bg: rgba(255, 245, 235, 0.92);
      --gold: #c7a76c;
      --gold-dark: #a67c52;
      --text: #3e2723;
      --text-light: #8d6e3f;
    }

    body {
      margin: 0;
      font-family: Georgia, serif;
      color: var(--text);
      background: transparent;
    }

    /* ===== BACKGROUND VIDEO ===== */
    #bg-video {
      position: fixed;
      inset: 0;
      width: 100vw;
      height: 100vh;
      object-fit: cover;
      z-index: -1;
    }

    /* ===== HEADER ===== */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 18px 24px;
      background: var(--bazaar-bg);
      border-bottom: 2px solid var(--gold);
    }

    .back-link {
      color: var(--text);
      text-decoration: none;
      font-size: 1.2rem;
      font-weight: bold;
    }

    .header-title {
      font-size: 2rem;
      font-weight: bold;
      letter-spacing: 1px;
      color: #6d4c1e;
    }

    .profile-btn {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: var(--bazaar-bg);
      border: 2px solid var(--gold);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .profile-btn svg {
      width: 24px;
      height: 24px;
      fill: var(--text-light);
    }

    /* ===== FILTER BAR ===== */
    .filters-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      padding: 20px 38px;
      background: var(--bazaar-bg);
    }

    .search-input,
    .filter-select,
    .filter-input {
      padding: 10px 18px;
      border: 2px solid var(--gold);
      border-radius: 30px;
      font-family: inherit;
      background: #fff;
    }

    .search-input {
      flex: 1;
      min-width: 260px;
    }

    /* ===== GRID ===== */
    .fabrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 30px;
      padding: 38px;
    }

    .fabric-card {
      background: var(--bazaar-bg);
      border-radius: 12px;
      border: 2px solid #e0c68c;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(180,140,60,0.08);
      transition: 0.2s ease;
    }

    .fabric-card:hover {
      box-shadow: 0 8px 28px rgba(180,140,60,0.18);
      border-color: var(--gold-dark);
    }

    .fabric-img {
      width: 100%;
      height: 180px;
      object-fit: cover;
    }

    .card-body {
      padding: 14px;
      position: relative;
    }

    .badge {
      position: absolute;
      top: 12px;
      right: 12px;
      background: #d4af37;
      color: #fff;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .restricted {
      background: #b22222;
    }

    .fabric-name {
      font-size: 1.15rem;
      font-weight: bold;
      color: #6d4c1e;
      margin-bottom: 6px;
    }

    .fabric-info {
      font-size: 0.92rem;
      color: var(--text-light);
      line-height: 1.4;
    }

    /* ===== BUTTONS ===== */
    .buttons {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 14px;
    }

    .btn {
      padding: 10px;
      border-radius: 30px;
      border: none;
      font-family: Georgia, serif;
      font-weight: bold;
      cursor: pointer;
      transition: 0.2s;
    }

    .btn-primary {
      background: #ffe7b3;
      color: #6d4c1e;
    }

    .btn-primary:hover {
      background: #ffd180;
    }

    .btn-request {
      background: #ffecb3;
      color: #a0522d;
    }

    .btn-request:hover {
      background: #ffdd88;
    }

    /* ===== FOOTER ===== */
    footer {
      text-align: center;
      padding: 20px;
      background: var(--bazaar-bg);
    }

    footer a {
      color: var(--text);
      text-decoration: none;
      margin: 0 10px;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 700px) {
      .filters-bar,
      .fabrics-grid {
        padding: 20px 12px;
      }

      .header-title {
        font-size: 1.6rem;
      }
    }
  </style>
</head>

<body>

  <!-- Background Video -->
  <video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
  </video>

  <!-- Header -->
  <div class="header">
    <a href="../bazaar-homepage.php" class="back-link">← Home</a>
    <div class="header-title">Browse Catalog / Fabrics</div>
    <div class="profile-btn">
      <svg viewBox="0 0 24 24">
        <circle cx="12" cy="8" r="5"/>
        <path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/>
      </svg>
    </div>
  </div>

  <!-- Filters -->
  <div class="filters-bar">
    <form method="GET" style="display:contents; width:100%;">
      <input name="search" class="search-input" placeholder="Search fabrics..." value="<?php echo htmlspecialchars($search); ?>">
      
      <select name="composition" class="filter-select">
        <option>Composition</option>
        <option value="Cotton"    <?php echo $composition === 'Cotton'    ? 'selected' : ''; ?>>Cotton</option>
        <option value="Silk"      <?php echo $composition === 'Silk'      ? 'selected' : ''; ?>>Silk</option>
        <option value="Polyester" <?php echo $composition === 'Polyester' ? 'selected' : ''; ?>>Polyester</option>
        <option value="Linen"     <?php echo $composition === 'Linen'     ? 'selected' : ''; ?>>Linen</option>
      </select>

      <input name="gsm"    class="filter-input" placeholder="GSM"    value="<?php echo htmlspecialchars($gsm); ?>">
      <input name="color"  class="filter-input" placeholder="Color"  value="<?php echo htmlspecialchars($color); ?>">
      <!-- Price input kept but not fully implemented in query yet -->
      <input name="price"  class="filter-input" placeholder="Price"  value="<?php echo htmlspecialchars($price); ?>">
    </form>
  </div>

  <!-- Grid -->
  <div class="fabrics-grid">

    <?php if (empty($fabrics)): ?>
      <div style="grid-column: 1 / -1; text-align:center; padding: 3rem 1rem; color:#6d4c1e; font-size:1.1rem;">
        No fabrics found matching your criteria.
      </div>
    <?php else: ?>
      <?php foreach ($fabrics as $fabric): ?>
        <div class="fabric-card">
          <?php 
            $main_image = !empty($fabric['image_urls']) ? $fabric['image_urls'][0] : '../../assests/fabric-placeholder.jpg';
            $badge_class = $fabric['visibility_type'] === 'restricted' ? 'restricted' : '';
            $badge_text  = $fabric['visibility_type'] === 'public' ? 'Public' : 'Restricted';
          ?>
          <img src="<?php echo htmlspecialchars($main_image); ?>" class="fabric-img" alt="<?php echo htmlspecialchars($fabric['name']); ?>">

          <div class="card-body">
            <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
            
            <div class="fabric-name"><?php echo htmlspecialchars($fabric['name']); ?></div>
            
            <div class="fabric-info">
              <?php echo htmlspecialchars($fabric['manufacturer']); ?><br>
              GSM: <?php echo $fabric['gsm']; ?> • MOQ: <?php echo $fabric['moq']; ?> m<br>
              <?php
                if ($fabric['min_price'] && $fabric['max_price']) {
                  echo "₹" . number_format($fabric['min_price']) . " – ₹" . number_format($fabric['max_price']) . " / m";
                } else {
                  echo "Price on request";
                }
              ?>
            </div>

            <div class="buttons">
              <?php if ($fabric['visibility_type'] === 'public'): ?>
                <button class="btn btn-primary">Add to Cart</button>
                <button class="btn btn-primary">Request Sample</button>
              <?php else: ?>
                <button class="btn btn-request">Request Access</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>

  <footer>
    <a href="#">FAQs</a> |
    <a href="#">Terms & Conditions</a> |
    <a href="#">Privacy Policy</a>
  </footer>

</body>
</html>