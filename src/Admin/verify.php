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
$sql = "SELECT u.id, u.username, u.company_name, u.role, v.doc_type, v.file_url
        FROM users u
        LEFT JOIN verification_doc_requests v ON u.id = v.user_id
        WHERE u.is_verified = 0 OR u.verification_status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$pending_users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count Pending Users
$pending_count = count($pending_users);

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => 'Invalid request'];

    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $admin_id = $_SESSION['admin_id'];
        $ip_address = $_SERVER['REMOTE_ADDR'];

        if ($_POST['action'] === 'approve') {
            $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_status = 'verified' WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) VALUES (?, 'USER_VERIFIED', ?, 'User verified by admin', ?)");
            $stmt->bind_param('iis', $admin_id, $user_id, $ip_address);
            $stmt->execute();
            $stmt->close();

            $response = ['success' => true, 'message' => 'User approved'];
        } elseif ($_POST['action'] === 'reject') {
            $stmt = $conn->prepare("UPDATE users SET is_verified = 0, verification_status = 'rejected' WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) VALUES (?, 'USER_REJECTED', ?, 'User rejected - invalid documents', ?)");
            $stmt->bind_param('iis', $admin_id, $user_id, $ip_address);
            $stmt->execute();
            $stmt->close();

            $response = ['success' => true, 'message' => 'User rejected'];
        } elseif ($_POST['action'] === 'change_role' && isset($_POST['new_role'])) {
            $new_role = $_POST['new_role'];
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param('si', $new_role, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) VALUES (?, 'ROLE_CHANGED', ?, CONCAT('Role changed to ', ?), ?)");
            $stmt->bind_param('iiss', $admin_id, $user_id, $new_role, $ip_address);
            $stmt->execute();
            $stmt->close();

            $response = ['success' => true, 'message' => 'Role updated'];
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
    <title>Verify Users</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
        }
        .card button {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Verify Users</h1>
            <span>Pending: <?= $pending_count ?></span>
        </div>

        <?php foreach ($pending_users as $user): ?>
            <div class="card" data-user-id="<?= $user['id'] ?>">
                <p><strong>User:</strong> <?= htmlspecialchars($user['company_name'] ?: $user['username']) ?></p>
                <p><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
                <p><strong>Docs:</strong> <a href="<?= htmlspecialchars($user['file_url']) ?>" target="_blank">View <?= htmlspecialchars($user['doc_type']) ?></a></p>
                <button class="approve">Approve</button>
                <button class="reject">Reject</button>
                <button class="next">Next</button>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        document.addEventListener('click', function (e) {
            if (e.target.tagName === 'BUTTON') {
                const card = e.target.closest('.card');
                const userId = card.dataset.userId;
                const action = e.target.className;

                let payload = { action, user_id: userId };

                if (action === 'change_role') {
                    const newRole = prompt('Enter new role (manufacturer/wholesaler/admin):');
                    if (!newRole) return;
                    payload.new_role = newRole;
                }

                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        card.remove();
                        document.querySelector('.header span').textContent = `Pending: ${data.new_pending_count}`;
                    }
                });
            }
        });
    </script>
</body>
</html>