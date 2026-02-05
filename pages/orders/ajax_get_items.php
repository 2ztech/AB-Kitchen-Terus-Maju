<?php
/**
 * Kuih Raya - AJAX Get Order Items
 * Location: pages/orders/ajax_get_items.php
 */

require_once '../../config/config.php';

// Security: Only staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    http_response_code(403);
    echo "Unauthorized";
    exit;
}

if (!isset($_POST['order_id'])) {
    echo "Invalid Order ID";
    exit;
}

$order_id = $_POST['order_id'];

try {
    // 1. Fetch Order Details for context (optional but good for print link)
    $stmtO = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
    $stmtO->execute([$order_id]);
    $order = $stmtO->fetch();

    if (!$order) {
        echo "Order not found.";
        exit;
    }

    // 2. Fetch Items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name 
        FROM order_items oi 
        JOIN products p ON oi.product_id = p.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        echo "<p>No items found for this order.</p>";
        exit;
    }

    // 3. Render Table
    echo '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">';
    echo '<thead><tr style="background:#f8f9fa;border-bottom:2px solid #eee;">
            <th style="padding:10px;text-align:left;">Item</th>
            <th style="padding:10px;text-align:center;">Qty</th>
            <th style="padding:10px;text-align:right;">Subtotal</th>
          </tr></thead>';
    echo '<tbody>';
    
    $total = 0;
    foreach ($items as $item) {
        $subtotal = $item['price_at_purchase'] * $item['quantity'];
        $total += $subtotal;
        
        echo '<tr>';
        echo '<td style="padding:10px;border-bottom:1px solid #eee;">' . htmlspecialchars($item['name']) . '</td>';
        echo '<td style="padding:10px;text-align:center;border-bottom:1px solid #eee;">' . $item['quantity'] . '</td>';
        echo '<td style="padding:10px;text-align:right;border-bottom:1px solid #eee;">RM ' . number_format($subtotal, 2) . '</td>';
        echo '</tr>';
    }
    
    echo '<tr style="font-weight:bold;background:#fafafa;">';
    echo '<td colspan="2" style="padding:10px;text-align:right;">Total:</td>';
    echo '<td style="padding:10px;text-align:right;color:#2c3e50;">RM ' . number_format($total, 2) . '</td>';
    echo '</tr>';
    
    echo '</tbody></table>';

    // Print Link Button
    echo '<div style="text-align:right;">';
    echo '<a href="/pages/shop/order_success.php?id='.$order_id.'" target="_blank" class="btn btn-primary" style="display:inline-block;text-decoration:none;">
            <i class="bx bx-printer"></i> Print Receipt
          </a>';
    echo '</div>';

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}
?>
