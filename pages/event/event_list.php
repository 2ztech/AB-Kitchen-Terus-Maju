<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check user role
$showAdminButtons = isset($_SESSION['role']) && in_array($_SESSION['role'], ['event_advisor', 'coordinator', 'admin']);
$isStudent = isset($_SESSION['role']) && $_SESSION['role'] === 'student';
$current_date = date('Y-m-d');

// Fetch event data from database
try {
    $stmt = $pdo->query("SELECT event_id, event_name, event_date, 
                        start_time, end_time, 
                        general_location, specific_venue, status 
                        FROM events ORDER BY event_date ASC");
    $events = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Event List</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <style>
    .events-container {
      max-width: 900px;
      margin: 0 auto;
      padding: 20px;
    }
    
    .create-event-container {
      margin-bottom: 30px;
      text-align: right;
    }
    
    .create-event-btn {
      padding: 12px 25px;
      background-color: #4CAF50;
      color: white;
      text-decoration: none;
      border-radius: 6px;
      font-weight: bold;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
    }
    
    .create-event-btn:hover {
      background-color: #3e8e41;
      transform: translateY(-2px);
    }
    
    .event-card {
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
      margin-bottom: 20px;
      overflow: hidden;
      transition: all 0.3s ease;
    }
    
    .event-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 16px rgba(0,0,0,0.12);
    }
    
    .event-card-content {
      padding: 25px;
    }
    
    .event-link {
      text-decoration: none;
      color: inherit;
      display: block;
    }
    
    .event-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 15px;
    }
    
    .event-title {
      font-size: 22px;
      font-weight: bold;
      color: #333;
      margin: 0;
      flex: 1;
    }
    
    .event-date-badge {
      background: #2196F3;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      font-weight: bold;
      text-align: center;
      min-width: 70px;
      margin-left: 15px;
    }
    
    .event-date-day {
      font-size: 22px;
      line-height: 1;
    }
    
    .event-date-month {
      font-size: 14px;
      text-transform: uppercase;
      line-height: 1;
    }
    
    .event-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-top: 15px;
    }
    
    .event-meta-item {
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }
    
    .event-meta-icon {
      color: #2196F3;
      font-size: 18px;
      width: 24px;
      text-align: center;
      margin-top: 2px;
    }
    
    .event-meta-text {
      color: #555;
      flex: 1;
    }
    
    .event-meta-label {
      font-weight: bold;
      color: #333;
      margin-bottom: 3px;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
      margin-left: 10px;
    }
    
    .status-active {
      background-color: #4CAF50;
      color: white;
    }
    
    .status-postponed {
      background-color: #FFC107;
      color: #333;
    }
    
    .status-cancelled {
      background-color: #f44336;
      color: white;
    }
    
    .admin-actions {
      display: flex;
      gap: 10px;
      justify-content: flex-end;
      margin-top: 20px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }
    
    .edit-btn, .delete-btn {
      padding: 8px 15px;
      border-radius: 6px;
      font-size: 14px;
      font-weight: bold;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.2s;
    }
    
    .edit-btn {
      background-color: #2196F3;
      color: white;
    }
    
    .edit-btn:hover {
      background-color: #0b7dda;
    }
    
    .delete-btn {
      background-color: #f44336;
      color: white;
      border: none;
      cursor: pointer;
    }
    
    .delete-btn:hover {
      background-color: #d32f2f;
    }
    
    .past-event {
      opacity: 0.8;
    }
    
    .past-event .event-date-badge {
      background-color: #666;
    }
    
    .no-events {
      text-align: center;
      padding: 40px;
      color: #666;
    }
    
    .disabled-event {
      opacity: 0.7;
    }
    
    .disabled-event .event-link {
      cursor: not-allowed;
      pointer-events: none;
    }
    
    .disabled-event-message {
      color: #f44336;
      font-weight: bold;
      margin-top: 10px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
  </style>
</head>
<body>
    <main id="main" onclick="closeNav()">
    <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
  
  <main id="main" onclick="closeNav()">
    <h1>Event List</h1>

    <div class="events-container">
      <?php if ($showAdminButtons): ?>
        <div class="create-event-container">
          <a href="create_event.php" class="create-event-btn">
            <i class="fa fa-plus"></i> Create New Event
          </a>
        </div>
      <?php endif; ?>

      <?php if (empty($events)): ?>
        <div class="no-events">
          <p>No events found.</p>
        </div>
      <?php else: ?>
        <?php foreach ($events as $event): 
          $is_past = (strtotime($event['event_date']) < strtotime($current_date));
          $event_date = new DateTime($event['event_date']);
          $isDisabledForStudent = $isStudent && ($event['status'] !== 'active' || $is_past);
        ?>
          <div class="event-card <?= $is_past ? 'past-event' : '' ?> <?= $isDisabledForStudent ? 'disabled-event' : '' ?>">
            <div class="event-card-content">
              <?php if ($isDisabledForStudent): ?>
                <div>
                  <h2 class="event-title">
                    <?= htmlspecialchars($event['event_name']) ?>
                    <span class="status-badge status-<?= $event['status'] ?>">
                      <?= ucfirst($event['status']) ?>
                    </span>
                  </h2>
                  <div class="disabled-event-message">
                    <i class="fa fa-exclamation-circle"></i>
                    This event is not available for registration
                  </div>
                </div>
              <?php else: ?>
                <a href="event_detail.php?id=<?= $event['event_id'] ?>" class="event-link">
                  <div class="event-header">
                    <h2 class="event-title">
                      <?= htmlspecialchars($event['event_name']) ?>
                      <span class="status-badge status-<?= $event['status'] ?>">
                        <?= ucfirst($event['status']) ?>
                      </span>
                    </h2>
                    
                    <div class="event-date-badge">
                      <div class="event-date-day"><?= $event_date->format('d') ?></div>
                      <div class="event-date-month"><?= $event_date->format('M') ?></div>
                    </div>
                  </div>
                  
                  <div class="event-meta">
                    <div class="event-meta-item">
                      <span class="event-meta-icon"><i class="fa fa-clock-o"></i></span>
                      <div class="event-meta-text">
                        <div class="event-meta-label">Time</div>
                        <?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?>
                      </div>
                    </div>
                    
                    <div class="event-meta-item">
                      <span class="event-meta-icon"><i class="fa fa-map-marker"></i></span>
                      <div class="event-meta-text">
                        <div class="event-meta-label">Location</div>
                        <?= htmlspecialchars($event['general_location']) ?>
                      </div>
                    </div>
                    
                    <?php if (!empty($event['specific_venue'])): ?>
                    <div class="event-meta-item">
                      <span class="event-meta-icon"><i class="fa fa-building"></i></span>
                      <div class="event-meta-text">
                        <div class="event-meta-label">Venue</div>
                        <?= htmlspecialchars($event['specific_venue']) ?>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endif; ?>
              
              <?php if ($showAdminButtons): ?>
                <div class="admin-actions">
                  <a href="edit_event.php?id=<?= $event['event_id'] ?>" class="edit-btn">
                    <i class="fa fa-edit"></i> Edit
                  </a>
                  <button class="delete-btn" onclick="confirmDelete(<?= $event['event_id'] ?>)">
                    <i class="fa fa-trash"></i> Delete
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <script>
    function openNav(e) {
      e.stopPropagation();
      document.getElementById("mySidenav").style.width = "250px";
      document.getElementById("main").style.marginLeft = "250px";
    }

    function closeNav() {
      document.getElementById("mySidenav").style.width = "0";
      document.getElementById("main").style.marginLeft = "0";
    }

    function confirmDelete(eventId) {
      if (confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_event.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id';
        input.value = eventId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
      }
    }
  </script>
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
