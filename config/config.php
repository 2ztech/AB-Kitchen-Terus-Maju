<?php
// config/config.php

// Load environment variables if using .env
require_once __DIR__ . '/db.php'; // Database configuration

// Session configuration
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'],
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global PDO instance
// Global PDO instance is already created in db.php, but we can ensure it here if needed
if (!isset($pdo)) {
    $pdo = Database::getConnection();
}
