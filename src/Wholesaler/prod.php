<?php
session_start();

// Security: Only logged-in wholesalers
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.html");
    exit();
}

// Database connection
$servername = "localhost";
$username_db = "root";          // ← Change if needed
$password_db = "";              // ← Change if needed
$dbname = "bazaar_e_hind";      // ← Your database name

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_user_id = $_SESSION['user_id'] ?? 0;

// Basic search & filter parameters (you can expand this later)
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$composition = isset($_GET['composition']) ? $conn->real_escape_string($_GET['composition']) : '';
$gsm = isset($_GET['gsm']) ? $conn->real_escape_string($_GET['gsm']) : '';
$color = isset($_GET['color']) ? $conn->real_escape_string($_GET['color']) : '';

// Build WHERE clause
$where = ["f.is_active = 1"];
$params = [];
$types = "";

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
    $params[] = $gsm;
    $types .= "i";
}

if ($color !== '') {
    $where[] = "JSON_CONTAINS(f.color_options, JSON_QUOTE(?))";
    $params[] = $color;
    $types .= "s";
}

// Combine conditions
$where_sql = count($where) > 0 ? "WHERE " . implode(" AND ", $where) : "";

// Main query - fetch fabrics with manufacturer info
$sql = "
    SELECT 
        f.fabric_id,
        f.name,
        f.description,
        f.composition,
        f.gsm,
        f.moq,
        f.color_options,
        f.image_urls,
        f.visibility_type,
        u.company_name AS manufacturer_name,
        fp.price_min,
        fp.price_max
    FROM fabrics f
    LEFT JOIN users u ON f.manufacturer_id = u.user_id
    LEFT JOIN fabric_prices fp ON f.fabric_id = fp.fabric_id
    $where_sql
    ORDER BY f.name ASC
