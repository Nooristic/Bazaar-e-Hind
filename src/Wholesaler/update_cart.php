<?php
session_start();
if ($_SESSION['role'] !== 'wholesaler') exit;

$mysqli = new mysqli("localhost","root","","bazaar_e_hind");

foreach ($_POST['qty'] as $cart_id => $qty) {
    $stmt = $mysqli->prepare("
      UPDATE cart SET quantity = ? WHERE cart_id = ?
    ");
    $stmt->bind_param("ii", $qty, $cart_id);
    $stmt->execute();
}

header("Location: cart.php");
