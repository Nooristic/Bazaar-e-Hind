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

// Fetch Pending Users
$sql = "SELECT u.id, u.username, u.company_name, u.role, u.created_at, v.doc_type, v.file_url
        FROM users u
        LEFT JOIN verification_doc_requests v ON u.id = v.user_id
        WHERE u.is_verified = 0 OR u.verification_status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$pending_users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch Open Disputes
$sql = "SELECT dispute_id, type, related_id, status FROM disputes WHERE status = 'open'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$open_disputes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Invalid request'];

    if (isset($_POST['action'])) {
        $admin_id = $_SESSION['admin_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];

        if ($_POST['action'] === 'approve' && isset($_POST['user_ids'])) {
            $user_ids = $_POST['user_ids'];
            foreach ($user_ids as $user_id) {
                $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_status = 'verified' WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) VALUES (?, 'USER_VERIFIED', ?, 'User verified by admin', ?)");
                $stmt->bind_param('iis', $admin_id, $user_id, $ip_address);
                $stmt->execute();
                $stmt->close();
            }
            $response = ['success' => true, 'message' => 'Selected users approved'];
        } elseif ($_POST['action'] === 'reject' && isset($_POST['user_ids'])) {
            $user_ids = $_POST['user_ids'];
            foreach ($user_ids as $user_id) {
                $stmt = $conn->prepare("UPDATE users SET is_verified = 0, verification_status = 'rejected' WHERE id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) VALUES (?, 'USER_REJECTED', ?, 'User rejected by admin', ?)");
                $stmt->bind_param('iis', $admin_id, $user_id, $ip_address);
                $stmt->execute();
                $stmt->close();
            }
            $response = ['success' => true, 'message' => 'Selected users rejected'];
        } elseif ($_POST['action'] === 'resolve_dispute' && isset($_POST['dispute_id'])) {
            $dispute_id = (int)$_POST['dispute_id'];
            $stmt = $conn->prepare("UPDATE disputes SET status = 'resolved', resolved_by = ? WHERE dispute_id = ?");
            $stmt->bind_param('ii', $admin_id, $dispute_id);
            $stmt->execute();
            $stmt->close();

            $response = ['success' => true, 'message' => 'Dispute resolved'];
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
    <title>Verification Queue & Disputes</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Verification Queue</h1>
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>User/Company</th>
                    <th>Role</th>
                    <th>Documents</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_users as $user): ?>
                    <tr data-user-id="<?= $user['id'] ?>">
                        <td><input type="checkbox" class="user-checkbox"></td>
                        <td><?= htmlspecialchars($user['company_name'] ?: $user['username']) ?></td>
                        <td><?= htmlspecialchars($user['role']) ?></td>
                        <td><a href="<?= htmlspecialchars($user['file_url']) ?>" target="_blank">View <?= htmlspecialchars($user['doc_type']) ?></a></td>
                        <td><?= htmlspecialchars($user['created_at']) ?></td>
                        <td class="actions">
                            <button class="approve">Approve</button>
                            <button class="reject">Reject</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Open Disputes</h2>
        <table>
            <thead>
                <tr>
                    <th>Dispute ID</th>
                    <th>Type</th>
                    <th>Related ID</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($open_disputes as $dispute): ?>
                    <tr data-dispute-id="<?= $dispute['dispute_id'] ?>">
                        <td><?= htmlspecialchars($dispute['dispute_id']) ?></td>
                        <td><?= htmlspecialchars($dispute['type']) ?></td>
                        <td><?= htmlspecialchars($dispute['related_id']) ?></td>
                        <td><?= htmlspecialchars($dispute['status']) ?></td>
                        <td><button class="resolve">Resolve</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById('select-all').addEventListener('change', function () {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        document.addEventListener('click', function (e) {
            if (e.target.tagName === 'BUTTON') {
                const row = e.target.closest('tr');
                const userId = row.dataset.userId;
                const disputeId = row.dataset.disputeId;
                const action = e.target.className;

                let payload = { action };
                if (userId) payload.user_ids = [userId];
                if (disputeId) payload.dispute_id = disputeId;

                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        row.remove();
                    }
                });
            }
        });
    </script>
</body>
</html>