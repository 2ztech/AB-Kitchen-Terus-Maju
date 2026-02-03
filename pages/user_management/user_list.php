<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check - only allow admin and coordinator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator'])) {
    header("Location: /index.php");
    exit();
}

// Check if user is admin
$can_edit = in_array($_SESSION['role'], ['admin', 'coordinator']);

// Handle status updates and deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $userId = $_POST['user_id'];
        $newStatus = $_POST['new_status'];
        
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $userId]);
        
        header("Location: user_list.php");
        exit();
    }
    
// Delete user
if (isset($_POST['delete_user'])) {
    $userId = $_POST['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Temporarily disable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        // 1. First delete from all tables that might reference the user
        $tablesToDelete = [
            'event_committee',
            'event_registrations',
            'membership_application',
            'merit_records',
            'students'
        ];
        
        foreach ($tablesToDelete as $table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
            $stmt->execute([$userId]);
        }
        
        // 2. Handle merit_claims and merit_applications separately
        //    since they reference student_id which is the same as user_id
        $stmt = $pdo->prepare("DELETE FROM merit_claims WHERE student_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("DELETE FROM merit_applications WHERE student_id = ?");
        $stmt->execute([$userId]);
        
        // 3. Finally delete from users table
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $pdo->commit();
        
        $_SESSION['success'] = "User deleted successfully";
        header("Location: user_list.php");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Ensure foreign key checks are re-enabled even if error occurs
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $_SESSION['error'] = "Delete failed: " . $e->getMessage();
        error_log("Delete user error: " . $e->getMessage());
        header("Location: user_list.php");
        exit();
    }
}

}

// Get all users
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.student_id, u.email, u.role, 
           u.status as user_status,
           ma.status as membership_status,
           ma.approval_date
    FROM users u
    LEFT JOIN membership_application ma ON u.id = ma.user_id
    ORDER BY u.full_name
");
$stmt->execute();
$users = $stmt->fetchAll();

// Session data
$full_name = $_SESSION['full_name'] ?? 'User';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_role = $_SESSION['role'] ?? '';

// Function to format role names
function formatRoleName($role) {
    $role = str_replace('_', ' ', $role); // Replace underscores with spaces
    return ucwords($role); // Capitalize each word
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User List</title>
  <link rel="stylesheet" href="../../styles/userlist.css" />
</head>
<body>

      <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1><u>User List</u></h1>
        
        <?php if ($can_edit): ?>
          <a href="/pages/user_management/add_user.php" class="btn btn-add">Add User</a>
        <?php endif; ?>
        
        <div class="user-list-container">
          <?php if (empty($users)): ?>
            <div class="no-users">
              <p>No users found in the system.</p>
            </div>
          <?php else: ?>
            <table class="user-table">
              <thead>
                <tr>
                  <th>Full Name</th>
                  <th>ID Number</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($users as $user): ?>
                  <tr>
                    <td class="full-name"><?= strtoupper(htmlspecialchars($user['full_name'])) ?></td>
                    <td><?= strtoupper(htmlspecialchars($user['student_id'])) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= formatRoleName($user['role']) ?></td>
                    <td>
                      <span class="status-<?= htmlspecialchars($user['user_status']) ?>">
                        <?= ucfirst(htmlspecialchars($user['user_status'])) ?>
                      </span>
                    </td>
                    <td>
                      <div class="action-buttons">
                        <?php if ($can_edit): ?>
                          <?php if ($user['user_status'] === 'rejected'): ?>
                            <form method="POST" style="display: inline;">
                              <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                              <input type="hidden" name="new_status" value="pending">
                              <button type="submit" name="update_status" class="btn btn-status">Revoke Rejection</button>
                            </form>
                          <?php endif; ?>
                          
                          <a href="user_profile.php?id=<?= $user['id'] ?>&edit=1" class="btn btn-edit">Edit</a>
                          
                          <button type="button" class="btn btn-delete" 
                                  onclick="confirmDelete(<?= $user['id'] ?>)">Delete</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <h3>Confirm Deletion</h3>
      <p>Are you sure you want to delete this user? This action cannot be undone.</p>
      <div class="modal-buttons">
        <button type="button" class="btn" onclick="closeModal()">Cancel</button>
        <form method="POST" id="deleteForm">
          <input type="hidden" name="user_id" id="deleteUserId">
          <button type="submit" name="delete_user" class="btn btn-delete">Confirm Delete</button>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Delete confirmation
    function confirmDelete(userId) {
      document.getElementById('deleteUserId').value = userId;
      document.getElementById('deleteModal').style.display = 'block';
    }
    
    function closeModal() {
      document.getElementById('deleteModal').style.display = 'none';
    }
    
    // Close modal if clicked outside
    window.onclick = function(event) {
      const modal = document.getElementById('deleteModal');
      if (event.target == modal) {
        closeModal();
      }
    }
  </script>
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
