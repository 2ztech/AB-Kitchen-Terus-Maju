<?php
require_once '../../config/config.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

// Only allow admin, coordinator, and event advisor
if (!in_array($_SESSION['role'], ['admin', 'coordinator', 'event_advisor'])) {
    die("Access denied. Admin, coordinator or event advisor access required.");
}

// Get top students (up to 10, but shows whatever is available)
$topStudents = [];
try {
    $topStudents = $pdo->query("
        SELECT s.student_id, u.full_name, s.total_merits 
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.total_merits > 0
        ORDER BY s.total_merits DESC 
        LIMIT 10
    ")->fetchAll();
} catch (PDOException $e) {
    // Log error but continue execution
    error_log("Merit report query error: " . $e->getMessage());
}

// Get pending merit claims
$pendingClaims = [];
try {
    $pendingClaims = $pdo->query("
        SELECT 
            mc.claim_id, 
            mc.student_id, 
            u.full_name, 
            s.total_merits,
            mc.event_name, 
            mc.event_date, 
            mc.event_id, 
            e.event_name as actual_event_name,
            mc.role_type, 
            mc.supporting_doc, 
            mc.status
        FROM merit_claims mc
        JOIN users u ON mc.student_id = u.id
        JOIN students s ON u.id = s.user_id
        LEFT JOIN events e ON mc.event_id = e.event_id
        WHERE mc.status = 'pending'
        ORDER BY mc.event_date DESC
    ")->fetchAll();
} catch (PDOException $e) {
    // Log error but continue execution
    error_log("Pending claims query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merit Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .report-card {
            transition: all 0.3s;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
        }
        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .merit-badge {
            background-color: #28a745;
            color: white;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .pending-badge {
            background-color: #ffc107;
            color: #212529;
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .empty-message {
            padding: 40px 20px;
            text-align: center;
            border: 1px dashed #ddd;
            border-radius: 8px;
            margin: 20px 0;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1 class="display-4">Merit Report</h1>
                <p class="lead">As of <?= date('F j, Y') ?></p>
            </div>
        </div>

        <!-- Top Students Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Top Students by Merit Points</h4>
                        <span class="badge bg-primary">Showing: <?= count($topStudents) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($topStudents)): ?>
                            <div class="empty-message">
                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                                <h4>No Student Data Available</h4>
                                <p>There are currently no student merit records to display.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($topStudents as $index => $student): ?>
                                <div class="list-group-item report-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">
                                                #<?= $index + 1 ?>: <?= htmlspecialchars($student['full_name']) ?>
                                            </h5>
                                            <small class="text-muted">
                                                <i class="bi bi-person-badge"></i> Student ID: <?= $student['student_id'] ?>
                                            </small>
                                        </div>
                                        <span class="merit-badge">
                                            <i class="bi bi-award"></i> <?= $student['total_merits'] ?> points
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Claims Section -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Pending Merit Claims</h4>
                        <span class="badge bg-warning text-dark">Pending: <?= count($pendingClaims) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pendingClaims)): ?>
                            <div class="empty-message">
                                <i class="bi bi-file-earmark-text" style="font-size: 2rem;"></i>
                                <h4>No Pending Claims</h4>
                                <p>There are currently no merit claims awaiting approval.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($pendingClaims as $claim): ?>
                                <div class="list-group-item report-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0"><?= htmlspecialchars($claim['full_name']) ?></h5>
                                        <span class="pending-badge">
                                            <i class="bi bi-clock-history"></i> Pending Approval
                                        </span>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong><i class="bi bi-calendar-event"></i> Event:</strong> 
                                                <?= $claim['event_id'] ? htmlspecialchars($claim['actual_event_name']) : htmlspecialchars($claim['event_name']) ?>
                                            </p>
                                            <p class="mb-1"><strong><i class="bi bi-person-rolodex"></i> Role:</strong> 
                                                <?= ucfirst(str_replace('_', ' ', $claim['role_type'])) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <p class="mb-1"><strong><i class="bi bi-star"></i> Current Merit:</strong> 
                                                <?= $claim['total_merits'] ?>
                                            </p>
                                            <p class="mb-1"><strong><i class="bi bi-calendar-date"></i> Event Date:</strong> 
                                                <?= date('M j, Y', strtotime($claim['event_date'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <a href="/pages/merit/review_claim.php?id=<?= $claim['claim_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                           <i class="bi bi-eye"></i> Review Claim
                                        </a>
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
