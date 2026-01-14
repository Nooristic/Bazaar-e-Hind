<?php
session_start();

// Security — only logged-in wholesalers (assuming this page is for wholesalers messaging manufacturers)
if (!isset($_SESSION['logged_in']) || $_SESSION['role'] !== 'wholesaler') {
    header("Location: ../login.html");
    exit();
}

// Database connection
$servername = "localhost";
$username_db = "root"; // Adjust as needed
$password_db = ""; // Adjust as needed
$dbname = "bazaar_e_hind"; // Adjust as needed

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get current user ID from session (assuming stored as 'user_id')
$current_user_id = $_SESSION['user_id'];

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message']) && isset($_POST['receiver_id'])) {
    $message = $conn->real_escape_string($_POST['message']);
    $receiver_id = intval($_POST['receiver_id']);
    if (!empty($message)) {
        $sql = "INSERT INTO messages (sender_id, receiver_id, message, timestamp) VALUES ($current_user_id, $receiver_id, '$message', NOW())";
        $conn->query($sql);
    }
    // Redirect to same page with chat selected to refresh
    header("Location: ?chat=$receiver_id");
    exit();
}

// Get selected chat from GET
$selected_receiver_id = isset($_GET['chat']) ? intval($_GET['chat']) : 0;

// Fetch list of manufacturers for sidebar with unread counts
$sidebar_manufacturers = [];
$sql_sidebar = "
    SELECT u.user_id, u.company_name, COUNT(m.message_id) AS unread
    FROM users u
    LEFT JOIN messages m ON m.sender_id = u.user_id AND m.receiver_id = $current_user_id AND m.is_read = 0
    WHERE u.role = 'manufacturer'
    GROUP BY u.user_id
    ORDER BY unread DESC
";
$result_sidebar = $conn->query($sql_sidebar);
if ($result_sidebar) {
    while ($row = $result_sidebar->fetch_assoc()) {
        $sidebar_manufacturers[] = $row;
    }
}

// Fetch chat history if selected
$chat_history = [];
if ($selected_receiver_id > 0) {
    $sql_chat = "
        SELECT m.message, m.sender_id, m.timestamp
        FROM messages m
        WHERE (m.sender_id = $current_user_id AND m.receiver_id = $selected_receiver_id)
           OR (m.sender_id = $selected_receiver_id AND m.receiver_id = $current_user_id)
        ORDER BY m.timestamp ASC
    ";
    $result_chat = $conn->query($sql_chat);
    if ($result_chat) {
        while ($row = $result_chat->fetch_assoc()) {
            $chat_history[] = $row;
        }
    }

    // Mark messages as read
    $sql_mark_read = "UPDATE messages SET is_read = 1 WHERE sender_id = $selected_receiver_id AND receiver_id = $current_user_id AND is_read = 0";
    $conn->query($sql_mark_read);
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Messages / Voice Call</title>
<style>
/* ===== FONT & BASE ===== */
body {
    font-family: 'Georgia', serif;
    margin: 0;
    padding: 0;
    background: transparent;
    color: #3e2723;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

/* ===== BACKGROUND VIDEO ===== */
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

/* ===== HEADER ===== */
.blue-header {
    background-color: rgba(73, 40, 22, 0.389);
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

/* ===== CONTAINER ===== */
.container {
    display: flex;
    flex: 1;
    overflow: hidden;
}

/* ===== SIDEBAR ===== */
.sidebar {
    width: 25%;
    background-color: rgba(255, 255, 255, 0.92);
    border-right: 1px solid #ddd;
    padding: 1rem;
    overflow-y: auto;
}

.sidebar h2 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
}

/* ===== MANUFACTURERS ===== */
.manufacturer {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem;
    border-bottom: 1px solid #ddd;
    cursor: pointer;
}

.manufacturer:hover {
    background-color: #f1f1f1;
}

.manufacturer .unread {
    background-color: #dc3545;
    color: white;
    padding: 0.3rem 0.6rem;
    border-radius: 50%;
    font-size: 0.8rem;
}

/* ===== CHAT WINDOW ===== */
.chat-window {
    flex: 1;
    display: flex;
    flex-direction: column;
    background-color: rgba(255, 255, 255, 0.92);
    padding: 1rem;
}

.chat-history {
    flex: 1;
    overflow-y: auto;
    border-bottom: 1px solid #ddd;
    padding-bottom: 1rem;
}

.chat-message {
    margin-bottom: 1rem;
}

.chat-message.sent {
    text-align: right;
}

.chat-message p {
    display: inline-block;
    padding: 0.5rem 1rem;
    background-color: #007bff;
    color: white;
    border-radius: 8px;
}

.chat-message.received p {
    background-color: #f1f1f1;
    color: #3e2723;
}

/* ===== MESSAGE INPUT ===== */
.message-input {
    display: flex;
    align-items: center;
    margin-top: 1rem;
}

.message-input input {
    flex: 1;
    padding: 0.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    color: #3e2723;
}

.message-input button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    background-color: #e0c68c;
    color: white;
    margin-left: 0.5rem;
    cursor: pointer;
    font-family: inherit;
}

/* ===== VOICE CALL ===== */
.voice-call {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-top: 1rem;
}

.voice-call button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    background-color: #28a745;
    color: white;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-family: inherit;
}

.voice-call button i {
    margin-right: 0.5rem;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .container {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid #ddd;
    }

    .chat-window {
        flex: 1;
    }
}
</style>
</head>
<body>

<!-- ✅ BACKGROUND VIDEO -->
<video autoplay muted loop playsinline id="bg-video">
  <source src="../../assests/silk video.mp4" type="video/mp4">
  Your browser does not support the video tag.
</video>

<header class="blue-header">
    <a href="../login.html" class="back-link">← Home</a>
    <h1>Messages / Voice Call</h1>
</header>

<div class="container">
    <div class="sidebar">
        <h2>Manufacturers</h2>
        <?php foreach ($sidebar_manufacturers as $man): ?>
        <div class="manufacturer" onclick="window.location.href='?chat=<?php echo $man['user_id']; ?>'">
            <span><?php echo htmlspecialchars($man['company_name']); ?></span>
            <?php if ($man['unread'] > 0): ?>
            <span class="unread"><?php echo $man['unread']; ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="chat-window">
        <div class="chat-history">
            <?php foreach ($chat_history as $msg): ?>
            <div class="chat-message <?php echo ($msg['sender_id'] == $current_user_id) ? 'sent' : 'received'; ?>">
                <p><?php echo htmlspecialchars($msg['message']); ?></p>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($selected_receiver_id > 0): ?>
        <form class="message-input" method="POST">
            <input type="hidden" name="receiver_id" value="<?php echo $selected_receiver_id; ?>">
            <input type="text" name="message" placeholder="Type your message...">
            <button type="submit">Send</button>
        </form>
        <?php endif; ?>

        <div class="voice-call">
            <button>
                <i class="icon">📞</i>
                Start Voice Call
            </button>
        </div>
    </div>
</div>

<footer style="background:rgba(255,255,255,0.92);text-align:center;padding:1rem;">
    &copy; 2025 Fabric Bazaar. All rights reserved.
</footer>

</body>
</html>