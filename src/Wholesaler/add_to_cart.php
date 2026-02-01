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

$wholesaler_id   = (int) $_SESSION['user_id'];
$fabric_id       = (int) ($_POST['fabric_id'] ?? 0);
$manufacturer_id = (int) ($_POST['manufacturer_id'] ?? 0);
$quantity        = (int) ($_POST['quantity'] ?? 0);

if ($fabric_id <= 0 || $manufacturer_id <= 0 || $quantity <= 0) {
    echo json_encode(['status' => 'error', 'msg' => 'Invalid data']);
    exit;
}

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
    "iii",
    $fabric_id,
    $manufacturer_id,
    $wholesaler_id
);

$accessCheck->execute();
$accessCheck->store_result();

if ($accessCheck->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'msg' => 'This fabric is not available for you'
    ]);
    exit;
}



/* ======================================================
   🛒 2️⃣ ADD / UPDATE CART (SAFE NOW)
   ====================================================== */

$check = $conn->prepare("
    SELECT cart_id, quantity
    FROM cart
    WHERE wholesaler_id = ?
      AND fabric_id = ?
");
$check->bind_param("ii", $wholesaler_id, $fabric_id);
$check->execute();
$res = $check->get_result();

if ($row = $res->fetch_assoc()) {
    $newQty = $row['quantity'] + $quantity;

    $upd = $conn->prepare("
        UPDATE cart
        SET quantity = ?
        WHERE cart_id = ?
    ");
    $upd->bind_param("ii", $newQty, $row['cart_id']);
    $upd->execute();
} else {
    $ins = $conn->prepare("
        INSERT INTO cart
        (wholesaler_id, manufacturer_id, fabric_id, quantity)
        VALUES (?, ?, ?, ?)
    ");
    $ins->bind_param(
        "iiii",
        $wholesaler_id,
        $manufacturer_id,
        $fabric_id,
        $quantity
    );
    $ins->execute();
}



/* ======================================================
   🔢 3️⃣ UPDATED CART COUNT
   ====================================================== */

$countStmt = $conn->prepare("
    SELECT COUNT(*) FROM cart WHERE wholesaler_id = ?
");
$countStmt->bind_param("i", $wholesaler_id);
$countStmt->execute();
$countStmt->bind_result($cartCount);
$countStmt->fetch();

$conn->close();



/* ======================================================
   ✅ 4️⃣ RESPONSE
   ====================================================== */

echo json_encode([
    'status'    => 'ok',
    'cartCount' => $cartCount
]);
