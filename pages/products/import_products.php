<?php
/**
 * Kuih Raya - Import Products
 * Location: pages/products/import_products.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: product_list.php");
    exit();
}

$message = '';
$msgType = ''; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "Upload error code: " . $file['error'];
        $msgType = 'error';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $message = "Please upload a valid CSV file.";
        $msgType = 'error';
    } else {
        // Process CSV
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle === false) {
            $message = "Could not open file.";
            $msgType = 'error';
        } else {
            // Skip Header
            fgetcsv($handle); 
            
            $imported = 0;
            $skipped = 0;
            
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO products (name, description, price, stock, image_url) VALUES (?, ?, ?, ?, ?)");
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE name = ?");

                $pdo->beginTransaction();

                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    // Check column count (expect at least 4: Name, Desc, Price, Stock)
                    if (count($data) < 4) continue;

                    $name = trim($data[0]);
                    $desc = trim($data[1]);
                    $price = floatval($data[2]);
                    $stock = intval($data[3]);
                    $image = isset($data[4]) ? trim($data[4]) : null;

                    if (empty($name)) continue;

                    // Check duplicate
                    $stmtCheck->execute([$name]);
                    if ($stmtCheck->fetchColumn() > 0) {
                        $skipped++;
                    } else {
                        $stmtInsert->execute([$name, $desc, $price, $stock, $image]);
                        $imported++;
                    }
                }
                
                $pdo->commit();
                $message = "Import Successful! Imported: $imported, Skipped (Duplicates): $skipped.";
                $msgType = 'success';

            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Error during import: " . $e->getMessage();
                $msgType = 'error';
            }
            
            fclose($handle);
        }
    }
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

            <p>Upload a CSV file with columns:<br><strong>Name, Description, Price, Stock, Image Filename</strong></p>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="csv_file" class="file-input" accept=".csv" required>
                <br>
                <button type="submit" class="btn-blue">Import List</button>
            </form>
            <br>
            <a href="product_list.php" style="color:#666;">Back</a>
        </div>
    </main>
    <?php include '../../footer.php'; ?>
</body>
</html>
