<?php
require_once 'config/config.php';

// Skip session check if we're in QR flow
$is_qr_flow = isset($_SESSION['qr_redirect']) || 
              (basename($_SERVER['SCRIPT_NAME']) === 'event_checkin.php' && isset($_GET['event_id']));

if (!isset($_SESSION['user_id']) && !$is_qr_flow) {
    header('Location: /index.php');
    exit;
}

// Get complete user data from database
try {
    $stmt = $pdo->prepare("SELECT full_name, role, profile_pic FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("User not found");
    }

    // Set session variables from database
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['profile_pic'] = $user['profile_pic'];

    // Determine dashboard URL based on role
    $dashboard_url = match($user['role']) {
        'admin' => '/pages/dashboard/admin_dashboard.php',
        'coordinator' => '/pages/dashboard/admin_dashboard.php',
        'advisor' => '/pages/dashboard/event_advisor_dashboard.php',
        'student' => '/pages/dashboard/student_dashboard.php',
        default => '/pages/dashboard/default_dashboard.php'
    };

    // Set default image path
    $default_image = '/images/icons/user.png';
    $profile_image = $default_image;

    // Validate and set profile image if available
    if (!empty($user['profile_pic'])) {
        $filename = htmlspecialchars($user['profile_pic']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($extension, $allowed)) {
            $image_path = '/images/profile_pic/'.$filename;
            $full_path = $_SERVER['DOCUMENT_ROOT'].$image_path;
            if (file_exists($full_path)) {
                $profile_image = $image_path;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in header.php: " . $e->getMessage());
    // Handle error appropriately, maybe redirect to error page
    header('Location: /error.php');
    exit;
} catch (Exception $e) {
    error_log("Error in header.php: " . $e->getMessage());
    // Handle error appropriately
    header('Location: /error.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MyPetakom</title>
  <link rel="stylesheet" href="/styles/header.css">
  <link rel="stylesheet" href="/styles/sidenav.css">
  <link rel="stylesheet" href="/styles/main_style.css">
</head>
<body>
  <header>
    <div class="logo">
      <img src="/images/Logo.png" alt="MyPetakom Logo">
    </div>
    <nav>
      <a href="<?= $dashboard_url ?>" class="nav-link">Dashboard</a>
      <div class="profile" onclick="toggleProfileMenu()">
        <div class="profile-pic" style="background-image: url('<?= $profile_image ?>')"></div>
        <div class="profile-menu" id="profileMenu">
          <h3><?= htmlspecialchars($_SESSION['full_name']) ?><br>
            <span><?= htmlspecialchars($_SESSION['role']) ?></span>
          </h3>
          <ul>
            <li>
              <img src="../../images/icons/user.png"><a href="/pages/user_management/user_profile.php">My Profile</a>
            </li>
            <li>
              <img src="../../images/icons/edit.png"><a href="/pages/user_management/user_profile.php?edit=1">Edit Profile</a>
            </li>
            <li>
              <img src="../../images/icons/log-out.png"><a href="/logout.php">Logout</a>
            </li>
          </ul>
        </div>
      </div>
    </nav>
  </header>

  <script>
    function toggleProfileMenu() {
      document.getElementById("profileMenu").classList.toggle("active");
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', function(e) {
      const profileMenu = document.getElementById("profileMenu");
      const profile = document.querySelector(".profile");
      if (!profile.contains(e.target) && !profileMenu.contains(e.target)) {
        profileMenu.classList.remove("active");
      }
    });
  </script>
