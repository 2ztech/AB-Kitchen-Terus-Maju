<?php
// pages/shop/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $qty) {
        $cart_count += $qty;
    }
}
?>
<header class="shop-header">
    <div class="shop-logo">
        <!-- Assuming logo exists, else text -->
        <a href="/index.php">
            <img src="/images/Logo.png" alt="Kuih Raya Logo" onerror="this.src='/images/icons/user.png'"> 
            <!-- Fallback just in case -->
        </a>
    </div>
    
    <nav class="shop-nav">
        <a href="/index.php">Home</a>
        <a href="/pages/shop/track_order.php">My Orders</a>
        <a href="/pages/shop/cart.php" class="cart-icon">
            <i class='bx bxs-cart-alt'></i> Cart
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>
        <!-- Staff Login removed as requested -->
    </nav>
</header>
