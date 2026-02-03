<?php
require_once '../../config/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'coordinator', 'event_advisor'])) {
    die("Access denied. Admin, coordinator or event advisor access required.");
}

// Get all past and current events with attendance data
$events = $pdo->query("
    SELECT 
        e.event_id,
        e.event_name,
        e.event_date,
        e.participant_slots,
        COUNT(DISTINCT ea.attendance_id) AS attendance_count,
        e.specific_venue,
        e.event_level,
        el.level_name
    FROM events e
    LEFT JOIN event_attendance ea ON e.event_id = ea.event_id
    JOIN event_levels el ON e.event_level = el.level_id
    WHERE e.event_date <= CURDATE() AND e.status = 'active'
    GROUP BY e.event_id
    ORDER BY e.event_date DESC
")->fetchAll();


// Define event level names
$eventLevelNames = [
    1 => 'UMPSA',
    2 => 'District',
    3 => 'State',
    4 => 'National',
    5 => 'International'
];

// Calculate attendance percentage for each event
foreach ($events as &$event) {
    $event['attendance_percentage'] = $event['participant_slots'] > 0 
        ? round(($event['attendance_count'] / $event['participant_slots']) * 100, 0)
        : 0;
}
unset($event);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendance Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .attendance-card {
            transition: all 0.3s;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        .attendance-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .progress-thin {
            height: 8px;
        }
        .badge-level-1 { background-color: #e74c3c; }
        .badge-level-2 { background-color: #3498db; }
        .badge-level-3 { background-color: #2ecc71; }
        .badge-level-4 { background-color: #f39c12; }
        .badge-level-5 { background-color: #9b59b6; }
        .attendance-percentage {
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">Event Attendance Report</h1>
                <p class="lead">As of <?= date('F j, Y') ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Event Attendance Summary</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                            <div class="alert alert-info">No attendance records found for past or current events</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($events as $event): 
                                    $isToday = date('Y-m-d') == date('Y-m-d', strtotime($event['event_date']));
                                    $levelClass = isset($event['event_level']) ? 'badge-level-' . $event['event_level'] : 'badge-level-1';
                                ?>
                                    <div class="list-group-item attendance-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="mb-1"><?= htmlspecialchars($event['event_name']) ?></h5>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar"></i> <?= date('M j, Y', strtotime($event['event_date'])) ?>
                                                    <?= $isToday ? '(Today)' : '' ?>
                                                    <span class="badge <?= $levelClass ?> ms-2">
                                                        <?= $eventLevelNames[$event['event_level']] ?? 'Unknown' ?>
                                                    </span>
                                                </small>
                                                <div class="mt-2">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['specific_venue']) ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="attendance-percentage">
                                                    <?= $event['attendance_percentage'] ?>%
                                                </span>
                                                <div class="text-muted small">
                                                    <?= $event['attendance_count'] ?>/<?= $event['participant_slots'] ?> attended
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="progress progress-thin">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                    style="width: <?= $event['attendance_percentage'] ?>%" 
                                                    aria-valuenow="<?= $event['attendance_count'] ?>" 
                                                    aria-valuemin="0" 
                                                    aria-valuemax="<?= $event['participant_slots'] ?>">
                                                </div>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
