<?php
/**
 * Kuih Raya - Track Order
 * Location: pages/shop/track_order.php
 */

require_once '../../config/db.php';

$orders = [];
$phone = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['phone'])) {
    $phone = trim($_GET['phone']);
    if (!empty($phone)) {
        try {
            // Find orders by phone with items
            $stmt = $pdo->prepare("
                SELECT o.*, oi.product_id, oi.quantity, oi.price_at_purchase, p.name as product_name
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE o.customer_phone = ? 
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$phone]);
            $raw_data = $stmt->fetchAll();
            
            // Group by Order ID
            $orders = [];
            foreach ($raw_data as $row) {
                $orderId = $row['id'];
                if (!isset($orders[$orderId])) {
                    $orders[$orderId] = [
                        'id' => $row['id'],
                        'created_at' => $row['created_at'],
                        'total_amount' => $row['total_amount'],
                        'status' => $row['status'],
                        'items' => []
                    ];
                }
                if ($row['product_name']) { // Check if item exists
                    $orders[$orderId]['items'][] = [
                        'name' => $row['product_name'],
                        'quantity' => $row['quantity'],
                        'price' => $row['price_at_purchase']
                    ];
                }
            }
            
            if (empty($orders)) {
                $error = "No orders found for this phone number.";
            }
        } catch (Exception $e) {
            $error = "Error fetching orders.";
        }
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="../../styles/shop.css?v=<?= filemtime(__DIR__ . '/../../styles/shop.css') ?>">
    <style>
        .track-container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .search-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
        .search-input { padding: 15px; width: 60%; font-size: 1.1rem; border: 1px solid #ddd; border-radius: 6px; margin-right: 10px; }
        .btn-track { padding: 15px 30px; background: var(--accent-color); color: white; border: none; border-radius: 6px; font-size: 1.1rem; cursor: pointer; }
        
        .order-list { margin-top: 40px; }
        .order-card { 
            background: white; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); 
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .order-card:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        
        .order-header {
            padding: 20px;
            display: flex; 
            justify-content: space-between; 
            align-items: center;
        }
        
        .order-info h3 { margin: 0 0 5px 0; color: #333; }
        .order-meta { color: #777; font-size: 0.9rem; }
        
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8em; text-transform: uppercase; font-weight: bold; }
        .status-pending { background: #ffeeba; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .order-details {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: #f9f9f9;
            border-top: 1px solid #eee;
        }
        
        .order-details.open {
            max-height: 500px; /* Arbitrary large height */
            transition: max-height 0.3s ease-in;
        }
        
        .item-row {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
        }
        .item-row:last-child { border-bottom: none; }
        
        .toggle-icon { font-size: 1.5rem; color: #ccc; transition: transform 0.3s; }
        .order-card.active .toggle-icon { transform: rotate(180deg); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="track-container">
        <h1 style="text-align:center;margin-bottom:30px;">Track Your Order</h1>

        <div class="search-box">
            <p style="margin-bottom:20px;color:#666;">Enter your phone number to see your order history and status.</p>
            <form method="GET">
                <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>" class="search-input" placeholder="e.g. 0123456789" required>
                <button type="submit" class="btn-track">Track</button>
            </form>
            <?php if ($error): ?>
                <p style="color:#dc3545;margin-top:15px;"><?= $error ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($orders)): ?>
        <div class="order-list">
            <h2 style="margin-bottom:20px;">Your Orders</h2>
            <?php foreach ($orders as $o): ?>
            <div class="order-card" onclick="toggleOrder(this)">
                <div class="order-header">
                    <div class="order-info">
                        <h3>Order #<?= $o['id'] ?></h3>
                        <div class="order-meta">
                            <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?> &bull; 
                            RM <?= number_format($o['total_amount'], 2) ?> &bull; 
                            <span class="badge status-<?= $o['status'] ?>"><?= $o['status'] ?></span>
                        </div>
                    </div>
                    <div>
                        <i class='bx bx-chevron-down toggle-icon'></i>
                    </div>
                </div>
                
                <div class="order-details">
                    <div style="padding: 10px 20px; background: #eee; font-weight: bold; font-size: 0.85rem; color: #666; text-transform: uppercase; letter-spacing: 0.5px;">Order Items</div>
                    <?php foreach ($o['items'] as $item): ?>
                    <div class="item-row">
                        <div>
                            <strong><?= htmlspecialchars($item['name']) ?></strong> 
                            <span style="color:#777; margin-left:5px;">x<?= $item['quantity'] ?></span>
                        </div>
                        <div>RM <?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="item-row" style="background:white; font-weight:bold; border-top:1px solid #ddd;">
                        <div>Total</div>
                        <div>RM <?= number_format($o['total_amount'], 2) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include '../../footer.php'; ?>
    
    <script>
        function toggleOrder(card) {
            card.classList.toggle('active');
            const details = card.querySelector('.order-details');
            if (card.classList.contains('active')) {
                details.classList.add('open');
            } else {
                details.classList.remove('open');
            }
        }
    </script>
</body>
</html>
