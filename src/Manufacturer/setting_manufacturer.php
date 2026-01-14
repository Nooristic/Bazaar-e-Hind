<?php
session_start();

// Security — only logged-in manufacturers
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../login.html");
    exit();
}

// Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "bazaar_e_hind";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$username = strtoupper(htmlspecialchars($_SESSION['username'], ENT_QUOTES));
$user_id = $_SESSION['user_id'];

// Fetch user data
$sql = "SELECT company_name, contact_no, gst_details, logo_url FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$stmt->close();

$company_name = $user_data['company_name'] ?? '';
$contact_no = $user_data['contact_no'] ?? '';
$gst_details = json_decode($user_data['gst_details'] ?? '{}', true);
$gst_no = $gst_details['gst_no'] ?? '';
$logo_url = $user_data['logo_url'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_company_name = mysqli_real_escape_string($conn, $_POST['company_name']);
    $new_contact_no = mysqli_real_escape_string($conn, $_POST['contact_no']);
    $new_gst_no = mysqli_real_escape_string($conn, $_POST['gst_no']);

    $new_logo_url = $logo_url;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
        $target_dir = "uploads/logos/";
        $target_file = $target_dir . basename($_FILES["logo"]["name"]);
        if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
            $new_logo_url = $target_file;
        }
    }

    $new_gst_details = json_encode([
        'gst_no' => $new_gst_no,
        'document_url' => $gst_details['document_url'] ?? '',
        'verified' => $gst_details['verified'] ?? false
    ]);

    $update_sql = "UPDATE users SET company_name = ?, contact_no = ?, gst_details = ?, logo_url = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssi", $new_company_name, $new_contact_no, $new_gst_details, $new_logo_url, $user_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Password change
    if (!empty($_POST['new_password']) && $_POST['new_password'] === $_POST['confirm_password']) {
        $old_password = $_POST['old_password'];
        $sql_pw = "SELECT password_hash FROM users WHERE user_id = ?";
        $stmt_pw = $conn->prepare($sql_pw);
        $stmt_pw->bind_param("i", $user_id);
        $stmt_pw->execute();
        $result_pw = $stmt_pw->get_result();
        $row_pw = $result_pw->fetch_assoc();
        if (password_verify($old_password, $row_pw['password_hash'])) {
            $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $update_pw_sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
            $update_pw_stmt = $conn->prepare($update_pw_sql);
            $update_pw_stmt->bind_param("si", $new_password_hash, $user_id);
            $update_pw_stmt->execute();
            $update_pw_stmt->close();
        }
        $stmt_pw->close();
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bazaar-e-Hind - Settings</title>

    <!-- External CSS -->
    <link rel="stylesheet" href="../style.css" />
    <link rel="stylesheet" href="../css_all_pages.css" />

    <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Corinthia" rel="stylesheet">
</head>
<body>

    <video autoplay muted loop id="bg-video">
        <source src="../../assests/silk video.mp4" type="video/mp4">
    </video>

    <div class="form-container">
        <h1 class="brand-title">Bazaar-e-Hind</h1>
        <a href="bazaar-homepage.php" style="display: block; text-align: left; margin-bottom: 20px; color: #6B1B1B;">← Home</a>

        <h2>Manage Account</h2>

        <form method="POST" enctype="multipart/form-data">
            <label for="company_name">Company Name</label>
            <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" required>

            <label for="contact_no">Contact Number</label>
            <input type="text" id="contact_no" name="contact_no" value="<?php echo htmlspecialchars($contact_no); ?>" required>

            <label for="gst_no">GST Number</label>
            <input type="text" id="gst_no" name="gst_no" value="<?php echo htmlspecialchars($gst_no); ?>">

            <label for="logo">Upload Logo</label>
            <input type="file" id="logo" name="logo" accept="image/*">
            <?php if ($logo_url): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Current Logo" style="max-width: 150px; margin: 10px 0; border-radius: 8px;">
            <?php endif; ?>

            <h2 style="margin-top: 30px;">Change Password</h2>

            <label for="old_password">Old Password</label>
            <input type="password" id="old_password" name="old_password">

            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password">

            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password">

            <!-- Coin-styled Submit Button -->
            <input type="image" src="../../assests/coin.png" alt="Submit" class="coin-submit">        
          </form>

        <div style="margin-top: 40px; text-align: center;">
            <button onclick="confirmLogout();" style="background: #8B2E2E; width: 100%; padding: 12px; border-radius: 10px; font-size: 1.1rem;">
                Logout
            </button>
        </div>
    </div>

    <script>
        function confirmLogout() {
            if (confirm("Are you sure you want to log out?")) {
                window.location.href = '../logout.php';
            }
        }
    </script>
</body>
</html>