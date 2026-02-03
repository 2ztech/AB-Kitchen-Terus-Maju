<?php
require_once '../../config/config.php';

// Authentication check - only allow admin, coordinator, and event advisor
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'coordinator', 'event_advisor'])) {
    die("Access denied. Admin, coordinator or event advisor access required.");
}

// Define event level names
$eventLevelNames = [
    1 => 'UMPSA',
    2 => 'District',
    3 => 'State',
    4 => 'National',
    5 => 'International'
];

// Get all upcoming events with new fields
$upcomingEvents = $pdo->query("
    SELECT 
        e.event_id, 
        e.event_name, 
        e.event_description, 
        e.event_date, 
        e.start_time, 
        e.end_time,
        e.participant_slots,
        e.status,
        e.general_location,
        e.specific_venue,
        e.event_level,
        el.level_name 
    FROM events e
    JOIN event_levels el ON e.event_level = el.level_id
    WHERE e.event_date >= CURDATE() AND e.status = 'active'
    ORDER BY e.event_date ASC
")->fetchAll();

// Process each event to get participant and committee counts
foreach ($upcomingEvents as &$event) {
    // Get total participants
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count 
        FROM event_registrations 
        WHERE event_id = ?
    ");
    $stmt->execute([$event['event_id']]);
    $result = $stmt->fetch();
    $event['total_participants'] = $result['count'];
    $event['available_slots'] = $event['participant_slots'] - $event['total_participants'];

    // Get committee counts
    $stmt = $pdo->prepare("
        SELECT 
            SUM(role = 'committee') AS committees,
            SUM(role = 'main_committee') AS main_committees
        FROM event_committee
        WHERE event_id = ?
    ");
    $stmt->execute([$event['event_id']]);
    $result = $stmt->fetch();
    $event['committees'] = $result['committees'];
    $event['main_committees'] = $result['main_committees'];
    
    // Format times
    $event['formatted_start_time'] = date('h:i A', strtotime($event['start_time']));
    $event['formatted_end_time'] = date('h:i A', strtotime($event['end_time']));
    
    // Ensure event_level exists and get level name
    $event['event_level'] = $event['event_level'] ?? 1; // Default to 1 (UMPSA) if not set
    $event['level_name'] = $eventLevelNames[$event['event_level']] ?? 'Unknown';
}
unset($event); // Break the reference

// Get event status summary
$statusSummary = $pdo->query("
    SELECT 
        COUNT(*) AS total_events,
        SUM(event_date < CURDATE()) AS past_events,
        SUM(event_date = CURDATE() AND status = 'active') AS today_events,
        SUM(event_date > CURDATE() AND status = 'active') AS future_events,
        SUM(status = 'cancelled') AS cancelled_events,
        SUM(status = 'postponed') AS postponed_events
    FROM events
")->fetch();

// Get recent event participation trend (last 6 months)
$participationTrend = $pdo->query("
    SELECT 
        DATE_FORMAT(e.event_date, '%Y-%m') AS month,
        COUNT(DISTINCT e.event_id) AS event_count,
        COUNT(DISTINCT er.registration_id) AS participant_count
    FROM events e
    LEFT JOIN event_registrations er ON e.event_id = er.event_id
    WHERE e.event_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(e.event_date, '%Y-%m')
    ORDER BY month ASC
")->fetchAll();

// Get event distribution by level with percentages
$levelDistribution = $pdo->query("
    SELECT 
        e.event_level,
        COUNT(e.event_id) AS event_count,
        ROUND(COUNT(e.event_id) * 100.0 / (SELECT COUNT(*) FROM events WHERE status = 'active'), 1) AS percentage
    FROM events e
    WHERE e.status = 'active'
    GROUP BY e.event_level
    ORDER BY e.event_level
")->fetchAll();

// Map level IDs to names for the chart
$chartData = [];
foreach ($levelDistribution as $level) {
    $levelId = $level['event_level'];
    $chartData[] = [
        'level_name' => $eventLevelNames[$levelId] ?? 'Unknown',
        'event_count' => $level['event_count'],
        'percentage' => $level['percentage']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
            position: relative;
            width: 75%;
            height: 400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .chart-wrapper {
            width: 100%;
            height: 70%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 15px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin: 5px 15px;
        }
        
        .legend-color {
            width: 15px;
            height: 15px;
            margin-right: 8px;
            border-radius: 3px;
        }

        .event-card {
            transition: all 0.3s;
            height: 100%;
        }
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .badge-level-1 { background-color: #e74c3c; }
        .badge-level-2 { background-color: #3498db; }
        .badge-level-3 { background-color: #2ecc71; }
        .badge-level-4 { background-color: #f39c12; }
        .badge-level-5 { background-color: #9b59b6; }
        
        .badge-status-active { background-color: #2ecc71; }
        .badge-status-cancelled { background-color: #e74c3c; }
        .badge-status-postponed { background-color: #f39c12; }
        
        .progress-thin {
            height: 8px;
        }
        
        /* Ensure 2 cards per row on medium screens and up */
        @media (min-width: 768px) {
            .event-col {
                flex: 0 0 50%;
                max-width: 50%;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">Event Management Report</h1>
                <p class="lead">As of <?= date('F j, Y') ?></p>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row">
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h3>Total Events</h3>
                    <div class="stat-value"><?= $statusSummary['total_events'] ?></div>
                    <p class="text-muted">All events in system</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h3>Past Events</h3>
                    <div class="stat-value"><?= $statusSummary['past_events'] ?></div>
                    <p class="text-muted">Completed events</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h3>Today's Events</h3>
                    <div class="stat-value"><?= $statusSummary['today_events'] ?></div>
                    <p class="text-muted">Happening today</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h3>Upcoming</h3>
                    <div class="stat-value"><?= $statusSummary['future_events'] ?></div>
                    <p class="text-muted">Future events</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h3>Cancelled</h3>
                    <div class="stat-value"><?= $statusSummary['cancelled_events'] ?></div>
                    <p class="text-muted">Cancelled events</p>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stat-card text-center">
                    <h3>Postponed</h3>
                    <div class="stat-value"><?= $statusSummary['postponed_events'] ?></div>
                    <p class="text-muted">Postponed events</p>
                </div>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="row mt-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Upcoming Active Events</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcomingEvents)): ?>
                            <div class="alert alert-info">No upcoming active events</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($upcomingEvents as $event): 
                                    $levelClass = 'badge-level-' . $event['event_level'];
                                    $statusClass = 'badge-status-' . strtolower($event['status']);
                                    $daysToEvent = floor((strtotime($event['event_date']) - time()) / (60 * 60 * 24));
                                ?>
                                <div class="col-md-6 mb-4 event-col">
                                    <div class="card event-card h-100">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between">
                                                <h5 class="card-title"><?= htmlspecialchars($event['event_name']) ?></h5>
                                                <div>
                                                    <span class="badge <?= $levelClass ?>"><?= $event['level_name'] ?></span>
                                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($event['status']) ?></span>
                                                </div>
                                            </div>
                                            <p class="card-text">
                                                <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                                (<?= $daysToEvent > 0 ? "in $daysToEvent days" : "Today" ?>)<br>
                                                <i class="bi bi-clock"></i> <?= $event['formatted_start_time'] ?> - <?= $event['formatted_end_time'] ?><br>
                                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['specific_venue']) ?><br>
                                                <small class="text-muted"><?= htmlspecialchars($event['general_location']) ?></small>
                                            </p>
                                            <div class="d-flex justify-content-between">
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-people"></i> 
                                                    <?= $event['total_participants'] ?>/<?= $event['participant_slots'] ?> participants
                                                </span>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-star"></i> <?= $event['main_committees'] + $event['committees'] ?> committees
                                                </span>
                                            </div>
                                            <div class="mt-2">
                                                <div class="progress progress-thin">
                                                    <div class="progress-bar bg-info" role="progressbar" 
                                                         style="width: <?= ($event['participant_slots'] > 0) ? round(($event['total_participants']/$event['participant_slots'])*100) : 0 ?>%" 
                                                         aria-valuenow="<?= $event['total_participants'] ?>" 
                                                         aria-valuemin="0" 
                                                         aria-valuemax="<?= $event['participant_slots'] ?>">
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    <?= $event['available_slots'] ?> slots available
                                                </small>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <a href="/pages/event/event_detail.php?id=<?= $event['event_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                               View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row mt-4">
            <div class="col-12 d-flex justify-content-center">
                <div class="chart-container">
                    <h3 class="text-center mb-4">Event Distribution by Level</h3>
                    <canvas id="eventLevelChart" height="500"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Event Level Distribution Doughnut Chart
        const levelCtx = document.getElementById('eventLevelChart');
        if (levelCtx) {
            const levelChart = new Chart(levelCtx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($chartData, 'level_name')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($chartData, 'event_count')) ?>,
                        backgroundColor: [
                            '#e74c3c', // UMPSA - red
                            '#3498db', // District - blue
                            '#2ecc71', // State - green
                            '#f39c12', // National - orange
                            '#9b59b6'  // International - purple
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 15,
                                padding: 15
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const percentage = <?= json_encode(array_column($chartData, 'percentage')) ?>[context.dataIndex];
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
    });
</script>

</body>
</html>
