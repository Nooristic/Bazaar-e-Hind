<?php
session_start();

// Security Check
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Database Connection
$host = 'localhost';
$user = 'root';
$password = ''; // Replace with your database password
$dbname = 'bazaar_e_hind';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Disputes for Dashboard
$sql = "SELECT d.dispute_id, d.type, d.amount, d.sla_breach, u1.username AS buyer, u2.username AS seller, d.status
        FROM disputes d
        LEFT JOIN users u1 ON d.buyer_id = u1.id
        LEFT JOIN users u2 ON d.seller_id = u2.id";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$disputes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Summary Stats
$open_count = $conn->query("SELECT COUNT(*) AS count FROM disputes WHERE status = 'open'")->fetch_assoc()['count'];
$today_new = $conn->query("SELECT COUNT(*) AS count FROM disputes WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
$avg_resolve_time = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_time FROM disputes WHERE status = 'resolved'")->fetch_assoc()['avg_time'];

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Invalid request'];

    if (isset($_POST['action'])) {
        $admin_id = $_SESSION['admin_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];

        if ($_POST['action'] === 'resolve' && isset($_POST['dispute_id'], $_POST['resolution_type'], $_POST['resolution_amount'])) {
            $dispute_id = (int)$_POST['dispute_id'];
            $resolution_type = $_POST['resolution_type'];
            $resolution_amount = (float)$_POST['resolution_amount'];
            $notes = $_POST['notes'] ?? '';

            $stmt = $conn->prepare("UPDATE disputes SET status = 'resolved', resolution_type = ?, resolution_amount = ?, resolved_by = ?, resolved_at = NOW() WHERE dispute_id = ?");
            $stmt->bind_param('sdii', $resolution_type, $resolution_amount, $admin_id, $dispute_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) VALUES (?, 'DISPUTE_RESOLVED', ?, ?, ?)");
            $description = "Dispute resolved with resolution type: $resolution_type, amount: $resolution_amount";
            $stmt->bind_param('iiss', $admin_id, $dispute_id, $description, $ip_address);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO dispute_messages (dispute_id, sender_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $message = "Resolution: $resolution_type, Amount: $resolution_amount. Notes: $notes";
            $stmt->bind_param('iis', $dispute_id, $admin_id, $message);
            $stmt->execute();
            $stmt->close();

            $response = ['success' => true, 'message' => 'Dispute resolved successfully'];
        }
    }

    echo json_encode($response);
    exit;
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispute Resolution Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table th, table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table th {
            background-color: #f4f4f4;
        }
        .actions button {
            margin-right: 5px;
        }
        .summary {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .summary div {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dispute Resolution Dashboard</h1>
        <div class="summary">
            <div>Open Disputes: <?= $open_count ?></div>
            <div>New Today: <?= $today_new ?></div>
            <div>Avg Resolve Time: <?= round($avg_resolve_time, 2) ?> hours</div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Dispute ID</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>SLA Breach</th>
                    <th>Buyer</th>
                    <th>Seller</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($disputes as $dispute): ?>
                    <tr data-dispute-id="<?= $dispute['dispute_id'] ?>">
                        <td><?= htmlspecialchars($dispute['dispute_id']) ?></td>
                        <td><?= htmlspecialchars($dispute['type']) ?></td>
                        <td><?= htmlspecialchars($dispute['amount']) ?></td>
                        <td><?= htmlspecialchars($dispute['sla_breach']) ?></td>
                        <td><?= htmlspecialchars($dispute['buyer']) ?></td>
                        <td><?= htmlspecialchars($dispute['seller']) ?></td>
                        <td><?= htmlspecialchars($dispute['status']) ?></td>
                        <td><button class="view-details">View Details</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('view-details')) {
                const row = e.target.closest('tr');
                const disputeId = row.dataset.disputeId;

                fetch(`dispute_details.php?dispute_id=${disputeId}`)
                    .then(response => response.text())
                    .then(html => {
                        const modal = document.createElement('div');
                        modal.innerHTML = html;
                        document.body.appendChild(modal);
                    });
            }
        });
    </script>
</body>
</html>