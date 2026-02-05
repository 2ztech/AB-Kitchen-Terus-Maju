<?php
// config/db.php
// Database Manager System for Kuih Raya Digital Store

// Ensure strict typing
declare(strict_types=1);

class Database {
    private static ?PDO $pdo = null;
    // Store database file in a 'db' directory outside or inside root. 
    // Using __DIR__ . '/../db/kuih_raya.db' assumes config is in /config/
    private static string $dbPath = __DIR__ . '/../db/kuih_raya.db';

    /**
     * Get the PDO connection instance (Singleton)
     */
    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            self::init();
        }
        return self::$pdo;
    }

    /**
     * Initialize database connection and schema
     */
    private static function init(): void {
        try {
            // Ensure db directory exists
            $dbDir = dirname(self::$dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            // Create new PDO connection (SQLite)
            self::$pdo = new PDO("sqlite:" . self::$dbPath);
            
            // Set error mode to exception for better debugging and security
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set default fetch mode to associative array
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enable foreign key constraints (SQLite specific)
            self::$pdo->exec("PRAGMA foreign_keys = ON");

            // Initialize Tables
            self::createTables();

            // Seed Default Data
            self::seedDefaults();

        } catch (PDOException $e) {
            // In a production environment, log this instead of showing it
            error_log("Database Connection Error: " . $e->getMessage());
            die("System Error: Unable to connect to database.");
        }
    }

    /**
     * Create necessary tables if they don't exist
     */
    private static function createTables(): void {
        $queries = [
            // Users Table
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'cashier', -- 'admin' or 'cashier'
                is_new INTEGER DEFAULT 1, -- 1 = User must change password, 0 = Active
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            // Categories Table
            "CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE
            )",

            // Products Table
            "CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                stock_price REAL DEFAULT 0.00,
                stock INTEGER DEFAULT 0,
                category_id INTEGER,
                image_url TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
            )",

            // Orders Table
            "CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER, -- Cashier who processed the order, NULL if guest
                customer_name TEXT, 
                customer_email TEXT,
                customer_phone TEXT,
                delivery_method TEXT DEFAULT 'pickup',
                shipping_address TEXT,
                payment_method TEXT DEFAULT 'manual',
                receipt_image TEXT,
                total_amount REAL NOT NULL DEFAULT 0.00,
                status TEXT DEFAULT 'pending', -- 'pending', 'completed', 'cancelled'
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )",

            // Order Items Table (for successful checkout items)
            "CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER,
                quantity INTEGER NOT NULL,
                price_at_purchase REAL NOT NULL, -- Handle price changes over time
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
            )",

            // Settings Table
            "CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT UNIQUE NOT NULL,
                setting_value TEXT
            )"
        ];

        foreach ($queries as $query) {
            self::$pdo->exec($query);
        }
    }

    /**
     * Seed default data (e.g., Admin user)
     */
    private static function seedDefaults(): void {
        // Check if Admin exists
        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $adminCount = $stmt->fetchColumn();

        if ($adminCount == 0) {
            // Create Default Admin
            // Username: admin
            // Password: password123 (Please change immediately)
            $defaultPass = password_hash('password123', PASSWORD_DEFAULT);
            
            $stmt = self::$pdo->prepare("INSERT INTO users (username, password, role, is_new) VALUES (:username, :password, 'admin', 1)");
            $stmt->execute([
                ':username' => 'admin',
                ':password' => $defaultPass
            ]);
        }
    }
}

// Global accessor for the PDO instance (for compatibility)
// This ensures that including this file automatically sets up the db connection variable.
$pdo = Database::getConnection();
