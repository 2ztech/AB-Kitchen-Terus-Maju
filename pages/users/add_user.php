<?php
/**
 * Kuih Raya - Add User
 * Location: pages/users/add_user.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check - only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $role = $_POST['role'];
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Username and Password are required.";
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Username already taken.";
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, is_new) VALUES (?, ?, ?, 1)");
                $stmt->execute([$username, $hashed_password, $role]);
                $success = "User '$username' created successfully!";
                
                // Clear form
                $username = ''; 
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
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
    <title>Add User - Kuih Raya</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 500px;
            margin: 20px auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;
        }
        .btn-submit {
            background-color: #28a745; color: white; border: none; padding: 12px 20px;
            width: 100%; cursor: pointer; border-radius: 4px; font-size: 16px;
        }
        .btn-submit:hover { background-color: #218838; }
        .back-link { display: block; margin-top: 15px; text-align: center; color: #666; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
         <!-- New Flex Header -->
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1>Add New User</h1>
            </div>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div style="background:#f8d7da;color:#721c24;padding:10px;margin-bottom:15px;border-radius:4px;"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;border-radius:4px;"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="cashier">Cashier</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn-submit">Create User</button>
            </form>
            <a href="user_list.php" class="back-link">Back to User List</a>
        </div>
    </main>

    <?php include '../../footer.php'; ?>
</body>
</html>
