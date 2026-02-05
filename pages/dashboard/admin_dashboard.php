<?php
/**
 * Kuih Raya - Admin Dashboard
 * Location: pages/dashboard/admin_dashboard.php
 */

// Adjust paths to go up 2 levels (../../) to root
include("../../header.php");
include("../../sidenav.php");

// Security: Ensure only Admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /");
    exit();
}

$username = $_SESSION['username'] ?? 'Admin';

// --- DATA FETCHING ---
try {
    // 1. Key Metrics
    
    // Total Revenue (Completed Orders)
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'");
    $total_revenue = $stmt->fetchColumn() ?: 0.00;

    // Today's Orders
    $today_start = date('Y-m-d 00:00:00');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at >= ?");
    $stmt->execute([$today_start]);
    $orders_today = $stmt->fetchColumn();

    // Pending Orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pending_orders = $stmt->fetchColumn();

    // Low Stock Items (< 10)
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10");
    $low_stock_count = $stmt->fetchColumn();

    // 2. Chart Data (Last 7 Days Sales)
    $sales_data = [];
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d M', strtotime($date));
        
        // Sum completed orders for this date (using SQLite date string match)
        $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE date(created_at) = ? AND status = 'completed'");
        $stmt->execute([$date]);
        $val = $stmt->fetchColumn() ?: 0;
        $sales_data[] = $val;
    }

    // 3. Recent 5 Orders
    $stmt = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage();
}
?>

<main class="dashboard-container" id="main">
    <div class="dashboard-header">
        <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
        <div class="welcome-banner">
            <h1>Selamat Datang, <?= htmlspecialchars($username) ?>!</h1>
            <p>Here is your store's performance overview.</p>
        </div>
    </div>

    <div class="dashboard-content">
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e3f2fd;color:#007bff;"><i class='bx bx-money'></i></div>
                <div class="stat-info">
                    <h3>RM <?= number_format($total_revenue, 2) ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#e8f5e9;color:#28a745;"><i class='bx bx-shopping-bag'></i></div>
                <div class="stat-info">
                    <h3><?= $orders_today ?></h3>
                    <p>Orders Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fff3e0;color:#ff9800;"><i class='bx bx-time'></i></div>
                <div class="stat-info">
                    <h3><?= $pending_orders ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#ffebee;color:#dc3545;"><i class='bx bx-error'></i></div>
                <div class="stat-info">
                    <h3><?= $low_stock_count ?></h3>
                    <p>Low Stock Items</p>
                </div>
            </div>
        </div>

        <!-- Dashboard Layout Split -->
        <div class="dashboard-split">
            
            <!-- Left: Sales Chart -->
            <div class="chart-section" style="background:white;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                <h3 class="section-title">Sales Overview (Last 7 Days)</h3>
                <div style="height: 300px; position: relative; width: 100%;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <!-- Right: Recent Activity -->
            <div class="recent-section">
                
                <!-- Low Stock Alert -->
                <?php if ($low_stock_count > 0): ?>
                <div class="alert-box" style="background:#dc3545;color:white;padding:10px 15px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;font-size:0.9em;">
                    <div>
                        <i class='bx bx-error-circle'></i> <strong>Warning:</strong> <?= $low_stock_count ?> items low stock.
                    </div>
                    <a href="/products" style="color:white;text-decoration:underline;">Check</a>
                </div>
                <?php endif; ?>

                <!-- Recent Orders List -->
                <div style="background:white;padding:20px;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                        <h3 class="section-title" style="margin:0;">Recent Orders</h3>
                        <a href="/orders" class="btn-link">View All</a>
                    </div>
                    <table style="width:100%;border-collapse:collapse;font-size:0.9em;">
                        <tbody>
                            <?php foreach ($recent_orders as $o): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:8px 0;">
                                    <strong>#<?= $o['id'] ?></strong><br>
                                    <small style="color:#888;"><?= htmlspecialchars($o['customer_name']) ?></small>
                                </td>
                                <td style="text-align:right;">
                                    RM <?= number_format($o['total_amount'], 2) ?><br>
                                    <span class="badge status-<?= $o['status'] ?>" style="font-size:0.75em;"><?= $o['status'] ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="2" style="text-align:center;padding:10px;color:#999;">No orders yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('salesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Sales (RM)',
            data: <?= json_encode($sales_data) ?>,
            backgroundColor: 'rgba(0, 123, 255, 0.6)',
            borderColor: 'rgba(0, 123, 255, 1)',
            borderWidth: 1,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<style>
/* Dashboard Specific Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.stat-info h3 { margin: 0; font-size: 1.5rem; color: #333; }
.stat-info p { margin: 5px 0 0; color: #666; font-size: 0.9rem; }

/* Dashboard Split Layout */
.dashboard-split {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
/* Removed media query to enforce vertical stacking on all screens */
.badge { padding: 4px 8px; border-radius: 12px; color: white; display: inline-block; }
.status-pending { background: #ff9800; }
.status-processing { background: #17a2b8; } /* Added Processing (Teal/Blue) */
.status-completed { background: #28a745; }
.status-cancelled { background: #dc3545; }
</style>

<?php include("../../footer.php"); ?>