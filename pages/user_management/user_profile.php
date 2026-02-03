<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Check if editing another user (admin/coordinator privilege)
$is_admin_or_coordinator = in_array($_SESSION['role'], ['admin', 'coordinator']);
$editing_other_user = false;

// Determine which user we're viewing/editing
if (isset($_GET['id']) && $is_admin_or_coordinator) {
    $target_user_id = $_GET['id'];
    $editing_other_user = ($target_user_id != $_SESSION['user_id']);
} else {
    $target_user_id = $_SESSION['user_id'];
}

// Check if in edit mode
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 1;

// Get user data
$stmt = $pdo->prepare("SELECT id, full_name, student_id, email, profile_pic, role FROM users WHERE id = ?");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch();

if (!$user) {
    header("Location: /index.php");
    exit();
}

// Set default empty value if null
$user['profile_pic'] = $user['profile_pic'] ?? '';

// Handle form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle profile update
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $student_id = trim($_POST['student_id']);
        $email = trim($_POST['email']);
        $role = $editing_other_user ? trim($_POST['role']) : $user['role'];
        
        // Validate inputs
        if (empty($full_name) || empty($student_id) || empty($email)) {
            $error = 'All fields are required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email format';
        } else {
            // Handle profile picture upload
            $profile_pic = $user['profile_pic'];
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_pic'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_ext = ['jpg', 'jpeg', 'png'];
                
                if (in_array($file_ext, $allowed_ext)) {
                    $upload_dir = '../../images/profile_pic/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $new_filename = 'user_' . $target_user_id . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        // Delete old profile picture if exists
                        if (!empty($profile_pic) && file_exists($upload_dir . $profile_pic)) {
                            unlink($upload_dir . $profile_pic);
                        }
                        $profile_pic = $new_filename;
                    }
                } else {
                    $error = 'Only JPG, JPEG, and PNG files are allowed';
                }
            }
            
            if (empty($error)) {
                // Update user in database
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, student_id = ?, email = ?, profile_pic = ?, role = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $student_id, $email, $profile_pic, $role, $target_user_id])) {
                    // Update session data if editing own profile
                    if (!$editing_other_user) {
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['profile_pic'] = $profile_pic;
                    }
                    $success = 'Profile updated successfully';
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    $user = $stmt->fetch();
                } else {
                    $error = 'Failed to update profile';
                }
            }
        }
    }
    
    // Handle password change (only for own account)
    if (isset($_POST['change_password']) && !$editing_other_user) {
        $current_password = trim($_POST['current_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$target_user_id]);
            $db_password = $stmt->fetchColumn();
            
            if (!password_verify($current_password, $db_password)) {
                $error = 'Current password is incorrect';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt->execute([$hashed_password, $target_user_id])) {
                    $success = 'Password changed successfully';
                } else {
                    $error = 'Failed to change password';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editing_other_user ? 'Edit User' : 'User Profile' ?></title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .profile-pic-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-size: cover;
            background-position: center;
            margin-right: 20px;
            border: 3px solid #eee;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-section h2 {
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-control:disabled {
            background-color: #f5f5f5;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .password-fields {
            display: <?= ($edit_mode && !$editing_other_user) ? 'block' : 'none' ?>;
        }
    </style>
</head>
<body>

            <main id="main" onclick="closeNav()">
                <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
                <h1><u><?= $editing_other_user ? 'Edit User: ' . htmlspecialchars($user['full_name']) : 'User Profile' ?></u></h1>
                
                <div class="profile-container">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="profile-header">
                            <div class="profile-pic-large" style="background-image: url('<?= 
                                !empty($user['profile_pic']) ? '/images/profile_pic/'.htmlspecialchars($user['profile_pic']) : '/images/icons/user.png' 
                            ?>')"></div>
                            <div class="profile-info">
                                <h2><?= htmlspecialchars($user['full_name']) ?></h2>
                                <?php if ($edit_mode): ?>
                                    <input type="file" name="profile_pic" accept=".jpg,.jpeg,.png">
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="profile-section">
                            <h2>Personal Information</h2>
                            
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control" 
                                    value="<?= htmlspecialchars($user['full_name']) ?>" 
                                    <?= !$edit_mode ? 'disabled' : '' ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>Student ID</label>
                                <input type="text" name="student_id" class="form-control" 
                                    value="<?= htmlspecialchars($user['student_id']) ?>" 
                                    <?= !$edit_mode ? 'disabled' : '' ?>>
                            </div>
                            
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" 
                                    value="<?= htmlspecialchars($user['email']) ?>" 
                                    <?= !$edit_mode ? 'disabled' : '' ?>>
                            </div>
                            
                            <?php if ($edit_mode && $is_admin_or_coordinator && $editing_other_user): ?>
                                <div class="form-group">
                                    <label>Role</label>
                                    <select name="role" class="form-control">
                                        <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                        <option value="coordinator" <?= $user['role'] === 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                                        <option value="event_advisor" <?= $user['role'] === 'event_advisor' ? 'selected' : '' ?>>Event Advisor</option>
                                    </select>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($edit_mode): ?>
                                <div class="form-actions">
                                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                    <a href="<?= $editing_other_user ? 'user_list.php' : 'user_profile.php' ?>" class="btn btn-danger">Cancel</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$editing_other_user): ?>
                            <div class="profile-section password-fields">
                                <h2>Change Password</h2>
                                
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password" name="current_password" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" name="new_password" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control">
                                </div>
                                
                                <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Profile menu toggle
        function toggleProfileMenu() {
            const profileMenu = document.getElementById("profileMenu");
            profileMenu.classList.toggle("active");
        }

        // Close profile menu when clicking outside
        document.addEventListener('click', function(event) {
            const profileMenu = document.getElementById("profileMenu");
            const profilePic = document.querySelector(".profile");
            
            if (!profileMenu.contains(event.target) && !profilePic.contains(event.target)) {
                profileMenu.classList.remove("active");
            }
        });

        // Sidebar navigation functions
        function openNav(e) {
            e.stopPropagation();
            document.getElementById("mySidenav").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }
        
        function closeNav() {
            document.getElementById("mySidenav").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }
        
        // Close nav when clicking anywhere outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.sidenav') && !event.target.closest('.menu-toggle')) {
                closeNav();
            }
        });
    </script>
        <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
