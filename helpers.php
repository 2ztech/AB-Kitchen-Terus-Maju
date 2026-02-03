<?php

function getDashboardUrl($role) {
    $dashboards = [
        'customer' => 'pages/dashboard/customer_dashboard.php',
        'cashier' => 'pages/dashboard/cashier_dashboard.php',
        'admin' => 'pages/dashboard/admin_dashboard.php'
    ];
    return $dashboards[$role] ?? 'pages/dashboard/customer_dashboard.php';
}

function redirectIfNotLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /index.php');
        exit;
    }
}
