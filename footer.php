<footer style="text-align: center; margin-top: 5px;">
<?php
if (!isset($settings) && isset($pdo) && function_exists('get_settings')) {
    $settings = get_settings($pdo);
}
$footer_store_name = $settings['store_name'] ?? 'My Digital Store';
?>
    <p>© <?= date('Y') ?> <?= htmlspecialchars($footer_store_name) ?>. All rights reserved.</p>
</footer>
