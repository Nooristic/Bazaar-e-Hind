<?php
session_start();

// Security — only logged-in wholesalers (or users with both role)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['wholesaler', 'both'])) {
    header("Location: ../login.html");
    exit();
}

// GET THE REAL USERNAME/COMPANY NAME FROM SESSION AND CONVERT TO UPPERCASE
$username = strtoupper(htmlspecialchars($_SESSION['username'] ?? $_SESSION['company_name'] ?? 'WHOLESALER', ENT_QUOTES));
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
      <div class="bazaar-header-bg">
        <h1>WELCOME, <?php echo $username; ?></h1>
        <p>Explore the finest fabrics & grow your business</p>
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
</body>
</html>