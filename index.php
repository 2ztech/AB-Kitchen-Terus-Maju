<?php
/**
 * Kuih Raya - Digital Storefront
 * Location: index.php
 */

require_once 'config/db.php';

// Initialize Session if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $p_id = $_POST['product_id'];
    $qty = 1; // Default add 1
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$p_id])) {
        $_SESSION['cart'][$p_id] += $qty;
    } else {
        $_SESSION['cart'][$p_id] = $qty;
    }
    
    // Refresh to show updated cart count
    header("Location: index.php");
    exit();
}

// Fetch Products (Active only can be filtered if needed, currently all)
try {
    // Only show products with stock > 0 optionally, but for now show all
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name ASC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error loading products.");
}

// Include Helper
require_once 'helpers.php';

// Fetch Settings
$settings = get_settings($pdo);

// Check Store Status (Skip if user is logged in as admin/cashier)
$is_staff = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'cashier']);

if (($settings['store_status'] ?? 'open') === 'closed' && !$is_staff) {
    include 'pages/shop/closed.php';
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['store_name'] ?? 'AcikBulat Digital Store') ?></title>
    <?php if (!empty($settings['store_favicon'])): ?>
        <link rel="icon" href="images/settings/<?= htmlspecialchars($settings['store_favicon']) ?>" type="image/x-icon">
    <?php endif; ?>
    <!-- BoxIcons for Cart Icon -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles/shop.css">
    <style>
        .announcement-banner {
            background: #ffeeba;
            color: #856404;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            margin: 20px auto 30px auto; 
            max-width: 800px;
            font-weight: bold;
            border-left: 5px solid #ffc107;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <?php include 'pages/shop/header.php'; ?>

    <section class="hero">
        <!-- Staff Preview Alert -->
        <?php if (($settings['store_status'] ?? 'open') === 'closed' && $is_staff): ?>
            <div style="background:#dc3545;color:white;padding:10px;text-align:center;margin-bottom:20px;border-radius:5px;">
                <i class='bx bxs-lock-alt'></i> <strong>Store is currently CLOSED to customers.</strong> (You can see this because you are Staff)
            </div>
        <?php endif; ?>

        <h1>Salam Ramadhan Mubarak!</h1>
        
        <?php if (!empty($settings['announcement_text'])): ?>
            <div class="announcement-banner">
                <i class='bx bxs-megaphone'></i> <?= nl2br(htmlspecialchars($settings['announcement_text'])) ?>
            </div>
        <?php endif; ?>

        <p>Order your favorite traditional Kuih Raya online.</p>
    </section>

    <div class="container">
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="<?= $p['image_url'] ? 'images/product/' . htmlspecialchars($p['image_url']) : 'images/icons/no-image.png' ?>" 
                             alt="<?= htmlspecialchars($p['name']) ?>"
                             onerror="this.src='https://placehold.co/400x400?text=No+Image'">
                    </div>
                    <div class="product-info">
                        <h3 class="product-title"><?= htmlspecialchars($p['name']) ?></h3>
                        <p class="product-desc"><?= htmlspecialchars(substr($p['description'], 0, 80)) ?>...</p>
                        <span class="product-price">RM <?= number_format($p['price'], 2) ?></span>
                        
                        <?php if ($p['stock'] > 0): ?>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="add_to_cart" class="btn-add-cart">Add into Cart</button>
                            </form>
                        <?php else: ?>
                            <button class="btn-add-cart" style="background:#ccc;cursor:not-allowed;" disabled>Out of Stock</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Simple Footer -->
    <?php include 'footer.php'; ?>

</body>
</html>