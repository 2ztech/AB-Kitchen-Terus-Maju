<?php
require_once '../../config/config.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_message'] = "You must be logged in as a student to register for events.";
    header("Location: /index.php");
    exit;
}

if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No event specified.";
    header("Location: event_list.php");
    exit;
}

$event_id = (int)$_GET['id'];
$user_id = (int)$_SESSION['user_id'];

try {
    // Check if event exists
    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
    $stmt->execute([$event_id]);
    
    if ($stmt->rowCount() === 0) {
        $_SESSION['error_message'] = "Event not found.";
        header("Location: event_list.php");
        exit;
    }

    // Check if already registered
    $stmt = $pdo->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$event_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['error_message'] = "You're already registered for this event.";
    } else {
        // Register the user
        $stmt = $pdo->prepare("INSERT INTO event_registrations (event_id, user_id) VALUES (?, ?)");
        $stmt->execute([$event_id, $user_id]);
        // No success message - just redirect
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
}

header("Location: event_detail.php?id=" . $event_id);
exit;
?>
