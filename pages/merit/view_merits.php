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

// Calculate current semester and academic year
$current_month = date('n');
$current_semester = ($current_month >= 1 && $current_month <= 6) ? '1' : '2';
$current_year = (date('n') <= 6) ? (date('Y')-1).'/'.date('Y') : date('Y').'/'.(date('Y')+1);

try {
    // Get student data
    $stmt = $pdo->prepare("SELECT s.* FROM students s JOIN users u ON s.user_id = u.id WHERE u.id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    
    // Total merits
    $stmt = $pdo->prepare("SELECT SUM(points) as total_merits FROM merit_records WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_merits = $stmt->fetchColumn() ?? 0;
    
    // Current semester merits
    $stmt = $pdo->prepare("SELECT SUM(points) as semester_merits FROM merit_records WHERE user_id = ? AND semester = ? AND academic_year = ?");
    $stmt->execute([$user_id, $current_semester, $current_year]);
    $semester_merits = $stmt->fetchColumn() ?? 0;
    
    // Get all merit records with details
    $stmt = $pdo->prepare("
        SELECT 
            mr.merit_id,
            COALESCE(e.event_name, mc.event_name) as event_name,
            el.level_name,
            mr.role_type,
            mr.points,
            mr.awarded_at,
            mr.semester,
            mr.academic_year,
            CASE 
                WHEN mr.claim_id IS NOT NULL THEN 'Claim Approved'
                ELSE 'Automatically Awarded'
            END as award_type
        FROM merit_records mr
        LEFT JOIN events e ON mr.event_id = e.event_id
        LEFT JOIN merit_claims mc ON mr.claim_id = mc.claim_id
        JOIN event_levels el ON mr.level_id = el.level_id
        WHERE mr.user_id = ?
        ORDER BY mr.awarded_at DESC
    ");
    $stmt->execute([$user_id]);
    $merit_records = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Merits | MyPetakom</title>
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
    
    .merit-summary {
      display: flex;
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .merit-card {
      flex: 1;
      background: #fff;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      text-align: center;
    }
    
    .merit-card h3 {
      color: #2c3e50;
      margin-top: 0;
    }
    
    .merit-value {
      font-size: 2.5rem;
      font-weight: bold;
      color: #4CAF50;
      margin: 10px 0;
    }
    
    .merit-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 30px;
    }
    
    .merit-table th, 
    .merit-table td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }
    
    .merit-table th {
      background-color: #f2f2f2;
      font-weight: 600;
    }
    
    .award-type-approved {
      color: #28a745;
      font-weight: 500;
    }
    
    .award-type-auto {
      color: #17a2b8;
      font-weight: 500;
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
    
    @media (max-width: 768px) {
      .merit-summary {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <main id="main" onclick="closeNav()">
    <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
    <div class="dashboard-container">
      <h1>My Merit Records</h1>
      <p>View your awarded merit points and participation history.</p>
      
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
      
      <!-- Merit Summary Cards -->
      <div class="form-container">
        <h2>Merit Summary</h2>
        <div class="merit-summary">
          <div class="merit-card">
            <h3>Total Merits</h3>
            <p class="merit-value"><?php echo $total_merits; ?></p>
            <p>All-time accumulated points</p>
          </div>
          
          <div class="merit-card">
            <h3>Current Semester</h3>
            <p class="merit-value"><?php echo $semester_merits; ?></p>
            <p>Semester <?php echo $current_semester; ?> <?php echo $current_year; ?></p>
          </div>
        </div>
      </div>
      
      <!-- Merit Details Table -->
      <div class="form-container">
        <h2>Detailed Merit Records</h2>
        <?php if (!empty($merit_records)): ?>
          <table class="merit-table">
            <thead>
              <tr>
                <th>Event</th>
                <th>Level</th>
                <th>Role</th>
                <th>Points</th>
                <th>Awarded On</th>
                <th>Semester</th>
                <th>Type</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($merit_records as $record): ?>
                <tr>
                  <td><?php echo htmlspecialchars($record['event_name']); ?></td>
                  <td><?php echo htmlspecialchars($record['level_name']); ?></td>
                  <td><?php echo ucfirst(str_replace('_', ' ', $record['role_type'])); ?></td>
                  <td><?php echo $record['points']; ?></td>
                  <td><?php echo date('d M Y', strtotime($record['awarded_at'])); ?></td>
                  <td>Sem <?php echo $record['semester']; ?> <?php echo $record['academic_year']; ?></td>
                  <td class="<?php echo $record['award_type'] == 'Claim Approved' ? 'award-type-approved' : 'award-type-auto'; ?>">
                    <?php echo htmlspecialchars($record['award_type']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>No merit records found.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <script>
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
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>