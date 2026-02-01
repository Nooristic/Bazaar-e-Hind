<?php
session_start();
if ($_SESSION['role'] !== 'wholesaler') exit;

$cart_id = (int)$_GET['id'];

$mysqli = new mysqli("localhost","root","","bazaar_e_hind");
$stmt = $mysqli->prepare("DELETE FROM cart WHERE cart_id = ?");
$stmt->bind_param("i", $cart_id);
$stmt->execute();

header("Location: cart.php");
