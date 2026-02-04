<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "Adding receipt_image column to orders table...\n";
    
    $sql = "ALTER TABLE orders ADD COLUMN receipt_image TEXT";

    try {
        $pdo->exec($sql);
        echo "Executed: $sql\n";
    } catch (PDOException $e) {
        // Ignore if column likely exists
        echo "Skipped (maybe exists): " . $e->getMessage() . "\n";
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
