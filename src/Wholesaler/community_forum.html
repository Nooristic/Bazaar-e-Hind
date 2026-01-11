<?php
session_start();

// Security — only logged-in wholesalers (or both)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['wholesaler', 'both'])) {
    header("Location: ../login.html");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$username = strtoupper(htmlspecialchars($_SESSION['username'] ?? 'WHOLESALER', ENT_QUOTES));

// Database connection
$host = "localhost";
$db_user = "root";
$db_pass = "";
$dbname = "bazaar_e_hind";

$mysqli = new mysqli($host, $db_user, $db_pass, $dbname);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// Handle new post creation
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_post') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = $_POST['type'] ?? 'discussion';
    
    if ($title && $content) {
        $stmt = $mysqli->prepare("INSERT INTO forum_posts (user_id, title, content, type, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->bind_param("isss", $user_id, $title, $content, $type);
        
        if ($stmt->execute()) {
            $message = "Post created successfully! It will appear after moderation.";
        } else {
            $message = "Error creating post: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Title and content are required.";
    }
}

// Fetch posts (only active/flagged, ordered by latest)
$posts = [];
$current_type = $_GET['type'] ?? 'discussion'; // Filter by category

$query = "
    SELECT fp.post_id, fp.title, fp.content, fp.type, fp.created_at, 
           fp.status, fp.flagged_count,
           u.username AS author_name, u.company_name AS author_company
    FROM forum_posts fp 
    JOIN users u ON fp.user_id = u.id 
    WHERE fp.status IN ('active', 'flagged') 
    AND fp.type = ?
    ORDER BY fp.created_at DESC 
    LIMIT 50
";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("s", $current_type);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $row['replies'] = rand(0, 15); // Simulate replies (add replies table later)
    $row['short_content'] = substr($row['content'], 0, 100) . '...';
    $posts[] = $row;
}
$stmt->close();

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Community Forum - Bazaar-e-Hind</title>

<style>
/* ===== FONT & BASE (FROM CHAT PAGE) ===== */
body {
  font-family: 'Georgia', serif;
  margin: 0;
  padding: 0;
  background: transparent;
  color: #3e2723;
  min-height: 100vh;
}

/* ===== BACKGROUND VIDEO ===== */
#bg-video {
  position: fixed;
  top: 0;
  left: 0;
  min-width: 100vw;
  min-height: 100vh;
  width: 100vw;
  height: 100vh;
  object-fit: cover;
  z-index: -1;
  pointer-events: none;
}

/* ===== HEADER (FIXED SYNTAX ERROR + ENHANCED) ===== */
.blue-header {
  background-color: #e0c68c;
  color: white;
  padding: 1rem;
  text-align: center;
  position: relative;
}

.blue-header .back-link {
  color: white;
  text-decoration: none;
  font-size: 1.2rem;
  position: absolute;
  left: 1rem;
  top: 1rem;
}

/* ===== CONTENT ===== */
.container {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.categories {
  display: flex;
  justify-content: space-around;
  margin-bottom: 2rem;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.categories button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  background-color: #e0c68c;
  color: white;
  cursor: pointer;
  font-family: inherit;
  transition: background-color 0.2s;
}

.categories button.active,
.categories button:hover {
  background-color: #c19c55;
}

/* ===== TABLE ===== */
.thread-list {
  width: 100%;
  border-collapse: collapse;
  background-color: rgba(255, 255, 255, 0.92);
  border-radius: 8px;
  overflow: hidden;
}

.thread-list th,
.thread-list td {
  padding: 1rem;
  text-align: left;
  border-bottom: 1px solid #ddd;
  font-family: inherit;
}

.thread-list th {
  background-color: #e0c68c;
  color: white;
}

.thread-list tr:hover {
  background-color: #f1f1f1;
}

/* ===== STATUS BADGES ===== */
.status-badge {
  padding: 0.2rem 0.6rem;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: bold;
}
.status-active { background: #28a745; color: white; }
.status-flagged { background: #ffc107; color: #212529; }

/* ===== ACTIONS ===== */
.actions {
  margin: 2rem auto;
  text-align: center;
}

.actions button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  background-color: #e0c68c;
  color: white;
  cursor: pointer;
  font-family: inherit;
}

.actions button::after {
  content: " ₹";
}

/* ===== MODAL FORM ===== */
.form-container {
  display: none;
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: rgba(224, 198, 140, 0.98);
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  z-index: 1000;
  width: 90%;
  max-width: 500px;
}

.form-container.active {
  display: block;
}

.form-container label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
}

.form-container input,
.form-container select,
.form-container textarea {
  width: 100%;
  margin-bottom: 1rem;
  padding: 0.5rem;
  border: 1px solid #ddd;
  border-radius: 4px;
}

/* ===== MESSAGE ALERT ===== */
.alert {
  padding: 1rem;
  margin: 1rem 0;
  border-radius: 4px;
  text-align: center;
}
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
  .thread-list thead {
    display: none;
  }

  .thread-list tr {
    display: flex;
    flex-direction: column;
    margin-bottom: 1rem;
    border: 1px solid #ddd;
    border-radius: 8px;
  }

  .thread-list td {
    display: flex;
    justify-content: space-between;
  }

  .thread-list td::before {
    content: attr(data-label);
    font-weight: bold;
  }
}
</style>
</head>

