<?php
require_once '../../config/config.php';

// Authentication check - only allow admin and coordinator
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'coordinator'])) {
    header("Location: /index.php");
    exit();
}

if ($_SESSION['role'] !== 'coordinator' && $_SESSION['role'] !== 'admin') {
    die("Access denied. Coordinator or admin access required.");
}

// Get live user statistics
$userStats = $pdo->query("
    SELECT 
        COUNT(*) AS total_users,
        SUM(created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS new_users,
        SUM(status = 'approved') AS active_users,
        SUM(status = 'pending') AS pending_users,
        SUM(status = 'rejected') AS rejected_users
    FROM users
")->fetch(PDO::FETCH_ASSOC);

// Get membership application statistics
$membershipStats = $pdo->query("
    SELECT 
        COUNT(*) AS total_applications,
        SUM(submission_date >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS new_applications,
        SUM(status = 'approved') AS approved_applications,
        SUM(status = 'rejected') AS rejected_applications,
        SUM(status = 'pending') AS pending_applications
    FROM membership_application
")->fetch(PDO::FETCH_ASSOC);

// Calculate approval rate
$approvalRate = $membershipStats['total_applications'] 
    ? round($membershipStats['approved_applications'] / $membershipStats['total_applications'] * 100) 
    : 0;

// Get historical reports (last 5 months)
$historicalReports = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS total_users,
        SUM(status = 'approved') AS active_users,
        SUM(status = 'pending') AS pending_users
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin: 0 auto 20px;
            width: 75%;
            position: relative;
            height: 500px;
            min-height: 500px;
        }
        .chart-wrapper {
            width: 100%;
            height: 70%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .report-table th {
            background-color: #f8f9fa;
        }
        .badge-new {
            background-color: #3498db;
        }
        .badge-approved {
            background-color: #2ecc71;
        }
        .badge-rejected {
            background-color: #e74c3c;
        }
        .badge-pending {
            background-color: #f39c12;
        }
        .chart-legend {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            width: 100%;
        }
        .legend-item {
            display: flex;
            align-items: center;
            margin: 0 15px;
        }
        .legend-color {
            width: 15px;
            height: 15px;
            margin-right: 8px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">User & Membership Report</h1>
                <p class="lead">Live data as of <?= date('F j, Y H:i') ?></p>
            </div>
        </div>

        <!-- User Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?= $userStats['total_users'] ?></div>
                    <p class="text-muted">All registered users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>New This Month</h3>
                    <div class="stat-value"><?= $userStats['new_users'] ?></div>
                    <p class="text-muted">Since <?= date('M 1') ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>Active Users</h3>
                    <div class="stat-value"><?= $userStats['active_users'] ?></div>
                    <p class="text-muted">Approved accounts</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>Pending Users</h3>
                    <div class="stat-value"><?= $userStats['pending_users'] ?></div>
                    <p class="text-muted">Awaiting approval</p>
                </div>
            </div>
        </div>

        <!-- User Status Chart -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="chart-container">
                    <h3 class="text-center mb-4">User Account Status</h3>
                    <div class="chart-wrapper">
                        <canvas id="userStatusChart"></canvas>
                    </div>
                    <div class="chart-legend" id="chartLegend"></div>
                </div>
            </div>
        </div>

        <!-- Membership Statistics -->
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>Total Applications</h3>
                    <div class="stat-value"><?= $membershipStats['total_applications'] ?></div>
                    <p class="text-muted">All-time applications</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>New Applications</h3>
                    <div class="stat-value"><?= $membershipStats['new_applications'] ?></div>
                    <p class="text-muted">Since <?= date('M 1') ?></p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>Approved</h3>
                    <div class="stat-value"><?= $membershipStats['approved_applications'] ?></div>
                    <p class="text-muted">Current month</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card text-center">
                    <h3>Approval Rate</h3>
                    <div class="stat-value"><?= $approvalRate ?>%</div>
                    <p class="text-muted">Of total applications</p>
                </div>
            </div>
        </div>

        <!-- Monthly Reports Table -->
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">User Growth (Last 5 Months)</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover report-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>New Users</th>
                                        <th>Active Users</th>
                                        <th>Pending Users</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historicalReports as $report): ?>
                                    <tr>
                                        <td><?= date('F Y', strtotime($report['month'] . '-01')) ?></td>
                                        <td><?= $report['total_users'] ?></td>
                                        <td><span class="badge badge-approved"><?= $report['active_users'] ?></span></td>
                                        <td><span class="badge badge-pending"><?= $report['pending_users'] ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // User Status Chart
        const userStatusCtx = document.getElementById('userStatusChart').getContext('2d');
        const userStatusChart = new Chart(userStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [
                        <?= $userStats['active_users'] ?>,
                        <?= $userStats['pending_users'] ?>,
                        <?= $userStats['rejected_users'] ?? 0 ?>
                    ],
                    backgroundColor: [
                        '#2ecc71', // Approved - green
                        '#f39c12', // Pending - orange
                        '#e74c3c'  // Rejected - red
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false // We'll use custom legend
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `${context.label}: ${context.raw} users (${Math.round(context.parsed * 100 / context.dataset.data.reduce((a, b) => a + b, 0))}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // Create custom legend
        const legendContainer = document.getElementById('chartLegend');
        userStatusChart.data.labels.forEach((label, i) => {
            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';
            
            const colorBox = document.createElement('div');
            colorBox.className = 'legend-color';
            colorBox.style.backgroundColor = userStatusChart.data.datasets[0].backgroundColor[i];
            
            const text = document.createElement('span');
            const total = userStatusChart.data.datasets[0].data.reduce((a, b) => a + b, 0);
            const percentage = Math.round(userStatusChart.data.datasets[0].data[i] * 100 / total);
            text.textContent = `${label}: ${userStatusChart.data.datasets[0].data[i]} (${percentage}%)`;
            
            legendItem.appendChild(colorBox);
            legendItem.appendChild(text);
            legendContainer.appendChild(legendItem);
        });
    </script>
</body>
</html>
