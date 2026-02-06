<?php
require_once '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Method']);
    exit();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Handle Time Logic
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $operation_hours = '';
    
    if ($start_time && $end_time) {
        $start_fmt = date("h:i A", strtotime($start_time));
        $end_fmt = date("h:i A", strtotime($end_time));
        $operation_hours = "$start_fmt - $end_fmt";
    } else {
        $operation_hours = trim($_POST['operation_hours_manual'] ?? '');
    }

    $settings = [
        // General
        'store_name' => trim($_POST['store_name']),
        'store_address' => trim($_POST['store_address']),
        'operation_hours' => $operation_hours,
        'store_status' => isset($_POST['store_status']) ? 'open' : 'closed',
        
        // Payment
        'bank_name' => trim($_POST['bank_name']),
        'bank_account' => trim($_POST['bank_account']),
        'bank_holder' => trim($_POST['bank_holder']),
        
        // Whatsapp
        'whatsapp_number' => trim($_POST['whatsapp_number']),
        
        // Promotion
        'announcement_text' => trim($_POST['announcement_text']),

        // SMTP
        'smtp_host' => trim($_POST['smtp_host'] ?? ''),
        'smtp_port' => trim($_POST['smtp_port'] ?? ''),
        'smtp_user' => trim($_POST['smtp_user'] ?? ''),
        'smtp_pass' => trim($_POST['smtp_pass'] ?? ''),
        'smtp_enc'  => trim($_POST['smtp_enc'] ?? ''),
        'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
        'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? '')
    ];
    
    $pdo->beginTransaction();
    
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
    $stmtUpdate = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");

    foreach ($settings as $key => $val) {
        $stmtCheck->execute([$key]);
        if ($stmtCheck->fetchColumn() > 0) {
            $stmtUpdate->execute([$val, $key]);
        } else {
            $stmtInsert->execute([$key, $val]);
        }
    }
    
    // Handle Cropped QR Upload
    if (!empty($_POST['cropped_qr'])) {
        $data = $_POST['cropped_qr'];
        if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
            $data = substr($data, strpos($data, ',') + 1);
            $type = strtolower($type[1]);
            $data = base64_decode($data);
            
            $dir = '../../images/settings/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = 'duitnow_qr.' . $type;
            file_put_contents($dir . $filename, $data);
            
            $stmtUpdate->execute([$filename, 'duitnow_qr']);
        }
    }
    
    // Handle Direct File Uploads (Logo, Favicon)
    foreach (['store_logo', 'store_favicon'] as $fileKey) {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fileKey];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'ico', 'svg', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $dir = '../../images/settings/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $filename = $fileKey . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $dir . $filename);
                
                // Upsert DB
                $stmtCheck->execute([$fileKey]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $stmtUpdate->execute([$filename, $fileKey]);
                } else {
                    $stmtInsert->execute([$fileKey, $filename]);
                }
            }
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
