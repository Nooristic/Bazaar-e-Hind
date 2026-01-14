<?php
session_start();

// Security: Only logged-in users (manufacturer or wholesaler)
if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['role'], ['manufacturer', 'wholesaler', 'both'])) {
    header("Location: ../login.html");
    exit();
}

$servername = "localhost";
$username_db = "root";          // ← Change if needed
$password_db = "";              // ← Change if needed
$dbname = "bazaar_e_hind";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$current_user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'];

// Fetch current user data
$sql = "
    SELECT 
        company_name,
        gst_details,
        contact_no,
        logo_url
    FROM users 
    WHERE user_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

// Parse GST JSON if exists
$gst_details = json_decode($user['gst_details'] ?? '{}', true);
$gst_number = $gst_details['gst_no'] ?? '';
$gst_verified = ($gst_details['verified'] ?? false) ? 'Yes (Verified)' : 'Not Verified';

// Simple addresses - assuming stored as JSON array in future
// For now we show placeholder / contact number as fallback
$addresses_placeholder = $contact_no = $user['contact_no'] ?? 'Not set';

// Handle form submission - Save Changes
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
    $new_company_name = trim($_POST['company_name'] ?? '');
    $new_gst = trim($_POST['gst'] ?? '');
    $new_password = trim($_POST['password'] ?? '');

    $update_fields = [];
    $update_types = "";
    $update_params = [];

    if ($new_company_name !== '') {
        $update_fields[] = "company_name = ?";
        $update_params[] = $new_company_name;
        $update_types .= "s";
    }

    // Update GST (simple version - you should add proper validation & document upload in production)
    if ($new_gst !== '') {
        $gst_json = json_encode([
            'gst_no' => $new_gst,
            'verified' => false, // reset verification status on change
            'document_url' => $gst_details['document_url'] ?? null
        ]);
        $update_fields[] = "gst_details = ?";
        $update_params[] = $gst_json;
        $update_types .= "s";
    }

    // Password change (very basic - in production use proper hashing & confirmation field!)
    if ($new_password !== '') {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_fields[] = "password_hash = ?";
        $update_params[] = $hashed_password;
        $update_types .= "s";
    }

    if (!empty($update_fields)) {
        $sql_update = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
        $update_params[] = $current_user_id;
        $update_types .= "i";

        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param($update_types, ...$update_params);

        if ($stmt_update->execute()) {
            $success_message = "Changes saved successfully!";
            // Refresh user data
            header("Location: " . $_SERVER['PHP_SELF'] . "?updated=1");
            exit();
        } else {
            $error_message = "Failed to save changes: " . $conn->error;
        }
        $stmt_update->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <style>
        /* General Styles */
        body {
            font-family: 'Georgia', serif;
            margin: 0;
            padding: 0;
            background: none; /* Remove background image to avoid overlap */
            color: #5a3e2b;
            position: relative; /* Ensure content layers properly */
            z-index: 1; /* Place content above the video */
        }

        .blue-header {
            background-color: #bf170840;
            color: rgb(66, 22, 5);
            padding: 1rem;
            text-align: center;
            font-family: 'Georgia', serif;
        }
        #bg-video {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            object-fit: cover;
            z-index: -1; /* Ensure video stays in the background */
        }

        .blue-header .back-link {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            position: absolute;
            left: 1rem;
            top: 1rem;
        }

        .tabs {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }

        .tabs button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            background-color:#e59f9f4d;
            color: white;
            cursor: pointer;
            font-family: 'Georgia', serif;
            margin: 0 0.5rem;
        }

        .tabs button.active {
            background-color: #e59f9f4d;
        }

        .tab-content {
            display: none;
            background-color: beige; /* Set the Manage Account section background color to beige */
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .tab-content.active {
            display: block;
        }

        .form-container {
            background-color: rgba(255, 245, 235, 0.92); /* Set the form container background color to beige */
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .form-container label {
            display: block;
            margin-bottom: 0.5rem;
        }

        .form-container input, .form-container textarea {
            width: 100%;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border: 1px solid rgba(255, 245, 235, 0.92);
            border-radius: 4px;
        }

        .form-container button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            background-color: rgba(255, 245, 235, 0.92); /* Set the Save Changes button background color to beige */
            color: #5a3e2b; /* Adjust text color for contrast */
            cursor: pointer;
        }

        .form-container button::after {
            content: ' ₹';
        }

        .faq-links {
            margin-top: 2rem;
        }

        .faq-links a {
            display: block;
            margin-bottom: 0.5rem;
            color: #490f0f32;
            text-decoration: none;
        }

        .faq-links a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 6px;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
                align-items: center;
            }

            .tabs button {
                margin-bottom: 0.5rem;
            }
        }

        .blue-header, .tabs, .tab-content, footer {
            position: relative; /* Ensure these elements are above the video */
            z-index: 1;
        }

        .settings-box {
            background-color: rgba(255, 245, 235, 0.92); /* Create a box for the settings page */
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: 2rem auto;
            width: 90%;
        }
    </style>
</head>
<body>
    <video autoplay muted loop playsinline id="bg-video">
        <source src="../../assests/silk video.mp4" type="video/mp4">
    </video>

    <header class="blue-header">
        <a href="../login.html" class="back-link">← Home</a>
        <h1>Settings</h1>
    </header>

    <div class="tabs">
        <button class="active" data-tab="manage-account">Manage Account</button>
        <button data-tab="help-center">Help Center</button>
    </div>

    <div class="tab-content active" id="manage-account">
        <h2>Manage Account</h2>

        <?php if (isset($_GET['updated'])): ?>
            <div class="message success">
                Changes saved successfully!
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="save_changes" value="1">

                <label for="company-name">Company Name</label>
                <input type="text" id="company-name" name="company_name" 
                       value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">

                <label for="gst">GST Number</label>
                <input type="text" id="gst" name="gst" 
                       value="<?php echo htmlspecialchars($gst_number); ?>" 
                       placeholder="<?php echo htmlspecialchars($gst_verified); ?>">

                <label for="addresses">Contact / Addresses</label>
                <textarea id="addresses" name="addresses" rows="4"><?php 
                    echo htmlspecialchars($addresses_placeholder); 
                ?></textarea>

                <label for="logo">Company Logo</label>
                <input type="file" id="logo" name="logo" accept="image/*">
                <?php if (!empty($user['logo_url'])): ?>
                    <small>Current logo: <a href="<?php echo htmlspecialchars($user['logo_url']); ?>" target="_blank">View</a></small>
                <?php endif; ?>

                <label for="password">New Password (leave blank to keep current)</label>
                <input type="password" id="password" name="password">

                <button type="submit">Save Changes</button>
            </form>
        </div>
    </div>

    <div class="tab-content" id="help-center">
        <h2>Help Center</h2>
        <div class="form-container">
            <label for="search">Search FAQs</label>
            <input type="text" id="search" placeholder="Type your query...">
        </div>

        <div class="faq-links">
            <a href="#">Full FAQs</a>
            <a href="#">Terms & Conditions</a>
            <a href="#">Privacy Policy</a>
        </div>
    </div>

    <footer>
        <p>© 2025 Fabric Bazaar. All rights reserved.</p>
    </footer>

    <script>
        const tabs = document.querySelectorAll('.tabs button');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                tabContents.forEach(content => {
                    content.classList.remove('active');
                });

                document.getElementById(tab.dataset.tab).classList.add('active');
            });
        });
    </script>
</body>
</html>