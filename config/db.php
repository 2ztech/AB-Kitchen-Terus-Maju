<?php
// config/db.php

class Database {
    private static $instance = null;
    private $pdo;
    private $dbFile;

    private function __construct() {
        // -----------------------------------------------------------
        // AUTOMATIC ENVIRONMENT DETECTION
        // -----------------------------------------------------------
        // __DIR__ gives the folder this file is in (e.g., C:\xampp\htdocs\project\config)
        // We go up one level '/../' to put the DB in the project root.
        // This works on both Windows (C:\...) and Linux (/var/www/...) automatically.
        $this->dbFile = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'kuih_raya.db';

        // fallback if realpath fails (sometimes happens on new file creation)
        if (!$this->dbFile) {
            $this->dbFile = __DIR__ . '/../kuih_raya.db';
        }

        $isNewDb = !file_exists($this->dbFile);

        try {
            // "sqlite:" connection string requires the absolute path we just calculated
            $this->pdo = new PDO('sqlite:' . $this->dbFile);
            
            // Set error mode to exception (Helps you debug SQL errors)
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Default fetch mode to associative array (Results come back as ["name" => "Tart Nanas"])
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // IMPORTANT: Enable Foreign Keys (SQLite has them off by default)
            $this->pdo->exec("PRAGMA foreign_keys = ON;");

            // If this is the first time running, create the tables automatically
            if ($isNewDb) {
                $this->initializeSchema();
            }

        } catch (PDOException $e) {
            // On a live server, you might not want to echo the full error for security,
            // but for development, it helps.
            die("Database Connection Error: " . $e->getMessage() . " <br>Path attempted: " . $this->dbFile);
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }

    // Creates the tables if they don't exist
    private function initializeSchema() {
        $commands = [
            // 1. Users Table
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT CHECK(role IN ('admin', 'cashier')) NOT NULL DEFAULT 'cashier',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            // 2. Products Table
            "CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                price REAL NOT NULL,
                stock_quantity INTEGER NOT NULL DEFAULT 0,
                image_path TEXT,
                is_active INTEGER DEFAULT 1
            )",

            // 3. Orders Table
            "CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_name TEXT NOT NULL,
                customer_phone TEXT NOT NULL,
                total_price REAL NOT NULL,
                status TEXT CHECK(status IN ('Pending', 'Paid', 'Completed', 'Cancelled')) DEFAULT 'Pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",

            // 4. Order Items
            "CREATE TABLE IF NOT EXISTS order_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                price_at_purchase REAL NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id)
            )"
        ];

        foreach ($commands as $sql) {
            $this->pdo->exec($sql);
        }

        // 1. Generate a secure hash for the password "admin123"
        // We calculate this live so it matches your PHP version's algorithm
        $defaultPass = password_hash('admin123', PASSWORD_DEFAULT);

        // 2. Insert the Default Admin User
        try {
            $sql = "INSERT INTO users (username, password, role, status) 
                    VALUES ('admin', '$defaultPass', 'admin', 'active')";
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Ignore error if user already exists (just in case)
        }
    }
}
?>