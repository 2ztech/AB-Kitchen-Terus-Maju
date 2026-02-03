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
    header("Location: ../../index.php");
    exit();
}

$username = $_SESSION['username'] ?? 'Admin';
?>
<!-- Header already opens body and html -->

<main class="dashboard-container" id="main">
    <div class="dashboard-header">
        <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
        <div class="welcome-banner">
            <h1>Selamat Datang, <?= htmlspecialchars($username) ?>!</h1>
            <p>Admin Dashboard - Manage your Kuih Raya digital store here.</p>
        </div>
    </div>

    <div class="dashboard-content">
        <!-- Content will be added here later -->
        <p>Select an option from the sidebar to get started.</p>
    </div>
</main>

<script>
// Any specific dashboard scripts can go here
</script>

<?php include("../../footer.php"); ?>