<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Start session and check authorization
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle QR redirect flow BEFORE including header.php
if (!isset($_SESSION['user_id']) && isset($_GET['event_id'])) {
    $_SESSION['qr_redirect'] = "/pages/event/event_checkin.php?event_id=" . $_GET['event_id'];
    header("Location: /index.php");
    exit();
}

// Include QR code library
require_once '../../vendor/autoload.php'; // Path to your QR code library
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Fetch all events
$events = $pdo->query("SELECT event_id, event_name FROM events ORDER BY event_date DESC")->fetchAll();

// Process QR generation
$qrCodeUrl = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_qr'])) {
    $event_id = $_POST['event_id'];
    
    // Generate a unique URL for this event's check-in
    $checkin_url = "https://" . $_SERVER['HTTP_HOST'] . "/index.php?redirect=" . 
               urlencode("/pages/event/event_checkin.php?event_id=" . $event_id);
    
    // Create QR code
    $qrCode = new QrCode($checkin_url);
    $qrCode->setSize(300);
    $qrCode->setMargin(10);
    
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    
    // Save QR code to file
    $qr_filename = 'event_qr_' . $event_id . '_' . time() . '.png';
    $qr_path = '../../uploads/qrcodes/' . $qr_filename;
    $result->saveToFile($qr_path);
    
    $qrCodeUrl = '/uploads/qrcodes/' . $qr_filename;
    
    // Store QR code info in database if needed
    $stmt = $pdo->prepare("UPDATE events SET qr_code_path = ? WHERE event_id = ?");
    $stmt->execute([$qrCodeUrl, $event_id]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event QR Code Generator</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../../styles/event_qr.css">
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1>Event QR Code Generator</h1>

        <div class="qr-generator-container">
            <form method="post" action="event_qr.php">
                <div class="form-group">
                    <label for="event_id">Select Event:</label>
                    <select name="event_id" id="event_id" required>
                        <option value="">-- Select an Event --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?= $event['event_id'] ?>" 
                                <?= (isset($_POST['event_id']) && $_POST['event_id'] == $event['event_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['event_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="generate_qr" class="generate-btn">Generate QR Code</button>
                </div>
            </form>

            <?php if ($qrCodeUrl): ?>
                <div class="qr-result">
                    <h2>Event Check-In QR Code</h2>
                    <div class="qr-code-container">
                        <img src="<?= $qrCodeUrl ?>" alt="Event QR Code" class="qr-image">
                        <div class="qr-actions">
                            <a href="<?= $qrCodeUrl ?>" download class="download-btn">Download QR Code</a>
                            <button onclick="window.print()" class="print-btn">Print QR Code</button>
                        </div>
                    </div>
                    <p class="qr-instructions">
                        This QR code can be scanned by participants to check in to the event. 
                        Display it at the event entrance or include it in event communications.
                    </p>
                </div>
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

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.sidenav') && !event.target.closest('.menu-toggle')) {
                closeNav();
            }
        });
    </script>
        <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
