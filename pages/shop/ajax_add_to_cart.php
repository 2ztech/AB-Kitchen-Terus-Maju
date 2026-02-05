<?php
/**
 * Kuih Raya - AJAX Add to Cart
 * Location: pages/shop/ajax_add_to_cart.php
 */

require_once '../../config/config.php';

// Start session if not started (config usually handles this but safety first)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Support both JSON body and standard form data
    $product_id = $input['product_id'] ?? $_POST['product_id'] ?? null;
    $quantity = $input['quantity'] ?? $_POST['quantity'] ?? 1;

    if ($product_id) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = $quantity;
        }

        // Calculate total items in cart
        $cart_count = 0;
        foreach ($_SESSION['cart'] as $qty) {
            $cart_count += $qty;
        }

        echo json_encode([
            'success' => true, 
            'message' => 'Item added to cart', 
            'cart_count' => $cart_count
        ]);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
