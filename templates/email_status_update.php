<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f0f2f5; -webkit-font-smoothing: antialiased;">
    
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <!-- Header -->
        <div style="background-color: #ffffff; padding: 30px 40px; border-bottom: 1px solid #f0f0f0; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; color: #333; letter-spacing: -0.5px;"><?= htmlspecialchars($store_name) ?></h1>
            <p style="margin: 5px 0 0; color: #888; font-size: 14px;"><?= htmlspecialchars($store_address ?? '') ?></p>
        </div>

        <!-- Hero Section -->
        <div style="padding: 40px 40px 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background-color: #e3f2fd; border-radius: 50%; display: inline-block; line-height: 60px; text-align: center; margin-bottom: 15px;">
                <span style="font-size: 30px; color: #1976d2; vertical-align: middle; line-height: normal;">ℹ</span>
            </div>
            <h2 style="margin: 0 0 10px; font-size: 22px; color: #2c3e50;">Order Status Update</h2>
        </div>

        <hr style="border: none; border-top: 2px dashed #e0e0e0; margin: 0 40px 20px;">

        <!-- Message Info -->
        <div style="padding: 0 40px;">
            <p style="color: #333; font-size: 16px; margin-bottom: 10px;">Hi <?= htmlspecialchars($order['customer_name']) ?>,</p>
            <p style="color: #555; font-size: 15px; line-height: 1.5; margin-bottom: 20px;">
                <?php if ($newStatus === 'ready_for_pickup'): ?>
                    Your order #<?= htmlspecialchars($order['id']) ?> is packed and ready for you to pick up at our store. See you soon!
                <?php elseif ($newStatus === 'ready_for_delivery'): ?>
                    Good news! Your order #<?= htmlspecialchars($order['id']) ?> is on its way to you.
                <?php endif; ?>
            </p>
        </div>

        <div style="background-color: #fafafa; padding: 20px 40px; margin: 10px 0;">
            <h3 style="margin: 0 0 15px; font-size: 16px; color: #333; font-weight: 600;">Order Summary</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach ($items as $item): ?>
                <tr>
                    <td style="padding: 8px 0; color: #555; font-size: 14px;">
                        <span style="color: #333; font-weight: 500;"><?= htmlspecialchars($item['name']) ?></span> <span style="color:#888; font-size:12px;">x<?= $item['quantity'] ?></span>
                    </td>
                    <td style="padding: 8px 0; text-align: right; color: #333; font-size: 14px;">RM <?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            
            <table style="width: 100%; border-collapse: collapse; margin-top: 15px; border-top: 1px solid #eee;">
                <tr>
                    <td style="padding: 15px 0 0 0; font-weight: 700; color: #333; font-size: 16px;">Total</td>
                    <td style="padding: 15px 0 0 0; text-align: right; font-weight: 700; color: #2e7d32; font-size: 18px;">RM <?= number_format($order['total_amount'], 2) ?></td>
                </tr>
            </table>
        </div>

        <!-- Footer -->
        <div style="padding: 30px 40px; text-align: center; background-color: #f8f9fa; border-top: 1px solid #f0f0f0;">
            <p style="margin: 0 0 10px; font-size: 12px; color: #999;">Need help? Contact us efficiently.</p>
            <p style="margin: 0; font-size: 12px; color: #bbb;">&copy; <?= date('Y') ?> <?= htmlspecialchars($store_name) ?>. All rights reserved.</p>
        </div>
    </div>
    
    <div style="text-align: center; padding-bottom: 20px; color: #999; font-size: 11px;">
        This email was automatically generated. Please do not reply.
    </div>

</body>
</html>
