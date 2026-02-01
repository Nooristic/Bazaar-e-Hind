<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    die("Unauthorized");
}

if (!isset($_POST['order_id'])) {
    die("Order ID missing");
}

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("DB connection failed");
}

$order_id = (int) $_POST['order_id'];
$manufacturer_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    UPDATE orders
    SET status = 'cancelled'
    WHERE order_id = ?
      AND manufacturer_id = ?
      AND status = 'ordered'
");
$stmt->bind_param("ii", $order_id, $manufacturer_id);
$stmt->execute();

header("Location: order_manufacturer.php?tab=new&msg=order_rejected");
exit();
