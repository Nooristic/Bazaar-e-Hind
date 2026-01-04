<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name           = trim($_POST['name']);
    $description    = trim($_POST['description']);
    $composition    = trim($_POST['composition']) ?: null;
    $gsm            = !empty($_POST['gsm']) ? (int)$_POST['gsm'] : null;
    $moq            = !empty($_POST['moq']) ? (int)$_POST['moq'] : null;
    $colors         = array_filter(array_map('trim', explode(',', $_POST['colors'])));
    $visibility     = $_POST['visibility'] === 'restricted' ? 'restricted' : 'public';

    // === IMAGE UPLOAD – 100% WORKING FOR YOUR STRUCTURE ===
$uploaded = [];
$upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/trial_project/uploads/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!empty($_FILES['images']['name'][0])) {
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    foreach ($_FILES['images']['name'] as $key => $imageName) {

        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
            continue;
        }

        $ext = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed) || $_FILES['images']['size'][$key] > 3 * 1024 * 1024) {
            continue;
        }

        $newname = 'fabric_' . uniqid() . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $newname;

        if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $destination)) {
            $uploaded[] = 'uploads/' . $newname;
        }
    }
}


    $image_json = !empty($uploaded) ? json_encode($uploaded) : null;
    $colors_json = !empty($colors) ? json_encode($colors) : null;

    $mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
    if ($mysqli->connect_error) die("Connection failed");
    $mysqli->set_charset("utf8mb4");

    $stmt = $mysqli->prepare("INSERT INTO fabrics (
        user_id, name, description, composition, gsm, moq, color_options, image_urls, visibility_type, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval')");

    $stmt->bind_param("isssiiiss", $user_id, $name, $description, $composition, $gsm, $moq, $colors_json, $image_json, $visibility);

    if ($stmt->execute()) {
        $message = '<div class="success">Fabric added successfully! Awaiting admin approval.</div>';
    } else {
        $message = '<div class="error">Error: Please try again.</div>';
    }
    $stmt->close();
    $mysqli->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Fabric</title>
  <base href="../../src/Manufacturer/">
  <link rel="stylesheet" href="css_all_pages.css">
  <style>
    /* Page-specific add-fabric styles; globals moved to css_all_pages.css */
    .container { max-width:800px; margin:40px auto; padding:30px; background:var(--bazaar-bg); border-radius:16px; border:2px solid var(--border); box-shadow:0 8px 30px rgba(0,0,0,0.15); }
    h2 { text-align:center; color:#5d3a1a; margin-bottom:30px; }
    input, textarea, select { width:100%; padding:12px; margin:10px 0; border:2px solid #c7a76c; border-radius:10px; font-family:inherit; font-size:1rem; }
    label { font-weight:bold; color:#6d4c1e; margin-top:15px; display:block; }
    /* .btn, .success, .error centralized in css_all_pages.css */
    .back { display:inline-block; margin:20px 0; color:#6d4c1e; text-decoration:none; font-weight:bold; }
  </style>
</head>
<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="container">
  <a href="prod_manufacturer.php" class="back">Back to Products</a>
  <h2>Add New Fabric</h2>

  <?php if($message): ?>
    <?= $message ?>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
<label>Fabric Name *</label>
    <input type="text" name="name" required>

    <label>Description *</label>
    <textarea name="description" rows="4" required></textarea>

    <label>Composition (e.g., 100% Cotton)</label>
    <input type="text" name="composition">

    <label>GSM</label>
    <input type="number" name="gsm">

    <label>MOQ (Minimum Order Quantity)</label>
    <input type="number" name="moq">

    <label>Available Colors (comma separated)</label>
    <input type="text" name="colors" placeholder="Red, Blue, Black">

    <label>Upload Images (up to 5, JPG/PNG/WebP, max 2MB each)</label>
    <input type="file" name="images[]" multiple accept="image/*" required>

    <label>Visibility</label>
    <select name="visibility">
      <option value="public">Public (All buyers can see)</option>
      <option value="restricted">Exclusive (Only selected buyers)</option>
    </select>

    <center><button type="submit" class="btn">Submit for Approval</button></center>
  </form>
</div>

</body>
</html>