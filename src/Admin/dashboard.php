<?php
session_start();

// Admin session check
// Note: `login.php` sets `$_SESSION['user_id']` and `$_SESSION['role']`.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    // Redirect to the login form (GET) rather than the POST-only handler
    header('Location: ../login.html');
    exit;
}

// Database connection (reuse pattern from verify.php)
$host = 'localhost';
$user = 'root';
$password = ''; // update if needed
$dbname = 'bazaar_e_hind';
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Helpers
function esc($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// DB helper: check table/column existence
function table_exists($conn, $table) {
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '{$t}'");
    return $r && $r->num_rows > 0;
}
function table_has_column($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $res && $res->num_rows > 0;
}

// Handle CSV export for audit logs
if (isset($_GET['export']) && $_GET['export'] == '1') {
    // Collect same filters as below
    $params = [];
    $where = "1=1";

    if (!empty($_GET['start_date'])) {
        $where .= " AND a.created_at >= ?";
        $params[] = $_GET['start_date'] . ' 00:00:00';
    }
    if (!empty($_GET['end_date'])) {
        $where .= " AND a.created_at <= ?";
        $params[] = $_GET['end_date'] . ' 23:59:59';
    }
    if (!empty($_GET['action_type'])) {
        $where .= " AND a.action_type = ?";
        $params[] = $_GET['action_type'];
    }
    if (!empty($_GET['admin_id'])) {
        $where .= " AND a.admin_id = ?";
        $params[] = (int)$_GET['admin_id'];
    }

        // If audit_logs or its created_at column doesn't exist, return empty CSV
        if (!table_exists($conn, 'audit_logs') || !table_has_column($conn, 'audit_logs', 'created_at')) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=audit_logs_export_' . date('Ymd_His') . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Admin ID','Admin Name','Action Type','Related ID','IP','Description','Created At']);
        fclose($out);
        exit;
        }

        $sql = "SELECT a.id, a.admin_id, COALESCE(u.username, 'Unknown') AS admin_name, a.action_type, a.related_id, a.ip_address, a.description, a.created_at
            FROM audit_logs a
            LEFT JOIN users u ON a.admin_id = u.id
            WHERE {$where}
            ORDER BY a.created_at DESC";

        $stmt = $conn->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        // make ints for admin_id param
        foreach ($params as $k => $v) {
            if (is_numeric($v) && strpos($_GET['admin_id'] ?? '', (string)$v) !== false) $types[$k] = 'i';
        }
        $stmt->bind_param(implode('', array_map(function($i) use ($params){return is_int($params[$i])? 'i':'s';}, array_keys($params))), ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=audit_logs_export_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Admin ID','Admin Name','Action Type','Related ID','IP','Description','Created At']);
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [$row['id'],$row['admin_id'],$row['admin_name'],$row['action_type'],$row['related_id'],$row['ip_address'],$row['description'],$row['created_at']]);
    }
    fclose($out);
    exit;
}

// Fetch metrics
$metrics = [];

// Pending Verifications
$metrics['pending_verifications'] = 0;
if (table_exists($conn, 'verification_doc_requests')) {
    $q = "SELECT COUNT(*) AS cnt FROM verification_doc_requests WHERE status = 'pending' OR status IS NULL OR status = ''";
    $res = $conn->query($q);
    $row = $res ? $res->fetch_assoc() : null;
    $metrics['pending_verifications'] = (int)($row['cnt'] ?? 0);
}

// Open disputes and average resolve time (hours)
$q1 = "SELECT SUM(CASE WHEN (status IN ('open','pending') OR resolved_at IS NULL) THEN 1 ELSE 0 END) AS open_cnt FROM disputes";
$r1 = $conn->query($q1);
$metrics['open_disputes'] = (int)($r1->fetch_assoc()['open_cnt'] ?? 0);
$q2 = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) AS avg_resolve_hours FROM disputes WHERE resolved_at IS NOT NULL";
$r2 = $conn->query($q2);
$metrics['avg_resolve_hours'] = round((float)($r2->fetch_assoc()['avg_resolve_hours'] ?? 0),2);

// Flagged content (forum_posts + fabrics) — guard against missing/renamed columns
$metrics['flagged_content'] = 0;

$fpc = 0;
// forum_posts: check common flag column names
if ($conn->query("SHOW TABLES LIKE 'forum_posts'")->num_rows) {
    if (table_has_column($conn, 'forum_posts', 'flagged')) {
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM forum_posts WHERE flagged = 1");
        $fpc = (int)($r->fetch_assoc()['cnt'] ?? 0);
    } elseif (table_has_column($conn, 'forum_posts', 'is_flagged')) {
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM forum_posts WHERE is_flagged = 1");
        $fpc = (int)($r->fetch_assoc()['cnt'] ?? 0);
    }
}

