<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payment Management</title>

<style>
/* ============ BAZAAR THEME ============ */
:root {
  --bazaar-bg: rgba(255, 245, 235, 0.92);
  --gold: #c7a76c;
  --gold-dark: #a67c52;
  --text: #3e2723;
  --text-light: #8d6e3f;
}

body {
  margin: 0;
  font-family: Georgia, serif;
  color: var(--text);
  background: transparent;
}

/* ============ BACKGROUND VIDEO ============ */
#bg-video {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  object-fit: cover;
  z-index: -1;
  pointer-events: none;
}

/* ============ HEADER ============ */
.header {
  background: var(--bazaar-bg);
  border-bottom: 2px solid #e0c68c;
  padding: 18px 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
}

.header h1 {
  margin: 0;
  font-size: 2rem;
  color: #6d4c1e;
}

.back-link {
  position: absolute;
  left: 24px;
  text-decoration: none;
  font-size: 1.2rem;
  font-weight: bold;
  color: var(--text);
}

/* ============ CONTAINER ============ */
.container {
  padding: 2rem;
}

.section,
.summary {
  background: var(--bazaar-bg);
  border-radius: 12px;
  border: 2px solid #e0c68c;
  padding: 1.5rem;
  margin-bottom: 2rem;
}

.section h2 {
  margin-top: 0;
  color: #6d4c1e;
}

/* ============ INVOICE FORM ============ */
.invoice-form {
  display: flex;
  gap: 1rem;
  flex-wrap: wrap;
}

.invoice-form select,
.invoice-form button {
  padding: 10px 16px;
  border-radius: 30px;
  font-family: inherit;
}

.invoice-form select {
  border: 2px solid var(--gold);
}

.invoice-form button {
  border: none;
  background: #ffe7b3;
  font-weight: bold;
  cursor: pointer;
}

/* ============ TABLE ============ */
.payment-history-table {
  width: 100%;
  border-collapse: collapse;
}

.payment-history-table th,
.payment-history-table td {
  padding: 1rem;
  border-bottom: 1px solid #e0c68c;
}

.payment-history-table th {
  background: #f3e2c3;
}

/* ============ BUTTON ============ */
.make-payment {
  margin-top: 1rem;
  padding: 10px 20px;
  border-radius: 30px;
  border: none;
  background: #ffecb3;
  font-weight: bold;
  cursor: pointer;
}

/* ============ FOOTER ============ */
footer {
  background: var(--bazaar-bg);
  border-top: 2px solid #e0c68c;
  text-align: center;
  padding: 1rem;
  color: var(--text-light);
}
</style>
</head>

<body>

<!-- ✅ BACKGROUND SILK VIDEO -->
<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
  Your browser does not support the video tag.
</video>

<header class="header">
  <a href="../login.html" class="back-link">← Home</a>
  <h1>Payment Management</h1>
</header>

<div class="container">

  <div class="section">
    <h2>Generate Invoice</h2>
    <form class="invoice-form">
      <select>
        <option>Order123</option>
        <option>Order456</option>
      </select>
      <button>Generate Invoice</button>
    </form>
  </div>

  <div class="section">
    <h2>Payment History</h2>
    <table class="payment-history-table">
      <tr>
        <th>Payment ID</th>
        <th>Date</th>
        <th>Amount</th>
        <th>Status</th>
      </tr>
      <tr>
        <td>P12345</td>
        <td>2025-12-01</td>
        <td>₹5000</td>
        <td>Completed</td>
      </tr>
      <tr>
        <td>P67890</td>
        <td>2025-12-05</td>
        <td>₹10000</td>
        <td>Pending</td>
      </tr>
    </table>
  </div>

  <div class="summary">
    <p><strong>Outstanding Dues:</strong> ₹15000</p>
    <p><strong>Credit Balance:</strong> ₹5000</p>
    <button class="make-payment">Make Payment</button>
  </div>

</div>

<footer>
  &copy; 2025 Fabric Bazaar. All rights reserved.
</footer>

</body>
</html>