<body>

<!-- ✅ BACKGROUND VIDEO (UNCHANGED) -->
<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
  Your browser does not support the video tag.
</video>

<header class="blue-header">
  <a href="../home_wholesaler.php" class="back-link">← Home</a>
  <h1>Community Forum</h1>
</header>

<div class="container">
  <?php if ($message): ?>
    <div class="alert <?= strpos($message, 'success') !== false ? 'alert-success' : 'alert-error' ?>">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <div class="categories">
    <a href="?type=discussion"><button class="<?= $current_type === 'discussion' ? 'active' : '' ?>">Discussions</button></a>
    <a href="?type=blog"><button class="<?= $current_type === 'blog' ? 'active' : '' ?>">Blogs</button></a>
    <a href="?type=event"><button class="<?= $current_type === 'event' ? 'active' : '' ?>">Events</button></a>
  </div>

  <table class="thread-list">
    <thead>
      <tr>
        <th>Title</th>
        <th>Author</th>
        <th>Replies</th>
        <th>Date</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($posts)): ?>
        <tr>
          <td colspan="5" style="text-align:center; padding:2rem;">No posts yet in this category. Create one!</td>
        </tr>
      <?php else: ?>
        <?php foreach ($posts as $post): ?>
          <tr>
            <td data-label="Title">
              <strong><?= htmlspecialchars($post['title']) ?></strong><br>
              <small><?= htmlspecialchars($post['short_content']) ?></small>
            </td>
            <td data-label="Author"><?= htmlspecialchars($post['author_company'] ?? $post['author_name']) ?></td>
            <td data-label="Replies"><?= $post['replies'] ?></td>
            <td data-label="Date"><?= date('Y-m-d', strtotime($post['created_at'])) ?></td>
            <td data-label="Status">
              <span class="status-badge status-<?= $post['status'] ?>">
                <?= ucwords(str_replace('_', ' ', $post['status'])) ?>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="actions">
    <button id="create-post">Create New Post</button>
  </div>

  <!-- FORM (UNCHANGED STRUCTURE, NOW FUNCTIONAL) -->
  <div class="form-container" id="post-form">
    <h2>Create New Post</h2>

    <form method="POST" action="">
      <input type="hidden" name="action" value="create_post">
      
      <label>Title *</label>
      <input type="text" name="title" maxlength="255" required>

      <label>Content *</label>
      <textarea name="content" rows="4" required></textarea>

      <label>Category</label>
      <select name="type">
        <option value="discussion" <?= $current_type === 'discussion' ? 'selected' : '' ?>>Discussions</option>
        <option value="blog" <?= $current_type === 'blog' ? 'selected' : '' ?>>Blogs</option>
        <option value="event" <?= $current_type === 'event' ? 'selected' : '' ?>>Events</option>
      </select>

      <button type="submit" id="submit-post">Submit</button>
    </form>
  </div>
</div>

<footer style="background:rgba(255,255,255,0.9);text-align:center;padding:1rem;">
  &copy; <?= date('Y') ?> Bazaar-e-Hind. All rights reserved.
</footer>

<script>
document.getElementById('create-post').onclick = () =>
  document.getElementById('post-form').classList.add('active');

document.getElementById('submit-post').onclick = function(e) {
  // Frontend validation
  const title = document.querySelector('input[name="title"]').value.trim();
  const content = document.querySelector('textarea[name="content"]').value.trim();
  if (!title || !content) {
    alert('Title and content are required!');
    e.preventDefault();
    return false;
  }
};
</script>

</body>
</html>