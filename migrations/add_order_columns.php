<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "Adding columns to orders table...\n";
    
    // Add columns one by one (SQLite doesn't support adding multiple in one statement easily)
    $cols = [
        "ALTER TABLE orders ADD COLUMN customer_email TEXT",
        "ALTER TABLE orders ADD COLUMN customer_phone TEXT",
        "ALTER TABLE orders ADD COLUMN delivery_method TEXT DEFAULT 'pickup'", // 'pickup' or 'delivery'
        "ALTER TABLE orders ADD COLUMN shipping_address TEXT",
        "ALTER TABLE orders ADD COLUMN payment_method TEXT DEFAULT 'manual'"
    ];

    foreach ($cols as $sql) {
        try {
            $pdo->exec($sql);
            echo "Executed: $sql\n";
        } catch (PDOException $e) {
            // Ignore if column likely exists
            echo "Skipped (maybe exists): " . $e->getMessage() . "\n";
        }
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
