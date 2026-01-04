<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

// set proper charset
$conn->set_charset('utf8mb4');

$action = $_POST['action'] ?? '';

if ($action === 'toggle_like') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!$post_id) {
        echo json_encode(['error' => 'Invalid post']);
        exit();
    }

    // determine if user already liked
    $check = $conn->prepare("SELECT 1 FROM forum_likes WHERE post_id = ? AND user_id = ? LIMIT 1");
    if (!$check) {
        echo json_encode(['error' => 'DB error']);
        exit();
    }
    $check->bind_param("ii", $post_id, $user_id);
    $check->execute();
    $check->store_result();
    $exists = $check->num_rows > 0;
    $check->close();

    if ($exists) {
        $del = $conn->prepare("DELETE FROM forum_likes WHERE post_id = ? AND user_id = ?");
        if ($del) {
            $del->bind_param("ii", $post_id, $user_id);
            $del->execute();
            $del->close();
        }
        $liked = false;
    } else {
        $ins = $conn->prepare("INSERT INTO forum_likes (post_id, user_id) VALUES (?, ?)");
        if ($ins) {
            $ins->bind_param("ii", $post_id, $user_id);
            $ins->execute();
            $ins->close();
        }
        $liked = true;
    }

    // updated count
    $count = 0;
    $cstmt = $conn->prepare("SELECT COUNT(*) FROM forum_likes WHERE post_id = ?");
    if ($cstmt) {
        $cstmt->bind_param("i", $post_id);
        $cstmt->execute();
        $cstmt->bind_result($count);
        $cstmt->fetch();
        $cstmt->close();
    }

    echo json_encode(['liked' => $liked, 'count' => (int)$count]);
    exit();
}

if ($action === 'reply') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    $content = trim($_POST['content'] ?? '');

    if (!$post_id || !$content) {
        echo json_encode(['error' => 'Invalid data']);
        exit();
    }

    $stmt = $conn->prepare("
        INSERT INTO forum_posts 
            (user_id, title, content, type, parent_post_id, status, created_at)
        VALUES 
            (?, 'Reply', ?, 'Discussion', ?, 'active', NOW())
    ");
    if (!$stmt) {
        echo json_encode(['error' => 'DB error']);
        exit();
    }
    $stmt->bind_param("isi", $user_id, $content, $post_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Insert failed']);
    }
    $stmt->close();
    exit();
}

echo json_encode(['error' => 'Invalid action']);
$conn->close();
?>