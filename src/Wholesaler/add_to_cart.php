<?php
session_start();
header('Content-Type: application/json');

/* ===============================
   AUTH CHECK
================================ */
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Unauthorized'
    ]);
    exit;
}

/* ===============================
   BASIC VALIDATION
================================ */
if (
    empty($_POST['fabric_id']) ||
    empty($_POST['manufacturer_id']) ||
    empty($_POST['quantity'])
) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Invalid request'
    ]);
    exit;
}

$wholesaler_id   = (int) $_SESSION['user_id'];
$fabric_id       = (int) $_POST['fabric_id'];
$manufacturer_id = (int) $_POST['manufacturer_id'];
$quantity        = (int) $_POST['quantity'];

if ($quantity < 1) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Invalid quantity'
    ]);
    exit;
}

/* ===============================
   DB CONNECTION
================================ */
$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Database connection failed'
    ]);
    exit;
}
$mysqli->set_charset("utf8mb4");

/* ===============================
   FETCH MOQ (BACKEND ENFORCEMENT)
================================ */
$moqStmt = $mysqli->prepare("
    SELECT moq
    FROM fabrics
    WHERE fabric_id = ? AND is_active = 1
");
$moqStmt->bind_param("i", $fabric_id);
$moqStmt->execute();
$moqStmt->bind_result($moq);
$moqStmt->fetch();
$moqStmt->close();

if (!$moq) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Fabric not available'
    ]);
    exit;
}

if ($quantity < $moq) {
    echo json_encode([
        'status' => 'error',
        'msg'    => "Minimum order quantity is $moq"
    ]);
    exit;
}

/* ===============================
   PREVENT DUPLICATES
================================ */
$checkStmt = $mysqli->prepare("
    SELECT cart_id
    FROM cart
    WHERE wholesaler_id = ? AND fabric_id = ?
");

$checkStmt->bind_param("ii", $wholesaler_id, $fabric_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Item already in cart'
    ]);
    exit;
}
$checkStmt->close();

/* ===============================
   INSERT INTO CART (NO COLOR)
================================ */
$insertStmt = $mysqli->prepare("
    INSERT INTO cart (wholesaler_id, fabric_id, manufacturer_id, quantity)
    VALUES (?, ?, ?, ?)
");
$insertStmt->bind_param(
    "iiii",
    $wholesaler_id,
    $fabric_id,
    $manufacturer_id,
    $quantity
);

if (!$insertStmt->execute()) {
    echo json_encode([
        'status' => 'error',
        'msg'    => 'Failed to add item to cart'
    ]);
    exit;
}
$insertStmt->close();

/* ===============================
   UPDATED CART COUNT
================================ */
$countStmt = $mysqli->prepare("
    SELECT COUNT(*)
    FROM cart
    WHERE wholesaler_id = ?
");
$countStmt->bind_param("i", $wholesaler_id);
$countStmt->execute();
$countStmt->bind_result($cartCount);
$countStmt->fetch();
$countStmt->close();

echo json_encode([
    'status'    => 'ok',
    'cartCount' => $cartCount
]);

$mysqli->close();
