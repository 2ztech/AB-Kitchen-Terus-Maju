<?php
/**
 * Kuih Raya - AJAX Category Manager
 * Location: pages/settings/ajax_categories.php
 */

require_once '../../config/config.php';

// Security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Category name required']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$name]);
        
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'name' => $name]);

    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        
        // Optional: Check if used by products
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmtCheck->execute([$id]);
        if ($stmtCheck->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'message' => 'Cannot delete: Category is in use.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true]);

    } elseif ($action === 'list') {
        $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $cats]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }

} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
         echo json_encode(['success' => false, 'message' => 'Category already exists']);
    } else {
         echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
