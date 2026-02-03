<?php
// handlers/auth_handler.php

// Ensure strict typing for better security
declare(strict_types=1);

// We assume this file is included from index.php, so paths are relative to root
// If accessed directly, we might need to adjust these paths
if (file_exists('config/db.php')) {
    require_once 'config/db.php';
    require_once 'helpers.php';
} elseif (file_exists('../config/db.php')) {
    // Fallback if accessed from a subdirectory
    require_once '../config/db.php';
    require_once '../helpers.php';
}

/**
 * Handles user login authentication
 * Matches the new SQLite 'users' table structure
 * * @param PDO $pdo Database connection
 * @return array ['error' => string|null, 'redirect' => string|null]
 */
function handleLogin(PDO $pdo): array {
    // 1. Check if the login form was submitted
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
        return ['error' => null, 'redirect' => null];
    }

    // 2. Sanitize inputs
    $username = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // 3. Basic Validation
    if (empty($username) || empty($password)) {
        return ['error' => 'Please fill in all fields', 'redirect' => null];
    }

    try {
        // 4. Query the database
        // We only select the columns that actually exist in your new SQLite schema
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // 5. Verify User Exists
        if (!$user) {
            return ['error' => 'Invalid username or password', 'redirect' => null];
        }

        // 6. Verify Password
        if (!password_verify($password, $user['password'])) {
            return ['error' => 'Invalid username or password', 'redirect' => null];
        }

        // 7. Success! Create Session
        // We use session_regenerate_id to prevent Session Fixation attacks
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['last_active'] = time();

        // 8. Redirect based on role
        // 'getDashboardUrl' is defined in helpers.php
        return ['error' => null, 'redirect' => getDashboardUrl($user['role'])];

    } catch (PDOException $e) {
        // Log the error internally, but show a generic message to the user
        error_log('Login System Error: ' . $e->getMessage());
        return ['error' => 'System error. Please contact the administrator.', 'redirect' => null];
    }
}

/**
 * Handles user registration
 * * NOTE: For the Kuih Raya system, public registration is DISABLED.
 * Accounts should be created manually by the Admin in the dashboard.
 * We keep this stub to prevent index.php from crashing.
 * * @param PDO $pdo Database connection
 * @return array ['error' => string|null, 'success' => bool]
 */
function handleRegistration(PDO $pdo): array {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
        return [
            'error' => 'Public registration is closed. Please ask the Admin to create an account.',
            'success' => false
        ];
    }
    return ['error' => null, 'success' => false];
}
?>