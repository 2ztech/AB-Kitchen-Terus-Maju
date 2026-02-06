<?php
/**
 * Kuih Raya - Order Receipt
 * Location: pages/shop/order_success.php
 */

require_once '../../config/db.php';

if (!isset($_GET['id'])) {
    header("Location: /");
    exit();
}

$order_id = htmlspecialchars($_GET['id']);
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    // Fetch Items
    $stmtItems = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
    $stmtItems->execute([$order_id]);
    $items = $stmtItems->fetchAll();

    // Fetch Settings for Receipt Header
    require_once '../../helpers.php';
    $settings = get_settings($pdo);
    $store_name = $settings['store_name'] ?? 'My Digital Store';
    $store_address = $settings['store_address'] ?? "Address not set.";

} catch (PDOException $e) {
    die("Error loading receipt.");
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
    <title>Receipt #<?= $order_id ?></title>
    <?php if (!empty($settings['store_favicon'])): ?>
        <link rel="icon" href="/images/settings/<?= htmlspecialchars($settings['store_favicon']) ?>" type="image/x-icon">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f0f0f0; margin: 0; padding: 20px; }
        .receipt-container {
            background: white;
            max-width: 400px;
            margin: 0 auto;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .header h2 { margin: 0; color: #333; }
        .header p { margin: 5px 0 20px 0; color: #777; font-size: 0.9rem; }
        
        .divider { border-top: 1px dashed #ccc; margin: 20px 0; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.9rem; }
        .info-label { color: #666; }
        .info-val { font-weight: bold; }
        
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9rem; }
        .items-table th { text-align: left; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .items-table td { padding: 8px 0; }
        .items-table .price-col { text-align: right; }
        
        .total-row { display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 20px; }
        
        .footer { text-align: center; margin-top: 30px; font-size: 0.8rem; color: #999; }
        
        .btn-print {
            display: block; width: 100%; background: #333; color: white; border: none; padding: 12px;
            margin-top: 20px; cursor: pointer; border-radius: 5px; font-size: 1rem;
            box-sizing: border-box;
        }
        .btn-home {
            display: block; width: 100%; background: none; border: 1px solid #ccc; color: #555; padding: 12px;
            margin-top: 10px; cursor: pointer; border-radius: 5px; font-size: 1rem; text-decoration: none; text-align: center;
            box-sizing: border-box;
        }
        
        @media print {
            body { background: white; padding: 0; }
            .receipt-container { box-shadow: none; max-width: 100%; padding: 0; }
            .btn-print, .btn-home { display: none; }
        }
    </style>
</head>
<body>

    <div class="receipt-container">
        <div class="header text-center">
            <h2><?= htmlspecialchars($store_name) ?></h2>
            <p><?= nl2br(htmlspecialchars($store_address)) ?></p>
        </div>
        
        <div class="divider"></div>
        
        <div class="info-row">
            <span class="info-label">Order ID:</span>
            <span class="info-val">#<?= $order_id ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Date:</span>
            <span class="info-val"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Customer:</span>
            <span class="info-val"><?= htmlspecialchars($order['customer_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Type:</span>
            <span class="info-val"><?= ucfirst($order['delivery_method']) ?></span>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="price-col">Price</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['name']) ?><br>
                        <small style="color:#777;">x<?= $item['quantity'] ?> @ RM<?= $item['price_at_purchase'] ?></small>
                    </td>
                    <td class="price-col">RM <?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="divider"></div>
        
        <div class="total-row">
            <span>Total</span>
            <span>RM <?= number_format($order['total_amount'], 2) ?></span>
        </div>
        
        <div class="footer">
            <p>Thank you for your order!</p>
            <p>Please keep this receipt for verification.</p>
            <p>This is a computer generated receipt.</p>
        </div>
        
        <button onclick="window.print()" class="btn-print">Print Receipt</button>
        <a href="/" class="btn-home">Back to Store</a>
    </div>

</body>
</html>
