<?php
/**
 * Kuih Raya - User List
 * Location: pages/users/user_list.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check - only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];
    
    // Check if trying to delete self
    if ($userId == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $success = "User deleted successfully.";
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users
try {
    $stmt = $pdo->query("SELECT id, username, role, is_new, created_at FROM users ORDER BY username ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Kuih Raya</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        /* Simple table styles */
        .user-list-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        .btn-add {
            background-color: #28a745;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 15px;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
        }
        .badge-admin { background: #d4edda; color: #155724; }
        .badge-cashier { background: #cce5ff; color: #004085; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <!-- New Flex Header -->
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1>User Management</h1>
                <p>Manage admins and cashiers.</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:10px;border-radius:4px;margin-bottom:15px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div style="background:#d4edda;color:#155724;padding:10px;border-radius:4px;margin-bottom:15px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <a href="add_user.php" class="btn-add">+ Add New User</a>

        <div class="user-list-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>#<?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td>
                            <span class="badge badge-<?= $user['role'] ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['is_new']): ?>
                                <span style="color:orange;font-size:0.9em;">New (Change Password)</span>
                            <?php else: ?>
                                <span style="color:green;font-size:0.9em;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display:inline;">
                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                            <?php else: ?>
                                <small>(You)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <?php include '../../footer.php'; ?>
</body>
</html>