$fbc = 0;
if ($conn->query("SHOW TABLES LIKE 'fabrics'")->num_rows) {
    if (table_has_column($conn, 'fabrics', 'flagged')) {
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM fabrics WHERE flagged = 1");
        $fbc = (int)($r->fetch_assoc()['cnt'] ?? 0);
    } elseif (table_has_column($conn, 'fabrics', 'is_flagged')) {
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM fabrics WHERE is_flagged = 1");
        $fbc = (int)($r->fetch_assoc()['cnt'] ?? 0);
    } elseif (table_has_column($conn, 'fabrics', 'status')) {
        // some schemas store status text
        $r = $conn->query("SELECT COUNT(*) AS cnt FROM fabrics WHERE status = 'flagged'");
        $fbc = (int)($r->fetch_assoc()['cnt'] ?? 0);
    }
}

$metrics['flagged_content'] = $fpc + $fbc;

// Today's admin actions
// Today's admin actions
$metrics['today_admin_actions'] = 0;
if (table_exists($conn, 'audit_logs') && table_has_column($conn, 'audit_logs', 'created_at')) {
    $today_actions_q = "SELECT COUNT(*) AS cnt FROM audit_logs WHERE DATE(created_at) = CURDATE()";
    $ra = $conn->query($today_actions_q);
    $metrics['today_admin_actions'] = (int)($ra->fetch_assoc()['cnt'] ?? 0);
}

// Quick stats last 7 days (admin actions per day)
$last7 = [];
if (table_exists($conn, 'audit_logs') && table_has_column($conn, 'audit_logs', 'created_at')) {
    $q7 = "SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM audit_logs WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY day ORDER BY day";
    $r7 = $conn->query($q7);
    while ($d = $r7->fetch_assoc()) { $last7[$d['day']] = (int)$d['cnt']; }
}
// ensure 7 days present
for ($i=6;$i>=0;$i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    if (!isset($last7[$day])) $last7[$day] = 0;
}

// Audit logs filters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$action_type = $_GET['action_type'] ?? '';
$admin_id = $_GET['admin_id'] ?? '';

$where = "1=1";
$params = [];
$types = '';
if ($start_date) { $where .= " AND a.created_at >= ?"; $params[] = $start_date . ' 00:00:00'; $types .= 's'; }
if ($end_date) { $where .= " AND a.created_at <= ?"; $params[] = $end_date . ' 23:59:59'; $types .= 's'; }
if ($action_type) { $where .= " AND a.action_type = ?"; $params[] = $action_type; $types .= 's'; }
if ($admin_id) { $where .= " AND a.admin_id = ?"; $params[] = (int)$admin_id; $types .= 'i'; }

$logs_res = null;
// Prepare logs result only if audit_logs exists and has created_at
if (table_exists($conn, 'audit_logs') && table_has_column($conn, 'audit_logs', 'created_at')) {
    $sql = "SELECT a.id, a.admin_id, COALESCE(u.username,'Unknown') AS admin_name, a.action_type, a.related_id, a.ip_address, a.description, a.created_at
            FROM audit_logs a
            LEFT JOIN users u ON a.admin_id = u.id
            WHERE {$where}
            ORDER BY a.created_at DESC
            LIMIT 1000";

    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $logs_res = $stmt->get_result();
} else {
    // create an empty result set (SELECT literals LIMIT 0 returns a mysqli_result)
    $logs_res = $conn->query("SELECT NULL AS id, NULL AS admin_id, '' AS admin_name, '' AS action_type, NULL AS related_id, '' AS ip_address, '' AS description, NULL AS created_at LIMIT 0");
}

// Load action types and admin list for filters
// Load action types for filters if audit_logs exists
$actionTypes = [];
if (table_exists($conn, 'audit_logs') && table_has_column($conn, 'audit_logs', 'action_type')) {
    $qat = $conn->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type");
    while ($a = $qat->fetch_assoc()) $actionTypes[] = $a['action_type'];
}

