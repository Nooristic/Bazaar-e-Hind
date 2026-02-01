<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../../login.php");
    exit();
}

$wholesaler_id = $_SESSION['user_id'];

$mysqli = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($mysqli->connect_error) die("DB Error");
$mysqli->set_charset("utf8mb4");

$stmt = $mysqli->prepare("
  SELECT 
    c.cart_id,
    c.quantity,
    f.fabric_id,
    f.name AS fabric_name,
    f.price,
    f.moq,
    u.company_name AS manufacturer
  FROM cart c
  JOIN fabrics f ON f.fabric_id = c.fabric_id
  JOIN users u ON u.user_id = c.manufacturer_id
  WHERE c.wholesaler_id = ?
");
$stmt->bind_param("i", $wholesaler_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
<title>Your Cart</title>
<link rel="stylesheet" href="../css_all_pages.css">

<style>
table {
  width:100%;
  border-collapse:collapse;
  background:var(--bazaar-bg);
  border-radius:14px;
  overflow:hidden;
}
th,td {
  padding:14px;
  border-bottom:1px solid #e0c68c;
  text-align:center;
}
th {
  background:#f6e6c7;
  color:#6d4c1e;
}
input[type=number] {
  width:80px;
  padding:6px;
  border-radius:8px;
  border:1px solid #c7a76c;
  text-align:center;
}

/* Buttons */
.btn {
  padding:8px 18px;
  border-radius:18px;
  border:none;
  font-weight:bold;
  cursor:pointer;
}

/* Secondary */
.update {
  background:#dbeafe;
  color:#1e40af;
}
.update:hover { background:#bfdbfe; }

/* Danger */
.remove {
  background:#f8d7da;
  color:#721c24;
  text-decoration:none;
}
.remove:hover { background:#f5c6cb; }

/* Primary */
.checkout {
  background:#c7a76c;
  color:#3e2723;
  padding:14px 32px;
  font-size:1.1rem;
}
.checkout:hover { background:#b89654; }

.actions-bar {
  display:flex;
  justify-content:flex-end;
  gap:20px;
  margin-top:30px;
}
</style>
</head>

<body>

<?php
$cartCount = 0;
$countStmt = $mysqli->prepare("SELECT COUNT(*) FROM cart WHERE wholesaler_id = ?");
$countStmt->bind_param("i", $wholesaler_id);
$countStmt->execute();
$countStmt->bind_result($cartCount);
$countStmt->fetch();
$countStmt->close();
?>

<div class="header">
  <a href="prod.php" class="back-link">← Browse Fabrics</a>

  <span class="header-title">Your Cart</span>

  <a href="cart.php"
     style="
       position:absolute;
       right:24px;
       top:18px;
       font-weight:bold;
       text-decoration:none;
       color:#6d4c1e;
     ">
    🛒 Cart
    <?php if ($cartCount > 0): ?>
      <span style="
        background:#c0392b;
        color:white;
        border-radius:50%;
        padding:2px 8px;
        font-size:0.75rem;
        margin-left:6px;
      ">
        <?= $cartCount ?>
      </span>
    <?php endif; ?>
  </a>
</div>
<?php if (empty($items)): ?>
  <p style="text-align:center;padding:60px;font-size:1.2rem">
    Your cart is empty.
  </p>
<?php else: ?>

<form method="post" action="update_cart.php">
<table>
<tr>
  <th>Fabric</th>
  <th>Manufacturer</th>
  <th>Price</th>
  <th>MOQ</th>
  <th>Quantity</th>
  <th>Action</th>
</tr>

<?php foreach ($items as $i): ?>
<tr>
  <td><?= htmlspecialchars($i['fabric_name']) ?></td>
  <td><?= htmlspecialchars($i['manufacturer']) ?></td>
  <td>₹<?= number_format($i['price'],2) ?></td>
  <td><?= $i['moq'] ?></td>
  <td>
    <input type="number"
           name="qty[<?= $i['cart_id'] ?>]"
           min="<?= $i['moq'] ?>"
           value="<?= $i['quantity'] ?>">
  </td>
  <td>
    <a href="remove_cart_item.php?id=<?= $i['cart_id'] ?>"
       class="btn remove"
       onclick="return confirm('Remove this item from cart?')">
       Remove
    </a>
  </td>
</tr>
<?php endforeach; ?>
</table>

<div class="actions-bar">
  <button class="btn update" type="submit">Update Cart</button>
</form>

<form method="post" action="place_order.php">
  <button class="btn checkout">Proceed to Checkout</button>
</form>
</div>

<?php endif; ?>
<?php $mysqli->close(); ?>
</body>
</html>
