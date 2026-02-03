<?php
require_once '../../config/config.php';

// Start session and check authorization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$allowed_roles = ['event_advisor', 'coordinator', 'admin'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    $_SESSION['error_message'] = "You don't have permission to create events.";
    header("Location: event_list.php");
    exit;
}

$eventLevels = [
    1 => 'UMPSA',
    2 => 'District',
    3 => 'State',
    4 => 'National',
    5 => 'International'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $event_name = filter_input(INPUT_POST, 'event_name', FILTER_SANITIZE_STRING);
        $event_description = filter_input(INPUT_POST, 'event_description', FILTER_SANITIZE_STRING);
        $event_date = filter_input(INPUT_POST, 'event_date', FILTER_SANITIZE_STRING);
        $start_time = filter_input(INPUT_POST, 'start_time', FILTER_SANITIZE_STRING);
        $end_time = filter_input(INPUT_POST, 'end_time', FILTER_SANITIZE_STRING);
        $participant_slots = filter_input(INPUT_POST, 'participant_slots', FILTER_VALIDATE_INT);
        $general_location = filter_input(INPUT_POST, 'general_location', FILTER_SANITIZE_STRING);
        $specific_venue = filter_input(INPUT_POST, 'specific_venue', FILTER_SANITIZE_STRING);
        $event_level = filter_input(INPUT_POST, 'event_level', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5]
        ]);

        // Validate required fields
        $required_fields = [
            'event_name' => $event_name,
            'event_date' => $event_date,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'participant_slots' => $participant_slots,
            'general_location' => $general_location,
            'specific_venue' => $specific_venue,
            'event_level' => $event_level
        ];
        
        foreach ($required_fields as $field => $value) {
            if (empty($value)) {
                throw new Exception("The $field field is required.");
            }
        }

        // Validate participant slots
        if ($participant_slots < 1) {
            throw new Exception("Participant slots must be at least 1.");
        }

        // Validate time
        if (strtotime($end_time) <= strtotime($start_time)) {
            throw new Exception("End time must be after start time.");
        }

        $stmt = $pdo->prepare("INSERT INTO events 
            (event_name, event_description, event_date, start_time, end_time, participant_slots, 
             general_location, specific_venue, event_level, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
        
        $stmt->execute([
            $event_name, 
            $event_description, 
            $event_date,
            $start_time,
            $end_time,
            $participant_slots,
            $general_location,
            $specific_venue,
            $event_level
        ]);

        $_SESSION['success_message'] = "Event created successfully!";
        header("Location: event_list.php");
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

require_once '../../header.php';
require_once '../../sidenav.php';
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Event</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../../styles/event_form.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        
        /* Form container styling */
        .event-form-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* Form group styling */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
            font-size: 16px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group textarea:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
            outline: none;
        }
        
        /* Time fields styling */
        .time-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 25px;
        }
        
        .time-field {
            display: flex;
            flex-direction: column;
        }
        
        .time-field label {
            margin-bottom: 8px;
            font-weight: bold;
        }
        
        .time-field input {
            width: 100%;
            padding: 12px;
            font-size: 16px;
        }
        
        /* Map styling */
        #map-container {
            height: 350px;
            width: 100%;
            margin-bottom: 20px;
            border-radius: 6px;
            border: 1px solid #ddd;
            overflow: hidden;
        }
        
        #map {
            height: 100%;
            width: 100%;
        }
        
        /* Location fields */   
        .location-fields {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 15px;  
        }
     
        
        .location-field {
            display: flex;
            flex-direction: column;
        }
        
        #general_location {
            background-color: #f5f5f5;
        }
        
        /* Button styling */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        .submit-btn:hover {
            background-color: #3e8e41;
        }
        
        .cancel-btn {
            background-color: #f44336;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .cancel-btn:hover {
            background-color: #d32f2f;
        }
        
        /* Error message styling */
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 6px;
            border: 1px solid transparent;
        }
    </style>
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1>Create New Event</h1>

        <div class="event-form-container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <form method="POST" action="create_event.php">
                <div class="form-group">
                    <label for="event_name">Event Name *</label>
                    <input type="text" id="event_name" name="event_name" required>
                </div>

                <div class="form-group">
                    <label for="event_description">Description</label>
                    <textarea id="event_description" name="event_description" rows="5"></textarea>
                </div>

                <div class="form-group">
                    <label for="event_date">Date *</label>
                    <input type="date" id="event_date" name="event_date" required>
                </div>

                <div class="form-group">
                    <label>Event Time *</label>
                    <div class="time-section">
                        <div class="time-field">
                            <label for="start_time">Start Time</label>
                            <input type="time" id="start_time" name="start_time" required>
                        </div>
                        <div class="time-field">
                            <label for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="participant_slots">Participant Slots *</label>
                    <input type="number" id="participant_slots" name="participant_slots" min="1" required>
                </div>

                <div class="form-group">
                    <label>Event Location *</label>
                    <div id="map-container">
                        <div id="map"></div>
                    </div>
                    <div class="location-fields">
                        <div class="location-field">
                            <label for="general_location">Location (from map):</label>
                            <input type="text" id="general_location" name="general_location" readonly required>
                        </div>
                        <div class="location-field">
                            <label for="specific_venue">Specific Venue *</label>
                            <input type="text" id="specific_venue" name="specific_venue" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="event_level">Event Level *</label>
                    <select id="event_level" name="event_level" required>
                        <?php foreach ($eventLevels as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">Create Event</button>
                    <a href="event_list.php" class="cancel-btn">Cancel</a>
                </div>
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

        // Set minimum date to today
        document.getElementById('event_date').min = new Date().toISOString().split('T')[0];

        // Initialize map centered on UMP
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

        // Add click event to place marker and get location
        map.on('click', function(e) {
            if (marker) map.removeLayer(marker);
            
            marker = L.marker(e.latlng).addTo(map);
            
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${e.latlng.lat}&lon=${e.latlng.lng}`)
                .then(response => response.json())
                .then(data => {
                    const location = data.display_name || "Selected Location";
                    document.getElementById('general_location').value = location;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('general_location').value = "Selected Location";
                });
        });

        // Form validation for time fields
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
