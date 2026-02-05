<?php
/**
 * Kuih Raya - Admin Order List
 * Location: pages/orders/list.php
 */

require_once '../../config/config.php';
require_once '../../header.php';
require_once '../../sidenav.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'cashier'])) {
    header("Location: /index.php");
    exit();
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['order_id'], $_POST['status'])) {
    $new_status = $_POST['status'];
    $order_id = $_POST['order_id'];
    
    // Validate status
    $allowed_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    if (in_array($new_status, $allowed_statuses)) {
        try {
            $stmtUpdate = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmtUpdate->execute([$new_status, $order_id]);
            $success_msg = "Order #$order_id status updated to " . ucfirst($new_status);
        } catch (PDOException $e) {
            $error_msg = "Failed to update status.";
        }
    }
}

// Build Query with Filter
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$sql = "SELECT * FROM orders";
$params = [];

if ($filter_status && in_array($filter_status, ['pending', 'processing', 'completed', 'cancelled'])) {
    $sql .= " WHERE status = ?";
    $params[] = $filter_status;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Helper for UI
$page_title = $filter_status ? ucfirst($filter_status) . " Orders" : "All Orders";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Management</title>
    <link rel="stylesheet" href="../../styles/admin_dashboard.css">
    <style>
        .data-table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .data-table th { background: #f8f9fa; }
        
        .badge { padding: 5px 10px; border-radius: 4px; font-size: 0.8em; text-transform: uppercase; font-weight: bold; }
        .status-pending { background: #ffeeba; color: #856404; }
        .status-processing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn-link { color: #007bff; text-decoration: none; font-size: 0.9em; }
        .btn-link:hover { text-decoration: underline; }

        /* Action Form */
        .status-form { display: flex; gap: 5px; align-items: center; }
        .status-select { padding: 5px; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9em; }
        .btn-update { padding: 5px 10px; background: #333; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.8em; }
        .btn-update:hover { background: #555; }
    </style>
</head>
<body>
    <main class="dashboard-container" id="main">
        <div class="dashboard-header">
            <span class="menu-toggle" onclick="openNav(event)">&#9776;</span>
            <div class="welcome-banner">
                <h1><?= $page_title ?></h1>
            </div>
        </div>

        <?php if (isset($success_msg)): ?>
            <div style="background:#d4edda;color:#155724;padding:10px;margin-bottom:15px;border-radius:4px;"><?= $success_msg ?></div>
        <?php endif; ?>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Receipt</th>
                    <th>Order Details</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:20px;color:#777;">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= $o['id'] ?></td>
                        <td><?= date('d M Y, H:i', strtotime($o['created_at'])) ?></td>
                        <td>
                            <strong><?= htmlspecialchars($o['customer_name'] ?? 'Guest') ?></strong><br>
                            <small><?= htmlspecialchars($o['delivery_method']) ?></small>
                        </td>
                        <td>RM <?= number_format($o['total_amount'], 2) ?></td>
                        <td>
                            <span class="badge status-<?= $o['status'] ?>"><?= $o['status'] ?></span>
                        </td>
                        <td>
                            <?php if (!empty($o['receipt_image'])): ?>
                                <a href="../../images/receipts/<?= htmlspecialchars($o['receipt_image']) ?>" target="_blank" class="btn-link">View Receipt</a>
                            <?php else: ?>
                                <span style="color:#999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" onclick="viewItems(<?= $o['id'] ?>)" class="btn btn-primary" style="padding:6px 12px;font-size:0.85em;">
                                <i class='bx bx-list-ul'></i> View Items
                            </button>
                        </td>
                        <td>
                            <form method="POST" class="status-form">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <input type="hidden" name="update_status" value="1">
                                <select name="status" class="status-select">
                                    <option value="pending" <?= $o['status']=='pending'?'selected':'' ?>>Pending</option>
                                    <option value="processing" <?= $o['status']=='processing'?'selected':'' ?>>Processing</option>
                                    <option value="completed" <?= $o['status']=='completed'?'selected':'' ?>>Completed</option>
                                    <option value="cancelled" <?= $o['status']=='cancelled'?'selected':'' ?>>Cancelled</option>
                                </select>
                                <button type="submit" class="btn-update">Update</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <!-- View Items Modal -->
    <div id="itemsModal" class="modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);">
        <div style="background:white;margin:5% auto;padding:25px;width:90%;max-width:600px;border-radius:12px;box-shadow:0 5px 25px rgba(0,0,0,0.2);position:relative;">
            <span onclick="closeModal()" style="position:absolute;top:15px;right:20px;font-size:28px;cursor:pointer;color:#aaa;">&times;</span>
            <h2 style="margin-top:0;margin-bottom:20px;color:#333;">Order Details</h2>
            <div id="modalContent">Loading...</div>
        </div>
    </div>

    <?php include '../../footer.php'; ?>
    
    <script>
        function viewItems(orderId) {
            const modal = document.getElementById('itemsModal');
            const content = document.getElementById('modalContent');
            
            modal.style.display = 'block';
            content.innerHTML = '<div style="text-align:center;padding:20px;"><i class="bx bx-loader-alt bx-spin" style="font-size:2rem;color:#888;"></i><p>Loading items...</p></div>';
            
            // Fetch items
            const formData = new FormData();
            formData.append('order_id', orderId);
            
            fetch('ajax_get_items.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                content.innerHTML = html;
            })
            .catch(err => {
                content.innerHTML = '<p style="color:red">Error loading items.</p>';
                console.error(err);
            });
        }

        function closeModal() {
            document.getElementById('itemsModal').style.display = 'none';
        }

        // Close on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('itemsModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
