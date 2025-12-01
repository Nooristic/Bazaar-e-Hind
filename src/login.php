<?php
// login.php - Secure Login with Role-Based Redirect

$servername = "localhost:3306";
$db_username = "root";
$db_password = "";
$dbname = "bazaar_e_hind";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed. Please try again later.");
}

session_start();

// DEMO MODE: Auto-clear old session on login (remove after project submission)
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_regenerate_id(true);
// END DEMO MODE

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid access.");
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    die("Please enter both username and password.");
}

// Fetch user from database
$stmt = $conn->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Invalid username or password.");
}

$user = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    die("Invalid username or password.");
}

// LOGIN SUCCESSFUL → Create session
$_SESSION['user_id']   = $user['user_id'];
$_SESSION['username']  = $user['username'];
$_SESSION['role']      = $user['role'];
$_SESSION['logged_in'] = true;

$stmt->close();
$conn->close();

// Redirect based on role
switch ($user['role']) {
    case 'manufacturer':
        header("Location: Manufacturer/bazaar-homepage.php");
        exit();

    case 'wholesaler':
        header("Location: Wholesaler/wholesaler-homepage.php");
        exit();

    case 'admin':
        header("Location: Admin/admin-homepage.php");
        exit();

}
?>