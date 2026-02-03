<?php
include ("../../header.php");
include ("../../sidenav.php");
require_once '../../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check user authorization
$allowed_roles = ['admin', 'coordinator', 'advisor'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: /index.php");
    exit();
}

// Validate event ID
$event_id = $_GET['id'] ?? null;
if (!$event_id || !is_numeric($event_id)) {
    header("Location: event_list.php");
    exit();
}

// Initialize variables
$event = null;
$error = null;

// Fetch event data
try {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        header("Location: event_list.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Error loading event: " . $e->getMessage();
}

// Event level definitions
$eventLevels = [
    1 => 'UMPSA',
    2 => 'District', 
    3 => 'State',
    4 => 'National',
    5 => 'International'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $required_fields = [
            'event_name' => $_POST['event_name'],
            'event_date' => $_POST['event_date'],
            'start_time' => $_POST['start_time'],
            'end_time' => $_POST['end_time'],
            'participant_slots' => $_POST['participant_slots'],
            'general_location' => $_POST['general_location'],
            'specific_venue' => $_POST['specific_venue'],
            'event_level' => $_POST['event_level'],
            'status' => $_POST['status']
        ];
        
        foreach ($required_fields as $field => $value) {
            if (empty($value)) {
                throw new Exception("The $field field is required.");
            }
        }
        
        if ($_POST['participant_slots'] < 1) {
            throw new Exception("Participant slots must be at least 1.");
        }
        
        if (strtotime($_POST['end_time']) <= strtotime($_POST['start_time'])) {
            throw new Exception("End time must be after start time.");
        }

        $stmt = $pdo->prepare("UPDATE events SET 
                             event_name = :name, 
                             event_description = :description, 
                             event_date = :date,
                             start_time = :start_time,
                             end_time = :end_time,
                             participant_slots = :slots, 
                             general_location = :general_location,
                             specific_venue = :specific_venue,
                             event_level = :level,
                             status = :status
                             WHERE event_id = :id");
        
        $stmt->execute([
            ':name' => $_POST['event_name'],
            ':description' => $_POST['event_description'],
            ':date' => $_POST['event_date'],
            ':start_time' => $_POST['start_time'],
            ':end_time' => $_POST['end_time'],
            ':slots' => $_POST['participant_slots'],
            ':general_location' => $_POST['general_location'],
            ':specific_venue' => $_POST['specific_venue'],
            ':level' => $_POST['event_level'],
            ':status' => $_POST['status'],
            ':id' => $event_id
        ]);
        
        $_SESSION['success_message'] = "Event updated successfully!";
        header("Location: event_list.php");
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Event - MyPetakom</title>
  <link rel="stylesheet" href="../../styles/admin_dashboard.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
  <style>
    /* Map container fixes */
    #map-container {
      position: relative;
      height: 300px;
      width: 100%;
      margin-bottom: 15px;
      border-radius: 4px;
      border: 1px solid #ddd;
      overflow: hidden;
      z-index: 1;
    }
    
    #map {
      height: 100%;
      width: 100%;
    }
    
    /* Time fields layout */
    .time-fields {
      display: flex;
      gap: 50px;
      margin-bottom: 15px;
    }
    
    .time-field {
      flex: 1;
    }
    
    /* Original form styles */
    .form-container {
      max-width: 800px;
      margin: 20px auto;
      padding: 30px;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .form-title {
      font-size: 24px;
      color: #333;
    }
    
    .status-badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: 12px;
      font-size: 12px;
      font-weight: bold;
      color: white;
    }
    
    .status-active { background-color: #4CAF50; }
    .status-postponed { background-color: #FFC107; color: #333; }
    .status-cancelled { background-color: #F44336; }
    
    .back-link {
      color: #2196F3;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: bold;
    }
    
    .form-input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 16px;
    }
    
    textarea.form-input {
      min-height: 100px;
      resize: vertical;
    }
    
    .submit-btn {
      background-color: #4CAF50;
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 16px;
      transition: background-color 0.3s;
    }
    
    .submit-btn:hover {
      background-color: #45a049;
    }
    
    .error-message {
      color: #f44336;
      background-color: #ffebee;
      padding: 15px;
      margin-bottom: 20px;
      border-radius: 4px;
    }
  </style>
</head>
<body>
  <main id="main">
    <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
    
    <div class="form-container">
      <div class="form-header">
        <div>
          <span>Status: </span>
          <span class="status-badge status-<?= $event['status'] ?? 'active' ?>">
            <?= ucfirst($event['status'] ?? 'active') ?>
          </span>
        </div>
        <h1 class="form-title">Edit Event</h1>
        <a href="event_list.php" class="back-link">
          <i class="fa fa-arrow-left"></i> Back to Events
        </a>
      </div>
      
      <?php if (isset($error)): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      
      <form method="POST" action="edit_event.php?id=<?php echo $event_id; ?>">
        <div class="form-group">
          <label for="event_name" class="form-label">Event Name</label>
          <input type="text" id="event_name" name="event_name" class="form-input" 
                 value="<?php echo htmlspecialchars($event['event_name'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label for="event_description" class="form-label">Event Description</label>
          <textarea id="event_description" name="event_description" class="form-input"><?php 
              echo htmlspecialchars($event['event_description'] ?? ''); 
          ?></textarea>
        </div>
        
        <div class="form-group">
          <label for="event_date" class="form-label">Event Date</label>
          <input type="date" id="event_date" name="event_date" class="form-input" 
                 value="<?php echo htmlspecialchars($event['event_date'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
          <label class="form-label">Event Time</label>
          <div class="time-fields">
            <div class="time-field">
              <label for="start_time">Start Time</label>
              <input type="time" id="start_time" name="start_time" class="form-input" 
                     value="<?php echo htmlspecialchars($event['start_time'] ?? '08:00'); ?>" required>
            </div>
            <div class="time-field">
              <label for="end_time">End Time</label>
              <input type="time" id="end_time" name="end_time" class="form-input" 
                     value="<?php echo htmlspecialchars($event['end_time'] ?? '17:00'); ?>" required>
            </div>
          </div>
        </div>
        
        <div class="form-group">
          <label for="participant_slots" class="form-label">Participant Slots</label>
          <input type="number" id="participant_slots" name="participant_slots" class="form-input" 
                 min="1" value="<?php echo htmlspecialchars($event['participant_slots'] ?? 20); ?>" required>
        </div>
        
        <div class="form-group">
          <label class="form-label">Event Location</label>
          <div id="map-container">
            <div id="map"></div>
          </div>
          <div class="form-group">
            <label for="general_location" class="form-label">General Location:</label>
            <input type="text" id="general_location" name="general_location" class="form-input" 
                   value="<?php echo htmlspecialchars($event['general_location'] ?? ''); ?>" required readonly>
          </div>
          <div class="form-group">
            <label for="specific_venue" class="form-label">Specific Venue:</label>
            <input type="text" id="specific_venue" name="specific_venue" class="form-input" 
                   value="<?php echo htmlspecialchars($event['specific_venue'] ?? ''); ?>" required>
          </div>
        </div>
        
        <div class="form-group">
          <label for="event_level" class="form-label">Event Level</label>
          <select id="event_level" name="event_level" class="form-input" required>
            <?php foreach ($eventLevels as $value => $label): ?>
              <option value="<?= $value ?>" <?= ($event['event_level'] ?? 1) == $value ? 'selected' : '' ?>>
                <?= $label ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="event_status" class="form-label">Event Status</label>
          <select id="event_status" name="status" class="form-input" required>
            <option value="active" <?= ($event['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="postponed" <?= ($event['status'] ?? 'active') === 'postponed' ? 'selected' : '' ?>>Postponed</option>
            <option value="cancelled" <?= ($event['status'] ?? 'active') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
          </select>
        </div>
        
        <button type="submit" class="submit-btn">
          <i class="fa fa-save"></i> Update Event
        </button>
      </form>
    </div>
  </main>

  <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
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

    // Initialize map with scroll prevention
    const map = L.map('map', {
      tap: false,
      scrollWheelZoom: false,
      dragging: true,
      touchZoom: false,
      doubleClickZoom: false
    }).setView([3.6426, 101.7046], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    let marker;

    // Map click handler
    map.on('click', function(e) {
      if (marker) map.removeLayer(marker);
      
      marker = L.marker(e.latlng).addTo(map);
      
      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
        .then(response => response.json())
        .then(data => {
          document.getElementById('general_location').value = data.display_name || "Selected Location";
        })
        .catch(() => {
          document.getElementById('general_location').value = "Selected Location";
        });
    });

    // Geocode existing location on load
    const initialLocation = document.getElementById('general_location').value;
    if (initialLocation) {
      fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(initialLocation)}`)
        .then(response => response.json())
        .then(data => {
          if (data.length > 0) {
            const lat = parseFloat(data[0].lat);
            const lon = parseFloat(data[0].lon);
            map.setView([lat, lon], 15);
            marker = L.marker([lat, lon]).addTo(map);
          }
        });
    }

    // Set minimum date to today
    document.getElementById('event_date').min = new Date().toISOString().split('T')[0];
    
    // Time validation
    document.querySelector('form').addEventListener('submit', function(e) {
      const startTime = document.getElementById('start_time').value;
      const endTime = document.getElementById('end_time').value;
      
      if (startTime && endTime && endTime <= startTime) {
        alert('End time must be after start time');
        e.preventDefault();
      }
    });
  </script>
      <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
