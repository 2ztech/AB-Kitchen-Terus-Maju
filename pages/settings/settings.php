<?php
/**
 * Kuih Raya - Store Settings V2
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
        'bank_account' => trim($_POST['bank_account']), // Numeric enforced by JS, but trim ok
        'bank_holder' => trim($_POST['bank_holder']),
        
        // Whatsapp
        'whatsapp_number' => trim($_POST['whatsapp_number']),
        
        // Promotion
        'announcement_text' => trim($_POST['announcement_text'])
    ];
    
    try {
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

// Parse Existing Time for Inputs (Format: 10:00 AM - 08:00 PM)
$existing_start = '';
$existing_end = '';
$raw_hours = $current_settings['operation_hours'] ?? '';
if (preg_match('/(\d{1,2}:\d{2}\s?[AP]M)\s*-\s*(\d{1,2}:\d{2}\s?[AP]M)/i', $raw_hours, $matches)) {
    $existing_start = date("H:i", strtotime($matches[1]));
    $existing_end = date("H:i", strtotime($matches[2]));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Settings</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../../styles/settings.css">
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner"><h1>Store Settings</h1></div>
        </div>

        <?php if ($success): ?><div style="background:#d4edda;color:#155724;padding:15px;margin-bottom:20px;border-radius:6px;"><?= $success ?></div><?php endif; ?>
        <?php if ($error): ?><div style="background:#f8d7da;color:#721c24;padding:15px;margin-bottom:20px;border-radius:6px;"><?= $error ?></div><?php endif; ?>

        <form method="POST" id="settingsForm" enctype="multipart/form-data" class="settings-layout">
            <div class="settings-sidebar">
                <button type="button" class="tab-btn active" onclick="openTab('general')"><i class='bx bxs-store'></i> General</button>
                <button type="button" class="tab-btn" onclick="openTab('payment')"><i class='bx bxs-bank'></i> Payment</button>
                <button type="button" class="tab-btn" onclick="openTab('whatsapp')"><i class='bx bxl-whatsapp'></i> WhatsApp</button>
                <button type="button" class="tab-btn" onclick="openTab('promo')"><i class='bx bxs-megaphone'></i> Promotion</button>
                <button type="button" class="tab-btn" onclick="openTab('categories')"><i class='bx bxs-category'></i> Categories</button>
                <button type="button" class="tab-btn" onclick="openTab('customization')"><i class='bx bxs-palette'></i> Customization</button>
            </div>

            <div class="settings-content">
                
                <!-- General Tab -->
                <div id="general" class="tab-pane active">
                    <h2 class="section-header">General Settings</h2>
                    
                    <div class="form-group">
                        <label>Store Name</label>
                        <input type="text" name="store_name" class="form-control" placeholder="AcikBulat Digital Store" value="<?= htmlspecialchars($current_settings['store_name'] ?? 'AcikBulat Digital Store') ?>">
                    </div>

                    <div class="form-group">
                        <label>Store Status</label>
                        <div style="display:flex;align-items:center;gap:15px;">
                            <label class="toggle-switch">
                                <input type="checkbox" name="store_status" value="open" <?= ($current_settings['store_status'] ?? 'open') === 'open' ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <span style="font-size:0.9rem;color:#666;">Turn <strong>OFF</strong> to close the store (Customers will see a "Closed" page).</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Operation Hours</label>
                        <div class="time-group">
                            <input type="time" name="start_time" class="form-control" value="<?= $existing_start ?>">
                            <span>to</span>
                            <input type="time" name="end_time" class="form-control" value="<?= $existing_end ?>">
                        </div>
                        <small style="color:#666;">Or manually type if complex:</small>
                        <input type="text" name="operation_hours_manual" class="form-control" placeholder="Optional manual text override" value="<?= htmlspecialchars($current_settings['operation_hours'] ?? '') ?>" style="margin-top:5px;">
                    </div>

                    <div class="form-group">
                        <label>Store Address</label>
                        <textarea name="store_address" class="form-control" rows="4"><?= htmlspecialchars($current_settings['store_address'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Payment Tab -->
                <div id="payment" class="tab-pane">
                    <h2 class="section-header">Payment Settings</h2>
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($current_settings['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="bank_account" class="form-control" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" value="<?= htmlspecialchars($current_settings['bank_account'] ?? '') ?>">
                        <small style="color:#666;">Numbers only</small>
                    </div>
                    <div class="form-group">
                        <label>Account Holder Name</label>
                        <input type="text" name="bank_holder" class="form-control" value="<?= htmlspecialchars($current_settings['bank_holder'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>DuitNow QR Image</label>
                        <input type="hidden" name="cropped_qr" id="cropped_qr">
                        <input type="file" id="qrInput" accept="image/*" class="form-control">
                        <div id="previewArea">
                            <?php if (!empty($current_settings['duitnow_qr'])): ?>
                                <img src="../../images/settings/<?= htmlspecialchars($current_settings['duitnow_qr']) ?>" class="qr-preview">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Whatsapp Tab -->
                <div id="whatsapp" class="tab-pane">
                    <h2 class="section-header">WhatsApp Integration</h2>
                    <div class="form-group">
                        <label>WhatsApp Number</label>
                        <input type="text" name="whatsapp_number" class="form-control" inputmode="numeric" oninput="this.value=this.value.replace(/[^0-9]/g,'')" placeholder="60123456789" value="<?= htmlspecialchars($current_settings['whatsapp_number'] ?? '') ?>">
                        <small style="color:#666;">Numbers only. Include country code (e.g. 60...).</small>
                    </div>
                </div>

                <div id="promo" class="tab-pane">
                    <h2 class="section-header">Promotion</h2>
                    <div class="form-group">
                        <label>Announcement Banner</label>
                        <textarea name="announcement_text" class="form-control" rows="3" placeholder="e.g. Special Ramadhan Sale!"><?= htmlspecialchars($current_settings['announcement_text'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Categories Tab -->
                <div id="categories" class="tab-pane">
                    <h2 class="section-header">Product Categories</h2>
                    <div style="display:flex;gap:10px;margin-bottom:20px;">
                        <input type="text" id="newCategory" class="form-control" placeholder="New Category Name (e.g. Cookies)" style="flex:1;">
                        <button type="button" class="btn-save" style="width:auto;" onclick="addCategory()">Add</button>
                    </div>
                    
                    <div id="categoryList" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(200px, 1fr));gap:10px;">
                        <!-- JS Loaded -->
                        <p style="color:#666;">Loading categories...</p>
                    </div>
                </div>

                <!-- Customization Tab -->
                <div id="customization" class="tab-pane">
                    <h2 class="section-header">Store Customization</h2>
                    <div class="form-group">
                        <label>Store Logo</label>
                        <input type="file" name="store_logo" class="form-control" accept="image/*">
                        <small style="color:#666;">Recommended height: 60px. Valid details: PNG, JPG, SVG.</small>
                        <?php if (!empty($current_settings['store_logo'])): ?>
                            <div style="margin-top:10px;"><img src="../../images/settings/<?= htmlspecialchars($current_settings['store_logo']) ?>" style="height:60px;border:1px solid #ddd;padding:5px;"></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label>Favicon (Browser Tab Icon)</label>
                        <input type="file" name="store_favicon" class="form-control" accept=".ico,.png,.svg">
                        <small style="color:#666;">Small icon (16x16 or 32x32). ICO or PNG.</small>
                         <?php if (!empty($current_settings['store_favicon'])): ?>
                            <div style="margin-top:10px;"><img src="../../images/settings/<?= htmlspecialchars($current_settings['store_favicon']) ?>" style="width:32px;height:32px;border:1px solid #ddd;padding:2px;"></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="clear:both;"></div>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </main>

    <!-- Crop Modal -->
    <div id="cropModal" class="modal">
        <div class="modal-content">
            <h3>Crop QR Code</h3>
            <div class="img-container"><img id="imageToCrop" src=""></div>
            <div style="text-align:right;margin-top:20px;">
                <button type="button" onclick="closeModal()" style="padding:10px;margin-right:10px;">Cancel</button>
                <button type="button" id="btnCrop" style="padding:10px 20px;background:#28a745;color:white;border:none;border-radius:4px;">Crop & Save</button>
            </div>
        </div>
    </div>
    
    <?php include '../../footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
            
            if (tabId === 'categories') {
                loadCategories();
            }
        }

        // Category Manager
        function loadCategories() {
            fetch('ajax_categories.php', { method: 'POST', body: new URLSearchParams({action: 'list'}) })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    const list = document.getElementById('categoryList');
                    list.innerHTML = '';
                    if (res.data.length === 0) list.innerHTML = '<p style="color:#888;">No categories yet.</p>';
                    
                    res.data.forEach(cat => {
                        const div = document.createElement('div');
                        div.style.cssText = 'background:white;padding:10px;border:1px solid #ddd;border-radius:6px;display:flex;justify-content:space-between;align-items:center;';
                        div.innerHTML = `<strong>${cat.name}</strong> <button type="button" onclick="deleteCategory(${cat.id})" style="background:#dc3545;color:white;border:none;padding:5px 10px;border-radius:4px;cursor:pointer;"><i class='bx bx-trash'></i></button>`;
                        list.appendChild(div);
                    });
                }
            });
        }

        function addCategory() {
            const input = document.getElementById('newCategory');
            const name = input.value.trim();
            if (!name) return;

            fetch('ajax_categories.php', { 
                method: 'POST', 
                body: new URLSearchParams({action: 'add', name: name}) 
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    input.value = '';
                    loadCategories();
                } else {
                    alert(res.message);
                }
            });
        }

        function deleteCategory(id) {
            if(!confirm('Delete this category?')) return;
            fetch('ajax_categories.php', { 
                method: 'POST', 
                body: new URLSearchParams({action: 'delete', id: id}) 
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    loadCategories();
                } else {
                    alert(res.message);
                }
            });
        }

        const qrInput = document.getElementById('qrInput');
        const modal = document.getElementById('cropModal');
        const img = document.getElementById('imageToCrop');
        let cropper;

        qrInput.addEventListener('change', e => {
            if(e.target.files[0]) {
                const reader = new FileReader();
                reader.onload = ev => {
                    img.src = ev.target.result;
                    modal.style.display = 'block';
                    if(cropper) cropper.destroy();
                    cropper = new Cropper(img, { viewMode: 1 });
                };
                reader.readAsDataURL(e.target.files[0]);
            }
        });

        document.getElementById('btnCrop').onclick = () => {
            if(!cropper) return;
            const canvas = cropper.getCroppedCanvas({width:500, height:500});
            document.getElementById('cropped_qr').value = canvas.toDataURL('image/png');
            document.getElementById('previewArea').innerHTML = '<p style="color:green">Ready to save!</p><img src="'+canvas.toDataURL()+'" class="qr-preview">';
            closeModal();
        };

        function closeModal() {
            modal.style.display = 'none';
            if(cropper) cropper.destroy();
            if (!document.getElementById('cropped_qr').value) qrInput.value = '';
        }
    </script>
</body>
</html>
