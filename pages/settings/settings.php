<?php
/**
 * Kuih Raya - Store Settings
 * Location: pages/settings/settings.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

$error = '';
$success = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $store_address = trim($_POST['store_address']);
    $bank_name = trim($_POST['bank_name']);
    $bank_account = trim($_POST['bank_account']);
    $bank_holder = trim($_POST['bank_holder']);
    
    try {
        $pdo->beginTransaction();
        
        $settings = [
            'store_address' => $store_address,
            'bank_name' => $bank_name,
            'bank_account' => $bank_account,
            'bank_holder' => $bank_holder
        ];
        
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settings as $key => $val) {
            $stmt->execute([$val, $key]);
        }
        
        // Handle Cropped QR Upload (Base64)
        if (!empty($_POST['cropped_qr'])) {
            $data = $_POST['cropped_qr'];
            
            // Extract base64 part (data:image/png;base64,.....)
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                $data = substr($data, strpos($data, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, etc.
                
                if (!in_array($type, [ 'jpg', 'jpeg', 'png' ])) {
                    throw new Exception("Invalid image type");
                }
                
                $data = base64_decode($data);
                if ($data === false) {
                     throw new Exception("Base64 decode failed");
                }

                $dir = '../../images/settings/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                $filename = 'duitnow_qr.' . $type;
                if (file_put_contents($dir . $filename, $data)) {
                     $stmt->execute([$filename, 'duitnow_qr']);
                } else {
                     throw new Exception("Failed to save image");
                }
            }
        }
        
        $pdo->commit();
        $success = "Settings updated successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Current Settings
$current_settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Settings</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <!-- Cropper.js CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <style>
        .form-container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .btn-save { background: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; font-size: 1rem; }
        .qr-preview { max-width: 200px; margin-top: 10px; border: 1px solid #ddd; padding: 5px; }
        
        /* Cropper Modal */
        .modal { display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.8); }
        .modal-content { background-color:#fff; margin:5% auto; padding:20px; border:1px solid #888; width:80%; max-width:600px; border-radius:8px; }
        .img-container { max-height:400px; min-height:200px; margin-bottom:20px; }
        .img-container img { max-width: 100%; }
        .modal-actions { text-align:right; }
        .btn-crop { background:#28a745; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; font-weight:bold; }
        .btn-cancel { background:#dc3545; color:white; padding:10px 20px; border:none; border-radius:4px; cursor:pointer; margin-right:10px; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1>Store Settings</h1>
            </div>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div style="background:#f8d7da;color:#721c24;padding:10px;margin-bottom:15px;"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST" id="settingsForm">
                <div class="form-group">
                    <label>Store Location / Pickup Address</label>
                    <textarea name="store_address" class="form-control" rows="4" required><?= htmlspecialchars($current_settings['store_address'] ?? '') ?></textarea>
                </div>

                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($current_settings['bank_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Bank Account Number</label>
                    <input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($current_settings['bank_account'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_holder" class="form-control" value="<?= htmlspecialchars($current_settings['bank_holder'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>DuitNow QR Image</label>
                    <!-- Hidden Input to store Base64 Cropped Image -->
                    <input type="hidden" name="cropped_qr" id="cropped_qr">
                    
                    <input type="file" id="qrInput" accept="image/*" class="form-control">
                    
                    <div id="previewArea">
                        <?php if (!empty($current_settings['duitnow_qr'])): ?>
                            <div style="margin-top:10px;">
                                <p style="font-size:0.9rem;color:#666;">Current QR:</p>
                                <img src="../../images/settings/<?= htmlspecialchars($current_settings['duitnow_qr']) ?>" class="qr-preview" id="currentQr">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn-save">Save Settings</button>
            </form>
        </div>
    </main>

    <!-- Modal for Cropping -->
    <div id="cropModal" class="modal">
        <div class="modal-content">
            <h3>Crop DuitNow QR</h3>
            <p>Please select only the QR Code area.</p>
            <div class="img-container">
                <img id="imageToCrop" src="">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="button" class="btn-crop" id="btnCrop">Crop & Save Selection</button>
            </div>
        </div>
    </div>

    <?php include '../../footer.php'; ?>
    
    <!-- Cropper JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        const qrInput = document.getElementById('qrInput');
        const modal = document.getElementById('cropModal');
        const imageToCrop = document.getElementById('imageToCrop');
        const btnCrop = document.getElementById('btnCrop');
        const croppedQrInput = document.getElementById('cropped_qr');
        let cropper;

        qrInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    imageToCrop.src = e.target.result;
                    modal.style.display = 'block';
                    
                    // Init Cropper
                    if (cropper) cropper.destroy();
                    cropper = new Cropper(imageToCrop, {
                        viewMode: 1,
                        background: false
                    });
                };
                reader.readAsDataURL(file);
            }
        });

        btnCrop.addEventListener('click', function() {
            if (cropper) {
                // Get Cropped Canvas (max 500x500 for optimization)
                const canvas = cropper.getCroppedCanvas({
                    width: 500,
                    height: 500
                });
                
                // Set hidden input value
                croppedQrInput.value = canvas.toDataURL('image/png');
                
                // Show simple preview to user that it's "ready"
                const previewArea = document.getElementById('previewArea');
                previewArea.innerHTML = '<p style="color:green;font-weight:bold;margin-top:10px;">New QR Ready to Save!</p><img src="' + canvas.toDataURL() + '" class="qr-preview">';
                
                closeModal();
            }
        });

        function closeModal() {
            modal.style.display = 'none';
            if (cropper) cropper.destroy();
            // Reset input if cancelled so change event fires again if same file selected
            if (!croppedQrInput.value) qrInput.value = '';
        }
        
        // Form validate
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            // Optional: prevent submit if no image change? No, allow text updates.
        });
    </script>
</body>
</html>
