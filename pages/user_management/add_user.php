<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check - only allow admin and coordinator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator'])) {
    header("Location: /index.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$form_data = [
    'full_name' => '',
    'student_id' => '',
    'email' => '',
    'role' => 'student' // Default role
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Get form data
    $form_data = [
        'full_name' => trim($_POST['full_name']),
        'student_id' => trim($_POST['student_id']),
        'email' => trim($_POST['email']),
        'role' => trim($_POST['role'])
    ];
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    // Validate inputs
    if (empty($form_data['full_name']) || empty($form_data['student_id']) || 
        empty($form_data['email']) || empty($form_data['role'])) {
        $error = 'All fields are required';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        // Check if email or student ID exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR student_id = ?");
        $stmt->execute([$form_data['email'], $form_data['student_id']]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email or Student ID already registered';
        } else {
            // Hash password and create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, student_id, email, password, role, status) 
                                  VALUES (?, ?, ?, ?, ?, 'approved')");
            
            if ($stmt->execute([
                $form_data['full_name'],
                $form_data['student_id'],
                $form_data['email'],
                $hashed_password,
                $form_data['role']
            ])) {
                $success = 'User added successfully';
                // Clear form on success
                $form_data = [
                    'full_name' => '',
                    'student_id' => '',
                    'email' => '',
                    'role' => 'student'
                ];
            } else {
                $error = 'Failed to add user. Please try again.';
            }
        }
    }
}

// Session data for header
$full_name = $_SESSION['full_name'] ?? 'User';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_role = $_SESSION['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add New User</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css">
  <style>
    .add-user-container {
      max-width: 800px;
      margin: 20px auto;
      padding: 20px;
      background: white;
      border-radius: 5px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: #495057;
    }
    
    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 16px;
    }
    
    .form-control:focus {
      border-color: #80bdff;
      outline: 0;
      box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
    }
    
    select.form-control {
      height: 40px;
    }
    
    .btn {
      padding: 10px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      transition: all 0.2s;
    }
    
    .btn-primary {
      background-color: #28a745;
      color: white;
    }
    
    .btn-primary:hover {
      background-color: #218838;
    }
    
    .btn-secondary {
      background-color: #6c757d;
      color: white;
    }
    
    .btn-secondary:hover {
      background-color: #5a6268;
    }
    
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
    }
    
    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 30px;
    }
  </style>
</head>
<body>
      <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1><u>Add New User</u></h1>
        
        <div class="add-user-container">
          <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          
          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
          <?php endif; ?>
          
          <form method="POST" action="">
            <div class="form-group">
              <label for="full_name">Full Name</label>
              <input type="text" id="full_name" name="full_name" class="form-control" 
                     value="<?= htmlspecialchars($form_data['full_name']) ?>" required>
            </div>
            
            <div class="form-group">
              <label for="student_id">Student/Staff ID</label>
              <input type="text" id="student_id" name="student_id" class="form-control" 
                     value="<?= htmlspecialchars($form_data['student_id']) ?>" required>
            </div>
            
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" class="form-control" 
                     value="<?= htmlspecialchars($form_data['email']) ?>" required>
            </div>
            
            <div class="form-group">
              <label for="role">User Role</label>
              <select id="role" name="role" class="form-control" required>
                <option value="student" <?= $form_data['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="coordinator" <?= $form_data['role'] === 'coordinator' ? 'selected' : '' ?>>Coordinator</option>
                <option value="event_advisor" <?= $form_data['role'] === 'event_advisor' ? 'selected' : '' ?>>Event Advisor</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="password">Password</label>
              <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
              <label for="confirm_password">Confirm Password</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>
            
            <div class="form-actions">
              <a href="/pages/user_management/user_list.php" class="btn btn-secondary">Cancel</a>
              <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
            </div>
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
