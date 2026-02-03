<?php
require_once '../../config/config.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle QR redirect flow BEFORE including other files
if (!isset($_SESSION['user_id']) && isset($_GET['event_id'])) {
    $_SESSION['qr_redirect'] = "/pages/event/event_checkin.php?event_id=" . (int)$_GET['event_id'];
    header("Location: /index.php");
    exit();
}
// Handle redirect back after login
if (isset($_SESSION['qr_redirect'])) {
    $redirect = $_SESSION['qr_redirect'];
    unset($_SESSION['qr_redirect']);
    header("Location: $redirect");
    exit();
}

require_once '../../header.php';
require_once '../../sidenav.php';

// Check if event_id is provided
if (!isset($_GET['event_id'])) {
    $_SESSION['error_message'] = "Invalid QR code. Please scan a valid event QR code.";
    header("Location: /index.php");
    exit();
}

$event_id = (int)$_GET['event_id'];

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['error_message'] = "Event not found.";
    header("Location: /index.php");
    exit();
}

// Function to get location name from coordinates
function getLocationName($lat, $lon) {
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "MyPetakom/1.0");
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['address'])) {
        $locationParts = [];
        if (isset($data['address']['road'])) $locationParts[] = $data['address']['road'];
        if (isset($data['address']['suburb'])) $locationParts[] = $data['address']['suburb'];
        if (isset($data['address']['city'])) $locationParts[] = $data['address']['city'];
        if (isset($data['address']['county'])) $locationParts[] = $data['address']['county'];
        if (isset($data['address']['state'])) $locationParts[] = $data['address']['state'];
        
        if (!empty($locationParts)) {
            return implode(', ', $locationParts);
        }
    }
    return "Unknown Location";
}

// Handle form submission
$error = '';
$success = false;
$showAttendanceForm = false;
$currentLocationName = '';
$attendanceStatus = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_credentials'])) {
        // Step 1: Verify student credentials
        $student_id = trim($_POST['student_id']);
        $password = $_POST['password'];
        
        // Verify student credentials
        $stmt = $pdo->prepare("SELECT * FROM users WHERE student_id = ? AND role = 'student'");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            $error = "Invalid student ID or password.";
        } else {
            // Check if student is registered for the event
            $stmt = $pdo->prepare("SELECT * FROM event_registrations 
                                  WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $user['id']]);
            
            if ($stmt->rowCount() === 0) {
                $error = "You are not registered for this event.";
            } else {
                $_SESSION['verified_user_id'] = $user['id'];
                $showAttendanceForm = true;
            }
        }
    } elseif (isset($_POST['submit_attendance']) && isset($_SESSION['verified_user_id'])) {
        // Step 2: Handle attendance submission
        $attendanceStatus = $_POST['attendance_status'];
        $user_id = $_SESSION['verified_user_id'];
        $currentLocationName = $_POST['location_name'] ?? '';
        
        // Validate user exists
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() === 0) {
            $error = "User account not found.";
            goto render_page;
        }

        // Validate event exists
        $stmt = $pdo->prepare("SELECT 1 FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        if ($stmt->rowCount() === 0) {
            $error = "Event no longer exists.";
            goto render_page;
        }

        // Validate registration exists
        $stmt = $pdo->prepare("SELECT 1 FROM event_registrations WHERE event_id = ? AND user_id = ?");
        $stmt->execute([$event_id, $user_id]);
        if ($stmt->rowCount() === 0) {
            $error = "You are not registered for this event.";
            goto render_page;
        }
        
        // Record attendance
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO event_attendance 
                (event_id, user_id, status, checkin_time, location) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                status = VALUES(status),
                checkin_time = VALUES(checkin_time),
                location = VALUES(location)");
            
            $stmt->execute([
                $event_id,
                $user_id,
                $attendanceStatus,
                date('Y-m-d H:i:s'),
                $currentLocationName
            ]);
            
            $pdo->commit();
            $success = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error recording attendance: " . $e->getMessage();
            error_log("Attendance Error: User $user_id, Event $event_id - " . $e->getMessage());
        }
    }
}

