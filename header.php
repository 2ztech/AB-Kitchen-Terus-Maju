<?php
require_once __DIR__ . '/config/config.php';

// Skip session check if we're in QR flow
$is_qr_flow = isset($_SESSION['qr_redirect']) || 
              (basename($_SERVER['SCRIPT_NAME']) === 'event_checkin.php' && isset($_GET['event_id']));

if (!isset($_SESSION['user_id']) && !$is_qr_flow) {
    header('Location: /index.php');
    exit;
}

// Get complete user data from database
try {
    // We only have 'username', 'role', 'is_new' in the new schema. 
    // No profile_pic or full_name yet.
    $stmt = $pdo->prepare("SELECT username, role, is_new FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Force logout if user no longer exists in DB
        session_destroy();
        header('Location: /index.php');
        exit;
    }

    // Set session variables
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['is_new'] = $user['is_new'];

    // Determine dashboard URL based on role
    $dashboard_url = match($user['role']) {
        'admin' => '/pages/dashboard/admin_dashboard.php',
        'cashier' => '/pages/dashboard/cashier_dashboard.php',
        default => '/index.php' // Fallback
    };

    // Default Avatar
    $profile_image = '/images/icons/user.png';

} catch (PDOException $e) {
    error_log("Database error in header.php: " . $e->getMessage());
    die("System Error");
} catch (Exception $e) {
    error_log("Error in header.php: " . $e->getMessage());
    die("System Error");
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
          <h3><?= htmlspecialchars($_SESSION['username']) ?><br>
            <span><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
          </h3>
          <ul>
            <li>
              <img src="../../images/icons/user.png"><a href="/pages/users/user_profile.php">My Profile</a>
            </li>
            <!-- Edit Profile is now part of My Profile (Change Password) -->
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
