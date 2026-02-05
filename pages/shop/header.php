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

<?php
// Ensure helpers and settings are available
if (!function_exists('get_settings')) {
    // Determine path to helpers based on file location relative to root
    require_once __DIR__ . '/../../helpers.php';
}

if (!isset($settings) && isset($pdo)) {
    $settings = get_settings($pdo);
}

$store_name = $settings['store_name'] ?? 'My Digital Store';
?>
<header class="shop-header">
    <div class="shop-logo">
        <!-- Assuming logo exists, else text -->
        <a href="/">
            <img src="<?= !empty($settings['store_logo']) ? '/images/settings/'.htmlspecialchars($settings['store_logo']) : '/images/Logo.png' ?>" alt="<?= htmlspecialchars($store_name) ?>" onerror="this.src='/images/icons/user.png'"> 
            <span><?= htmlspecialchars($store_name) ?></span>
        </a>
    </div>
    
    <nav class="shop-nav">
        <a href="/">Home</a>
        <a href="/track-order">My Orders</a>
        <a href="/cart" class="cart-icon">
            <i class='bx bxs-cart-alt'></i> Cart
            <?php if ($cart_count > 0): ?>
                <span class="cart-badge"><?= $cart_count ?></span>
            <?php endif; ?>
        </a>
        <!-- Staff Login removed as requested -->
    </nav>
</header>
