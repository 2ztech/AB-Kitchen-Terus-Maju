<?php
/**
 * Kuih Raya - Admin Dashboard
 * Location: pages/dashboard/admin_dashboard.php
 */

// Adjust paths to go up 2 levels (../../) to root
include("../../header.php");
include("../../sidenav.php");
require_once '../../config/config.php'; // Uses the centralized db.php connection

// 1. Security: Ensure only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If not admin, send them back to the shop
    header("Location: ../../index.php");
    exit();
}

$username = $_SESSION['username'] ?? 'Admin';

try {
    // 2. Statistics: Get the numbers that matter for business
    
    // Total Revenue (Paid or Completed orders)
    $stmt = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status IN ('Paid', 'Completed')");
    $totalRevenue = $stmt->fetchColumn() ?: 0;

    // Pending Orders (Need action)
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'");
    $pendingOrders = $stmt->fetchColumn();

    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = $stmt->fetchColumn();

    // Low Stock Alert (Less than 10 jars left)
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 10");
    $lowStockCount = $stmt->fetchColumn();

    // 3. Recent Orders Table
    $recentOrders = $pdo->query("
        SELECT id, customer_name, total_price, status, created_at 
        FROM orders 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // 4. Chart Data: Stock Levels of Top 5 Products
    $stockData = $pdo->query("SELECT name, stock_quantity FROM products LIMIT 5")->fetchAll();
    $chartLabels = json_encode(array_column($stockData, 'name'));
    $chartValues = json_encode(array_column($stockData, 'stock_quantity'));

} catch (PDOException $e) {
    die("Data Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Kuih Raya</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <main class="dashboard-container">
        
        <div class="welcome-banner">
            <h1>Selamat Datang, <?= htmlspecialchars($username) ?>!</h1>
            <p>Here is your business overview for Ramadan 2026.</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card" style="border-left: 5px solid #28a745;">
                <h3>Total Revenue</h3>
                <p class="stat-number">RM <?= number_format($totalRevenue, 2) ?></p>
            </div>
            <div class="stat-card" style="border-left: 5px solid #ffc107;">
                <h3>Pending Orders</h3>
                <p class="stat-number"><?= $pendingOrders ?></p>
                <small>Need processing</small>
            </div>
            <div class="stat-card" style="border-left: 5px solid #17a2b8;">
                <h3>Products</h3>
                <p class="stat-number"><?= $totalProducts ?></p>
                <small>Varieties available</small>
            </div>
            <div class="stat-card" style="border-left: 5px solid #dc3545;">
                <h3>Low Stock</h3>
                <p class="stat-number"><?= $lowStockCount ?></p>
                <small>Items need restocking</small>
            </div>
        </div>

        <div class="content-split">
            
            <div class="recent-activity section-box">
                <h2>Latest Incoming Orders</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Total (RM)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($recentOrders) > 0): ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                    <td><?= number_format($order['total_price'], 2) ?></td>
                                    <td>
                                        <span class="status-badge <?= strtolower($order['status']) ?>">
                                            <?= $order['status'] ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No orders yet. Time to promote!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <a href="../orders/list.php" class="view-all-btn">View All Orders</a>
            </div>

            <div class="chart-container section-box">
                <h2>Inventory Levels</h2>
                <canvas id="stockChart"></canvas>
            </div>
        </div>
    </main>

    <script>
        // Simple Chart.js implementation for Stock Levels
        const ctx = document.getElementById('stockChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= $chartLabels ?>, // PHP array to JS array
                datasets: [{
                    label: 'Jars in Stock',
                    data: <?= $chartValues ?>,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)',
                        'rgba(153, 102, 255, 0.6)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>

    <?php include("../../footer.php"); ?>
</body>
</html>