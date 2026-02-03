<?php
require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Start session and check authorization
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['event_advisor', 'coordinator', 'admin'])) {
    header("Location: /index.php");
    exit();
}

// Fetch all events
$events = $pdo->query("SELECT event_id, event_name FROM events ORDER BY event_date DESC")->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_roles'])) {
    try {
        $pdo->beginTransaction();
        
        // First clear existing assignments for this event
        $stmt = $pdo->prepare("DELETE FROM event_committee WHERE event_id = ?");
        $stmt->execute([$_POST['event_id']]);
        
        // Insert new assignments
        $stmt = $pdo->prepare("INSERT INTO event_committee (event_id, user_id, role) VALUES (?, ?, ?)");
        
        foreach ($_POST['roles'] as $userId => $role) {
            $stmt->execute([$_POST['event_id'], $userId, $role]);
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Committee assignments updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error updating assignments: " . $e->getMessage();
    }
    header("Location: event_committee.php?event_id=".$_POST['event_id']);
    exit();
}


// Get selected event attendees
$attendees = [];
$event_id = $_GET['event_id'] ?? null;
if ($event_id) {
    $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.student_id, 
                          COALESCE(ec.role, 'participant') as role
                          FROM event_registrations er
                          JOIN users u ON er.user_id = u.id
                          LEFT JOIN event_committee ec ON ec.event_id = er.event_id AND ec.user_id = u.id
                          WHERE er.event_id = ?");
    $stmt->execute([$event_id]);
    $attendees = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Committee Management</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <link rel="stylesheet" href="../../styles/event_committee.css">
</head>
<body>
    <main id="main" onclick="closeNav()">
        <span class="menu-toggle" onclick="openNav(event)">&#9776; Menu</span>
        <h1>Event Committee Management</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success_message'] ?></div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error_message'] ?></div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="committee-container">
            <form method="get" action="event_committee.php" class="event-selector">
                <div class="form-group">
                    <label for="event_id">Select Event:</label>
                    <select name="event_id" id="event_id" onchange="this.form.submit()" required>
                        <option value="">-- Select an Event --</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?= $event['event_id'] ?>" 
                                <?= ($event_id == $event['event_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($event['event_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($event_id && !empty($attendees)): ?>
                <form method="post" action="event_committee.php">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    
                    <div class="attendees-list">
                        <h2>Assign Committee Roles</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Role</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendees as $attendee): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($attendee['full_name']) ?></td>
                                        <td><?= htmlspecialchars($attendee['student_id']) ?></td>
                                        <td>
                                            <select name="roles[<?= $attendee['id'] ?>]">
                                                <option value="participant" <?= $attendee['role'] == 'participant' ? 'selected' : '' ?>>Participant</option>
                                                <option value="committee" <?= $attendee['role'] == 'committee' ? 'selected' : '' ?>>Committee</option>
                                                <option value="main_committee" <?= $attendee['role'] == 'main_committee' ? 'selected' : '' ?>>Main Committee</option>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" name="assign_roles" class="submit-btn">Save Assignments</button>
                    </div>
                </form>
            <?php elseif ($event_id): ?>
                <p>No attendees found for this event.</p>
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
