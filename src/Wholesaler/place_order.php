<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    die("Unauthorized");
}

$wholesaler_id = (int)$_SESSION['user_id'];

$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) die("DB error");

$conn->begin_transaction();

try {

    /* 1️⃣ Fetch cart items */
    $stmt = $conn->prepare("
        SELECT 
    c.fabric_id,
    c.manufacturer_id,
    c.quantity,
    COALESCE(f.price, 0) AS price
FROM cart c
JOIN fabrics f ON f.fabric_id = c.fabric_id
WHERE c.wholesaler_id = ?

    ");
    $stmt->bind_param("i", $wholesaler_id);
    $stmt->execute();
    $cartItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($cartItems)) {
        throw new Exception("Cart is empty");
    }

    /* 2️⃣ Group by manufacturer */
    $grouped = [];
    foreach ($cartItems as $item) {
        $grouped[$item['manufacturer_id']][] = $item;
    }

    /* 3️⃣ Create orders per manufacturer */
    foreach ($grouped as $manufacturer_id => $items) {

        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }

        // Create order
        $orderStmt = $conn->prepare("
            INSERT INTO orders (
                wholesaler_id,
                manufacturer_id,
                total_amount,
                status,
                order_date
            ) VALUES (?, ?, ?, 'ordered', NOW())
        ");
        $orderStmt->bind_param("iid", $wholesaler_id, $manufacturer_id, $total_amount);
        $orderStmt->execute();
        $order_id = $conn->insert_id;
        $orderStmt->close();

        // Insert order items
        /* =======================
   4️⃣ INSERT ORDER ITEMS
   ======================= */
$itemStmt = $conn->prepare("
    INSERT INTO order_items (
        order_id,
        fabric_id,
        quantity,
        price_at_purchase
    )
    VALUES (?, ?, ?, ?)
");

foreach ($cartItems as $item) {

    if ($item['price'] <= 0) {
        throw new Exception("Invalid price for fabric ID " . $item['fabric_id']);
    }

    $itemStmt->bind_param(
        "iiid",
        $order_id,
        $item['fabric_id'],
        $item['quantity'],
        $item['price']
    );
    $itemStmt->execute();
}

$itemStmt->close();

    }

    /* 4️⃣ Clear cart */
    $clear = $conn->prepare("DELETE FROM cart WHERE wholesaler_id = ?");
    $clear->bind_param("i", $wholesaler_id);
    $clear->execute();
    $clear->close();

    $conn->commit();

    header("Location: orders.php?msg=order_placed");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    die("Order failed: " . $e->getMessage());
}
