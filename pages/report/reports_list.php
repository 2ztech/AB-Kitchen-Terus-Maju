<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication check - only allow admin and coordinator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator', 'event_advisor'])) {
    header("Location: /index.php");
    exit();
}

// Get the requested report type from URL
$activeReport = isset($_GET['report']) ? $_GET['report'] : 'membership';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reports Dashboard</title>
  <style>
    /* Internal CSS for reports navigation */
    .report-nav {
      margin: 20px 0 30px;
      padding: 0;
    }
    
    .report-nav ul {
      list-style-type: none;
      padding: 0;
      margin: 0;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }
    
    .report-nav li {
      margin: 0;
    }
    
    .report-nav a {
      display: block;
      padding: 12px 20px;
      background-color: #2c3e50;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      transition: all 0.3s ease;
      font-weight: 500;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .report-nav a:hover {
      background-color: #3498db;
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    .report-nav a.active {
      background-color: #3498db;
      font-weight: bold;
    }
    
    .report-section {
      background: white;
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      margin-bottom: 30px;
      display: none;
    }
    
    .report-section.active {
      display: block;
    }
    
    .report-section h2 {
      color: #2c3e50;
      border-bottom: 2px solid #3498db;
      padding-bottom: 10px;
      margin-top: 0;
    }
    
    .coming-soon {
      text-align: center;
      padding: 40px 20px;
      color: #7f8c8d;
      font-style: italic;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <?php include '../../sidenav.php'; ?>
    
    <main id="main" onclick="closeNav()">
      <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
      <h1><u>Reports Dashboard</u></h1>
      
      <!-- Reports Navigation -->
      <nav class="report-nav">
        <ul>
          <li><a href="?report=membership" class="<?= $activeReport === 'membership' ? 'active' : '' ?>">Membership Reports</a></li>
          <li><a href="?report=events" class="<?= $activeReport === 'events' ? 'active' : '' ?>">Event Management</a></li>
          <li><a href="?report=attendance" class="<?= $activeReport === 'attendance' ? 'active' : '' ?>">Attendance Reports</a></li>
          <li><a href="?report=merit" class="<?= $activeReport === 'merit' ? 'active' : '' ?>">Merit Awards</a></li>
        </ul>
      </nav>
      
      <!-- Membership Reports Section -->
      <section id="membership" class="report-section <?= $activeReport === 'membership' ? 'active' : '' ?>">
        <h2>Membership Reports</h2>
        <div class="report-content">
          <?php include 'membership_reports.php'; ?>
        </div>
      </section>
      
      <!-- Event Reports Section -->
      <section id="events" class="report-section <?= $activeReport === 'events' ? 'active' : '' ?>">
        <h2>Event Management Reports</h2>
        <div class="report-content">
          <?php include 'event_reports.php'; ?>
        </div>
      </section>
      
      <!-- Attendance Reports Section -->
      <section id="attendance" class="report-section <?= $activeReport === 'attendance' ? 'active' : '' ?>">
        <h2>Attendance Reports</h2>
        <div class="report-content">
          <?php include 'attendance_reports.php'; ?>
        </div>
      </section>
      
      <!-- Merit Awards Section -->
      <section id="merit" class="report-section <?= $activeReport === 'merit' ? 'active' : '' ?>">
        <h2>Merit Award Reports</h2>
        <div class="coming-soon">
          <?php include 'merit_reports.php'; ?>
        </div>
      </section>
    </main>
  </div>

  <script>
    // Tab navigation functionality
    document.querySelectorAll('.report-nav a').forEach(link => {
      link.addEventListener('click', function(e) {
        // Update active tab
        document.querySelectorAll('.report-nav a').forEach(a => {
          a.classList.remove('active');
        });
        this.classList.add('active');
        
        // Show corresponding section
        const targetId = this.getAttribute('href').split('=')[1];
        document.querySelectorAll('.report-section').forEach(section => {
          section.classList.remove('active');
        });
        document.getElementById(targetId).classList.add('active');
      });
    });
  </script>
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
