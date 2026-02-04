<?php
/**
 * Kuih Raya - Store Closed Page
 * Location: pages/shop/closed.php
 */
// Ensure this file handles its own basic header/style since regular header might show links we don't want (like cart)
// But to keep it consistent, we can include a simplified header or just custom HTML.
// Let's go with custom nice page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Closed - <?= htmlspecialchars($settings['store_name'] ?? 'My Digital Store') ?></title>
    <?php if (!empty($settings['store_favicon'])): ?>
        <link rel="icon" href="/images/settings/<?= htmlspecialchars($settings['store_favicon']) ?>" type="image/x-icon">
    <?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Outfit', sans-serif; 
            margin: 0; 
            height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: #fdf5e6; 
            text-align: center; 
            color: #333;
        }
        .container {
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        h1 { margin: 0 0 20px 0; font-size: 2.5rem; color: #d35400; }
        p { font-size: 1.1rem; line-height: 1.6; color: #666; margin-bottom: 30px; }
        .icon { font-size: 80px; margin-bottom: 20px; display: block; }
        .hours { background: #eee; padding: 15px; border-radius: 10px; display: inline-block; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <span class="icon">🌙</span>
        <h1>We Are Currently Closed</h1>
        <p>
            Maaf, kedai tutup buat masa ini.<br>
            Please check back during our operation hours.
        </p>

        <?php if (!empty($settings['operation_hours'])): ?>
            <div class="hours">
                Business Hours:<br>
                <?= htmlspecialchars($settings['operation_hours']) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['whatsapp_number'])): ?>
            <div style="margin-top:30px;">
                <p>Have a question?</p>
                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $settings['whatsapp_number']) ?>" 
                   style="background:#25d366;color:white;text-decoration:none;padding:10px 20px;border-radius:20px;">
                   Contact us on WhatsApp
                </a>
            </div>
        <?php endif; ?>
        
    </div>
</body>
</html>
