<?php
ob_start(); // Buffer output to catch warnings/notices

try {
    require_once '../../config/db.php';
    require_once '../../handlers/email_handler.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['email'])) {
        throw new Exception('Invalid Request');
    }

    $email = trim($_POST['email']);
    $handler = new EmailHandler($pdo);
    $result = $handler->sendTestEmail($email);
    
    // Clear buffer before sending JSON
    ob_clean();
    header('Content-Type: application/json');

    if ($result === true) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $result]);
    }

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'System Error: ' . $e->getMessage()]);
} catch (Error $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Fatal Error: ' . $e->getMessage()]);
}
ob_end_flush();
