<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator'])) {
    header("Location: /index.php");
    exit();
}

// Process approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve']) || isset($_POST['reject'])) {
        $appl_id = $_POST['appl_id'];
        $newStatus = isset($_POST['approve']) ? 'approved' : 'rejected';
        
        $pdo->beginTransaction();
        
        try {
            // Update membership application
            $stmt = $pdo->prepare("
                UPDATE membership_application 
                SET status = ?, approval_date = NOW() 
                WHERE appl_id = ?
            ");
            $stmt->execute([$newStatus, $appl_id]);
            
            // Update user status
            $stmt = $pdo->prepare("
                UPDATE users u
                JOIN membership_application ma ON u.id = ma.user_id
                SET u.status = ?
                WHERE ma.appl_id = ?
            ");
            $stmt->execute([$newStatus, $appl_id]);
            
            $pdo->commit();
        
        // Refresh to show updated list
        header("Location: membership_application.php");
        exit();
    } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to process application: " . $e->getMessage();
        }
    }
}

// Get pending applications
$stmt = $pdo->prepare("
    SELECT ma.appl_id, ma.student_card, ma.submission_date,
           u.id as user_id, u.full_name, u.student_id, u.email
    FROM membership_application ma
    JOIN users u ON ma.user_id = u.id
    WHERE (ma.status = 'pending' OR (ma.status = 'rejected' AND u.status = 'pending'))
    ORDER BY ma.submission_date
");
$stmt->execute();
$applications = $stmt->fetchAll();


// Session data
$full_name = $_SESSION['full_name'] ?? 'Admin';
$profile_pic = $_SESSION['profile_pic'] ?? '';
$user_role = $_SESSION['role'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Membership Applications</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css" />
  <style>
    /* Application List Styles */
    .application-list {
      margin-top: 20px;
      max-width: 800px;
    }
    
    .application-item {
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-bottom: 10px;
      background: white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .application-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
    }
    
    .student-info {
      font-size: 16px;
    }
    
    .student-info strong {
      font-weight: 600;
    }
    
    .student-id {
      color: #666;
      margin-left: 10px;
    }
    
    .action-buttons {
      display: flex;
      gap: 8px;
    }
    
    .btn {
      padding: 5px 12px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.2s;
    }
    
    .btn-approve {
      background-color: #28a745;
      color: white;
    }
    
    .btn-approve:hover {
      background-color: #218838;
    }
    
    .btn-reject {
      background-color: #dc3545;
      color: white;
    }
    
    .btn-reject:hover {
      background-color: #c82333;
    }
    
    .application-details {
      display: none;
      padding-top: 15px;
      margin-top: 15px;
      border-top: 1px solid #eee;
    }
    
    .student-card-container {
      text-align: center;
      margin: 15px 0;
    }
    
    .student-card {
      max-width: 100%;
      max-height: 400px;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .expanded .application-details {
      display: block;
    }
    
    .expanded .application-header .action-buttons {
      display: none;
    }
    
    .no-applications {
      padding: 20px;
      text-align: center;
      color: #666;
      font-style: italic;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .application-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .action-buttons {
        margin-top: 10px;
        width: 100%;
        justify-content: flex-end;
      }
    }
  </style>
</head>
<body>
      <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1><u>Membership Applications</u></h1>
        
        <div class="application-list">
          <?php if (empty($applications)): ?>
            <div class="no-applications">
              <p>No pending membership applications at this time.</p>
            </div>
          <?php else: ?>
            <?php foreach ($applications as $app): ?>
              <div class="application-item" id="app-<?= $app['appl_id'] ?>">
                <div class="application-header" onclick="toggleApplication(<?= $app['appl_id'] ?>)">
                  <div class="student-info">
                    <strong><?= strtoupper(htmlspecialchars($app['full_name'])) ?></strong>
                    <span class="student-id"><?= strtoupper(htmlspecialchars($app['student_id'])) ?></span>
                  </div>
                  <div class="action-buttons">
                    <form method="POST" onsubmit="return confirmAction('approve')" onclick="event.stopPropagation()">
                      <input type="hidden" name="appl_id" value="<?= $app['appl_id'] ?>">
                      <button type="submit" name="approve" class="btn btn-approve">Approve</button>
                    </form>
                    <form method="POST" onsubmit="return confirmAction('reject')" onclick="event.stopPropagation()">
                      <input type="hidden" name="appl_id" value="<?= $app['appl_id'] ?>">
                      <button type="submit" name="reject" class="btn btn-reject">Reject</button>
                    </form>
                  </div>
                </div>
                
                <div class="application-details">
                  <div class="student-card-container">
                      <h4>Student Card Verification</h4>
                      <?php if (!empty($app['student_card'])): ?>
                          <img src="/<?= htmlspecialchars($app['student_card']) ?>" 
                              alt="Student Card of <?= htmlspecialchars($app['full_name']) ?>" 
                              class="student-card"
                              onerror="this.src='/images/icons/user.png'">
                      <?php else: ?>
                          <p>No student card uploaded</p>
                          <img src="/images/icons/user.png" alt="Placeholder" class="student-card">
                      <?php endif; ?>
                  </div>

                  
                  <div class="action-buttons">
                    <form method="POST" onsubmit="return confirmAction('approve')">
                      <input type="hidden" name="appl_id" value="<?= $app['appl_id'] ?>">
                      <button type="submit" name="approve" class="btn btn-approve">Approve Application</button>
                    </form>
                    <form method="POST" onsubmit="return confirmAction('reject')">
                      <input type="hidden" name="appl_id" value="<?= $app['appl_id'] ?>">
                      <button type="submit" name="reject" class="btn btn-reject">Reject Application</button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </main>
    </div>
  </div>

  <script>
    // Toggle application expansion
    function toggleApplication(appId) {
      const appElement = document.getElementById(`app-${appId}`);
      appElement.classList.toggle('expanded');
    }

    // Confirm approval/rejection
    function confirmAction(action) {
      return confirm(`Are you sure you want to ${action} this application?`);
    }

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
