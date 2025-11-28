<?php
session_start();

// Security — only logged-in manufacturers
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../login.html");
    exit();
}

// GET THE REAL USERNAME FROM SESSION AND CONVERT TO UPPERCASE
$username = strtoupper(htmlspecialchars($_SESSION['username'], ENT_QUOTES));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bazaar-e-Hind - Welcome <?php echo $username; ?></title>
  <link rel="stylesheet" href="bazaar-homepage.css" />
  <link href="https://fonts.googleapis.com/css2?family=Marcellus:wght@400;700&display=swap" rel="stylesheet">
</head>
<body>
  <div class="bazaar-content">
    <header class="bazaar-header">
      <div class="bazaar-header-bg">
        <h1>WELCOME, <?php echo $username; ?></h1>
        <p>Choose your destination in the marketplace</p>
      </div>
    </header>

    <main>
      <div class="tent-grid">
        <!-- Your tent cards go here -->
      </div>
    </main>

    <footer class="bazaar-footer">
      <a href="#faq">FAQs</a>
      <span class="footer-sep">|</span>
      <a href="#terms">Terms &amp; Conditions</a>
      <span class="footer-sep">|</span>
      <a href="#privacy">Privacy Policy</a>
    </footer>
  </div>

  <script src="bazaar-homepage.js"></script>
</body>
</html>

