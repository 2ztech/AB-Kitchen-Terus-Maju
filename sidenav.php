<div class="content-wrapper">
    <div id="mySidenav" class="sidenav">
        <?php if ($_SESSION['role'] === 'student') : ?>
        <!-- Student Menu -->
        <div class="student-submenu">
            <a href="#">Event</a>
            <div class="submenu">
                <a href="/pages/event/event_list.php">Event List</a>
            </div>
        </div>
        
        <div class="student-submenu">
            <a href="#">Merit</a>
            <div class="submenu">
                <a href="/pages/merit/view_merits.php">View Merit</a>
                <a href="/pages/merit/claim_merit.php">Claim Missing Merit</a>
            </div>
        </div>

        <?php elseif ($_SESSION['role'] === 'event_advisor') : ?>
        <!-- Event Advisor Menu -->
        <div class="has-submenu">
            <a href="#">Event</a>
            <div class="submenu">
                <a href="/pages/event/event_list.php">Event List</a>
                <a href="/pages/event/event_committee.php">Event Committee</a>
                <a href="/pages/event/event_qr.php">Generate QR</a>
            </div>
        </div>
        
        <div class="has-submenu">
            <a href="#">Merit</a>
            <div class="submenu">
                <a href="/pages/merit/merit_application.php">Merit Application</a>
            </div>
        </div>

        <?php else : ?>
        <!-- Coordinator/Admin Menu -->
        <div class="has-submenu">
            <a href="#">Event</a>
            <div class="submenu">
                <a href="/pages/event/event_list.php">Event List</a>
            </div>
        </div>
        
        <div class="has-submenu">
            <a href="#">Merit</a>
            <div class="submenu">
                <a href="/pages/merit/merit_approval.php">Merit Approval</a>
            </div>
        </div>
        
        <div class="has-submenu">
            <a href="#">Manage User</a>
            <div class="submenu">
                <a href="/pages/user_management/user_list.php">User List</a>
                <a href="/pages/user_management/membership_application.php">Membership Application</a>
            </div>
        </div>
        
        <div class="has-submenu">
            <a href="/pages/report/reports_list.php">View Report</a>
        </div>
        <?php endif; ?>
    </div>
</div>

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
    
    // Close nav when clicking anywhere outside
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.sidenav') && !event.target.closest('.menu-toggle')) {
            closeNav();
        }
    });
</script>
