<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "Creating settings table...\n";
    
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT UNIQUE NOT NULL,
        setting_value TEXT
    )";
    $pdo->exec($sql);
    echo "Table 'settings' created.\n";

    // Seed Defaults
    $defaults = [
        'store_address' => "123, Jalan Kuih Raya,\nTaman Sedap,\n50000 Kuala Lumpur.",
        'bank_name' => "Bank Islam",
        'bank_account' => "1234 5678 9012",
        'bank_holder' => "Acik Bulat Ent",
        'duitnow_qr' => "" // Empty initially
    ];

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)");
    
    foreach ($defaults as $key => $val) {
        $stmt->execute([$key, $val]);
        echo "Seeded: $key\n";
    }

    echo "Migration complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
