<div class="content-wrapper">
    <div id="mySidenav" class="sidenav">
        <!-- Admin Menu -->
        <?php if ($_SESSION['role'] === 'admin') : ?>
        <div class="has-submenu">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)">Products</a>
            <div class="submenu">
                <a href="/products">Manage Products</a>
                <a href="/products/add">Add New Product</a>
            </div>
        </div>

        <div class="has-submenu">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)">Orders</a>
            <div class="submenu">
                <a href="/orders">All Orders</a>
                <a href="/orders?status=pending">Pending</a>
                <a href="/orders?status=processing">Processing</a>
                <a href="/orders?status=completed">Completed</a>
                <a href="/orders?status=cancelled">Cancelled</a>
            </div>
        </div>
        
        <div class="has-submenu">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)">Users</a>
            <div class="submenu">
                <a href="/users">Manage Users</a>
            </div>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="/settings">Settings</a>
        <?php endif; ?>

        <!-- Cashier Menu -->
        <?php elseif ($_SESSION['role'] === 'cashier') : ?>
        <div class="has-submenu">
            <a href="/pages/pos/checkout.php">New Order (POS)</a>
        </div>
        
        <div class="has-submenu">
            <a href="/products">View Products</a>
        </div>

        <div class="has-submenu">
            <a href="javascript:void(0)" onclick="toggleSubmenu(this)">Orders</a>
            <div class="submenu">
                <a href="/orders">All Orders</a>
                <a href="/orders?status=pending">Pending</a>
                <a href="/orders?status=processing">Processing</a>
                <a href="/orders?status=completed">Completed</a>
                <a href="/orders?status=cancelled">Cancelled</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleSubmenu(element) {
        // Toggle the show class on the sibling submenu
        var submenu = element.nextElementSibling;
        if (submenu.classList.contains("show")) {
            submenu.classList.remove("show");
        } else {
            // Close other open submenus? Optional. For now let's keep multiple open allowed or close others.
            // Let's close others for cleaner UI
            var allSubmenus = document.querySelectorAll('.submenu');
            allSubmenus.forEach(function(sm) {
                sm.classList.remove('show');
            });
            submenu.classList.add("show");
        }
    }

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