$admins = [];
$qadm = $conn->query("SELECT id, username FROM users WHERE role = 'admin' ORDER BY username");
while ($ad = $qadm->fetch_assoc()) $admins[] = $ad;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Dashboard</title>
    <style>
        /* Minimal modern layout inspired by bazaar-homepage.css */
        :root{--bg:#f4f6fb;--card:#fff;--muted:#6b7280;--accent:#6c5ce7}
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,Segoe UI,Arial,sans-serif;background:var(--bg);color:#111}
        .wrap{max-width:1200px;margin:24px auto;padding:16px}
        header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
        h1{margin:0;font-size:20px}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px}
        .card{background:var(--card);padding:16px;border-radius:10px;box-shadow:0 6px 18px rgba(12,24,64,0.06)}
        .metric{font-size:28px;font-weight:600}
        .muted{color:var(--muted);font-size:13px}
        .small{font-size:13px}
        .filters{display:flex;gap:8px;flex-wrap:wrap}
        .filters input,.filters select{padding:8px;border:1px solid #e6e9ef;border-radius:6px}
        .btn{background:var(--accent);color:#fff;padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
        .btn.secondary{background:#fff;color:#111;border:1px solid #e6e9ef}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #f0f3f7;text-align:left}
        th{background:#fbfdff;font-weight:600}
        .table-wrap{overflow:auto}
        @media(max-width:640px){.filters{flex-direction:column}}
    </style>
</head>
<body>
    <div class="wrap">
        <header>
            <h1>Admin Dashboard Home</h1>
            <div class="small muted">Welcome, <?= esc($_SESSION['username'] ?? 'Admin') ?></div>
        </header>

        <section class="grid" style="margin-bottom:16px">
            <div class="card">
                <div class="muted">Pending Verifications</div>
                <div class="metric"><?= esc($metrics['pending_verifications']) ?></div>
            </div>
            <div class="card">
                <div class="muted">Open Disputes (avg hrs to resolve)</div>
                <div class="metric"><?= esc($metrics['open_disputes']) ?> <span class="small muted">(avg <?= esc($metrics['avg_resolve_hours']) ?> hrs)</span></div>
            </div>
            <div class="card">
                <div class="muted">Flagged Content</div>
                <div class="metric"><?= esc($metrics['flagged_content']) ?></div>
            </div>
            <div class="card">
                <div class="muted">Today's Admin Actions</div>
                <div class="metric"><?= esc($metrics['today_admin_actions']) ?></div>
            </div>
        </section>

        <section class="card" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <strong>Quick stats — last 7 days</strong>
                </div>
                <div class="small muted">Admin actions per day</div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <?php foreach ($last7 as $day => $cnt): ?>
                    <div style="flex:1 1 120px;padding:8px;background:#fbfdff;border-radius:8px"> 
                        <div class="muted" style="font-size:12px"><?= esc($day) ?></div>
                        <div style="font-weight:600"><?= esc($cnt) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <strong>Audit Logs (Improved)</strong>
                <div style="display:flex;gap:8px">
                    <form id="filterForm" method="get" style="display:flex;gap:8px" onsubmit="return true">
                        <div class="filters">
                            <input type="date" name="start_date" value="<?= esc($start_date) ?>">
                            <input type="date" name="end_date" value="<?= esc($end_date) ?>">
                            <select name="action_type">
                                <option value="">All actions</option>
                                <?php foreach ($actionTypes as $at): ?>
                                    <option value="<?= esc($at) ?>" <?= $action_type===$at?'selected':'' ?>><?= esc($at) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="admin_id">
                                <option value="">All admins</option>
                                <?php foreach ($admins as $ad): ?>
                                    <option value="<?= esc($ad['id']) ?>" <?= $admin_id===$ad['id']?'selected':'' ?>><?= esc($ad['username']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn">Filter</button>
                        </div>
                    </form>
                    <a class="btn secondary" href="?<?= http_build_query(array_merge($_GET, ['export'=>'1'])) ?>">Export CSV</a>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>User / ID</th>
                            <th>IP</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $logs_res->fetch_assoc()): ?>
                            <tr>
                                <td><?= esc($log['created_at']) ?></td>
                                <td><?= esc($log['admin_name']) ?> (<?= esc($log['admin_id']) ?>)</td>
                                <td><?= esc($log['action_type']) ?></td>
                                <td><?= esc($log['related_id']) ?></td>
                                <td><?= esc($log['ip_address']) ?></td>
                                <td><?= esc($log['description']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <footer style="text-align:center;color:var(--muted);font-size:13px;margin-top:12px">Logs powered by audit_logs · quick analytics from related tables</footer>
    </div>

    <script>
        // Simple progressive enhancement: preserve filters when exporting
        document.getElementById('filterForm').addEventListener('submit', function(e){
            // Allow normal GET submit; you could convert to AJAX here
        });
    </script>
</body>
</html>
