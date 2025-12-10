<?php
session_start();

// === SECURITY: Only logged-in manufacturers ===
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'manufacturer') {
    header("Location: ../../login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$author_name = strtoupper(htmlspecialchars($_SESSION['company_name'] ?? $_SESSION['username'], ENT_QUOTES));

// === DATABASE CONNECTION ===
$conn = new mysqli("localhost", "root", "", "bazaar_e_hind");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// === FETCH POSTS ===
$stmt = $conn->prepare("
    SELECT 
        p.post_id, p.title, p.content, p.type, p.created_at,
        u.company_name AS author,
        COALESCE(rc.reply_count, 0) AS reply_count
    FROM forum_posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN (
        SELECT parent_post_id, COUNT(*) AS reply_count
        FROM forum_posts
        WHERE parent_post_id IS NOT NULL 
          AND status = 'active'
        GROUP BY parent_post_id
    ) rc ON p.post_id = rc.parent_post_id
    WHERE p.parent_post_id IS NULL 
      AND p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 50
");
$stmt->execute();
$result = $stmt->get_result();
$posts = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// === CREATE NEW POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type    = $_POST['type'] ?? 'Discussion';

    if ($title && $content && in_array($type, ['Discussion', 'Blog', 'Event'])) {
        $insert = $conn->prepare("
            INSERT INTO forum_posts (user_id, title, content, type, parent_post_id, status)
            VALUES (?, ?, ?, ?, NULL, 'active')
        ");
        $insert->bind_param("isss", $user_id, $title, $content, $type);
        $insert->execute();
        $insert->close();
        header("Location: community_manufacturer.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bazaar-e-Hind - Manufacturer Community Forum</title>
  <base href="../../src/Manufacturer/">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    :root {
      --bazaar-bg: #f5ebe3;
      --primary: #b88c2b;
      --border: #e0c68c;
      --text: #3e2723;
    }
    body { margin:0; font-family:'Georgia',serif; background:transparent; color:var(--text); min-height:100vh; display:flex; flex-direction:column; }
    #bg-video { position:fixed; top:0; left:0; width:100vw; height:100vh; object-fit:cover; z-index:-1; opacity:1; }
    header { display:flex; align-items:center; background:var(--bazaar-bg); border-bottom:2px solid var(--border); padding:18px 24px; gap:18px; }
    .back-link { color:var(--primary); text-decoration:none; font-size:1.2rem; font-weight:bold; }
    .header-title { font-size:1.6rem; font-weight:bold; }
    .forum-container { flex:1; padding:32px; display:flex; gap:32px; align-items:flex-start; }
    .posts-section { flex:2; background:#fff; border-radius:12px; border:1.5px solid var(--border); padding:18px; box-shadow:0 2px 8px rgba(180,140,60,0.05); }
    .posts-title { font-size:1.2rem; font-weight:bold; color:#6d4c1e; margin-bottom:12px; }
    .post-list { display:flex; flex-direction:column; gap:10px; }
    .post-item { background:#fffbe6; border:1.5px solid var(--border); border-radius:8px; padding:12px 14px; cursor:pointer; position:relative; transition:all 0.15s; }
    .post-item.active { background:#ffe7b3; border-color:var(--primary); box-shadow:0 4px 16px rgba(180,140,60,0.13); }
    .post-header { display:flex; justify-content:space-between; align-items:center; gap:10px; }
    .post-title { font-weight:bold; flex:1; }
    .post-type { background:#e0c68c; color:#6d4c1e; padding:3px 10px; border-radius:8px; font-size:0.95rem; }
    .post-author { color:#b88c2b; font-style:italic; font-size:0.95rem; }
    .post-snippet { margin:8px 0 0; color:#6d4c1e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .post-content-full { margin-top:10px; display:none; white-space:pre-line; }
    .post-item.active .post-content-full { display:block; }
    .post-item.active .post-snippet { display:none; }
    .post-replies { position:absolute; right:18px; bottom:10px; background:white; padding:2px 10px; border-radius:8px; font-size:0.95rem; color:#b88c2b; }

    /* Engagement */
    .engagement-bar { margin-top:12px; display:flex; gap:16px; align-items:center; font-size:0.95rem; }
    .like-btn { background:none; border:1.5px solid #b88c2b; color:#b88c2b; padding:4px 12px; border-radius:20px; cursor:pointer; }
    .like-btn.liked { background:#b88c2b; color:white; }
    .reply-btn { background:none; border:none; color:#6d4c1e; cursor:pointer; font-weight:500; }
    .reply-box { display:none; margin-top:12px; }
    .reply-text { width:100%; min-height:60px; padding:8px; border:1.5px solid var(--border); border-radius:8px; background:#fffbe6; }
    .submit-reply { background:var(--primary); color:white; border:none; padding:6px 16px; border-radius:20px; cursor:pointer; margin-top:6px; }

    .forum-form-section { flex:1.2; background:#fff; border-radius:12px; border:1.5px solid var(--border); padding:18px; box-shadow:0 2px 8px rgba(180,140,60,0.05); }
    .form-title { font-weight:bold; color:#6d4c1e; margin-bottom:10px; }
    .form-group label { display:block; margin-bottom:4px; }
    .form-group input, .form-group textarea, .form-group select { width:100%; padding:8px 10px; border:1.5px solid var(--border); border-radius:7px; background:#fffbe6; }
    .submit-btn { background:var(--primary); color:white; border:none; padding:10px 30px; border-radius:22px; font-weight:bold; cursor:pointer; margin-top:18px; display:flex; align-items:center; gap:6px; }
    footer { background:#fffbe6; padding:16px; text-align:center; border-top:2px solid var(--border); margin-top:auto; }
    .footer-links a { color:#6d4c1e; margin:0 16px; text-decoration:none; }
    @media (max-width:1050px) { .forum-container { flex-direction:column; } }
  </style>
</head>
<body>
  <video autoplay muted loop id="bg-video">
    <source src="../../assests/silk video.mp4" type="video/mp4">
  </video>

  <header>
    <a href="bazaar-homepage.php" class="back-link">← Home</a>
    <span class="header-title">Community Forum</span>
  </header>

  <main class="forum-container">
    <section class="posts-section">
      <div class="posts-title">Browse Posts</div>
      <div class="post-list">
        <?php if (empty($posts)): ?>
          <div style="text-align:center; padding:50px; color:#b88c2b; font-style:italic;">
            No posts yet. Be the first to start a conversation!
          </div>
        <?php else: ?>
          <?php foreach ($posts as $post):
            $snippet = strlen($post['content']) > 100 ? substr($post['content'], 0, 100).'...' : $post['content'];

            // Like count
            $like_stmt = $conn->prepare("SELECT COUNT(*) FROM forum_likes WHERE post_id = ?");
            $like_stmt->bind_param("i", $post['post_id']);
            $like_stmt->execute();
            $like_count = $like_stmt->get_result()->fetch_row()[0];
            $like_stmt->close();

            // Did current user like?
            $user_liked = false;
            if ($user_id) {
                $check = $conn->prepare("SELECT 1 FROM forum_likes WHERE post_id = ? AND user_id = ?");
                $check->bind_param("ii", $post['post_id'], $user_id);
                $check->execute();
                $user_liked = $check->get_result()->num_rows > 0;
                $check->close();
            }
          ?>
            <div class="post-item" onclick="togglePost(this)">
              <div class="post-header">
                <span class="post-title"><?= htmlspecialchars($post['title']) ?></span>
                <span class="post-type"><?= htmlspecialchars($post['type']) ?></span>
                <span class="post-author">by <?= htmlspecialchars($post['author']) ?></span>
              </div>
              <div class="post-snippet"><?= htmlspecialchars($snippet) ?></div>
              <div class="post-content-full"><?= nl2br(htmlspecialchars($post['content'])) ?></div>

              <div class="engagement-bar">
                <button class="like-btn <?= $user_liked ? 'liked' : '' ?>"
                        onclick="event.stopPropagation(); toggleLike(<?= $post['post_id'] ?>, this)">
                  Heart <?= $like_count ?> Like<?= $like_count != 1 ? 's' : '' ?>
                </button>
                <button class="reply-btn" onclick="event.stopPropagation(); showReplyBox(<?= $post['post_id'] ?>, this)">
                  Reply
                </button>
                <span class="post-replies"><?= (int)$post['reply_count'] ?> Replies</span>
              </div>

              <div class="reply-box" style="display:none; margin-top:12px;">
                <textarea placeholder="Write your reply..." class="reply-text"></textarea>
                <button class="submit-reply" onclick="event.stopPropagation(); submitReply(<?= $post['post_id'] ?>, this)">
                  Post Reply
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <form class="forum-form-section" method="POST">
      <div class="form-title">Create New Post</div>
      <div class="form-group">
        <label>Author Name</label>
        <input type="text" value="<?= $author_name ?>" readonly>
      </div>
      <div class="form-group">
        <label>Title</label>
        <input type="text" name="title" required maxlength="80" placeholder="Enter a catchy title">
      </div>
      <div class="form-group">
        <label>Content</label>
        <textarea name="content" required maxlength="2000" placeholder="Share your thoughts..."></textarea>
      </div>
      <div class="form-group">
        <label>Type</label>
        <select name="type" required>
          <option>Discussion</option>
          <option>Blog</option>
          <option>Event</option>
        </select>
      </div>
      <button class="submit-btn" type="submit">Submit</button>
    </form>
  </main>

  <footer>
    <div class="footer-links">
      <a href="#">FAQs</a>
      <a href="#">Terms & Conditions</a>
      <a href="#">Privacy Policy</a>
    </div>
  </footer>

  <script>
    function togglePost(div) {
      document.querySelectorAll('.post-item').forEach(item => {
        if (item !== div) item.classList.remove('active');
      });
      div.classList.toggle('active');
    }

    function toggleLike(postId, btn) {
      event.stopPropagation();
      const fd = new FormData();
      fd.append('action', 'toggle_like');
      fd.append('post_id', postId);
      fetch('forum_actions.php', {method:'POST', body:fd})
        .then(r => r.json())
        .then(d => {
          btn.classList.toggle('liked', d.liked);
          btn.innerHTML = `Heart ${d.count} Like${d.count != 1 ? 's' : ''}`;
        });
    }

    function showReplyBox(postId, btn) {
      event.stopPropagation();
      const box = btn.closest('.post-item').querySelector('.reply-box');
      box.style.display = box.style.display === 'block' ? 'none' : 'block';
      if (box.style.display === 'block') box.querySelector('textarea').focus();
    }

    function submitReply(postId, btn) {
      event.stopPropagation();
      const content = btn.closest('.reply-box').querySelector('.reply-text').value.trim();
      if (!content) return alert('Write something first!');
      const fd = new FormData();
      fd.append('action', 'reply');
      fd.append('post_id', postId);
      fd.append('content', content);
      fetch('forum_actions.php', {method:'POST', body:fd})
        .then(() => location.reload());
    }
  </script>
</body>
</html>
<?php $conn->close(); ?>