";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$fabrics = [];
while ($row = $result->fetch_assoc()) {
    // Handle JSON fields
    $row['color_options'] = json_decode($row['color_options'] ?? '[]', true);
    $row['image_urls'] = json_decode($row['image_urls'] ?? '[]', true);
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
    :root {
      --bazaar-bg: rgba(255, 245, 235, 0.92);
      --gold: #c7a76c;
      --gold-dark: #a67c52;
      --text: #3e2723;
      --text-light: #8d6e3f;
    }

    body {
      margin: 0;
      font-family: 'Georgia', serif;
      color: var(--text);
      background: transparent;
    }

    #bg-video {
      position: fixed;
      top: 0; left: 0;
      min-width: 100vw;
      min-height: 100vh;
      width: 100vw;
      height: 100vh;
      object-fit: cover;
      z-index: -1;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 18px 24px;
      background: var(--bazaar-bg);
      border-bottom: 2px solid #e0c68c;
    }

    .back-link {
      color: var(--text);
      text-decoration: none;
      font-size: 1.2rem;
      font-weight: bold;
      display: flex;
      align-items: center;
    }

    .header-title {
      font-size: 2rem;
      font-weight: bold;
      letter-spacing: 1px;
    }

    .profile-btn {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: var(--bazaar-bg);
      border: 2px solid #e0c68c;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .profile-btn svg {
      width: 24px;
      height: 24px;
      fill: #8d6e3f;
    }

    /* Search & Filters */
    .filters-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      padding: 20px 38px;
      background: var(--bazaar-bg);
      align-items: center;
    }

    .search-input {
      flex: 1;
      min-width: 260px;
      padding: 10px 18px;
      border: 2px solid var(--gold);
      border-radius: 30px;
      font-size: 1rem;
      background: white;
    }

    .filter-select, .filter-input {
      padding: 10px 16px;
      border: 2px solid var(--gold);
      border-radius: 30px;
      background: white;
      font-family: inherit;
    }

    /* Grid */
    .fabrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 30px;
      padding: 38px;
    }

    .fabric-card {
      background: var(--bazaar-bg);
      border-radius: 12px;
      box-shadow: 0 2px 12px rgba(180,140,60,0.08);
      border: 2px solid #e0c68c solid;
      overflow: hidden;
      transition: all 0.2s;
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

    .badge {
      position: absolute;
      top: 12px;
      right: 12px;
      background: #d4af37;
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: bold;
    }

    .card-body {
      padding: 14px;
      position: relative;
    }

    .fabric-name {
      font-size: bold 1.15rem Georgia, serif;
      margin-bottom: 6px;
      color: #6d4c1e;
    }

    .fabric-info {
      font-size: 0.92rem;
      color: var(--text-light);
      line-height: 1.4;
    }

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
      position: relative;
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

    .btn::after {
      content: " coin";
      font-size: 1.4rem;
      margin-left: 6px;
    }

    footer {
      text-align: center;
      padding: 20px;
      background: var(--bazaar-bg);
      margin-top: auto;
    }

    footer a {
      color: var(--text);
      margin: 0 12px;
      text-decoration: none;
    }

    @media (max-width: 700px) {
      .filters-bar, .fabrics-grid { padding: 20px 12px; }
      .header-title { font-size: 1.6rem; }
    }
  </style>
</head>
<body>

  <video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
    Your browser does not support the video tag.
  </video>

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

  <!-- Search & Filters -->
  <div class="filters-bar">
    <form method="GET" style="display:contents;">
      <input type="text" name="search" class="search-input" placeholder="Search fabrics..." value="<?php echo htmlspecialchars($search); ?>">
      <select name="composition" class="filter-select">
        <option>Composition</option>
        <option value="Cotton" <?php echo $composition === 'Cotton' ? 'selected' : ''; ?>>Cotton</option>
        <option value="Silk" <?php echo $composition === 'Silk' ? 'selected' : ''; ?>>Silk</option>
        <option value="Polyester" <?php echo $composition === 'Polyester' ? 'selected' : ''; ?>>Polyester</option>
        <option value="Linen" <?php echo $composition === 'Linen' ? 'selected' : ''; ?>>Linen</option>
      </select>
      <input type="text" name="gsm" class="filter-input" placeholder="GSM" value="<?php echo htmlspecialchars($gsm); ?>">
      <input type="text" name="color" class="filter-input" placeholder="Color" value="<?php echo htmlspecialchars($color); ?>">
      <!-- Add more filters as needed -->
    </form>
  </div>

  <!-- Fabrics Grid -->
  <div class="fabrics-grid">
    <?php if (empty($fabrics)): ?>
      <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6d4c1e;">
        No fabrics found matching your criteria.
      </div>
    <?php else: ?>
      <?php foreach ($fabrics as $fabric): ?>
        <div class="fabric-card">
          <?php 
            $first_image = !empty($fabric['image_urls']) ? $fabric['image_urls'][0] : '../../assests/fabric-placeholder.jpg';
            $badge_text = $fabric['visibility_type'] === 'public' ? 'Public' : 'Restricted';
            $badge_style = $fabric['visibility_type'] === 'public' ? '#d4af37' : '#b22222';
          ?>
          <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($fabric['name']); ?>" class="fabric-img">
          <div class="badge" style="background:<?php echo $badge_style; ?>;"><?php echo $badge_text; ?></div>
          
          <div class="card-body">
            <div class="fabric-name"><?php echo htmlspecialchars($fabric['name']); ?></div>
            <div class="fabric-info">
              <?php echo htmlspecialchars($fabric['manufacturer_name']); ?><br>
              GSM: <?php echo $fabric['gsm']; ?> • MOQ: <?php echo $fabric['moq']; ?> m<br>
              <?php 
                if ($fabric['price_min'] && $fabric['price_max']) {
                  echo "₹" . number_format($fabric['price_min']) . " – ₹" . number_format($fabric['price_max']) . " / m";
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
    <a href="#faqs">FAQs</a> |
    <a href="#terms">Terms & Conditions</a> |
    <a href="#privacy">Privacy Policy</a>
  </footer>

</body>
</html>