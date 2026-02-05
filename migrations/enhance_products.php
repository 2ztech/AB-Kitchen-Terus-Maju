<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    // 1. Create Categories Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )");
    echo "Table 'categories' created or already exists.<br>";

    // Helper function to check if column exists (SQLite compatible)
    function columnExists($pdo, $table, $column) {
        $stmt = $pdo->prepare("PRAGMA table_info($table)");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            if ($col['name'] === $column) {
                return true;
            }
        }
        return false;
    }

    // 2. Add Columns to Products Table if they don't exist
    
    // Check for stock_price
    if (!columnExists($pdo, 'products', 'stock_price')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN stock_price REAL DEFAULT 0.00");
        echo "Column 'stock_price' added.<br>";
    } else {
        echo "Column 'stock_price' already exists.<br>";
    }

    // Check for category_id
    if (!columnExists($pdo, 'products', 'category_id')) {
        $pdo->exec("ALTER TABLE products ADD COLUMN category_id INTEGER DEFAULT NULL");
        echo "Column 'category_id' added.<br>";
    } else {
        echo "Column 'category_id' already exists.<br>";
    }

    $pdo->commit();
    echo "Migration completed successfully!";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Migration failed: " . $e->getMessage());
}
?>
