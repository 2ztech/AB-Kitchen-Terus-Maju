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



// Fetch Categories using SQLite compatible query
$stmtCat = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmtCat->fetchAll();

// Filter Parameters
$category_filter = $_GET['category'] ?? '';
$max_price = $_GET['max_price'] ?? '';

// Build Query
$sql = "SELECT * FROM products WHERE 1=1";
$params = [];

if ($category_filter) {
    $sql .= " AND category_id = ?";
    $params[] = $category_filter;
}

if ($max_price) {
    $sql .= " AND price <= ?";
    $params[] = $max_price;
}

$sql .= " ORDER BY name ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
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
    include '/closed';
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

        .whatsapp-float {
            position: fixed;
            width: 60px;
            height: 60px;
            bottom: 30px;
            right: 30px;
            background-color: #25d366;
            color: #FFF;
            border-radius: 50px;
            text-align: center;
            font-size: 35px;
            box-shadow: 2px 2px 5px #999;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .whatsapp-float:hover {
            background-color: #128c7e;
            transform: scale(1.1);
            color: #FFF;
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

    <div class="container shop-layout">
        
        <!-- Sidebar Filter -->
        <aside class="shop-sidebar">
            <div class="filter-group">
                <h3>Categories</h3>
                <a href="/" class="<?= $category_filter == '' ? 'active' : '' ?>">All Categories</a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= $cat['id'] ?>&max_price=<?= $max_price ?>" 
                       class="<?= $category_filter == $cat['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="filter-group">
                <h3>Price Filter</h3>
                <form method="GET" action="/">
                    <?php if ($category_filter): ?>
                        <input type="hidden" name="category" value="<?= $category_filter ?>">
                    <?php endif; ?>
                    <label>Max Price: RM <span id="priceVal"><?= $max_price ? $max_price : '100' ?></span></label>
                    <input type="range" name="max_price" min="0" max="100" value="<?= $max_price ? $max_price : '100' ?>" 
                           oninput="document.getElementById('priceVal').innerText = this.value">
                    <button type="submit" class="btn-filter">Apply</button>
                </form>
            </div>
        </aside>

        <!-- Product Grid -->
        <div class="product-grid">
            <?php if (count($products) > 0): ?>
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
                                <button onclick="addToCart(<?= $p['id'] ?>, this)" class="btn-add-cart">Add to Cart</button>
                            <?php else: ?>
                                <button class="btn-add-cart" style="background:#ccc;cursor:not-allowed;" disabled>Out of Stock</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px; color: #777;">
                    <i class='bx bx-search' style="font-size:3rem;margin-bottom:10px;"></i>
                    <p>No products found matching your filter.</p>
                    <a href="/" style="color:var(--accent-color);text-decoration:none;">Clear Filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- WhatsApp Floating Button -->
    <?php if (!empty($settings['whatsapp_number'])): ?>
        <a href="https://wa.me/<?= htmlspecialchars($settings['whatsapp_number']) ?>" target="_blank" class="whatsapp-float">
            <i class='bx bxl-whatsapp'></i>
        </a>
    <?php endif; ?>

    <!-- Toast Notification -->
    <div id="toast" style="visibility:hidden;min-width:250px;margin-left:-125px;background-color:#333;color:#fff;text-align:center;border-radius:2px;padding:16px;position:fixed;z-index:1;left:50%;bottom:30px;font-size:17px;">
        Item added to cart
    </div>

    <!-- Simple Footer -->
    <?php include 'footer.php'; ?>

    <script>
        function addToCart(productId, btnElement) {
            // Visual Feedback
            const originalText = btnElement.innerText;
            btnElement.innerText = "Adding...";
            btnElement.disabled = true;

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            fetch('pages/shop/ajax_add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update Cart Count Badge
                    let badge = document.querySelector('.cart-badge');
                    if (!badge) {
                        // Create badge if it doesn't exist
                        const cartLink = document.querySelector('.cart-icon');
                        badge = document.createElement('span');
                        badge.className = 'cart-badge';
                        cartLink.appendChild(badge);
                    }
                    badge.innerText = data.cart_count;

                    // Show Toast
                    showToast("Item added to cart!");
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast("Error adding item.");
            })
            .finally(() => {
                // Reset Button
                btnElement.innerText = originalText;
                btnElement.disabled = false;
            });
        }

        function showToast(message) {
            const x = document.getElementById("toast");
            x.innerText = message;
            x.style.visibility = "visible";
            // Check if animation class is needed, or just simple visibility toggle
            x.className = "show"; // Assuming simple CSS or none
            
            // Add fade in/out animation styles dynamically if not present
            x.style.opacity = 1;
            
            setTimeout(function(){ 
                x.style.visibility = "hidden"; 
            }, 3000);
        }
    </script>
</body>
</html>