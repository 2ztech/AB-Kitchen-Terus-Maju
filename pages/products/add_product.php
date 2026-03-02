<?php
/**
 * Kuih Raya - Add Product
 * Location: pages/products/add_product.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

// Admin Only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /products"); // or index
    exit();
}

$error = '';
$success = '';

// Fetch Categories
$stmtCat = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmtCat->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $price = $_POST['price']; // Sell Price
$stock_price = !empty($_POST['stock_price']) ? (float)$_POST['stock_price'] : 0.00;    $stock = $_POST['stock'];
    $category_id = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
    
    // Image Upload
    $image_url = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            if ($_FILES['image']['size'] > 2 * 1024 * 1024) {
                 $error = "File size must be less than 2MB.";
            } else {
                $dir = '../../images/product/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                
                // New Format: alphanumeric-product-name_timestamp.ext
                $clean_name = preg_replace('/[^a-zA-Z0-9]/', '-', strtolower($name));
                $filename = $clean_name . '_' . time() . '.' . $ext;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $dir . $filename)) {
                    $image_url = $filename;
                } else {
                    $error = "Failed to upload image.";
                }
            }
        } else {
            $error = "Invalid image format. Use JPG, PNG, or WEBP.";
        }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_price, stock, category_id, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $desc, $price, $stock_price, $stock, $category_id, $image_url]);
            $success = "Product added successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Kuih Raya</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .form-container { background: white; padding: 25px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn-submit { background: #28a745; color: white; padding: 12px; width: 100%; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1>Add New Product</h1>
            </div>
        </div>

        <div class="form-container">
            <?php if ($error): ?>
                <div style="background:#f8d7da;color:#721c24;padding:10px;margin-bottom:15px;"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;"><?= $success ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="category_id" class="form-control" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;">
                        <option value="">-- No Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="display:flex;gap:20px;">
                    <div style="flex:1;">
                        <label>Stock Price (Cost)</label>
                        <input type="number" step="0.01" name="stock_price" placeholder="0.00">
                    </div>
                    <div style="flex:1;">
                        <label>Sell Price (RM)</label>
                        <input type="number" step="0.01" name="price" required>
                    </div>
                    <div style="flex:1;">
                        <label>Initial Stock</label>
                        <input type="number" name="stock" value="0" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <button type="submit" class="btn-submit">Add Product</button>
            </form>
            <a href="/products" style="display:block;text-align:center;margin-top:15px;color:#666;">Back to List</a>
        </div>
    </main>
    <?php include '../../footer.php'; ?>
</body>
</html>
