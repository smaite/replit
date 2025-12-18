<?php
/**
 * Wishlist API - Add/Remove/List wishlist items
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once '../config/database.php';
require_once 'config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API key verification is handled by config.php

// Get user from auth header
$user = getAuthUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}
$user_id = $user['id'];

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Create wishlist table if not exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_wishlist (user_id, product_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )
    ");
    
    switch ($method) {
        case 'GET':
            // Get user's wishlist with product details
            $stmt = $conn->prepare("
                SELECT w.id as wishlist_id, w.created_at as added_at,
                       p.id, p.name, p.price, p.sale_price, p.image, p.stock,
                       c.name as category_name
                FROM wishlist w
                JOIN products p ON w.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE w.user_id = ? AND p.status = 'active'
                ORDER BY w.created_at DESC
            ");
            $stmt->execute([$user_id]);
            $items = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ]);
            break;
            
        case 'POST':
            // Add to wishlist
            $input = json_decode(file_get_contents('php://input'), true);
            $product_id = (int)($input['product_id'] ?? 0);
            
            if (!$product_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Product ID required']);
                exit;
            }
            
            // Check if product exists
            $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$product_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Product not found']);
                exit;
            }
            
            // Check if already in wishlist
            $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => true, 'message' => 'Already in wishlist']);
                exit;
            }
            
            // Add to wishlist
            $stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Added to wishlist',
                'wishlist_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'DELETE':
            // Remove from wishlist
            $product_id = (int)($_GET['product_id'] ?? 0);
            
            if (!$product_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Product ID required']);
                exit;
            }
            
            $stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Removed from wishlist'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
