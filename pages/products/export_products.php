<?php
/**
 * Kuih Raya - Export Products (ZIP)
 * Location: pages/products/export_products.php
 */

require_once '../../config/config.php';

// Authentication: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

$zip = new ZipArchive();
$zipFilename = tempnam(sys_get_temp_dir(), 'products_export_') . '.zip';

if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
    die("Error creating ZIP file");
}

// 1. Prepare CSV Data
$csvData = [];
// Header
$csvData[] = ['Name', 'Description', 'Price', 'Stock', 'Image Filename'];

$stmt = $pdo->query("SELECT name, description, price, stock, image_url FROM products ORDER BY id ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Directory where images are stored
$imageDir = __DIR__ . '/../../images/products/';

foreach ($products as $row) {
    // Add to CSV array
    $csvData[] = [
        $row['name'],
        $row['description'],
        $row['price'],
        $row['stock'],
        $row['image_url']
    ];

    // 2. Add Image to ZIP
    if (!empty($row['image_url'])) {
        $imagePath = $imageDir . $row['image_url'];
        if (file_exists($imagePath)) {
            // Add to 'images/' folder inside ZIP
            $zip->addFile($imagePath, 'images/' . $row['image_url']);
        }
    }
}

// 3. Write CSV to memory and add to ZIP
$fp = fopen('php://temp', 'r+');
foreach ($csvData as $line) {
    fputcsv($fp, $line);
}
rewind($fp);
$csvContent = stream_get_contents($fp);
fclose($fp);

$zip->addFromString('products.csv', $csvContent);

$zip->close();

// 4. Force Download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.zip"');
header('Content-Length: ' . filesize($zipFilename));

readfile($zipFilename);

// 5. Cleanup
unlink($zipFilename);
exit();
