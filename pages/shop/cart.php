<?php
/**
 * Kuih Raya - Shopping Cart
 * Location: pages/shop/cart.php
 */

require_once '../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Cart Actions (Remove, Update, Clear)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_item'])) {
        $key = $_POST['product_id'];
        unset($_SESSION['cart'][$key]);
    }
    
    if (isset($_POST['update_qty'])) {
        $key = $_POST['product_id'];
        $qty = intval($_POST['qty']);
        if ($qty > 0) {
            $_SESSION['cart'][$key] = $qty;
        } else {
            // If qty is 0 or less, maybe remove it? Or just keep min 1.
            // Let's enforce min 1 for now.
             $_SESSION['cart'][$key] = 1;
        }
    }

    if (isset($_POST['clear_cart'])) {
        unset($_SESSION['cart']);
    }
    header("Location: cart.php");
    exit();
}

// Fetch Cart Products
$cartItems = [];
$total = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
        $products = $stmt->fetchAll();
        
        foreach ($products as $p) {
            $qty = $_SESSION['cart'][$p['id']];
            $subtotal = $p['price'] * $qty;
            $total += $subtotal;
            
            $p['qty'] = $qty;
            $p['subtotal'] = $subtotal;
            $cartItems[] = $p;
        }
    } catch (PDOException $e) {
        $error = "Error loading cart.";
    }
}

if (!function_exists('get_settings')) {
    require_once '../../helpers.php';
}
$settings = get_settings($pdo);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['store_name'] ?? 'My Digital Store') ?></title>
    <?php if (!empty($settings['store_favicon'])): ?>
        <link rel="icon" href="/images/settings/<?= htmlspecialchars($settings['store_favicon']) ?>" type="image/x-icon">
    <?php endif; ?>
    <!-- BoxIcons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles/shop.css">
    <style>
        .cart-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .cart-table th, .cart-table td {
            padding: 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .cart-table th {
            background: #f9f9f9;
            color: #555;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }
        .qty-input {
            width: 50px;
            padding: 5px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-update {
            background: none;
            border: none;
            color: var(--accent-color);
            cursor: pointer;
            font-size: 1.2rem;
            margin-left: 5px;
        }
        .btn-remove {
            color: #ff6b6b;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
        }
        .btn-checkout {
            background: var(--accent-color);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .cart-summary {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-top: 30px;
            text-align: right;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:40px;">
        <h1>Your Shopping Cart</h1>
        
        <?php if (empty($cartItems)): ?>
            <div style="text-align:center;padding:50px;color:#999;">
                <i class='bx bx-basket' style="font-size:4rem;margin-bottom:20px;"></i>
                <p>Your cart is empty.</p>
                <a href="/index.php" style="color:var(--accent-color);text-decoration:none;font-weight:bold;">Continue Shopping</a>
            </div>
        <?php else: ?>
            <table class="cart-table">
                <thead>
                    <tr>
                        <th width="45%">Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Subtotal</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cartItems as $item): ?>
                    <tr>
                        <td style="display:flex;align-items:center;gap:15px;">
                            <img src="<?= $item['image_url'] ? '../../images/product/'.$item['image_url'] : '../../images/icons/no-image.png' ?>" 
                                 style="width:60px;height:60px;object-fit:cover;border-radius:8px;">
                            <strong><?= htmlspecialchars($item['name']) ?></strong>
                        </td>
                        <td>RM <?= number_format($item['price'], 2) ?></td>
                        <td>
                            <form method="POST" style="display:flex;align-items:center;">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <input type="number" name="qty" value="<?= $item['qty'] ?>" min="1" class="qty-input">
                                <button type="submit" name="update_qty" class="btn-update" title="Update Quantity">
                                    <i class='bx bx-check-circle'></i>
                                </button>
                            </form>
                        </td>
                        <td>RM <?= number_format($item['subtotal'], 2) ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="remove_item" class="btn-remove" title="Remove Item">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-summary">
                <h2 style="margin:0 0 20px 0;">Total: RM <?= number_format($total, 2) ?></h2>
                <form method="POST" style="display:inline;margin-right:20px;">
                    <button type="submit" name="clear_cart" style="background:none;border:none;color:#999;cursor:pointer;text-decoration:underline;">Clear Cart</button>
                </form>
                <a href="checkout.php" class="btn-checkout">Proceed to Checkout</a>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../footer.php'; ?>

</body>
</html>
