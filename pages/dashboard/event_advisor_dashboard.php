<?php
include ("../../header.php");
include ("../../sidenav.php");
require_once '../../config/config.php';

// Check if user is logged in and is an event advisor
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

if ($_SESSION['role'] !== 'event_advisor') {
    die("Access denied. This page is for event advisors only.");
}


$full_name = $_SESSION['full_name'] ?? 'Event Advisor';

// Fetch statistics for the advisor
try {
    // Basic stats
    $stats = [
        'total_events' => 0,
        'upcoming_events' => 0,
        'past_events' => 0,
        'total_participants' => 0,
        'pending_applications' => 0
    ];

    // Total events
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events");
    $stmt->execute();
    $stats['total_events'] = $stmt->fetchColumn();

    // Upcoming events
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE()");
    $stmt->execute();
    $stats['upcoming_events'] = $stmt->fetchColumn();

    // Past events
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE event_date < CURDATE()");
    $stmt->execute();
    $stats['past_events'] = $stmt->fetchColumn();

    // Total participants across all events
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM event_registrations");
    $stmt->execute();
    $stats['total_participants'] = $stmt->fetchColumn();

    // Pending merit applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM merit_applications WHERE status = 'pending'");
$stmt->execute();
$stats['pending_applications'] = $stmt->fetchColumn();


    // Event participation trend (last 6 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(event_date, '%Y-%m') AS month,
            COUNT(*) AS event_count
        FROM events
        WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(event_date, '%Y-%m')
        ORDER BY month
    ");
    $stmt->execute();
    $monthly_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Event level distribution
    $stmt = $pdo->prepare("
        SELECT 
            event_level,
            COUNT(*) AS count
        FROM events
        GROUP BY event_level
        ORDER BY event_level
    ");
    $stmt->execute();
    $event_levels = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent events (last 5) - Fixed to include event_level
    $stmt = $pdo->prepare("
        SELECT 
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_level,
            COUNT(er.user_id) AS participants
        FROM events e
        LEFT JOIN event_registrations er ON e.event_id = er.event_id
        GROUP BY e.event_id
        ORDER BY e.event_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Define event level names
$eventLevelNames = [
    1 => 'UMPSA',
    2 => 'District',
    3 => 'State',
    4 => 'National',
    5 => 'International'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Advisor Dashboard</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card h3 {
            margin-top: 0;
            color: #333;
            font-size: 1.1rem;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 10px 0;
        }
        .chart-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            grid-column: span 2;
        }
        .welcome-message {
            grid-column: 1 / -1;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .recent-events {
            grid-column: span 2;
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .event-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        .event-item:last-child {
            border-bottom: none;
        }
        .event-name {
            font-weight: bold;
        }
        .event-date {
            color: #666;
        }
        .event-participants {
            background: #e3f2fd;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .level-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-left: 5px;
        }
        .level-1 { background-color: #e3f2fd; color: #0d47a1; }
        .level-2 { background-color: #e8f5e9; color: #2e7d32; }
        .level-3 { background-color: #fff8e1; color: #f57f17; }
        .level-4 { background-color: #fce4ec; color: #c2185b; }
        .level-5 { background-color: #ede7f6; color: #4527a0; }
    </style>
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1><u>Event Advisor Dashboard</u></h1>
        
        <div class="dashboard-container">
            <div class="welcome-message">
                <h2>Welcome, <?= htmlspecialchars($full_name) ?>!</h2>
                <p>Here's your event management overview</p>
            </div>

            <!-- Quick Stats Cards -->
            <div class="stat-card">
                <h3>Total Events</h3>
                <div class="stat-value"><?= $stats['total_events'] ?></div>
                <p>Events you're managing</p>
            </div>

            <div class="stat-card">
                <h3>Upcoming Events</h3>
                <div class="stat-value"><?= $stats['upcoming_events'] ?></div>
                <p>Scheduled events</p>
            </div>

            <div class="stat-card">
                <h3>Past Events</h3>
                <div class="stat-value"><?= $stats['past_events'] ?></div>
                <p>Completed events</p>
            </div>

            <div class="stat-card">
                <h3>Total Participants</h3>
                <div class="stat-value"><?= $stats['total_participants'] ?></div>
                <p>Across all events</p>
            </div>

            <div class="stat-card">
                <h3>Pending Applications</h3>
                <div class="stat-value"><?= $stats['pending_applications'] ?></div>
                <p>Merit applications to review</p>
            </div>

            <!-- Event Level Distribution Chart -->
            <div class="chart-container">
                <h3>Event Level Distribution</h3>
                <canvas id="eventLevelChart"></canvas>
            </div>

            <!-- Monthly Events Chart -->
            <div class="chart-container">
                <h3>Event Participation Trend (Last 6 Months)</h3>
                <canvas id="monthlyEventsChart"></canvas>
            </div>

            <!-- Recent Events List -->
            <div class="recent-events">
                <h3>Recent Events</h3>
                <?php foreach ($recent_events as $event): ?>
                    <div class="event-item">
                        <div>
                            <span class="event-name"><?= htmlspecialchars($event['event_name']) ?></span>
                            <?php if (isset($event['event_level'])): ?>
                                <span class="level-badge level-<?= $event['event_level'] ?>">
                                    <?= $eventLevelNames[$event['event_level']] ?? 'Unknown' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <span class="event-date"><?= date('M j, Y', strtotime($event['event_date'])) ?></span>
                            <span class="event-participants"><?= $event['participants'] ?> participants</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        // Event Level Distribution Chart
        const eventLevelCtx = document.getElementById('eventLevelChart').getContext('2d');
        const eventLevelChart = new Chart(eventLevelCtx, {
            type: 'doughnut',
            data: {
                labels: [<?= implode(',', array_map(function($level) use ($eventLevelNames) { 
                    return "'" . ($eventLevelNames[$level['event_level']] ?? 'Level ' . $level['event_level']) . "'"; 
                }, $event_levels)) ?>],
                datasets: [{
                    data: [<?= implode(',', array_map(function($level) { return $level['count']; }, $event_levels)) ?>],
                    backgroundColor: [
                        '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    }
                }
            }
        });

        // Monthly Events Chart
        const monthlyCtx = document.getElementById('monthlyEventsChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($month) { return "'" . $month['month'] . "'"; }, $monthly_events)) ?>],
                datasets: [{
                    label: 'Events per Month',
                    data: [<?= implode(',', array_map(function($month) { return $month['event_count']; }, $monthly_events)) ?>],
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: '#3498db',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        function openNav(e) {
            e.stopPropagation();
            document.getElementById("mySidenav").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
        }

        function closeNav() {
            document.getElementById("mySidenav").style.width = "0";
            document.getElementById("main").style.marginLeft = "0";
        }

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.sidenav') && !event.target.closest('.menu-toggle')) {
                closeNav();
            }
        });
    </script>
    <?php include(__DIR__ . '/../../footer.php'); ?>
</body>
</html>
