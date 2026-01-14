<?php
session_start();

// Security — only logged-in admins
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.html");
    exit();
}

// GET THE REAL USERNAME FROM SESSION AND CONVERT TO UPPERCASE
$username = strtoupper(htmlspecialchars($_SESSION['username'], ENT_QUOTES));

// Fetch notifications for this user
$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");

$notifications = [];
$unread_count = 0;

if (!$mysqli->connect_error) {
    $user_id = $_SESSION['user_id'] ?? 0; // Must be set during login!

    if ($user_id > 0) {
        $stmt = $mysqli->prepare("
            SELECT id, message, created_at 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $unread_count = count($notifications);
        $stmt->close();
    }
}
$mysqli->close();
// ────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bazaar-e-Hind - Welcome <?php echo $username; ?></title>
  <link rel="stylesheet" href="home_page.css" />
  <link href="https://fonts.googleapis.com/css2?family=Marcellus:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="bazaar-content">
    <header class="bazaar-header">
  <div class="header-wrapper">

    <!-- Left spacer (empty but required for centering) -->
    <div></div>

    <!-- Center welcome -->
    <div class="bazaar-header-bg">
      <h1>WELCOME, <?php echo $username; ?></h1>
      <p>Explore the finest fabrics & grow your business</p>
    </div>

    <!-- Right notification bell -->
    <div class="notification-bell" onclick="toggleNotifications()">
      🔔
      <?php if ($unread_count > 0): ?>
        <span class="badge"><?php echo $unread_count; ?></span>
      <?php endif; ?>

      <div class="notification-dropdown" id="notificationDropdown">
        <?php if (empty($notifications)): ?>
          <div class="no-notif">No new notifications</div>
        <?php else: ?>
          <?php foreach ($notifications as $n): ?>
            <div class="notification-item">
              <?php echo htmlspecialchars($n['message']); ?>
              <small><?php echo date('d M Y, H:i', strtotime($n['created_at'])); ?></small>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</header>
    <main>
      <div class="tent-grid">
        <!-- Tent cards will be populated by bazaar-homepage.js -->
      </div>
    </main>

    <footer class="bazaar-footer">
      <a href="#faq">FAQs</a>
      <span class="footer-sep">|</span>
      <a href="#terms">Terms & Conditions</a>
      <span class="footer-sep">|</span>
      <a href="#privacy">Privacy Policy</a>
    </footer>
  </div>

  <script src="home_page.js"></script>
  <!-- Notification toggle script -->
  <script>
    function toggleNotifications() {
      const dropdown = document.getElementById('notificationDropdown');
      if (dropdown) {
        dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
      }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
      const bell = document.querySelector('.notification-bell');
      const dropdown = document.getElementById('notificationDropdown');
      if (bell && dropdown && !bell.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  </script>
</body>
</html>