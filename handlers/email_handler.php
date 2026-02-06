<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/../vendor/autoload.php';

class EmailHandler {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    private function getSettings() {
        $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%' OR setting_key = 'store_name'");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    public function sendOrderReceipt($orderId) {
        $settings = $this->getSettings();
        
        // Check if SMTP is configured
        if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
            error_log("SMTP not configured. Skipping email for Order #$orderId");
            return false;
        }

        // Fetch Order Details
        $stmtOrder = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmtOrder->execute([$orderId]);
        $order = $stmtOrder->fetch();

        if (!$order) {
            error_log("Order #$orderId not found.");
            return false;
        }

        // Fetch Items
        $stmtItems = $this->pdo->prepare("
            SELECT oi.*, p.name 
            FROM order_items oi 
            JOIN products p ON oi.product_id = p.id 
            WHERE oi.order_id = ?
        ");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll();

        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_user'];
            $mail->Password   = $settings['smtp_pass'] ?? '';
            $mail->SMTPSecure = $settings['smtp_enc'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $settings['smtp_port'] ?? 587;

            // Recipients
            $fromName = $settings['smtp_from_name'] ?? ($settings['store_name'] ?? 'Store Admin');
            $fromEmail = $settings['smtp_from_email'] ?? $settings['smtp_user'];
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($order['customer_email'], $order['customer_name']);

            // Attachments (Receipt)
            if (!empty($order['receipt_image'])) {
                $receiptPath = __DIR__ . '/../images/receipts/' . $order['receipt_image'];
                if (file_exists($receiptPath)) {
                    $mail->addAttachment($receiptPath, 'PaymentReceipt.jpg');
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = "Order Receipt #" . $order['id'] . " - " . ($settings['store_name'] ?? 'My Store');
            
            // Build Body
            $body = "<h2>Thank you for your order, {$order['customer_name']}!</h2>";
            $body .= "<p>Your order <strong>#{$order['id']}</strong> has been placed successfully.</p>";
            $body .= "<p><strong>Total Amount:</strong> RM " . number_format($order['total_amount'], 2) . "</p>";
            $body .= "<h3>Order Summary:</h3><ul>";
            
            foreach ($items as $item) {
                $body .= "<li>{$item['name']} (x{$item['quantity']}) - RM " . number_format($item['price_at_purchase'] * $item['quantity'], 2) . "</li>";
            }
            $body .= "</ul>";
            $body .= "<p>We will process your order shortly.</p>";
            
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</li>'], ["\n", "\n\n", "\n"], $body));

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    public function sendTestEmail($toEmail) {
        $settings = $this->getSettings();
        
        if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
            return "SMTP settings are missing (Host or User empty).";
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $settings['smtp_host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $settings['smtp_user'];
            $mail->Password   = $settings['smtp_pass'] ?? '';
            $mail->SMTPSecure = $settings['smtp_enc'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $settings['smtp_port'] ?? 587;

            $fromName = $settings['smtp_from_name'] ?? ($settings['store_name'] ?? 'Store Admin');
            $fromEmail = $settings['smtp_from_email'] ?? $settings['smtp_user'];
            
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail);

            $mail->isHTML(true);
            $mail->Subject = "SMTP Test Email - " . $fromName;
            $mail->Body    = "<h1>It works!</h1><p>Your SMTP settings are correctly configured.</p>";

            $mail->send();
            return true;

        } catch (Exception $e) {
            return "Mailer Error: " . $mail->ErrorInfo;
        }
    }
}
