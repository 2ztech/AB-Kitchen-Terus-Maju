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
            // Find orders by phone
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_phone = ? ORDER BY created_at DESC");
            $stmt->execute([$phone]);
            $orders = $stmt->fetchAll();
            
            if (count($orders) === 0) {
                $error = "No orders found for this phone number.";
            }
        } catch (Exception $e) {
            $error = "Error fetching orders.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Track Order - Kuih Raya</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../styles/shop.css">
    <style>
        .track-container { max-width: 800px; margin: 50px auto; padding: 20px; }
        .search-box { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); text-align: center; }
        .search-input { padding: 15px; width: 60%; font-size: 1.1rem; border: 1px solid #ddd; border-radius: 6px; margin-right: 10px; }
        .btn-track { padding: 15px 30px; background: var(--accent-color); color: white; border: none; border-radius: 6px; font-size: 1.1rem; cursor: pointer; }
        
        .order-list { margin-top: 40px; }
        .order-card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .order-info h3 { margin: 0 0 5px 0; color: #333; }
        .order-meta { color: #777; font-size: 0.9rem; }
        
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8em; text-transform: uppercase; font-weight: bold; }
        .status-pending { background: #ffeeba; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-view { padding: 8px 15px; border: 1px solid #333; color: #333; text-decoration: none; border-radius: 4px; transition: all 0.2s; }
        .btn-view:hover { background: #333; color: white; }
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
            <div class="order-card">
                <div class="order-info">
                    <h3>Order #<?= $o['id'] ?></h3>
                    <div class="order-meta">
                        <?= date('d M Y, h:i A', strtotime($o['created_at'])) ?> &bull; 
                        RM <?= number_format($o['total_amount'], 2) ?> &bull; 
                        <span class="badge status-<?= $o['status'] ?>"><?= $o['status'] ?></span>
                    </div>
                </div>
                <div>
                    <a href="order_success.php?id=<?= $o['id'] ?>" class="btn-view">View Receipt</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
