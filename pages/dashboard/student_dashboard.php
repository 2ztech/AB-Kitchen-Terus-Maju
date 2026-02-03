<?php
include ("../../header.php");
include ("../../sidenav.php");
require_once ("../../config/config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Get user data from session
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
    
    // Total academic year merit
    $stmt = $pdo->prepare("SELECT SUM(points) as total_merits FROM merit_records WHERE user_id = ? AND academic_year = ?");
    $stmt->execute([$user_id, $current_year]);
    $total_merits = $stmt->fetchColumn() ?? 0;
    
    // Current semester merit
    $stmt = $pdo->prepare("SELECT SUM(points) as semester_merits FROM merit_records WHERE user_id = ? AND semester = ? AND academic_year = ?");
    $stmt->execute([$user_id, $current_semester, $current_year]);
    $semester_merits = $stmt->fetchColumn() ?? 0;
    
    // Pending claims
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_claims FROM merit_claims WHERE student_id = ? AND status = 'submitted'");
    $stmt->execute([$user_id]);
    $pending_claims = $stmt->fetchColumn() ?? 0;
    
// Recent merit activities (last 5) - FINAL CORRECTED VERSION
$stmt = $pdo->prepare("
    SELECT 
        e.event_date as display_date,
        e.event_name,
        el.level_name,
        mr.role_type,
        mr.points,
        CASE 
            WHEN mr.claim_id IS NOT NULL THEN 'Claim Approved'
            ELSE 'Automatically Awarded'
        END as status
    FROM merit_records mr
    JOIN events e ON mr.event_id = e.event_id
    JOIN event_levels el ON mr.level_id = el.level_id
    WHERE mr.user_id = ?
    ORDER BY e.event_date DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent_activities = $stmt->fetchAll();
    
    // Merit distribution data
    $stmt = $pdo->prepare("
        SELECT 
            role_type,
            SUM(points) as total_points
        FROM merit_records
        WHERE user_id = ? AND semester = ? AND academic_year = ?
        GROUP BY role_type
    ");
    $stmt->execute([$user_id, $current_semester, $current_year]);
    $merit_distribution = $stmt->fetchAll();
    
    // Merit trend data (all semesters)
    $stmt = $pdo->prepare("
        SELECT 
            semester,
            academic_year,
            SUM(points) as total_points
        FROM merit_records
        WHERE user_id = ?
        GROUP BY semester, academic_year
        ORDER BY academic_year, semester
    ");
    $stmt->execute([$user_id]);
    $merit_trend = $stmt->fetchAll();
    
    // QR code path
    $qr_code = $student['qr_code'] ?? null;
    
    // If no data exists for charts, create sample data
    if (empty($merit_distribution)) {
        $merit_distribution = [
            ['role_type' => 'participant', 'total_points' => 0],
            ['role_type' => 'committee', 'total_points' => 0],
            ['role_type' => 'main_committee', 'total_points' => 0]
        ];
    }
    
    if (empty($merit_trend)) {
        $merit_trend = [
            ['semester' => '1', 'academic_year' => $current_year, 'total_points' => 0],
            ['semester' => '2', 'academic_year' => $current_year, 'total_points' => 0]
        ];
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Student Dashboard</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css" />
  <link rel="stylesheet" href="../../styles/student_dashboard.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

  <!-- Chart.js for graphical data -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
   .action-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 8px 15px;
    background-color: #4CAF50;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.action-btn:hover {
    background-color: #45a049;
}

.action-btn i {
    font-size: 0.9em;
}

  </style>
</head>
<body>
      <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <div class="dashboard-container">
          <!-- Header Card -->
          <div class="card">
            <div class="dashboard-header">
              <h1>Student Dashboard</h1>
              <p>Welcome back, <?php echo htmlspecialchars($full_name); ?>!</p>
            </div>
          </div>
          
          <!-- Merit Summary Section -->
          <div class="card">
            <h2>Merit Summary</h2>
            <div class="merit-summary">
              <div class="merit-card">
                <h3>Current Semester Merit</h3>
                <p class="merit-value"><?php echo $semester_merits; ?></p>
                <p>Semester <?php echo $current_semester; ?> <?php echo $current_year; ?></p>
              </div>
              
              <div class="merit-card">
                <h3>Total Academic Year Merit</h3>
                <p class="merit-value"><?php echo $total_merits; ?></p>
                <p>Cumulative merit score</p>
              </div>
              
              <div class="merit-card">
                <h3>Pending Claims</h3>
                <p class="merit-value"><?php echo $pending_claims; ?></p>
                <p>Applications awaiting approval</p>
              </div>
            </div>
          </div>
          
          <!-- QR Code Section -->
          <div class="card">
              <h2>Your Merit QR Code</h2>
              <div class="qr-code-section" style="text-align: center;">
                  <?php if ($qr_code): ?>
                      <img src="<?php echo htmlspecialchars($qr_code); ?>" alt="QR Code" style="width: 150px; height: 150px; margin: 0 auto; display: block;">
                      <p style="margin: 10px 0;">Scan this code to verify your merit records</p>
                      <div style="display: flex; justify-content: center; gap: 10px; margin-top: 15px;">
                          <a href="<?php echo htmlspecialchars($qr_code); ?>" download="petakom_merit_qr_<?php echo $student['student_id']; ?>.png" class="action-btn">
                              <i class="fas fa-download"></i> Download QR
                          </a>
                          <a href="generate_qr.php?refresh=1" class="action-btn" style="background-color: #2196F3;">
                              <i class="fas fa-sync-alt"></i> Refresh QR
                          </a>
                      </div>
                      <p style="font-size: 0.8em; color: #666; margin-top: 10px;">
                          Last updated: <?php echo date('d M Y H:i', filemtime('../../' . $qr_code)); ?>
                      </p>
                  <?php else: ?>
                      <div style="width: 150px; height: 150px; margin: 0 auto; background: #eee; display: flex; align-items: center; justify-content: center; border: 1px dashed #ccc;">
                          <i class="fas fa-qrcode" style="font-size: 2em; color: #999;"></i>
                      </div>
                      <p style="margin: 10px 0;">Generate a QR code to verify your merit records</p>
                      <a href="generate_qr.php" class="action-btn" style="margin-top: 10px;">
                          <i class="fas fa-qrcode"></i> Generate QR Code
                      </a>
                  <?php endif; ?>
              </div>
          </div>

          
          <!-- Quick Actions (simplified) -->
              <div class="card">
              <h2>Navigation</h2>
              <p>Use the menu button (<span class="menu-toggle" style="cursor:default">&#9776;</span>) in the top left corner to navigate between sections.</p>
            </div>
          </div>
          
          <!-- Charts Section -->
          <div class="card">
            <h2>Merit Analytics</h2>
            <div class="chart-container">
              <div class="chart">
                <h3>Merit Distribution This Semester</h3>
                <canvas id="meritDistributionChart" height="200"></canvas>
              </div>
              
              <div class="chart">
                <h3>Merit Trend</h3>
                <canvas id="meritTrendChart" height="200"></canvas>
              </div>
            </div>
          </div>
          
          <!-- Recent Activities -->
          <div class="card">
            <h2>Recent Merit Activities</h2>
            <?php if (!empty($recent_activities)): ?>
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Event</th>
                    <th>Level</th>
                    <th>Role</th>
                    <th>Merit</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recent_activities as $activity): ?>
                    <tr>
                      <td><?php echo date('d M Y', strtotime($activity['display_date'])); ?></td>
                      <td><?php echo htmlspecialchars($activity['event_name']); ?></td>
                      <td><?php echo htmlspecialchars($activity['level_name']); ?></td>
                      <td><?php echo ucfirst(str_replace('_', ' ', $activity['role_type'])); ?></td>
                      <td><?php echo $activity['points']; ?></td>
                      <td class="<?php echo strpos($activity['status'], 'Approved') !== false ? 'status-approved' : 'status-pending'; ?>">
                        <?php echo htmlspecialchars($activity['status']); ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p>No recent merit activities found.</p>
            <?php endif; ?>
          </div>
        </div>
      </main>
      
        <script>
    // Merit Distribution Chart
    const meritDistributionCtx = document.getElementById('meritDistributionChart').getContext('2d');
    const meritDistributionChart = new Chart(meritDistributionCtx, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode(array_map(function($item) { 
            return ucfirst(str_replace('_', ' ', $item['role_type'])); 
        }, $merit_distribution)); ?>,
        datasets: [{
          data: <?php echo json_encode(array_column($merit_distribution, 'total_points')); ?>,
          backgroundColor: [
            '#4CAF50',
            '#2196F3',
            '#FFC107',
            '#9C27B0',
            '#E91E63'
          ],
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom',
          }
        }
      }
    });
    
    // Merit Trend Chart
    const meritTrendCtx = document.getElementById('meritTrendChart').getContext('2d');
    const meritTrendChart = new Chart(meritTrendCtx, {
      type: 'line',
      data: {
        labels: <?php echo json_encode(array_map(function($item) { 
            return 'Sem ' . $item['semester'] . ' ' . $item['academic_year']; 
        }, $merit_trend)); ?>,
        datasets: [{
          label: 'Cumulative Merit',
          data: <?php 
            $cumulative = 0;
            $trend_data = [];
            foreach ($merit_trend as $item) {
                $cumulative += $item['total_points'];
                $trend_data[] = $cumulative;
            }
            echo json_encode($trend_data); 
          ?>,
          fill: false,
          borderColor: '#4CAF50',
          tension: 0.4 
        }] 
      } 
    });

    // ===== ADD THE SIDE NAVIGATION CODE RIGHT HERE =====
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
    // ===== END OF SIDE NAVIGATION CODE =====
  </script>
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>