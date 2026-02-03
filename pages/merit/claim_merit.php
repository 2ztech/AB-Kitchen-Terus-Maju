<?php
include ("../../header.php");
include ("../../sidenav.php");
require_once ("../../config/config.php");

// Check if user is logged in as student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: /index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';

// Initialize variables
$edit_mode = false;
$current_claim = null;
$claim_id = null;

// Check if we're editing a claim
if (isset($_GET['edit'])) {
    $claim_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($claim_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM merit_claims 
                WHERE claim_id = ? AND student_id = ? AND status = 'draft'
            ");
            $stmt->execute([$claim_id, $user_id]);
            $current_claim = $stmt->fetch();
            
            if ($current_claim) {
                $edit_mode = true;
            } else {
                $_SESSION['error_message'] = "Claim not found or cannot be edited";
                header("Location: claim_merit.php");
                exit();
            }
        } catch (PDOException $e) {
            die("Database error: " . $e->getMessage());
        }
    }
}

// Handle form submissions (create/update/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle delete action
        if (isset($_POST['delete_claim'])) {
            $claim_id = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
            
            if ($claim_id) {
                // Get claim to delete (only if it's a draft)
                $stmt = $pdo->prepare("
                    SELECT * FROM merit_claims 
                    WHERE claim_id = ? AND student_id = ? AND status = 'draft'
                ");
                $stmt->execute([$claim_id, $user_id]);
                $claim = $stmt->fetch();
                
                if ($claim) {
                    // Delete supporting document if exists
                    if ($claim['supporting_doc'] && file_exists("../../".$claim['supporting_doc'])) {
                        unlink("../../".$claim['supporting_doc']);
                    }
                    
                    // Delete claim from database
                    $stmt = $pdo->prepare("DELETE FROM merit_claims WHERE claim_id = ?");
                    $stmt->execute([$claim_id]);
                    
                    $_SESSION['success_message'] = "Claim deleted successfully!";
                } else {
                    $_SESSION['error_message'] = "Claim not found or cannot be deleted";
                }
            }
            header("Location: claim_merit.php");
            exit();
        }
        
        // Handle submit for approval action
        if (isset($_POST['submit_claim'])) {
            $claim_id = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
            
            if ($claim_id) {
                // Update claim status to submitted
                $stmt = $pdo->prepare("
                    UPDATE merit_claims 
                    SET status = 'submitted' 
                    WHERE claim_id = ? AND student_id = ? AND status = 'draft'
                ");
                $stmt->execute([$claim_id, $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success_message'] = "Claim submitted for approval!";
                } else {
                    $_SESSION['error_message'] = "Claim not found or cannot be submitted";
                }
            }
            header("Location: claim_merit.php");
            exit();
        }
        
            // Handle create/update actions
            $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
            $role_type = filter_input(INPUT_POST, 'role_type', FILTER_SANITIZE_STRING);
            $claim_id = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);

            // Get event details if creating new claim
            if (!$edit_mode && $event_id) {
                $stmt = $pdo->prepare("SELECT event_name, event_date, event_level FROM events WHERE event_id = ?");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch();
                
                if ($event) {
                    $event_name = $event['event_name'];
                    $event_date = $event['event_date'];
                    $level_id = $event['event_level'];
                } else {
                    $_SESSION['error_message'] = "Selected event not found";
                    header("Location: claim_merit.php");
                    exit();
                }
            } elseif (!$edit_mode) {
                $_SESSION['error_message'] = "Please select an event";
                header("Location: claim_merit.php");
                exit();
            }

            // Get the current claim data if in edit mode
            if ($claim_id && !$edit_mode) {
                $stmt = $pdo->prepare("SELECT * FROM merit_claims WHERE claim_id = ? AND student_id = ?");
                $stmt->execute([$claim_id, $user_id]);
                $current_claim = $stmt->fetch();
                $edit_mode = true;
            }

            // Handle file upload - keep existing file if no new file is uploaded
            $supporting_doc = $current_claim['supporting_doc'] ?? '';
            if (isset($_FILES['supporting_doc']) && $_FILES['supporting_doc']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = '../../uploads/merit_claims/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Delete old file if exists
                if ($supporting_doc && file_exists("../../$supporting_doc")) {
                    unlink("../../$supporting_doc");
                }
                
                $file_name = uniqid() . '_' . basename($_FILES['supporting_doc']['name']);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $target_file)) {
                    $supporting_doc = 'uploads/merit_claims/' . $file_name;
                }
            }

            if ($edit_mode && $claim_id) {
                // Update existing claim
                $stmt = $pdo->prepare("
                    UPDATE merit_claims SET
                        role_type = ?,
                        supporting_doc = ?
                    WHERE claim_id = ? AND student_id = ? AND status = 'draft'
                ");
                
                $stmt->execute([
                    $role_type,
                    $supporting_doc,
                    $claim_id,
                    $user_id
                ]);
            
            $_SESSION['success_message'] = "Claim updated successfully!";
            header("Location: claim_merit.php");
            exit();
        } elseif (!$edit_mode) {
            // Create new claim
            $stmt = $pdo->prepare("
                INSERT INTO merit_claims (
                    student_id, 
                    event_id,
                    event_name, 
                    event_date, 
                    level_id, 
                    role_type, 
                    supporting_doc, 
                    status
                ) VALUES (
                    :student_id, 
                    :event_id,
                    :event_name, 
                    :event_date, 
                    :level_id, 
                    :role_type, 
                    :supporting_doc, 
                    'draft'
                )
            ");
            
            $stmt->execute([
                ':student_id' => $user_id,
                ':event_id' => $event_id,
                ':event_name' => $event_name,
                ':event_date' => $event_date,
                ':level_id' => $level_id,
                ':role_type' => $role_type,
                ':supporting_doc' => $supporting_doc
            ]);
            
            $_SESSION['success_message'] = "Claim created successfully!";
            header("Location: claim_merit.php");
            exit();
        }
        
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Get existing claims for this student
try {
    $stmt = $pdo->prepare("
        SELECT 
            mc.*,
            el.level_name,
            rm.merit_value as potential_points
        FROM merit_claims mc
        JOIN event_levels el ON mc.level_id = el.level_id
        JOIN role_merits rm ON mc.level_id = rm.level_id AND mc.role_type = rm.role_type
        WHERE mc.student_id = :student_id
        ORDER BY mc.status, mc.event_date DESC
    ");
    $stmt->execute([':student_id' => $user_id]);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get event levels for dropdown
    $stmt = $pdo->query("SELECT * FROM event_levels");
    $event_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all events for dropdown
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Claim Missing Merit | MyPetakom</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css">
  <style>
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .form-container {
      background: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
    }
    
    .form-control {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 5px;
      font-size: 16px;
    }
    
    .btn {
      padding: 10px 20px;
      background: #4CAF50;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      margin-right: 10px;
    }
    
    .btn:hover {
      background: #45a049;
    }
    
    .btn-secondary {
      background: #6c757d;
    }
    
    .btn-secondary:hover {
      background: #5a6268;
    }
    
    .btn-danger {
      background: #dc3545;
    }
    
    .btn-danger:hover {
      background: #c82333;
    }
    
    .btn-info {
      background: #17a2b8;
    }
    
    .btn-info:hover {
      background: #138496;
    }
    
    .claims-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 30px;
    }
    
    .claims-table th, 
    .claims-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    
    .claims-table th {
      background-color: #f2f2f2;
      font-weight: 600;
    }
    
    .status-draft {
      color: #FFC107;
    }
    
    .status-submitted {
      color: #2196F3;
    }
    
    .status-approved {
      color: #4CAF50;
    }
    
    .status-rejected {
      color: #F44336;
    }
    
    .alert {
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 5px;
    }
    
    .alert-success {
      background-color: #dff0d8;
      color: #3c763d;
    }
    
    .alert-error {
      background-color: #f2dede;
      color: #a94442;
    }

    .action-buttons {
      display: flex;
      gap: 5px;
    }

    .action-buttons form {
      display: inline;
    }
  </style>
</head>
<body>
  <main id="main" onclick="closeNav()">
    <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
    <div class="dashboard-container">
      <h1>Claim Missing Merit</h1>
      <p>Submit a claim for merits you believe you earned but weren't automatically recorded.</p>
      
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
          <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
          <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>
      
      <div class="form-container">
        <h2><?php echo $edit_mode ? 'Edit Merit Claim' : 'New Merit Claim'; ?></h2>
        <form action="claim_merit.php" method="POST" enctype="multipart/form-data">
          <?php if ($edit_mode): ?>
            <input type="hidden" name="claim_id" value="<?php echo $current_claim['claim_id']; ?>">
          <?php endif; ?>
          
          <div class="form-group">
            <label for="event_id">Event</label>
            <?php if ($edit_mode): ?>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_claim['event_name']); ?>" readonly>
              <input type="hidden" name="event_id" value="<?php echo $current_claim['event_id']; ?>">
            <?php else: ?>
              <select id="event_id" name="event_id" class="form-control" required>
                <option value="">Select Event</option>
                <?php foreach ($events as $event): ?>
                  <option value="<?php echo $event['event_id']; ?>">
                    <?php echo htmlspecialchars($event['event_name']); ?> 
                    (<?php echo date('d M Y', strtotime($event['event_date'])); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          
          <div class="form-group">
            <label for="role_type">Your Role</label>
            <select id="role_type" name="role_type" class="form-control" required>
              <option value="">Select Your Role</option>
              <option value="main_committee" <?php echo ($edit_mode && $current_claim['role_type'] == 'main_committee') ? 'selected' : ''; ?>>Main Committee</option>
              <option value="committee" <?php echo ($edit_mode && $current_claim['role_type'] == 'committee') ? 'selected' : ''; ?>>Committee Member</option>
              <option value="participant" <?php echo ($edit_mode && $current_claim['role_type'] == 'participant') ? 'selected' : ''; ?>>Participant</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="supporting_doc">Supporting Document (Official Letter)</label>
            <input type="file" id="supporting_doc" name="supporting_doc" class="form-control" <?php echo !$edit_mode ? 'required' : ''; ?> accept=".pdf,.jpg,.jpeg,.png">
            <?php if ($edit_mode && $current_claim['supporting_doc']): ?>
              <small>Current file: <a href="/<?php echo htmlspecialchars(str_replace('../../uploads/merit_claims/', 'uploads/merit_claims/', $current_claim['supporting_doc'])); ?>" target="_blank">View</a></small>
            <?php endif; ?>
          </div>
          
          <div class="form-group">
            <button type="submit" class="btn"><?php echo $edit_mode ? 'Update Claim' : 'Create Claim'; ?></button>
            
            <?php if ($edit_mode): ?>
              <a href="claim_merit.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
      
      <h2>Your Previous Claims</h2>
      <?php if (count($claims) > 0): ?>
        <table class="claims-table">
          <thead>
            <tr>
              <th>Event</th>
              <th>Date</th>
              <th>Level</th>
              <th>Role</th>
              <th>Potential Points</th>
              <th>Status</th>
              <th>Document</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($claims as $claim): ?>
              <tr>
                <td><?php echo htmlspecialchars($claim['event_name']); ?></td>
                <td><?php echo date('d M Y', strtotime($claim['event_date'])); ?></td>
                <td><?php echo htmlspecialchars($claim['level_name']); ?></td>
                <td><?php echo ucfirst(str_replace('_', ' ', $claim['role_type'])); ?></td>
                <td><?php echo $claim['potential_points']; ?></td>
                <td class="status-<?php echo $claim['status']; ?>">
                  <?php echo ucfirst($claim['status']); ?>
                </td>
                <td>
                  <?php if ($claim['supporting_doc']): ?>
                    <a href="/<?php echo htmlspecialchars(str_replace('../../uploads/merit_claims/', 'uploads/merit_claims/', $claim['supporting_doc'])); ?>" target="_blank">View</a>
                  <?php else: ?>
                    N/A
                  <?php endif; ?>
                </td>
                <td>
                  <div class="action-buttons">
                    <?php if ($claim['status'] === 'draft'): ?>
                      <a href="claim_merit.php?edit=<?php echo $claim['claim_id'];                      ?>" class="btn" style="background:#2196F3;">Edit</a>
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="claim_id" value="<?php echo $claim['claim_id']; ?>">
                        <button type="submit" name="submit_claim" class="btn btn-info">Submit</button>
                      </form>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this claim?');">
                        <input type="hidden" name="claim_id" value="<?php echo $claim['claim_id']; ?>">
                        <button type="submit" name="delete_claim" class="btn btn-danger">Delete</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p>You haven't made any merit claims yet.</p>
      <?php endif; ?>
    </div>
  </main>
  
  <script>
    // Simple form validation
    document.querySelector('form').addEventListener('submit', function(e) {
      const fileInput = document.getElementById('supporting_doc');
      const maxSize = 5 * 1024 * 1024; // 5MB
      
      if (!<?php echo $edit_mode ? 'true' : 'false'; ?> && fileInput.files.length === 0) {
        alert('Please upload a supporting document');
        e.preventDefault();
      }
      
      if (fileInput.files.length > 0 && fileInput.files[0].size > maxSize) {
        alert('File size exceeds 5MB limit');
        e.preventDefault();
      }
    });

    // Navigation Functions
    function openNav(e) {
        e.stopPropagation();
        document.getElementById("mySidenav").style.width = "250px";
        document.getElementById("main").style.marginLeft = "250px";
    }

    function closeNav() {
        document.getElementById("mySidenav").style.width = "0";
        document.getElementById("main").style.marginLeft = "0";
    }

    // Close nav when clicking outside
    document.addEventListener('click', function(event) {
        const sidenav = document.getElementById("mySidenav");
        const menuToggle = document.querySelector(".menu-toggle");
        
        if (!event.target.closest('.sidenav') && !event.target.closest('.menu-toggle')) {
            closeNav();
        }
    });

    // Close nav when pressing Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            closeNav();
        }
    });
  </script>
</body>
</html>

