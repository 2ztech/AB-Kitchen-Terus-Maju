<?php
/**
 * Kuih Raya - Product List
 * Location: pages/products/product_list.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Authentication: Admin or Cashier
if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit();
}

$isAdmin = ($_SESSION['role'] === 'admin');

// Handle Delete (Admin Only)
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    $id = $_POST['product_id'];
    try {
        // Optional: Delete image file too
        $stmt = $pdo->prepare("SELECT image_url FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $img = $stmt->fetchColumn();
        if ($img && file_exists(__DIR__ . '/../../images/product/' . $img)) {
            unlink(__DIR__ . '/../../images/product/' . $img);
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Product deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Products
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name ASC");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product List - Kuih Raya</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .product-table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .product-table th, .product-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .product-table th { background: #f8f9fa; }
        .product-img { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
        .stock-low { color: red; font-weight: bold; }
        .stock-ok { color: green; }
        .btn-action { padding: 5px 10px; border-radius: 4px; text-decoration: none; color: white; font-size: 0.9em; margin-right: 5px;}
        .btn-edit { background: #007bff; }
        .btn-delete { background: #dc3545; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1>Product Inventory</h1>
                <p>View and manage your Kuih Raya stock.</p>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <div style="margin-bottom:15px;">
                <a href="add_product.php" class="btn-add" style="background:#28a745;color:white;padding:10px 15px;text-decoration:none;border-radius:4px;display:inline-block;">+ Add New Product</a>
                <a href="export_products.php" class="btn-action" style="background:#17a2b8;padding:10px 15px;float:right;margin-left:5px;">Export CSV</a>
                <a href="import_products.php" class="btn-action" style="background:#6c757d;padding:10px 15px;float:right;">Import CSV</a>
            </div>
        <?php endif; ?>

        <table class="product-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Price (RM)</th>
                    <th>Stock</th>
                    <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <?php if ($p['image_url']): ?>
                            <img src="../../images/product/<?= htmlspecialchars($p['image_url']) ?>" class="product-img">
                        <?php else: ?>
                            <div style="width:50px;height:50px;background:#eee;display:flex;align-items:center;justify-content:center;color:#999;font-size:10px;">No Img</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($p['name']) ?></strong><br>
                        <small style="color:#666;"><?= htmlspecialchars(substr($p['description'], 0, 50)) ?>...</small>
                    </td>
                    <td><?= number_format($p['price'], 2) ?></td>
                    <td class="<?= $p['stock'] < 10 ? 'stock-low' : 'stock-ok' ?>">
                        <?= $p['stock'] ?>
                    </td>
                    <?php if ($isAdmin): ?>
                    <td>
                        <a href="edit_product.php?id=<?= $p['id'] ?>" class="btn-action btn-edit">Edit</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" name="delete_product" class="btn-action btn-delete">Delete</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
    <?php include '../../footer.php'; ?>
</body>
</html>
