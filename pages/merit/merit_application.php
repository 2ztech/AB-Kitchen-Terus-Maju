<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SESSION['role'] !== 'event_advisor') {
    die("Access denied. This page is for event advisor only.");
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_merit'])) {
        try {
            $pdo->beginTransaction();
            
            foreach ($_POST['participants'] as $participant_id => $data) {
                $stmt = $pdo->prepare("
                    INSERT INTO merit_applications 
                    (event_id, student_id, role, event_level, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $_POST['event_id'],
                    $participant_id,
                    $data['existing_role'],
                    $_POST['event_level']
                ]);
            }
            
            $pdo->commit();
            $_SESSION['success'] = "Merit applications submitted successfully!";
            header("Location: ".$_SERVER['PHP_SELF']."?event_id=".$_POST['event_id']);
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
        } elseif (isset($_POST['approve_claim']) || isset($_POST['reject_claim'])) {
            $claim_id = $_POST['claim_id'];
            $status = isset($_POST['approve_claim']) ? 'approved' : 'rejected';
            
            try {
                $pdo->beginTransaction();
                
                // Update claim status
                $stmt = $pdo->prepare("UPDATE merit_claims SET status = ? WHERE claim_id = ?");
                $stmt->execute([$status, $claim_id]);
                
                if ($status === 'approved') {
                    // Get claim details
                    $stmt = $pdo->prepare("
                        SELECT mc.*, rm.merit_value 
                        FROM merit_claims mc
                        JOIN role_merits rm ON mc.level_id = rm.level_id AND mc.role_type = rm.role_type
                        WHERE mc.claim_id = ?
                    ");
                    $stmt->execute([$claim_id]);
                    $claim = $stmt->fetch();
                    
                    if ($claim) {
                        // Calculate current semester and academic year
                        $current_month = date('n');
                        $current_semester = ($current_month >= 1 && $current_month <= 6) ? '1' : '2';
                        $current_year = (date('n') <= 6) ? (date('Y')-1).'/'.date('Y') : date('Y').'/'.(date('Y')+1);
                        
                        // Insert into merit_records
                        $stmt = $pdo->prepare("
                            INSERT INTO merit_records 
                            (user_id, event_id, claim_id, level_id, role_type, points, semester, academic_year, awarded_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $claim['student_id'],
                            $claim['event_id'],
                            $claim_id,
                            $claim['level_id'],
                            $claim['role_type'],
                            $claim['merit_value'],
                            $current_semester,
                            $current_year,
                            $_SESSION['user_id']
                        ]);
                        
                        // Update student's total merits
                        $stmt = $pdo->prepare("
                            UPDATE students 
                            SET total_merits = total_merits + ? 
                            WHERE user_id = ?
                        ");
                        $stmt->execute([$claim['merit_value'], $claim['student_id']]);
                    }
                }
                
                $pdo->commit();
                $_SESSION['success'] = "Claim $status successfully!";
                header("Location: ".$_SERVER['PHP_SELF']);
                exit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error updating claim: " . $e->getMessage();
            }
        }
}

// Fetch all events from database
try {
    $stmt = $pdo->query("SELECT * FROM events ORDER BY event_date DESC");
    $events = $stmt->fetchAll();
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

// Get all participants if event is selected
$participants = [];
$selectedEvent = null;
$alreadySubmitted = false;

if (isset($_GET['event_id']) && !empty($_GET['event_id'])) {
    $event_id = (int)$_GET['event_id'];
    
    try {
        // Get event details
        $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
        $stmt->execute([$event_id]);
        $selectedEvent = $stmt->fetch();
        
        // Check if applications were already submitted
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM merit_applications 
            WHERE event_id = ?
        ");
        $stmt->execute([$event_id]);
        $result = $stmt->fetch();
        $alreadySubmitted = ($result['count'] > 0);
        
        // Get all participants
        $stmt = $pdo->prepare("
            SELECT 
                u.id, 
                u.full_name, 
                u.student_id, 
                u.email, 
                er.registration_date,
                COALESCE(ec.role, 'participant') as participant_role
            FROM event_registrations er
            JOIN users u ON er.user_id = u.id
            LEFT JOIN event_committee ec ON ec.event_id = er.event_id AND ec.user_id = er.user_id
            WHERE er.event_id = ? 
            AND u.role = 'student' 
            AND u.status = 'approved'
            ORDER BY
                CASE COALESCE(ec.role, 'participant')
                    WHEN 'main_committee' THEN 1
                    WHEN 'committee' THEN 2
                    ELSE 3
                END,
                er.registration_date
        ");
        $stmt->execute([$event_id]);
        $participants = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
}

// Fetch all pending merit claims
$meritClaims = [];
try {
    $stmt = $pdo->prepare("
        SELECT mc.*, u.full_name, u.student_id, el.level_name 
        FROM merit_claims mc
        JOIN users u ON mc.student_id = u.id
        JOIN event_levels el ON mc.level_id = el.level_id
        WHERE mc.status = 'draft'
        ORDER BY mc.claim_id DESC
    ");
    $stmt->execute();
    $meritClaims = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Participants</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../../styles/merit_application.css">
    <style>
        .btn-submit:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .submission-status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            text-align: center;
        }
        .submitted {
            background-color: #d4edda;
            color: #155724;
        }
        .merit-claims-container {
            margin-top: 40px;
            border-top: 2px solid #ddd;
            padding-top: 20px;
        }
        .claims-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .claims-table th, .claims-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .claims-table th {
            background-color: #f2f2f2;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-approve {
            background-color: #28a745;
            color: white;
        }
        .btn-reject {
            background-color: #dc3545;
            color: white;
        }
        .document-link {
            color: #007bff;
            text-decoration: none;
        }
        .document-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1><u>Event Participants</u></h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['success'] ?>
                <?php unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="event-container">
            <form method="get">
                <select name="event_id" class="event-select" onchange="this.form.submit()">
                    <option value="">-- Select an Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?= $event['event_id'] ?>" 
                            <?= ($_GET['event_id'] ?? '') == $event['event_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['event_name']) ?> 
                            (<?= date('M j, Y', strtotime($event['event_date'])) ?>)
                            <span class="event-level event-level-<?= $event['event_level'] ?>">
                                <?= $eventLevelNames[$event['event_level']] ?? 'Unknown' ?>
                            </span>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <?php if ($selectedEvent): ?>
                <?php if ($alreadySubmitted): ?>
                    <div class="submission-status submitted">
                        Merit applications for this event have already been submitted.
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="event_id" value="<?= $selectedEvent['event_id'] ?>">
                    <input type="hidden" name="event_level" value="<?= $selectedEvent['event_level'] ?>">
                    
                    <div class="event-header">
                        <h2><?= htmlspecialchars($selectedEvent['event_name']) ?></h2>
                        <p>
                            <strong>Date:</strong> <?= date('F j, Y', strtotime($selectedEvent['event_date'])) ?>
                            <span class="event-level event-level-<?= $selectedEvent['event_level'] ?>">
                                <?= $eventLevelNames[$selectedEvent['event_level']] ?? 'Unknown' ?>
                            </span>
                        </p>
                        <p><strong>Venue:</strong> <?= htmlspecialchars($selectedEvent['specific_venue']) ?></p>
                    </div>
                    
                    <h3>All Participants</h3>
                    
                    <?php if (!empty($participants)): ?>
                        <table class="participants-table">
                            <thead>
                                <tr>
                                    <th>No.</th>
                                    <th>Name</th>
                                    <th>Student ID</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Registered On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($participants as $index => $participant): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($participant['full_name']) ?></td>
                                        <td><?= htmlspecialchars($participant['student_id']) ?></td>
                                        <td>
                                            <span class="participant-role role-<?= $participant['participant_role'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $participant['participant_role'])) ?>
                                            </span>
                                            <input type="hidden" 
                                                   name="participants[<?= $participant['id'] ?>][apply_merit]" 
                                                   value="1">
                                            <input type="hidden" 
                                                   name="participants[<?= $participant['id'] ?>][existing_role]" 
                                                   value="<?= $participant['participant_role'] ?>">
                                        </td>
                                        <td><?= htmlspecialchars($participant['email']) ?></td>
                                        <td>
                                            <?= date('M j, Y', strtotime($participant['registration_date'])) ?>
                                            <span class="registration-date">
                                                (<?= date('g:i a', strtotime($participant['registration_date'])) ?>)
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="submit-section">
                            <button type="submit" name="submit_merit" class="btn-submit" <?= $alreadySubmitted ? 'disabled' : '' ?>>
                                Submit Merit Applications
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="no-participants">
                            No participants found for this event.
                        </div>
                    <?php endif; ?>
                </form>
            <?php endif; ?>

            <!-- Merit Claims Section -->
            <div class="merit-claims-container">
                <h2>Pending Merit Claims</h2>
                
                <?php if (!empty($meritClaims)): ?>
                    <table class="claims-table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Student</th>
                                <th>Event</th>
                                <th>Date</th>
                                <th>Level</th>
                                <th>Role</th>
                                <th>Document</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meritClaims as $claim): ?>
                                <tr>
                                    <td><?= $claim['claim_id'] ?></td>
                                    <td>
                                        <?= htmlspecialchars($claim['full_name']) ?><br>
                                        <small><?= $claim['student_id'] ?></small>
                                    </td>
                                    <td>
                                        <?= $claim['event_id'] ? 
                                            htmlspecialchars($events[array_search($claim['event_id'], array_column($events, 'event_id'))]['event_name']) : 
                                            htmlspecialchars($claim['event_name']) ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($claim['event_date'])) ?></td>
                                    <td><?= htmlspecialchars($claim['level_name']) ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $claim['role_type'])) ?></td>
                                    <td>
                                        <a href="<?= $claim['supporting_doc'] ?>" class="document-link" target="_blank">View Document</a>
                                    </td>
                                    <td>
                                        <form method="post" class="action-buttons">
                                            <input type="hidden" name="claim_id" value="<?= $claim['claim_id'] ?>">
                                            <button type="submit" name="approve_claim" class="btn-approve">Approve</button>
                                            <button type="submit" name="reject_claim" class="btn-reject">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No pending merit claims found.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
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
