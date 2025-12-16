<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fabric_id'])) {
    $fabric_id = (int) $_POST['fabric_id'];

    $mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
    if ($mysqli->connect_error) die("Connection failed");

    $stmt = $mysqli->prepare("
        UPDATE fabrics
        SET is_active = 0
        WHERE fabric_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ii", $fabric_id, $user_id);
    $stmt->execute();

    $stmt->close();
    $mysqli->close();
}

header("Location: prod_manufacturer.php");
exit();
