<?php
/**
 * Global Header - Loads on every page
 * Location: /header.php
 */
require_once __DIR__ . '/config/config.php';

// 1. Session & Login Check
$is_qr_flow = isset($_SESSION['qr_redirect']) || 
              (basename($_SERVER['SCRIPT_NAME']) === 'event_checkin.php' && isset($_GET['event_id']));

if (!isset($_SESSION['user_id']) && !$is_qr_flow) {
    header('Location: /');
    exit;
}

// 2. Fetch User Data
try {
    $stmt = $pdo->prepare("SELECT username, role, is_new FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: /');
        exit;
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];

    // 3. Smart Base URL Detection (The Fix)
    // This finds the "root" folder of your project automatically
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    
    // Calculate the project root based on where this file (header.php) is located
    // If header.php is in C:/xampp/htdocs/my-project/, this gets "/my-project/"
    $project_dir = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', __DIR__));
$base_url = $protocol . "://" . $host . "/";
    // Dashboard Links
    $dashboard_url = match($user['role']) {
        'admin' => $base_url . 'admin',
        'cashier' => $base_url . 'admin',
        default => $base_url
    };

    // Store Name & Logo Logic
    require_once __DIR__ . '/helpers.php';
    $settings = get_settings($pdo);
    $store_name = $settings['store_name'] ?? 'AB Kitchen';
    
    $logo_path = !empty($settings['store_logo']) 
        ? $base_url . 'images/settings/' . htmlspecialchars($settings['store_logo']) 
        : $base_url . 'images/Logo.png';

} catch (Exception $e) {
    die("System Error");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($store_name) ?></title>
  
  <link rel="stylesheet" href="<?= $base_url ?>styles/header.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= $base_url ?>styles/sidenav.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= $base_url ?>styles/main_style.css?v=<?= time() ?>">
  
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>
<body>
  <header>
    <a href="<?= $dashboard_url ?>" class="logo">
      <img src="<?= $logo_path ?>" alt="Logo" onerror="this.src='<?= $base_url ?>images/icons/user.png'">
      <span><?= htmlspecialchars($store_name) ?></span>
    </a>

    <nav>
      <div class="profile" onclick="toggleProfileMenu()">
        <div class="profile-pic" style="background-image: url('<?= $base_url ?>images/icons/user.png')"></div>
        
        <div class="profile-menu" id="profileMenu">
          <h3><?= htmlspecialchars($_SESSION['username']) ?><br>
            <span><?= htmlspecialchars(ucfirst($_SESSION['role'])) ?></span>
          </h3>
          <ul>
            <li>
              <img src="<?= $base_url ?>images/icons/user.png">
              <a href="<?= $base_url ?>users/profile">My Profile</a>
            </li>
            <li>
              <img src="<?= $base_url ?>images/icons/log-out.png">
              <a href="<?= $base_url ?>logout.php">Logout</a>
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
      const menu = document.getElementById("profileMenu");
      const profile = document.querySelector(".profile");
      if (!profile.contains(e.target) && !menu.contains(e.target)) {
        menu.classList.remove("active");
      }
    });
  </script>