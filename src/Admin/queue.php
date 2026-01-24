<?php
session_start();

// Security: only logged-in admin
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'admin') {
    header("Location: /trial_project/src/login.html");
    exit();
}

$admin_id = $_SESSION['user_id'] ?? 0;

// Simple CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Database Connection
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'bazaar_e_hind';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch pending users + verification docs
$sql = "SELECT u.user_id, u.username, u.company_name, u.role, u.created_at, 
               v.doc_type, v.file_url
        FROM users u
        LEFT JOIN verification_doc_requests v ON u.user_id = v.user_id
        WHERE u.is_verified = 0 OR u.verification_status = 'pending'
        ORDER BY u.created_at DESC";

$result = $conn->query($sql);
$pending_users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Fetch open disputes (using your current column names)
$sql = "SELECT id AS dispute_id, type, against_id AS related_id, status 
        FROM disputes 
        WHERE status = 'open'
        ORDER BY id DESC";

$result = $conn->query($sql);
$open_disputes = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// ────────────────────────────────────────────────
// ────────────────────────────────────────────────
// AJAX POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true) ?: $_POST;

    $response = ['success' => false, 'message' => 'Invalid request'];

    if (!isset($data['csrf']) || $data['csrf'] !== $csrf_token) {
        $response['message'] = 'Invalid CSRF token';
        echo json_encode($response);
        exit;
    }

    $action = $data['action'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $conn->begin_transaction(); // Start transaction for atomicity

    try {
        if ($action === 'approve' && !empty($data['user_ids'])) {
            $user_ids = array_map('intval', (array)$data['user_ids']);
            $approved_count = 0;

            foreach ($user_ids as $uid) {
                // Update user
                $stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_status = 'verified' WHERE user_id = ?");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();

                // Log action
                $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) 
                                        VALUES (?, 'USER_VERIFIED', ?, 'User verified by admin', ?)");
                $stmt->bind_param('iis', $admin_id, $uid, $ip);
                $stmt->execute();
                $stmt->close();

                // Send notification to user
                $msg = "Your account has been approved by the Bazaar-e-Hind admin team. You can now fully use the platform (list products / place orders).";
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'verification_approved', ?)");
                $stmt->bind_param('is', $uid, $msg);
                $stmt->execute();
                $stmt->close();

                $approved_count++;
            }

            $response = [
                'success' => true,
                'message' => $approved_count . ' user(s) approved. They have been notified.'
            ];
        } 
        elseif ($action === 'reject' && !empty($data['user_ids'])) {
            $user_ids = array_map('intval', (array)$data['user_ids']);
            $rejected_count = 0;

            foreach ($user_ids as $uid) {
                $stmt = $conn->prepare("UPDATE users SET is_verified = 0, verification_status = 'rejected' WHERE user_id = ?");
                $stmt->bind_param('i', $uid);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, related_id, description, ip_address) 
                                        VALUES (?, 'USER_REJECTED', ?, 'User rejected by admin', ?)");
                $stmt->bind_param('iis', $admin_id, $uid, $ip);
                $stmt->execute();
                $stmt->close();

                // Notification to user
                $msg = "Your account verification was rejected by the admin team. Please review your documents and submit again.";
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'verification_rejected', ?)");
                $stmt->bind_param('is', $uid, $msg);
                $stmt->execute();
                $stmt->close();

                $rejected_count++;
            }

            $response = [
                'success' => true,
                'message' => $rejected_count . ' user(s) rejected. They have been notified.'
            ];
        } 
        elseif ($action === 'resolve_dispute' && !empty($data['dispute_id'])) {
            $dispute_id = (int)$data['dispute_id'];
            $stmt = $conn->prepare("UPDATE disputes SET status = 'resolved', resolved_by = ? WHERE id = ?");
            $stmt->bind_param('ii', $admin_id, $dispute_id);
            $stmt->execute();
            $stmt->close();

            // Optional: notify involved parties (you would need to query who is involved in the dispute)
            // For now just admin message
            $response = ['success' => true, 'message' => 'Dispute resolved'];
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Database error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Queue & Disputes - Bazaar-e-Hind</title>

    <!-- Shared stylesheet (same as prod_manufacturer.php) -->
    <link rel="stylesheet" href="../css_all_pages.css">

    <!-- Page-specific styles -->
    <style>
        .container {
            max-width: 1280px;
            margin: 2.5rem auto;
            padding: 0 2rem;
        }

        h1 {
            color: #c4924b;
            font-size: 2.1rem;
            margin-bottom: 1.8rem;
            text-align: center;
        }

        h2 {
            color: #c4924b;
            font-size: 1.5rem;
            margin: 2.5rem 0 1.2rem;
            border-bottom: 2px solid #e8d5b7;
            padding-bottom: 0.6rem;
        }

        .bulk-actions {
            margin: 1.5rem 0;
            display: flex;
            gap: 1rem;
        }

        .bulk-actions button {
            padding: 10px 24px;
            border-radius: 18px;
            border: 2px solid #d4b37e;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .approve {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .reject {
            background: #ffebee;
            color: #c62828;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 14px rgba(180, 140, 60, 0.12);
            margin-bottom: 2.5rem;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #f0e4d2;
        }
        th {
            background: #fdf8f0;
            color: #6d4c1e;
            font-weight: 600;
        }
        tr:hover {
            background: #fffaf0;
        }
        .actions button {
            padding: 7px 16px;
            border-radius: 12px;
            border: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .approve { background: #c8e6c9; color: #1b5e20; }
        .reject  { background: #ffcdd2; color: #b71c1c; }
        .resolve { background: #bbdefb; color: #0d47a1; }

        .actions button:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .empty-message {
            text-align: center;
            padding: 4rem 1rem;
            color: #8d6e3f;
            font-size: 1.3rem;
            background: #fffaf0;
            border-radius: 12px;
            border: 2px dashed #d4b37e;
        }

        .loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 1.2rem 2.5rem;
            border-radius: 12px;
            z-index: 9999;
        }
    </style>
</head>
<body>

<!-- Background video (same as other pages) -->
<video autoplay muted loop playsinline id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
</video>

<div class="header">
    <a href="admin_homepage.php" class="back-link">← Home</a>
    <span class="header-title">Verification Queue & Disputes</span>
    <div class="profile-btn">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="5"/><path d="M12 14c-5 0-8 2.5-8 5v1h16v-1c0-2.5-3-5-8-5z"/></svg>
    </div>
</div>

<div class="container">

    <h1>Admin Moderation Queue</h1>

    <h2>Pending User Verifications</h2>

    <div class="bulk-actions">
        <button id="approve-selected" class="approve">Approve Selected</button>
        <button id="reject-selected" class="reject">Reject Selected</button>
    </div>

    <table id="verification-table">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>User / Company</th>
                <th>Role</th>
                <th>Document</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pending_users)): ?>
                <tr><td colspan="6" class="empty-message">No pending verifications at this time.</td></tr>
            <?php else: ?>
                <?php foreach ($pending_users as $user): ?>
                <tr data-user-id="<?= htmlspecialchars($user['user_id']) ?>">
                    <td><input type="checkbox" class="user-checkbox"></td>
                    <td><?= htmlspecialchars($user['company_name'] ?: $user['username']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($user['role'])) ?></td>
                    <td>
                        <?php if (!empty($user['file_url'])): ?>
                            <a href="<?= htmlspecialchars($user['file_url']) ?>" target="_blank" style="color:#8d6e3f;">
                                View <?= htmlspecialchars($user['doc_type'] ?: 'Document') ?>
                            </a>
                        <?php else: ?>
                            No document uploaded
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                    <td class="actions">
                        <button class="approve">Approve</button>
                        <button class="reject">Reject</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Open Disputes</h2>

    <table id="disputes-table">
        <thead>
            <tr>
                <th>Dispute ID</th>
                <th>Type</th>
                <th>Related ID</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($open_disputes)): ?>
                <tr><td colspan="5" class="empty-message">No open disputes at this time.</td></tr>
            <?php else: ?>
                <?php foreach ($open_disputes as $d): ?>
                <tr data-dispute-id="<?= htmlspecialchars($d['dispute_id']) ?>">
                    <td><?= htmlspecialchars($d['dispute_id']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($d['type'] ?? '—')) ?></td>
                    <td><?= htmlspecialchars($d['related_id'] ?? '—') ?></td>
                    <td><?= htmlspecialchars(ucfirst($d['status'])) ?></td>
                    <td class="actions"><button class="resolve">Resolve</button></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

</div>
<script>
const csrfToken = "<?= htmlspecialchars($csrf_token) ?>";

function sendAction(action, payload = {}) {
    console.log('→ Sending:', action, payload); // debug

    const loading = document.createElement('div');
    loading.className = 'loading';
    loading.textContent = 'Processing...';
    document.body.appendChild(loading);

    fetch(window.location.pathname, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, csrf: csrfToken, ...payload })
    })
    .then(response => {
        console.log('← Status:', response.status);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        console.log('← Response:', data);
        loading.remove();

        if (data.success) {
            alert(data.message || 'Action completed successfully');
            // Remove rows
            if (payload.user_ids) {
                payload.user_ids.forEach(id => {
                    const row = document.querySelector(`tr[data-user-id="${id}"]`);
                    if (row) row.remove();
                });
            }
            if (payload.dispute_id) {
                const row = document.querySelector(`tr[data-dispute-id="${payload.dispute_id}"]`);
                if (row) row.remove();
            }
        } else {
            alert('Failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        loading.remove();
        console.error('Error:', err);
        alert('Network/server error: ' + err.message);
    });
}

// Select all checkbox
document.getElementById('select-all')?.addEventListener('change', function(e) {
    document.querySelectorAll('.user-checkbox').forEach(cb => {
        cb.checked = e.target.checked;
    });
});

// Individual buttons
document.addEventListener('click', function(e) {
    if (!e.target.matches('.actions button')) return;

    const btn = e.target;
    const row = btn.closest('tr');
    if (!row) return;

    const action = btn.className.trim(); // 'approve' or 'reject' or 'resolve'

    if (action === 'approve' || action === 'reject') {
        const userId = row.getAttribute('data-user-id');
        if (!userId) return alert('Error: No user ID found');
        
        if (!confirm(`Really ${action} this user?`)) return;
        sendAction(action, { user_ids: [userId] });
    }
    else if (action === 'resolve') {
        const disputeId = row.getAttribute('data-dispute-id');
        if (!disputeId) return alert('Error: No dispute ID found');
        
        if (!confirm('Resolve this dispute?')) return;
        sendAction('resolve_dispute', { dispute_id: disputeId });
    }
});

// Bulk approve / reject
document.getElementById('approve-selected')?.addEventListener('click', () => {
    const checked = Array.from(document.querySelectorAll('.user-checkbox:checked'));
    if (!checked.length) return alert('No users selected');
    
    if (!confirm(`Approve ${checked.length} users?`)) return;
    
    const userIds = checked
        .map(cb => cb.closest('tr')?.getAttribute('data-user-id'))
        .filter(id => id);
    
    if (userIds.length === 0) return alert('No valid users selected');
    
    sendAction('approve', { user_ids: userIds });
});

document.getElementById('reject-selected')?.addEventListener('click', () => {
    const checked = Array.from(document.querySelectorAll('.user-checkbox:checked'));
    if (!checked.length) return alert('No users selected');
    
    if (!confirm(`Reject ${checked.length} users?`)) return;
    
    const userIds = checked
        .map(cb => cb.closest('tr')?.getAttribute('data-user-id'))
        .filter(id => id);
    
    if (userIds.length === 0) return alert('No valid users selected');
    
    sendAction('reject', { user_ids: userIds });
});
</script>
</body>
</html>