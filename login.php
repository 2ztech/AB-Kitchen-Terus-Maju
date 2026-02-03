<?php
require_once 'config/config.php';
require_once 'helpers.php';
require_once 'handlers/auth_handler.php';

// Initialize variables
$login_error = '';


// After successful login in index.php
if (isset($_SESSION['qr_redirect'])) {
    $redirect = $_SESSION['qr_redirect'];
    unset($_SESSION['qr_redirect']);
    header("Location: $redirect");
    exit;
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl($_SESSION['role']));
    exit;
}


// Handle authentication
$login_result = handleLogin($pdo);
if ($login_result['redirect']) {
    header('Location: ' . $login_result['redirect']);
    exit;
}
$login_error = $login_result['error'];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AcikBulat</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="./styles/index_style.css">
</head>
<body>
    <!-- Login Page -->
    <section id="login-page">
        <div class="login-container">
            <form method="POST" action="">
                <h1>Welcome to Admin Dashboard</h1>
                <?php if ($login_error): ?>
                    <div class="error-message"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <div class="input-box">
                    <input type="text" name="identifier" placeholder="Username" required>
                    <i class='bx bxs-user-circle'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                
                <div class="remember-forgot">
                    <label><input type="checkbox"> Remember me</label>
                    <a href="#">Forgot password?</a>
                </div>

                <button type="submit" name="login" class="btn">Login</button>


            </form>
        </div>
    </section>


</body>
</html>
