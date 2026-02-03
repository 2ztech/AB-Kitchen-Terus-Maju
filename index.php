<?php
// index.php - Public Customer Page
require_once 'config/db.php';

// Get the database connection
$pdo = Database::getInstance();

// Fetch all active products
$stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Kuih Raya</title>
    <style>
        /* Simple inline CSS for the shop view */
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
        .card { background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: center; }
        .card img { width: 100%; height: 150px; object-fit: cover; border-radius: 4px; }
        .price { color: #d9534f; font-weight: bold; font-size: 1.2em; }
        .btn-order { background: #28a745; color: white; padding: 10px; text-decoration: none; display: block; margin-top: 10px; border-radius: 4px; }
        .sold-out { background: #ccc; cursor: not-allowed; }
    </style>
</head>
<body>

    <div class="header">
        <h1>✨ Kuih Raya Orders</h1>
        <a href="login.php" style="font-size: 0.8em; color: #666;">Staff Login</a>
    </div>

    <div class="grid">
        <?php if (count($products) > 0): ?>
            <?php foreach ($products as $kuih): ?>
                <div class="card">
                    <img src="<?= $kuih['image_path'] ? 'uploads/'.$kuih['image_path'] : 'https://placehold.co/200x150?text=No+Image' ?>" alt="<?= htmlspecialchars($kuih['name']) ?>">
                    
                    <h3><?= htmlspecialchars($kuih['name']) ?></h3>
                    <p class="price">RM <?= number_format($kuih['price'], 2) ?></p>
                    <p>Stock: <?= $kuih['stock_quantity'] ?> jars</p>

                    <?php if ($kuih['stock_quantity'] > 0): ?>
                        <a href="order_form.php?id=<?= $kuih['id'] ?>" class="btn-order">Order Now</a>
                    <?php else: ?>
                        <span class="btn-order sold-out">Sold Out</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No kuih added yet. Login to the admin dashboard to add products!</p>
        <?php endif; ?>
    </div>

</body>
</html>