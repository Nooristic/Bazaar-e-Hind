<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized']);
    exit;
}

if (
    empty($_POST['fabric_id']) ||
    empty($_POST['manufacturer_id'])
) {
    echo json_encode(['status' => 'error', 'msg' => 'Missing data']);
    exit;
}

$wholesaler_id   = (int) $_SESSION['user_id'];
$fabric_id       = (int) $_POST['fabric_id'];
$manufacturer_id = (int) $_POST['manufacturer_id'];
$quantity        = isset($_POST['quantity']) ? (float) $_POST['quantity'] : 1;

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'msg' => 'DB error']);
    exit;
}
$conn->set_charset("utf8mb4");


/* ======================================================
   🔐 1️⃣ EXCLUSIVITY + VISIBILITY CHECK (CRITICAL)
   ====================================================== */

$accessCheck = $conn->prepare("
    SELECT f.fabric_id
    FROM fabrics f

    LEFT JOIN fabric_exclusive_access fe
      ON fe.fabric_id = f.fabric_id
      AND fe.is_active = 1
      AND CURDATE() BETWEEN fe.start_date AND fe.end_date

    WHERE f.fabric_id = ?
      AND f.user_id = ?
      AND f.status = 'approved'
      AND f.is_active = 1
      AND (
          f.visibility_type = 'public'
          OR fe.wholesaler_id = ?
      )
    LIMIT 1
");

$accessCheck->bind_param(
    'iii',
    $fabric_id,
    $manufacturer_id,
    $wholesaler_id
);

$accessCheck->execute();
$accessCheck->store_result();

if ($accessCheck->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'You are not allowed to request a sample for this fabric'
    ]);
    exit;
}



/* ======================================================
   🔒 2️⃣ PREVENT DUPLICATE ACTIVE REQUESTS
   ====================================================== */

$check = $conn->prepare("
    SELECT sample_id
    FROM sample_requests
    WHERE wholesaler_id = ?
      AND fabric_id = ?
      AND status IN ('requested', 'accepted')
    LIMIT 1
");
$check->bind_param("ii", $wholesaler_id, $fabric_id);
$check->execute();
$exists = $check->get_result()->fetch_assoc();
$check->close();

if ($exists) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Sample already requested'
    ]);
    exit;
}



/* ======================================================
   ✅ 3️⃣ INSERT SAMPLE REQUEST
   ====================================================== */

$stmt = $conn->prepare("
    INSERT INTO sample_requests (
        wholesaler_id,
        manufacturer_id,
        fabric_id,
        quantity,
        status,
        created_at
    )
    VALUES (?, ?, ?, ?, 'requested', NOW())
");

$stmt->bind_param(
    "iiid",
    $wholesaler_id,
    $manufacturer_id,
    $fabric_id,
    $quantity
);

if ($stmt->execute()) {
    echo json_encode([
        'status' => 'ok',
        'msg' => 'Sample request sent'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'msg' => 'Failed to send request'
    ]);
}

$stmt->close();
$conn->close();
