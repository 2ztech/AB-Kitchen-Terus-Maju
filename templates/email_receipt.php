<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Receipt</title>
</head>
<body style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; background-color: #f0f2f5; -webkit-font-smoothing: antialiased;">
    
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; margin-top: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
        
        <!-- Header -->
        <div style="background-color: #ffffff; padding: 30px 40px; border-bottom: 1px solid #f0f0f0; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; color: #333; letter-spacing: -0.5px;"><?= htmlspecialchars($store_name) ?></h1>
            <p style="margin: 5px 0 0; color: #888; font-size: 14px;"><?= htmlspecialchars($store_address) ?></p>
        </div>

        <!-- Hero Section -->
        <div style="padding: 40px 40px 20px; text-align: center;">
            <div style="width: 60px; height: 60px; background-color: #e8f5e9; border-radius: 50%; display: inline-block; line-height: 60px; text-align: center; margin-bottom: 15px;">
                <span style="font-size: 30px; color: #2e7d32; vertical-align: middle; line-height: normal;">✓</span>
            </div>
            <h2 style="margin: 0 0 10px; font-size: 22px; color: #2c3e50;">Payment Successful</h2>
            <h1 style="margin: 0; font-size: 36px; color: #2e7d32; font-weight: 700;">RM <?= number_format($order['total_amount'], 2) ?></h1>
            <p style="margin: 10px 0 0; color: #666; font-size: 14px;">Paid via <?= ucfirst($order['payment_method'] ?? 'cash') ?></p>
        </div>

        <hr style="border: none; border-top: 2px dashed #e0e0e0; margin: 20px 40px;">

        <!-- Order Info -->
        <div style="padding: 0 40px;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="color: #666; font-size: 14px; padding-bottom: 15px;">Order ID</td>
                    <td style="text-align: right; color: #333; font-weight: 600; font-size: 14px; padding-bottom: 15px;">#<?= $order['id'] ?></td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px; padding-bottom: 15px;">Date</td>
                    <td style="text-align: right; color: #333; font-weight: 600; font-size: 14px; padding-bottom: 15px;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
                </tr>
                <tr>
                    <td style="color: #666; font-size: 14px; padding-bottom: 15px;">Customer</td>
                    <td style="text-align: right; color: #333; font-weight: 600; font-size: 14px; padding-bottom: 15px;"><?= htmlspecialchars($order['customer_name']) ?></td>
                </tr>
            </table>
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
