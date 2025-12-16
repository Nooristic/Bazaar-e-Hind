<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: prod_manufacturer.php");
    exit();
}

$fabric_id = (int)$_GET['id'];
$message = '';

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) die("Connection failed");
$mysqli->set_charset("utf8mb4");

/* 🔹 FETCH EXISTING FABRIC */
$stmt = $mysqli->prepare("
    SELECT * FROM fabrics
    WHERE fabric_id = ? AND user_id = ? AND is_active = 1
");
$stmt->bind_param("ii", $fabric_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$fabric = $result->fetch_assoc();
$stmt->close();

if (!$fabric) {
    header("Location: prod_manufacturer.php");
    exit();
}

/* 🔹 HANDLE UPDATE */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $composition = trim($_POST['composition']) ?: null;
    $gsm         = !empty($_POST['gsm']) ? (int)$_POST['gsm'] : null;
    $moq         = !empty($_POST['moq']) ? (int)$_POST['moq'] : null;
    $colors      = array_filter(array_map('trim', explode(',', $_POST['colors'])));
    $visibility  = ($_POST['visibility'] === 'restricted') ? 'restricted' : 'public';

    /* 🔹 EXISTING IMAGES */
    $existing_images = json_decode($fabric['image_urls'] ?? '[]', true);

    /* 🔹 HANDLE NEW IMAGE UPLOAD */
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/trial_project/uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $key => $name_file) {
            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($name_file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) continue;

            $newname = 'fabric_' . uniqid() . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $upload_dir . $newname)) {
                $existing_images[] = 'uploads/' . $newname;
            }
        }
    }

    $images_json = !empty($existing_images) ? json_encode($existing_images) : null;
    $colors_json = !empty($colors) ? json_encode($colors) : null;

    $stmt = $mysqli->prepare("
        UPDATE fabrics SET
            name = ?, description = ?, composition = ?,
            gsm = ?, moq = ?, color_options = ?,
            image_urls = ?, visibility_type = ?, status = 'pending_approval'
        WHERE fabric_id = ? AND user_id = ?
    ");
    $stmt->bind_param(
        "sssiiissii",
        $name, $description, $composition,
        $gsm, $moq, $colors_json,
        $images_json, $visibility,
        $fabric_id, $user_id
    );

    if ($stmt->execute()) {
        $message = '<div class="success">Fabric updated successfully! Sent for re-approval.</div>';
        $fabric = array_merge($fabric, $_POST);
        $fabric['image_urls'] = $images_json;
    } else {
        $message = '<div class="error">Update failed. Try again.</div>';
    }

    $stmt->close();
}

$mysqli->close();

$colors_value = !empty($fabric['color_options'])
    ? implode(', ', json_decode($fabric['color_options'], true))
    : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Fabric</title>
<base href="../../src/Manufacturer/">

<style>
:root { --bazaar-bg: rgba(255,245,235,.92); }
body { font-family:Georgia, serif; background:transparent; color:#3e2723; }
#bg-video { position:fixed; inset:0; object-fit:cover; z-index:-1; }
.container {
    max-width:800px; margin:40px auto; padding:30px;
    background:var(--bazaar-bg); border-radius:16px;
    border:2px solid #e0c68c;
}
input, textarea, select {
    width:100%; padding:12px; margin:10px 0;
    border-radius:10px; border:2px solid #c7a76c;
}
label { font-weight:bold; color:#6d4c1e; }
.btn {
    padding:12px 30px; border-radius:12px;
    background:#b8860b; color:white; border:none;
    font-size:1.1rem; cursor:pointer;
}
.success { background:#d4edda; padding:15px; border-radius:8px; margin-bottom:15px; }
.error { background:#f8d7da; padding:15px; border-radius:8px; margin-bottom:15px; }
.back { display:inline-block; margin-bottom:20px; color:#6d4c1e; font-weight:bold; }
</style>
</head>

<body>

<video autoplay muted loop id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="container">
<a href="prod_manufacturer.php" class="back">← Back to Products</a>
<h2>Edit Fabric</h2>

<?= $message ?>

<form method="POST" enctype="multipart/form-data">

<label>Fabric Name *</label>
<input type="text" name="name" value="<?= htmlspecialchars($fabric['name']) ?>" required>

<label>Description *</label>
<textarea name="description" rows="4" required><?= htmlspecialchars($fabric['description']) ?></textarea>

<label>Composition</label>
<input type="text" name="composition" value="<?= htmlspecialchars($fabric['composition']) ?>">

<label>GSM</label>
<input type="number" name="gsm" value="<?= htmlspecialchars($fabric['gsm']) ?>">

<label>MOQ</label>
<input type="number" name="moq" value="<?= htmlspecialchars($fabric['moq']) ?>">

<label>Available Colors</label>
<input type="text" name="colors" value="<?= htmlspecialchars($colors_value) ?>">

<label>Add More Images</label>
<input type="file" name="images[]" multiple>

<label>Visibility</label>
<select name="visibility">
    <option value="public" <?= $fabric['visibility_type']=='public'?'selected':'' ?>>Public</option>
    <option value="restricted" <?= $fabric['visibility_type']=='restricted'?'selected':'' ?>>Exclusive</option>
</select>

<center><button class="btn">Update Fabric</button></center>
</form>
</div>

</body>
</html>
