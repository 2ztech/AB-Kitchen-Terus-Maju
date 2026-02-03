<?php
require_once '../../config/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../header.php';
require_once '../../sidenav.php';

$event = null;
$current_date = date('Y-m-d');

if (isset($_GET['id'])) {
    $event_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
}

if (!$event) {
    echo "<h2>Event not found.</h2>";
    exit;
}

// For students, check if event is past or inactive
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    $event_date = date('Y-m-d', strtotime($event['event_date']));
    
    if ($event_date < $current_date || $event['status'] !== 'active') {
        $_SESSION['error_message'] = "This event is no longer available";
        header("Location: event_list.php");
        exit;
    }
}

// Check registration status
$isRegistered = false;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    $stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    $isRegistered = $stmt->rowCount() > 0;
}

// Handle unregistration
if (isset($_POST['unregister']) && isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = "You have successfully unregistered from this event";
        header("Location: event_detail.php?id=" . $event_id);
        exit;
    } else {
        $_SESSION['error_message'] = "Failed to unregister from the event";
    }
}

// Display messages
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
}

// Define event level names
$eventLevelNames = [
    1 => 'UMPSA',
    2 => 'District',
    3 => 'State',
    4 => 'National',
    5 => 'International'
];

$is_past = (strtotime($event['event_date']) < strtotime($current_date));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($event['event_name']) ?> - Event Details</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css">
  <link rel="stylesheet" href="../../styles/event_detail.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    .event-detail-wrapper {
      max-width: 800px;
      margin: 20px auto;
      padding: 30px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .event-detail-title {
      font-size: 28px;
      color: #333;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .status-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 16px;
      font-size: 14px;
      font-weight: bold;
      color: white;
    }
    
    .status-active {
      background-color: #4CAF50;
    }
    
    .status-postponed {
      background-color: #FFC107;
      color: #333;
    }
    
    .status-cancelled {
      background-color: #F44336;
    }
    
    .event-meta {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    
    .event-meta div {
      display: flex;
      flex-direction: column;
    }
    
    .event-meta strong {
      font-size: 16px;
      color: #555;
      margin-bottom: 5px;
    }
    
    .event-description {
      line-height: 1.6;
      margin-bottom: 30px;
      white-space: pre-line;
    }
    
    .action-buttons {
      display: flex;
      gap: 15px;
      margin-top: 30px;
    }
    
    .register-btn, .joined-btn, .back-link, .unregister-btn {
      padding: 12px 24px;
      border-radius: 6px;
      font-weight: bold;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
    }
    
    .register-btn {
      background-color: #4CAF50;
      color: white;
    }
    
    .register-btn:hover {
      background-color: #3e8e41;
    }
    
    .joined-btn {
      background-color: #2196F3;
      color: white;
      cursor: default;
    }
    
    .back-link {
      background-color: #f5f5f5;
      color: #333;
    }
    
    .back-link:hover {
      background-color: #e0e0e0;
    }
    
    .unregister-btn {
      background-color: #f44336;
      color: white;
      border: none;
      cursor: pointer;
    }
    
    .unregister-btn:hover {
      background-color: #d32f2f;
    }
    
    .admin-actions {
      display: flex;
      gap: 15px;
      margin-top: 25px;
    }
    
    .admin-btn {
      padding: 10px 20px;
      border-radius: 6px;
      text-decoration: none;
      color: white;
      font-weight: bold;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    
    .edit-btn {
      background-color: #2196F3;
    }
    
    .edit-btn:hover {
      background-color: #0b7dda;
    }
    
    .delete-btn {
      background-color: #f44336;
    }
    
    .delete-btn:hover {
      background-color: #d32f2f;
    }
    
    .attendees-list {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #eee;
    }
    
    .attendees-list h3 {
      margin-bottom: 15px;
      font-size: 20px;
    }
    
    .attendees-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    
    .attendees-table th {
      background-color: #f5f5f5;
      text-align: left;
      padding: 12px;
      text-transform: uppercase;
      font-weight: bold;
    }
    
    .attendees-table td {
      padding: 12px;
      border-bottom: 1px solid #eee;
    }
    
    .attendees-table tr:hover {
      background-color: #f9f9f9;
    }
    
    .past-event-notice {
      background-color: #fff3cd;
      padding: 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      color: #856404;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .venue-detail {
      color: #666;
      margin-left: 5px;
      font-weight: normal;
    }
    
    .event-level {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 12px;
      background-color: #e3f2fd;
      color: #1976d2;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <main id="main" onclick="closeNav()">
    <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
    <h1>Event Details</h1>

    <div class="event-detail-wrapper">
      <?php if ($is_past && in_array($_SESSION['role'] ?? '', ['coordinator', 'admin', 'event_advisor'])): ?>
        <div class="past-event-notice">
          <i class="fa fa-exclamation-circle"></i>
          This is a past event (only visible to admins/coordinators)
        </div>
      <?php endif; ?>
      
      <h2 class="event-detail-title">
        <?= htmlspecialchars($event['event_name']) ?>
        <span class="status-badge status-<?= $event['status'] ?>">
          <?= ucfirst($event['status']) ?>
          <?= $is_past ? ' (Past)' : '' ?>
        </span>
      </h2>
      
      <div class="event-meta">
        <div>
          <strong>📅 Date:</strong>
          <?= date('F j, Y', strtotime($event['event_date'])) ?>
        </div>
        
        <div>
          <strong>🕒 Time:</strong>
          <?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?>
        </div>
        
        <div>
          <strong>👥 Available Slots:</strong>
          <?php
            // Calculate available slots
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE event_id = ?");
            $stmt->execute([$event_id]);
            $registered_count = $stmt->fetchColumn();
            $available_slots = $event['participant_slots'] - $registered_count;
            echo "$available_slots of {$event['participant_slots']}";
          ?>
        </div>
        
        <div>
          <strong>📍 Location:</strong>
          <?= htmlspecialchars($event['general_location']) ?>
          <?php if (!empty($event['specific_venue'])): ?>
            <span class="venue-detail">(<?= htmlspecialchars($event['specific_venue']) ?>)</span>
          <?php endif; ?>
        </div>
        
        <div>
          <strong>🏆 Level:</strong>
          <span class="event-level event-level-<?= $event['event_level'] ?>">
            <?= $eventLevelNames[$event['event_level']] ?? 'Unknown' ?>
          </span>
        </div>
      </div>
      
      <div class="event-description">
        <?= nl2br(htmlspecialchars($event['event_description'])) ?>
      </div>
      
      <div class="action-buttons">
        <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
          <?php if ($isRegistered): ?>
            <button class="joined-btn">
              <i class="fa fa-check-circle"></i> Already Registered
            </button>
            <form method="post" style="display: inline;">
              <button type="submit" name="unregister" class="unregister-btn" onclick="return confirm('Are you sure you want to unregister from this event?')">
                <i class="fa fa-times-circle"></i> Unregister
              </button>
            </form>
          <?php elseif ($available_slots > 0): ?>
            <a href="register_event.php?id=<?= $event_id ?>" class="register-btn">
              <i class="fa fa-calendar-plus"></i> Register for Event
            </a>
          <?php else: ?>
            <button class="joined-btn" disabled>
              <i class="fa fa-times-circle"></i> Event Full
            </button>
          <?php endif; ?>
        <?php endif; ?>
        <a href="event_list.php" class="back-link">
          <i class="fa fa-arrow-left"></i> Back to Events
        </a>
      </div>

      <?php if (in_array(($_SESSION['role'] ?? ''), ['coordinator', 'admin', 'event_advisor'])): ?>
        <div class="admin-actions">
          <a href="edit_event.php?id=<?= $event_id ?>" class="admin-btn edit-btn">
            <i class="fa fa-edit"></i> Edit Event
          </a>
          <button class="admin-btn delete-btn" onclick="confirmDelete(<?= $event_id ?>)">
            <i class="fa fa-trash"></i> Delete Event
          </button>
        </div>
      <?php endif; ?>
        
      <div class="attendees-list">
        <h3>Attendees (<?= $registered_count ?>)</h3>
        <?php
        $stmt = $pdo->prepare("SELECT users.full_name, users.student_id 
                             FROM event_registrations 
                             JOIN users ON event_registrations.user_id = users.id
                             WHERE event_registrations.event_id = ?
                             ORDER BY users.full_name");
        $stmt->execute([$event_id]);
        $attendees = $stmt->fetchAll();
        
        if (count($attendees) > 0): ?>
          <table class="attendees-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Student Id</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attendees as $attendee): ?>
                <tr>
                  <td><?= strtoupper(htmlspecialchars($attendee['full_name'])) ?></td>
                  <td><?= strtoupper(htmlspecialchars($attendee['student_id'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p>No attendees yet.</p>
        <?php endif; ?>
      </div>
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

    document.addEventListener('click', function(event) {
      if (!event.target.closest('.sidenav') && !event.target.closest('.menu-toggle')) {
        closeNav();
      }
    });
  </script>
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
