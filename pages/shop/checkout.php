<?php
/**
 * Kuih Raya - Checkout
 * Location: pages/shop/checkout.php
 */

require_once '../../config/db.php';
require_once '../../handlers/email_handler.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if cart empty
if (empty($_SESSION['cart'])) {
    header("Location: /cart");
    exit();
}

// Calculate Total
$total = 0;
$cart_products = [];
if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_keys($_SESSION['cart']));
    $stmt = $pdo->query("SELECT * FROM products WHERE id IN ($ids)");
    $products = $stmt->fetchAll();
    foreach ($products as $p) {
        $qty = $_SESSION['cart'][$p['id']];
        $total += $p['price'] * $qty;
        $p['qty'] = $qty;
        $cart_products[] = $p;
    }

}

// Fetch Settings
$settings = [];
$stmtSettings = $pdo->query("SELECT * FROM settings");
while ($row = $stmtSettings->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$store_address = $settings['store_address'] ?? 'Address not set';
$bank_info = ($settings['bank_name'] ?? '') . ': ' . ($settings['bank_account'] ?? '') . ' (' . ($settings['bank_holder'] ?? '') . ')';
$qr_image = $settings['duitnow_qr'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    // Normalize phone number: Remove +60 or 60 prefix, replace with 0
    // First remove all non-numeric characters except +
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    // Replace +60 or 60 at the start with 0
    if (preg_match('/^(\+?60)/', $phone)) {
        $phone = preg_replace('/^(\+?60)/', '0', $phone);
    }
    $email = trim($_POST['email']);
    $delivery_method = $_POST['delivery_method']; // 'pickup' or 'delivery'
    $address = trim($_POST['address']);
    
    // Validate Basic Fields
    if (empty($name) || empty($phone) || empty($email)) {
        $error = "Please fill in all required fields.";
    } elseif ($delivery_method === 'delivery' && empty($address)) {
        $error = "Shipping address is required for delivery.";
    } elseif (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $error = "Payment receipt is required. Please upload your proof of payment.";
    } else {
        // Prepare Receipt Info
        $file = $_FILES['receipt'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowed)) {
            $error = "Invalid receipt format. Use JPG, PNG or PDF.";
        } elseif ($file['size'] > 2 * 1024 * 1024) {
            $error = "File size must be less than 2MB.";
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Insert Order first to get ID
                $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_email, customer_phone, delivery_method, shipping_address, total_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                $addressIdx = ($delivery_method === 'pickup') ? '' : $address;
                $stmt->execute([$name, $email, $phone, $delivery_method, $addressIdx, $total]);
                $order_id = $pdo->lastInsertId();

                // 2. Handle Receipt Upload
                // Format: order_{id}_{name}_{time}.ext
                $clean_name = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($name));
                $filename = "order_{$order_id}_{$clean_name}_" . time() . ".{$ext}";
                
                $upload_dir = '../../images/receipts/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                    // Update Order with receipt filename
                    $stmtReceipt = $pdo->prepare("UPDATE orders SET receipt_image = ? WHERE id = ?");
                    $stmtReceipt->execute([$filename, $order_id]);
                } else {
                    throw new Exception("Failed to upload receipt file.");
                }

                // 3. Insert Items and Deduct Stock
                $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES (?, ?, ?, ?)");
                $stmtStock = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

                foreach ($cart_products as $item) {
                    $stmtItem->execute([$order_id, $item['id'], $item['qty'], $item['price']]);
                    $stmtStock->execute([$item['qty'], $item['id']]);
                }

                $pdo->commit();

                // Send Email Receipt
                try {
                    $emailer = new EmailHandler($pdo);
                    $emailer->sendOrderReceipt($order_id);
                } catch (Exception $e) {
                    error_log("Failed to trigger email: " . $e->getMessage());
                    // Continue flow even if email fails
                }
                
                // Clear Cart
                unset($_SESSION['cart']);
                
                // Redirect
                header("Location: /order-success?id=" . $order_id);
                exit();

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Order failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch settings for title
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../../styles/shop.css?v=<?= filemtime(__DIR__ . '/../../styles/shop.css') ?>">
    <style>
        .checkout-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .form-section { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .order-summary { background: #fafafa; padding: 30px; border-radius: 10px; height: fit-content; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9em; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        
        /* Payment Area */
        .payment-box { 
            border: 2px dashed #ccc; padding: 20px; text-align: center; background: #f9f9f9; border-radius: 8px; margin-top: 10px;
        }
        .qr-placeholder { width: 150px; height: 150px; background: #ddd; margin: 10px auto; display: flex; align-items: center; justify-content: center; color: #777; font-weight: bold; }
        
        .btn-place-order { background: var(--accent-color); color: white; width: 100%; padding: 15px; border: none; border-radius: 8px; font-size: 1.1rem; font-weight: bold; cursor: pointer; margin-top: 20px; }
        
        .radio-group { display: flex; gap: 20px; margin-bottom: 10px; }
        .radio-option { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        
        .pickup-info { background: #e3f2fd; color: #0d47a1; padding: 15px; border-radius: 6px; font-size: 0.9rem; margin-top: 10px; display: none; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        
        @media(max-width: 768px) { 
            .checkout-grid { grid-template-columns: 1fr; } 
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container" style="padding-top:40px;">
        <h1 style="margin-bottom:30px;">Checkout</h1>
        
        <?php if ($error): ?>
            <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:6px;margin-bottom:20px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="checkout-grid" enctype="multipart/form-data">
            <!-- Left: Customer Details -->
            <div class="form-section">
                <h2>Customer Information</h2>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Ahmad Albab">
                </div>
                
                <div class="form-row">
                    <div>
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" required placeholder="email@example.com">
                    </div>
                    <div>
                        <label>Phone Number</label>
                        <input type="tel" name="phone" id="phone_input" class="form-control" required placeholder="012-3456789">
                    </div>
                
                <script>
                document.getElementById('phone_input').addEventListener('blur', function() {
                    let val = this.value.trim();
                    // Remove all non-numeric chars except +
                    val = val.replace(/[^0-9+]/g, '');
                    
                    // Replace +60 or 60 at start with 0
                    if (val.startsWith('+60')) {
                        val = '0' + val.substring(3);
                    } else if (val.startsWith('60')) {
                        val = '0' + val.substring(2);
                    }
                    
                    this.value = val;
                });
                </script>
                </div>

                <h2 style="margin-top:30px;">Delivery Method</h2>
                <div class="form-group">
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="delivery_method" value="pickup" checked onchange="toggleAddress(false)"> Self-Pickup
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="delivery_method" value="delivery" onchange="toggleAddress(true)"> Delivery
                        </label>
                    </div>
                    
                    <div id="pickup-info" class="pickup-info" style="display:block;">
                        <strong>Store Location:</strong><br>
                        <?= nl2br(htmlspecialchars($store_address)) ?><br>
                        (Open: 10am - 8pm)
                    </div>

                    <div id="address-box" style="display:none;margin-top:15px;">
                        <label>Shipping Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="No 123, Jalan..."></textarea>
                    </div>
                </div>

                <h2 style="margin-top:30px;">Payment & Verification</h2>
                <div class="payment-box">
                    <p style="font-weight:bold;">1. Make Payment via DuitNow QR</p>
                    
                    <?php if ($qr_image): ?>
                        <div style="margin:15px auto;">
                            <!-- Secured Image: Prevent right click to copy (basic deterrent) -->
                            <img src="../../images/settings/<?= htmlspecialchars($qr_image) ?>" 
                                 style="max-width:200px;border-radius:8px;border:1px solid #ddd;" 
                                 oncontextmenu="return false;"
                                 draggable="false">
                        </div>
                    <?php else: ?>
                        <div class="qr-placeholder">QR CODE NOT UPLOADED</div>
                    <?php endif; ?>
                    
                    <p style="font-size:0.9rem;"><?= htmlspecialchars($bank_info) ?></p>
                    <hr style="margin:20px 0;border:none;border-top:1px dashed #ccc;">
                    
                    <p style="font-weight:bold;margin-bottom:10px;">2. Upload Payment Receipt</p>
                    <input type="file" name="receipt" required accept=".jpg,.jpeg,.png,.pdf" class="form-control">
                    <small style="color:#666;display:block;margin-top:5px;">Accepted formats: JPG, PNG, PDF. Max size: 2MB.</small>
                </div>
            </div>

            <!-- Right: Order Summary -->
            <div class="order-summary">
                <h2>Order Summary</h2>
                <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                    <?php foreach ($cart_products as $p): ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px 0;">
                            <?= htmlspecialchars($p['name']) ?> <span style="color:#888;">x<?= $p['qty'] ?></span>
                        </td>
                        <td style="text-align:right;">RM <?= number_format($p['price'] * $p['qty'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight:bold;font-size:1.2rem;">
                        <td style="padding-top:20px;">Total</td>
                        <td style="padding-top:20px;text-align:right;">RM <?= number_format($total, 2) ?></td>
                    </tr>
                </table>

                <button type="submit" class="btn-place-order">Place Order</button>
            </div>
        </form>
    </div>
    
    <?php include '../../footer.php'; ?>
    
    <script>
        function toggleAddress(show) {
            const addrBox = document.getElementById('address-box');
            const pickupInfo = document.getElementById('pickup-info');
            
            if (show) {
                addrBox.style.display = 'block';
                pickupInfo.style.display = 'none';
                document.querySelector('[name="address"]').required = true;
            } else {
                addrBox.style.display = 'none';
                pickupInfo.style.display = 'block';
                document.querySelector('[name="address"]').required = false;
            }
        }
    </script>
</body>
</html>
