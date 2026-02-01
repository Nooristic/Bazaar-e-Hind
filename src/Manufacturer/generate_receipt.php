<?php
session_start();

/* =========================
   SECURITY CHECKS
========================= */
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.php");
    exit();
}

if (!isset($_POST['payment_id'])) {
    die("Invalid request");
}

$manufacturer_id = $_SESSION['user_id'];
$payment_id = (int) $_POST['payment_id'];

/* =========================
   DATABASE CONNECTION
========================= */
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("Database connection failed");
}

/* =========================
   VALIDATE PAYMENT
========================= */
$stmt = $conn->prepare("
    SELECT payment_id 
    FROM payments 
    WHERE payment_id = ? 
      AND manufacturer_id = ?
      AND status = 'completed'
");
$stmt->bind_param("ii", $payment_id, $manufacturer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Unauthorized or invalid payment");
}

/* =========================
   PREVENT DUPLICATE RECEIPTS
========================= */
$check = $conn->prepare("
    SELECT receipt_id 
    FROM receipts 
    WHERE payment_id = ?
");
$check->bind_param("i", $payment_id);
$check->execute();
$exists = $check->get_result();

if ($exists->num_rows > 0) {
    header("Location: payment_management.php?msg=receipt_exists");
    exit();
}

/* =========================
   CREATE RECEIPT
========================= */
$insert = $conn->prepare("
    INSERT INTO receipts (payment_id, generated_by)
    VALUES (?, ?)
");
$insert->bind_param("ii", $payment_id, $manufacturer_id);

if ($insert->execute()) {
    header("Location: payment_management.php?msg=receipt_generated");
    exit();
} else {
    die("Failed to generate receipt");
}
