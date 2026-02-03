<?php
/**
 * Kuih Raya - User Profile / Change Password
 * Location: pages/users/user_profile.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "All fields are required.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Verify current
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $hash = $stmt->fetchColumn();

        if (password_verify($current, $hash)) {
            // Update
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            // Also clear is_new flag
            $stmt = $pdo->prepare("UPDATE users SET password = ?, is_new = 0 WHERE id = ?");
            if ($stmt->execute([$new_hash, $user_id])) {
                $success = "Password changed successfully!";
                $_SESSION['is_new'] = 0; // Update session
            } else {
                $error = "Database error.";
            }
        } else {
            $error = "Incorrect current password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Kuih Raya</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .profile-info {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        .profile-info h2 { margin: 10px 0 5px; }
        .profile-info .badge { 
            padding: 5px 12px; border-radius: 15px; font-size: 0.9em; 
            background: #eee; color: #555; display: inline-block;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; 
        }
        .btn-primary {
            background: #007bff; color: white; border: none; padding: 10px 20px;
            width: 100%; border-radius: 4px; cursor: pointer; font-size: 16px;
        }
        .btn-primary:hover { background: #0056b3; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1>My Profile</h1>
            </div>
        </div>

        <div class="profile-card">
            <div class="profile-info">
                <img src="/images/icons/user.png" alt="User" style="width:80px;opacity:0.5;">
                <h2><?= htmlspecialchars($_SESSION['username']) ?></h2>
                <span class="badge"><?= ucfirst($_SESSION['role']) ?></span>
                
                <?php if (!empty($_SESSION['is_new'])): ?>
                    <p style="color:orange;margin-top:10px;">
                        <i class='bx bxs-error-circle'></i> Please change your password to continue.
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div style="background:#f8d7da;color:#721c24;padding:10px;margin-bottom:15px;border-radius:4px;"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;border-radius:4px;"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <h3>Change Password</h3>
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-primary">Update Password</button>
            </form>
        </div>
    </main>
    <?php include '../../footer.php'; ?>
</body>
</html>
