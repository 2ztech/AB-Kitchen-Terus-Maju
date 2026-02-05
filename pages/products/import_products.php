<?php

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /products");
    exit();
}

$message = '';
$msgType = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['import_file'])) {
    $file = $_FILES['import_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Create Uploads Temp Directory if not exists
    $tempDir = '../../uploads/temp_import_' . time() . '/';
    
    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Upload error code: " . $file['error']);
        }

        if ($ext === 'zip') {
            // --- HANDLE ZIP ---
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) === TRUE) {
                if (!mkdir($tempDir, 0777, true)) {
                    throw new Exception("Failed to create temp directory");
                }
                
                $zip->extractTo($tempDir);
                $zip->close();
                
                // Locate CSV
                $csvPath = $tempDir . 'products.csv';
                if (!file_exists($csvPath)) {
                    // Try finding any CSV
                    $files = glob($tempDir . '*.csv');
                    if (count($files) > 0) $csvPath = $files[0];
                    else throw new Exception("No CSV file found in ZIP.");
                }
                
                processCSV($pdo, $csvPath, $tempDir);
                $message = "ZIP Import Successful!";
                $msgType = 'success';
                
            } else {
                throw new Exception("Failed to open ZIP file.");
            }
        } elseif ($ext === 'csv') {
            // --- HANDLE CSV ---
            processCSV($pdo, $file['tmp_name']);
            $message = "CSV Import Successful! (Note: Images not included in CSV import)";
            $msgType = 'success';
        } else {
            throw new Exception("Invalid file type. Upload valid ZIP or CSV.");
        }

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msgType = 'error';
    } finally {
        // Cleanup Temp Directory
        if (is_dir($tempDir)) {
            deleteDir($tempDir);
        }
    }
}

// -----------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------

function processCSV($pdo, $csvPath, $extractDir = null) {
    $handle = fopen($csvPath, 'r');
    if ($handle === false) throw new Exception("Could not read CSV file.");
    
    // Skip Header
    fgetcsv($handle); 
    
    $imported = 0;
    $targetImageDir = __DIR__ . '/../../images/products/';
    if (!is_dir($targetImageDir)) mkdir($targetImageDir, 0777, true);

    $stmtInsert = $pdo->prepare("INSERT INTO products (name, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?)");
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE name = ?");

    $pdo->beginTransaction();

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        if (count($data) < 4) continue;

        $name = trim($data[0]);
        $desc = trim($data[1]);
        $price = floatval($data[2]);
        $stock = intval($data[3]);
        $image = isset($data[4]) ? trim($data[4]) : null;

        if (empty($name)) continue;

        // SKIP DUPLICATES (Simple logic)
        $stmtCheck->execute([$name]);
        if ($stmtCheck->fetchColumn() == 0) {
            
            // Handle Image Move if ZIP extract
            if ($extractDir && $image) {
                // Check 'images/' subdir first
                $srcImg = $extractDir . 'images/' . $image;
                if (!file_exists($srcImg)) {
                    // Check root
                    $srcImg = $extractDir . $image;
                }
                
                if (file_exists($srcImg)) {
                    // Copy to Main Products folder
                    copy($srcImg, $targetImageDir . $image);
                }
            }

            $stmtInsert->execute([$name, $desc, $price, $stock, $image]);
            $imported++;
        }
    }
    
    $pdo->commit();
    fclose($handle);
}

function deleteDir($dirPath) {
    if (!is_dir($dirPath)) return;
    $files = array_diff(scandir($dirPath), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dirPath/$file")) ? deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
    }
    return rmdir($dirPath);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Products</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .import-box { background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 0 auto; text-align: center; }
        .btn-blue { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .file-input { margin: 20px 0; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
             <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
             <div class="welcome-banner">
                <h1>Import Products</h1>
             </div>
        </div>

        <div class="import-box">
            <?php if ($message): ?>
                <div style="padding:10px;margin-bottom:15px;border-radius:4px;background:<?= $msgType=='success'?'#d4edda':'#f8d7da' ?>;color:<?= $msgType=='success'?'#155724':'#721c24' ?>;">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <h2>Upload Backup (ZIP) or CSV</h2>
            
            <p style="text-align:left; font-size:0.9rem; color:#555; margin-bottom: 20px;">
                <strong>For ZIP Uploads:</strong><br>
                Ensure the ZIP contains a <code>products.csv</code> and an <code>images/</code> folder.<br>
                This allows you to restore products WITH their images.<br><br>
                <strong>For CSV Uploads:</strong><br>
                Only product text data will be imported.
            </p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="import_file" class="file-input" accept=".zip,.csv" required>
                <br>
                <button type="submit" class="btn-blue">Import Data</button>
            </form>
            <br>
            <a href="/products" style="color:#666;">Back</a>
        </div>
    </main>
    <?php include '../../footer.php'; ?>
</body>
</html>
