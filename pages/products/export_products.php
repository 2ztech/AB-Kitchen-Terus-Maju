<?php
/**
 * Kuih Raya - Export Products
 * Location: pages/products/export_products.php
 */

require_once '../../config/config.php';

// Authentication: Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied");
}

// Set Headers for CSV Download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="products_export_' . date('Y-m-d') . '.csv"');

// Open PHP output stream
$output = fopen('php://output', 'w');

// Add CSV Header Row
fputcsv($output, ['Name', 'Description', 'Price', 'Stock', 'Image Filename']);

// Fetch Products from DB
try {
    $stmt = $pdo->query("SELECT name, description, price, stock, image_url FROM products ORDER BY id ASC");
    $start = microtime(true);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Handle null values
        $row['image_url'] = $row['image_url'] ?? '';
        fputcsv($output, $row);
    }
} catch (PDOException $e) {
    // Write error to CSV if fails (so user sees something)
    fputcsv($output, ['ERROR: ' . $e->getMessage()]);
}

fclose($output);
exit();
