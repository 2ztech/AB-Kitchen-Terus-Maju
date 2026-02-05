<?php

function getDashboardUrl($role) {
    $dashboards = [
        'customer' => '/',          // Customers go to Homepage
        'cashier' => '/admin',      // Cashiers share Admin dashboard for now, or '/admin' if they have restricted view there
        'admin' => '/admin'         // Clean URL mapped in .htaccess
    ];
    return $dashboards[$role] ?? '/';
}

function redirectIfNotLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /');
        exit;
    }

}


function get_settings($pdo) {
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}