render_page:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance - MyPetakom</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .attendance-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .event-header {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .event-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .event-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .event-meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .event-meta-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        
        .attendance-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background-color: #3e8e41;
        }
        
        .btn-secondary {
            background-color: #2196F3;
        }
        
        .btn-secondary:hover {
            background-color: #0b7dda;
        }
        
        .error-message {
            color: #f44336;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #ffebee;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-message {
            color: #FF9800;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #FFF3E0;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .success-message {
            color: #4CAF50;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .location-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #e3f2fd;
            border-radius: 6px;
            color: #1976d2;
        }
        
        .location-name {
            font-weight: bold;
            color: #1976d2;
            margin-top: 10px;
        }
        
        .coordinates-display {
            font-family: monospace;
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
        
        .hidden {
            display: none;
        }
        
        .success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .success-modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 600px;
            width: 90%;
            text-align: center;
        }
        
        .thank-you-icon {
            font-size: 50px;
            color: #4CAF50;
            margin-bottom: 20px;
        }
        
        .attendance-details {
            margin-top: 20px;
            padding: 15px;
            background-color: #e8f5e9;
            border-radius: 6px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: bold;
            width: 120px;
        }
        
        .location-verification {
            margin-top: 15px;
            padding: 10px;
            background-color: #fff8e1;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .modal-close-btn {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        
        <!-- Success Modal (hidden by default) -->
        <div id="successModal" class="success-modal">
            <div class="success-modal-content">
                <div class="thank-you-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h2>Thank You for Your Response!</h2>
                <p>Your attendance has been recorded successfully.</p>
                
                <div class="attendance-details">
                    <div class="detail-row">
                        <div class="detail-label">Event:</div>
                        <div id="modalEventName"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Date:</div>
                        <div id="modalEventDate"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Time:</div>
                        <div id="modalEventTime"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div id="modalAttendanceStatus"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Location:</div>
                        <div id="modalLocation"></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Check-in Time:</div>
                        <div id="modalCheckinTime"></div>
                    </div>
                </div>
                
                <button class="modal-close-btn" onclick="closeSuccessModal()">
                    <i class="fas fa-home"></i> Return Home
                </button>
            </div>
        </div>
        
        <div class="attendance-container">
            <?php if ($error): ?>
                <div class="<?= strpos($error, 'Warning:') === 0 ? 'warning-message' : 'error-message' ?>">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
                <div class="event-header">
                    <h1 class="event-title"><?= htmlspecialchars($event['event_name']) ?></h1>
                    <p>Please verify your identity to record your attendance for this event.</p>
                </div>
                
                <div class="event-meta">
                    <div class="event-meta-item">
                        <span class="event-meta-label">Date:</span>
                        <?= date('F j, Y', strtotime($event['event_date'])) ?>
                    </div>
                    
                    <div class="event-meta-item">
                        <span class="event-meta-label">Time:</span>
                        <?= date('g:i A', strtotime($event['start_time'])) ?> - <?= date('g:i A', strtotime($event['end_time'])) ?>
                    </div>
                    
                    <div class="event-meta-item">
                        <span class="event-meta-label">Location:</span>
                        <?= htmlspecialchars($event['general_location']) ?>
                        <?php if (!empty($event['specific_venue'])): ?>
                            <br><small><?= htmlspecialchars($event['specific_venue']) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!$showAttendanceForm): ?>
                    <!-- Step 1: Credential Verification -->
                    <form method="post" class="attendance-form" id="credentialForm">
                        <div class="form-group">
                            <label for="student_id">Student ID</label>
                            <input type="text" id="student_id" name="student_id" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" name="verify_credentials" class="btn">
                            <i class="fas fa-user-check"></i> Verify Identity
                        </button>
                    </form>
                    
                    <div id="locationInfo" class="location-info hidden">
                        <i class="fas fa-map-marker-alt"></i>
                        <strong>Your Current Location:</strong> 
                        <div id="locationStatus">Location will appear after verification</div>
                        <div id="locationName" class="location-name"></div>
                        <input type="hidden" name="location_name" id="locationNameInput">
                        <div class="location-verification">
                            <i class="fas fa-info-circle"></i> Please ensure your location matches the event venue
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Step 2: Attendance Selection -->
                    <form method="post" class="attendance-form" id="attendanceForm">
                        <div class="form-group">
                            <label for="attendance_status">Attendance Status</label>
                            <select id="attendance_status" name="attendance_status" required>
                                <option value="">-- Select your status --</option>
                                <option value="present">Present</option>
                                <option value="late">Late</option>
                                <option value="not_present">Not Present</option>
                            </select>
                        </div>
                        
                        <div class="location-info">
                            <i class="fas fa-map-marker-alt"></i>
                            <strong>Your Current Location:</strong> 
                            <div id="confirmedLocationStatus">Detecting your location...</div>
                            <div id="confirmedLocationName" class="location-name"></div>
                            <input type="hidden" name="location_name" id="confirmedLocationNameInput">
                            <div class="location-verification">
                                <i class="fas fa-info-circle"></i> System will verify your location matches the event venue
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="submit_attendance" class="btn">
                                <i class="fas fa-check-circle"></i> Submit Attendance
                            </button>
                            <button type="button" onclick="window.location.reload()" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Start Over
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        &copy; <?= date('Y') ?> MyPetakom. All rights reserved.
    </footer>

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

        // Show success modal with attendance details
        function showSuccessModal(eventName, eventDate, eventTime, status, location, checkinTime) {
            document.getElementById('modalEventName').textContent = eventName;
            document.getElementById('modalEventDate').textContent = eventDate;
            document.getElementById('modalEventTime').textContent = eventTime;
            document.getElementById('modalAttendanceStatus').textContent = status;
            document.getElementById('modalLocation').textContent = location;
            document.getElementById('modalCheckinTime').textContent = checkinTime;
            
            document.getElementById('successModal').style.display = 'flex';
        }

        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            window.location.href = '/index.php';
        }

        // Geolocation handling
        let currentLocationName = '';
        let currentCoordinates = null;
        
        async function reverseGeocode(lat, lng) {
            try {
                const statusElement = document.getElementById('confirmedLocationStatus') || document.getElementById('locationStatus');
                if (statusElement) statusElement.textContent = "Identifying location...";
                
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
                const data = await response.json();
                
                if (data.address) {
                    // Build a more complete location string
                    let locationParts = [];
                    if (data.address.road) locationParts.push(data.address.road);
                    if (data.address.suburb) locationParts.push(data.address.suburb);
                    if (data.address.city) locationParts.push(data.address.city);
                    if (data.address.county) locationParts.push(data.address.county);
                    if (data.address.state) locationParts.push(data.address.state);
                    
                    if (locationParts.length > 0) {
                        currentLocationName = locationParts.join(', ');
                        return currentLocationName;
                    }
                    
                    if (data.address.county) return data.address.county;
                    if (data.address.state) return data.address.state;
                }
                currentLocationName = "Unknown Location";
                return currentLocationName;
            } catch (error) {
                console.error("Geocoding error:", error);
                currentLocationName = "Location detection failed";
                return currentLocationName;
            }
        }

        async function updateLocationDisplay(position) {
            currentCoordinates = position.coords;
            currentLocationName = await reverseGeocode(position.coords.latitude, position.coords.longitude);
            
            // Update location display in both forms if elements exist
            const locationNameElement = document.getElementById('locationName');
            const confirmedLocationElement = document.getElementById('confirmedLocationName');
            const locationStatusElement = document.getElementById('locationStatus');
            const confirmedStatusElement = document.getElementById('confirmedLocationStatus');
            
            if (locationNameElement) {
                locationNameElement.textContent = currentLocationName;
                document.getElementById('locationNameInput').value = currentLocationName;
            }
            
            if (confirmedLocationElement) {
                confirmedLocationElement.textContent = currentLocationName;
                document.getElementById('confirmedLocationNameInput').value = currentLocationName;
            }
            
            if (locationStatusElement) {
                locationStatusElement.textContent = "Location detected:";
            }
            
            if (confirmedStatusElement) {
                confirmedStatusElement.textContent = "Your current location:";
            }
        }

        function handleLocationError(error) {
            let message = "Location access: ";
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += "Permission denied. Please enable location services.";
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += "Position unavailable. Please check your network connection.";
                    break;
                case error.TIMEOUT:
                    message += "Request timeout. Please try again.";
                    break;
                default:
                    message += "Unknown error occurred.";
            }
            
            // Update status messages in both forms if elements exist
            const locationStatusElement = document.getElementById('locationStatus');
            const confirmedStatusElement = document.getElementById('confirmedLocationStatus');
            
            if (locationStatusElement) {
                locationStatusElement.textContent = message;
            }
            
            if (confirmedStatusElement) {
                confirmedStatusElement.textContent = message;
            }
        }

        // Start geolocation tracking
        function startGeolocation() {
            if (navigator.geolocation) {
                // Show location info container
                const locationInfo = document.getElementById('locationInfo');
                if (locationInfo) {
                    locationInfo.classList.remove('hidden');
                }
                
                // Start watching position
                navigator.geolocation.watchPosition(
                    updateLocationDisplay,
                    handleLocationError,
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            } else {
                const errorMessage = "Geolocation is not supported by your browser.";
                const locationStatusElement = document.getElementById('locationStatus');
                if (locationStatusElement) {
                    locationStatusElement.textContent = errorMessage;
                }
            }
        }

        // Form submission handlers
        document.getElementById('credentialForm')?.addEventListener('submit', function() {
            // Store location before submitting
            if (currentLocationName) {
                document.getElementById('locationNameInput').value = currentLocationName;
            }
            
            // Start geolocation after successful verification
            setTimeout(startGeolocation, 0);
        });

        document.getElementById('attendanceForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate attendance status is selected
            const statusSelect = document.getElementById('attendance_status');
            if (!statusSelect.value) {
                alert('Please select your attendance status');
                return false;
            }
            
            // Store confirmed location
            if (currentLocationName) {
                document.getElementById('confirmedLocationNameInput').value = currentLocationName;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            // Prepare form data
            const formData = new FormData(this);
            
            // Submit form via AJAX
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                // On successful submission, show the modal
                const now = new Date();
                const checkinTime = now.toLocaleString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                showSuccessModal(
                    '<?= htmlspecialchars($event["event_name"]) ?>',
                    '<?= date("F j, Y", strtotime($event["event_date"])) ?>',
                    '<?= date("g:i A", strtotime($event["start_time"])) ?> - <?= date("g:i A", strtotime($event["end_time"])) ?>',
                    statusSelect.options[statusSelect.selectedIndex].text,
                    currentLocationName,
                    checkinTime
                );
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while submitting your attendance');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Submit Attendance';
            });
            
            return false;
        });

        // Initialize geolocation if already verified
        <?php if ($showAttendanceForm): ?>
        document.addEventListener('DOMContentLoaded', function() {
            startGeolocation();
        });
        <?php endif; ?>

        // Show success modal if attendance was just recorded
        <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const checkinTime = now.toLocaleString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            showSuccessModal(
                '<?= htmlspecialchars($event["event_name"]) ?>',
                '<?= date("F j, Y", strtotime($event["event_date"])) ?>',
                '<?= date("g:i A", strtotime($event["start_time"])) ?> - <?= date("g:i A", strtotime($event["end_time"])) ?>',
                '<?= ucfirst($attendanceStatus) ?>',
                '<?= htmlspecialchars($currentLocationName) ?>',
                checkinTime
            );
        });
        <?php endif; ?>
    </script>
</body>
</html>

