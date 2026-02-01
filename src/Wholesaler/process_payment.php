<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    exit("Unauthorized");
}

if (!isset($_POST['invoice_id'], $_POST['amount'])) {
    exit("Invalid request");
}

$invoice_id = (int)$_POST['invoice_id'];
$amount = $_POST['amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Process Payment</title>

<style>
:root {
  --bg: rgba(255,245,235,0.92);
  --gold: #c7a76c;
  --dark: #3e2723;
}

body {
  margin: 0;
  font-family: Georgia, serif;
  color: var(--dark);
}

#bg-video {
  position: fixed;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  z-index: -1;
}

.payment-box {
  max-width: 520px;
  margin: 8% auto;
  background: var(--bg);
  border-radius: 20px;
  padding: 30px;
  border: 2px solid var(--gold);
  text-align: center;
}

.method {
  border: 2px solid var(--gold);
  border-radius: 14px;
  padding: 14px;
  margin: 15px 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 15px;
  justify-content: center;
  font-weight: bold;
}

.method img {
  height: 32px;
}

.hidden {
  display: none;
}

input {
  width: 90%;
  padding: 10px;
  margin: 8px 0;
  border-radius: 10px;
  border: 1px solid #c7a76c;
}

button {
  margin-top: 12px;
  padding: 12px 30px;
  border-radius: 25px;
  border: none;
  background: #ffe7b3;
  font-weight: bold;
  cursor: pointer;
}

.processing {
  font-weight: bold;
  margin-top: 15px;
}
</style>
</head>

<body>

<video autoplay muted loop id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="payment-box">
  <h2>Complete Your Payment</h2>
  <p><strong>Invoice ID:</strong> #<?= $invoice_id ?></p>
  <p><strong>Amount Payable:</strong> ₹<?= number_format($amount) ?></p>

  <!-- PAYMENT METHODS -->
  <div class="method" onclick="selectMethod('upi')">
    <img src="../../assests/upi.png">
    Pay via UPI
  </div>

  <div class="method" onclick="selectMethod('card')">
    <img src="../../assests/card.png">
    Credit / Debit Card
  </div>

  <div class="method" onclick="selectMethod('netbanking')">
    <img src="../../assests/bank.png">
    Net Banking
  </div>

  <!-- FORMS -->
  <form method="post" action="finalize_payment.php" id="paymentForm">
    <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">
    <input type="hidden" name="amount" value="<?= $amount ?>">
    <input type="hidden" name="method" id="method">

    <!-- UPI -->
    <div id="upi" class="hidden">
      <input name="upi_id" placeholder="Enter UPI ID">
    </div>

    <!-- CARD -->
    <div id="card" class="hidden">
      <input name="card_name" placeholder="Cardholder Name" >
      <input name="card_number" placeholder="Card Number" >
      <input name="cvv" placeholder="CVV" >
    </div>

    <!-- NETBANKING -->
    <div id="netbanking" class="hidden">
      <input name="bank" placeholder="Bank Name" >
    </div>

<button type="submit" id="payBtn" disabled>
  Pay ₹<?= number_format($amount) ?>
</button>
  </form>

  <div class="processing hidden" id="processing">
    Processing payment...
  </div>

  <p style="margin-top:15px;font-size:14px">
    This is a simulated payment. No real transaction will occur.
  </p>
</div>
<div id="overlay" style="
  position:fixed;
  inset:0;
  background:rgba(0,0,0,0.4);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:999;
">
  <div style="
    background:#fff4e6;
    padding:30px 40px;
    border-radius:18px;
    text-align:center;
    font-weight:bold;
  ">
    <div class="spinner"></div>
    Processing payment…
  </div>
</div>

<style>
.spinner {
  width:40px;
  height:40px;
  border:4px solid #c7a76c;
  border-top:4px solid transparent;
  border-radius:50%;
  margin:0 auto 15px;
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>

<script>
let selectedMethod = null;

function selectMethod(method) {
  selectedMethod = method;
  document.getElementById('method').value = method;

  ['upi','card','netbanking'].forEach(m => {
    document.getElementById(m).classList.add('hidden');
  });

  document.getElementById(method).classList.remove('hidden');
}
document.getElementById('payBtn').disabled = false;

/* Validate before submit */
document.getElementById('paymentForm').addEventListener('submit', function (e) {

  if (!selectedMethod) {
    alert("Please select a payment method");
    e.preventDefault();
    return;
  }

  if (selectedMethod === 'upi') {
    const upi = document.querySelector("input[name='upi_id']").value.trim();
    if (!upi) {
      alert("Please enter UPI ID");
      e.preventDefault();
    }
  }

  if (selectedMethod === 'card') {
    const name = document.querySelector("input[name='card_name']").value.trim();
    const number = document.querySelector("input[name='card_number']").value.trim();
    const cvv = document.querySelector("input[name='cvv']").value.trim();

    if (!name || !number || !cvv) {
      alert("Please fill all card details");
      e.preventDefault();
    }
  }

  if (selectedMethod === 'netbanking') {
    const bank = document.querySelector("input[name='bank']").value.trim();
    if (!bank) {
      alert("Please enter bank name");
      e.preventDefault();
    }
  }
});

document.getElementById('paymentForm').addEventListener('submit', function(e) {
  e.preventDefault(); // stop instant submit

  // show processing overlay
  document.getElementById('overlay').style.display = 'flex';

  // simulate payment gateway delay
  setTimeout(() => {

    // realistic success rate
    const success = Math.random() < 0.85;

    if (success) {
      e.target.submit(); // now submit to finalize_payment.php
    } else {
      document.getElementById('overlay').style.display = 'none';
      alert("Payment failed. Please try again.");
    }

  }, 2000); // 2 seconds
});
</script>

</body>
</html>
