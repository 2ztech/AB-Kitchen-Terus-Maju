<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], [ 'coordinator', 'admin'])) {
    header("Location: /index.php");
    exit();
}

// Process approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $pdo->beginTransaction();
        
        $application_id = (int)$_POST['application_id'];
        $action = $_POST['action'];
        
        $stmt = $pdo->prepare("
            UPDATE merit_applications 
            SET status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $action,
            $application_id
        ]);
        
        $pdo->commit();
        $_SESSION['success'] = "Application ".($action === 'approved' ? 'approved' : 'rejected')." successfully!";
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error processing application: " . $e->getMessage();
    }
}

// Fetch pending merit applications
try {
    $stmt = $pdo->prepare("
        SELECT 
            ma.id as application_id,
            ma.event_id,
            ma.student_id,
            ma.role,
            ma.event_level,
            ma.application_date,
            e.event_name,
            e.event_date,
            e.specific_venue,
            u.full_name as student_name,
            u.student_id as student_number
        FROM merit_applications ma
        JOIN events e ON ma.event_id = e.event_id
        JOIN users u ON ma.student_id = u.id
        WHERE ma.status = 'pending'
        ORDER BY ma.application_date ASC
    ");
    $stmt->execute();
    $applications = $stmt->fetchAll();
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
    <title>Pending Merit Applications</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../../styles/merit_application.css">
    <style>
        /* Added to adjust for removed advisor column */
        .participants-table th:nth-child(6),
        .participants-table td:nth-child(6) {
            display: none;
        }
    </style>
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1><u>Pending Merit Applications</u></h1>
        
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
            <?php if (!empty($applications)): ?>
                <table class="participants-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Student</th>
                            <th>Event</th>
                            <th>Role</th>
                            <th>Level</th>
                            <th>Applied On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $index => $app): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($app['student_name']) ?></strong><br>
                                    <?= htmlspecialchars($app['student_number']) ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($app['event_name']) ?></strong><br>
                                    <?= date('M j, Y', strtotime($app['event_date'])) ?><br>
                                    <?= htmlspecialchars($app['specific_venue']) ?>
                                </td>
                                <td>
                                    <span class="participant-role role-<?= $app['role'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $app['role'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="event-level event-level-<?= $app['event_level'] ?>">
                                        <?= $eventLevelNames[$app['event_level']] ?? 'Unknown' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= date('M j, Y', strtotime($app['application_date'])) ?>
                                    <span class="registration-date">
                                        (<?= date('g:i a', strtotime($app['application_date'])) ?>)
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                        <input type="hidden" name="action" value="approved">
                                        <button type="submit" class="btn-approve">Approve</button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?= $app['application_id'] ?>">
                                        <input type="hidden" name="action" value="rejected">
                                        <button type="submit" class="btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-participants">
                    No pending merit applications found.
                </div>
            <?php endif; ?>
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
