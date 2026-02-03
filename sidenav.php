<div class="content-wrapper">
    <div id="mySidenav" class="sidenav">
        <!-- Admin Menu -->
        <?php if ($_SESSION['role'] === 'admin') : ?>
        <div class="has-submenu">
            <a href="#">Products</a>
            <div class="submenu">
                <a href="/pages/products/product_list.php">Manage Products</a>
                <a href="/pages/products/add_product.php">Add New Product</a>
            </div>
        </div>

        <div class="has-submenu">
            <a href="#">Orders</a>
            <div class="submenu">
                <a href="/pages/orders/list.php">All Orders</a>
            </div>
        </div>
        
        <div class="has-submenu">
            <a href="#">Users</a>
            <div class="submenu">
                <a href="/pages/users/user_list.php">Manage Users</a>
            </div>
        </div>

        <!-- Cashier Menu -->
        <?php elseif ($_SESSION['role'] === 'cashier') : ?>
        <div class="has-submenu">
            <a href="/pages/pos/checkout.php">New Order (POS)</a>
        </div>
        
        <div class="has-submenu">
            <a href="/pages/products/product_list.php">View Products</a>
        </div>

        <div class="has-submenu">
            <a href="/pages/orders/history.php">Order History</a>
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
