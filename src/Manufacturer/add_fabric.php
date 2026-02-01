<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ================= INPUTS ================= */
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $composition = trim($_POST['composition']) ?: null;
    $gsm         = !empty($_POST['gsm']) ? (int)$_POST['gsm'] : null;
    $moq         = !empty($_POST['moq']) ? (int)$_POST['moq'] : null;
    $price       = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $colors      = array_filter(array_map('trim', explode(',', $_POST['colors'] ?? '')));
    $visibility  = ($_POST['visibility'] === 'restricted') ? 'restricted' : 'public';

    /* ================= VALIDATION ================= */
    if ($price <= 0) {
        $message = '<div class="error">Price must be greater than 0.</div>';
    } else {

        /* ================= IMAGE UPLOAD ================= */
        $uploaded = [];
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/trial_project/uploads/';

        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (!empty($_FILES['images']['name'][0])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            foreach ($_FILES['images']['name'] as $key => $imageName) {

                if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;

                $ext = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) continue;

                if ($_FILES['images']['size'][$key] > 3 * 1024 * 1024) continue;

                $newname = 'fabric_' . uniqid() . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $newname;

                if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $destination)) {
                    $uploaded[] = 'uploads/' . $newname;
                }
            }
        }

        $image_json  = !empty($uploaded) ? json_encode($uploaded) : null;
        $colors_json = !empty($colors) ? json_encode($colors) : null;

        /* ================= DB INSERT ================= */
        $mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
        if ($mysqli->connect_error) {
            die("Database connection failed");
        }
        $mysqli->set_charset("utf8mb4");

        $stmt = $mysqli->prepare("
            INSERT INTO fabrics (
                user_id,
                name,
                description,
                composition,
                gsm,
                moq,
                price,
                color_options,
                image_urls,
                visibility_type,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval')
        ");

        $stmt->bind_param(
            "isssiidsss",
            $user_id,
            $name,
            $description,
            $composition,
            $gsm,
            $moq,
            $price,
            $colors_json,
            $image_json,
            $visibility
        );

        if ($stmt->execute()) {
            $message = '<div class="success">Fabric added successfully! Awaiting admin approval.</div>';
        } else {
            $message = '<div class="error">Database error. Please try again.</div>';
        }

        $stmt->close();
        $mysqli->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Fabric</title>
<base href="../../src/Manufacturer/">
<link rel="stylesheet" href="css_all_pages.css">
<style>
    body {
  min-height: 100vh;
  position: relative;
  background: transparent;
}
.container {
  max-width: 880px;
  margin: 50px auto;
  padding: 36px 40px;
  background: rgba(255,245,235,0.96);
  border-radius: 20px;
  border: 2px solid var(--border);
  box-shadow: 0 20px 45px rgba(0,0,0,.08);
}

.back {
  display: inline-block;
  margin-bottom: 14px;
  font-weight: bold;
  color: #6d4c1e;
  text-decoration: none;
}

h2 {
  margin-top: 0;
  margin-bottom: 24px;
  color: #4e342e;
  letter-spacing: .5px;
}

.form-section {
  margin-bottom: 28px;
  padding-bottom: 20px;
  border-bottom: 1px dashed #e0c68c;
}

.form-section:last-child {
  border-bottom: none;
}

.section-title {
  font-size: 1.1rem;
  font-weight: bold;
  color: #6d4c1e;
  margin-bottom: 12px;
}

label {
  font-weight: 600;
  margin-top: 14px;
  display: block;
  font-size: .95rem;
}

input, textarea, select {
  width: 100%;
  padding: 12px 14px;
  margin-top: 6px;
  border-radius: 12px;
  border: 2px solid #c7a76c;
  background: #fffdf8;
  font-size: .95rem;
  transition: border .2s, box-shadow .2s;
}

input:focus,
textarea:focus,
select:focus {
  outline: none;
  border-color: #a67c3c;
  box-shadow: 0 0 0 3px rgba(166,124,60,.2);
}

textarea {
  min-height: 110px;
  resize: vertical;
}

.helper-text {
  font-size: .8rem;
  color: #7a5a3a;
  margin-top: 4px;
}

.file-box {
  background: #fff8e8;
  padding: 14px;
  border-radius: 12px;
  border: 2px dashed #c7a76c;
}

button.btn {
  margin-top: 26px;
  padding: 12px 26px;
  font-size: 1rem;
  border-radius: 30px;
}

/* Success & error messages */
.success {
  background: #e8f5e9;
  color: #2e7d32;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 18px;
  font-weight: bold;
}

.error {
  background: #fdecea;
  color: #c62828;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 18px;
  font-weight: bold;
}
#bg-video {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  object-fit: cover;
  z-index: -100;
  pointer-events: none;
}
/* Mobile */
@media (max-width: 700px) {
  .container {
    padding: 26px 22px;
  }
}
</style>
</head>

<body>

<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="container">
<a href="prod_manufacturer.php" class="back">← Back to Products</a>
<h2>Add New Fabric</h2>

<?= $message ?>

<form method="POST" enctype="multipart/form-data">

  <!-- BASIC INFO -->
  <div class="form-section">
    <div class="section-title">Basic Details</div>

    <label>Fabric Name *</label>
    <input type="text" name="name" required>

    <label>Description *</label>
    <textarea name="description" required></textarea>
  </div>

  <!-- SPECIFICATIONS -->
  <div class="form-section">
    <div class="section-title">Specifications</div>

    <label>Composition</label>
    <input type="text" name="composition">
    <div class="helper-text">e.g. 100% Silk, Cotton Blend</div>

    <label>GSM</label>
    <input type="number" name="gsm">

    <label>MOQ</label>
    <input type="number" name="moq">
  </div>

  <!-- PRICING -->
  <div class="form-section">
    <div class="section-title">Pricing & Availability</div>

    <label>Price per unit (₹) *</label>
    <input type="number" name="price" min="1" step="0.01" required>

    <label>Available Colors</label>
    <input type="text" name="colors">
    <div class="helper-text">Comma separated (e.g. Red, Blue, Ivory)</div>

    <label>Visibility</label>
    <select name="visibility">
      <option value="public">Public (Visible to all)</option>
      <option value="restricted">Restricted (Limited buyers)</option>
    </select>
  </div>

  <!-- MEDIA -->
  <div class="form-section">
    <div class="section-title">Images</div>

    <div class="file-box">
      <label>Upload Fabric Images *</label>
      <input type="file" name="images[]" multiple required>
      <div class="helper-text">JPG, PNG, WEBP · Max 3MB each</div>
    </div>
  </div>

  <center>
    <button class="btn">Submit for Approval</button>
  </center>

</form>
</div>

</body>
</html>
