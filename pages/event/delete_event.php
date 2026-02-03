<?php
require_once '../../config/config.php';
require_once '../../header.php';

// Authentication check - only allow admin, coordinator, and event advisors
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator', 'event_advisor'])) {
    header("Location: /index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $event_id = $_POST['id'];
    
    try {
        // Directly delete the event (no transaction needed for single operation)
        $stmt = $pdo->prepare("DELETE FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        
        // Check if any row was actually deleted
        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Event deleted successfully";
        } else {
            $_SESSION['error_message'] = "Event not found or already deleted";
        }
        
        header("Location: event_list.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        header("Location: event_list.php");
        exit();
    }
} else {
    // Invalid request
    $_SESSION['error_message'] = "Invalid request";
    header("Location: event_list.php");
    exit();
}
