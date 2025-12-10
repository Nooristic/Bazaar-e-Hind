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

$action = $_POST['action'] ?? '';

if ($action === 'toggle_like') {
    $post_id = (int)($_POST['post_id'] ?? 0);
    if (!$post_id) exit(json_encode(['error' => 'Invalid post']));

    $check = $conn->prepare("SELECT 1 FROM forum_likes WHERE post_id = ? AND user_id = ?");
    $check->bind_param("ii", $post_id, $user_id);
    $check->execute();
    $exists = $check->get_result()->num_rows > 0;
    $check->close();

    if ($exists) {
        $conn->query("DELETE FROM forum_likes WHERE post_id = $post_id AND user_id = $user_id");
        $liked = false;
    } else {
        $conn->query("INSERT INTO forum_likes (post_id, user_id) VALUES ($post_id, $user_id)");
        $liked = true;
    }

    $count = $conn->query("SELECT COUNT(*) FROM forum_likes WHERE post_id = $post_id")->fetch_row()[0];
   .echo json_encode(['liked' => $liked, 'count' => $count]);
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
?>