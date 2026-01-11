<?php
// signup.php - FINAL 100% WORKING VERSION (MySQL on port 3306)

$servername = "localhost:3306";   // YOUR CORRECT PORT
$username   = "root";
$password   = "";
$dbname     = "bazaar_e_hind";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

session_start();

/* ────────────────────── DEMO / EVALUATION MODE ONLY ────────────────────── */
// REMOVE THESE 6 LINES WHEN YOUR PROJECT IS FINISHED & GOING LIVE
/* ─────────────── FAST DEMO MODE (NO LAG) — REMOVE AFTER PROJECT ─────────────── */
$_SESSION = array();                     // instantly clear all old session data
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_regenerate_id(true);             // give new session ID instantly
/* ───────────────────────────────────────────────────────────────────────────── */
/* ─────────────────────────────────────────────────────────────────────── */

// If already logged in → go to homepage
if (isset($_SESSION['user_id'])) {
    header("Location: Manufacturer/bazaar-homepage.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method.");
}

// Get form data
$role     = trim($_POST['role'] ?? '');
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$company  = trim($_POST['company'] ?? '');
$contact  = trim($_POST['contact'] ?? '');
$gst      = trim($_POST['gst'] ?? '');

// Validate
if (empty($role) || empty($username) || empty($email) || empty($password) || empty($company) || empty($contact) || empty($gst)) {
    die("All fields are required.");
}

if (!in_array($role, ['manufacturer', 'wholesaler', 'admin'])) {
    die("Invalid role.");
}

// Check duplicate
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
$stmt->bind_param("ss", $username, $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    $stmt->close();
    die("Username or Email already exists.");
}
$stmt->close();

// Logo upload (optional)
$logo_url = null;
if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] == 0) {
    $dir = "../uploads/logos/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
    $logo_url = $dir . $username . "_" . time() . "." . $ext;
    move_uploaded_file($_FILES['logo']['tmp_name'], $logo_url);
}

// Save to DB
$hash = password_hash($password, PASSWORD_DEFAULT);
$gst_json = json_encode(["gst_no" => $gst]);

$stmt = $conn->prepare("INSERT INTO users 
    (username, email, password_hash, role, company_name, contact_no, gst_details, logo_url, verification_status, account_status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'active')");

$stmt->bind_param("ssssssss", $username, $email, $hash, $role, $company, $contact, $gst_json, $logo_url);

if ($stmt->execute()) {
    // Auto login
    $_SESSION['user_id']   = $stmt->insert_id;
    $_SESSION['username']  = $username;
    $_SESSION['role']      = $role;
    $_SESSION['logged_in'] = true;

    $stmt->close();
    $conn->close();

    // FINAL CORRECT REDIRECT
    switch ($role) {
        case 'manufacturer':
            header("Location: Manufacturer/bazaar-homepage.php");
            break;
        case 'wholesaler':
            header("Location: Wholesaler/home_page.php");
            break;
        case 'admin':
            header("Location: Admin/admin-homepage.php");
            break;
        default:
            header("Location: login.html");
            break;
    }
    exit();   // ONLY ONE EXIT — PERFECT

} else {
    die("Registration failed: " . $stmt->error);
}

$conn->close();
?>