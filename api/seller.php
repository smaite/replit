<?php
/**
 * Seller API - For vendor product and order management in mobile app
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once '../config/database.php';
require_once 'config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API key verification is handled by config.php

// Get user from auth header - must be a vendor
$user = getAuthUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}
$user_id = $user['id'];

// Check if user is a vendor
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vendor access required']);
    exit;
}

$vendor_id = $vendor['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            // Get vendor dashboard stats
            $stats = [];
            
            // Total products
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
            $stmt->execute([$vendor_id]);
            $stats['total_products'] = (int)$stmt->fetchColumn();
            
            // Active products
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND status = 'active'");
            $stmt->execute([$vendor_id]);
            $stats['active_products'] = (int)$stmt->fetchColumn();
            
            // Total orders (items from vendor)
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT o.id) 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.vendor_id = ?
            ");
            $stmt->execute([$vendor_id]);
            $stats['total_orders'] = (int)$stmt->fetchColumn();
            
            // Pending orders
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT o.id) 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.vendor_id = ? AND o.status = 'pending'
            ");
            $stmt->execute([$vendor_id]);
            $stats['pending_orders'] = (int)$stmt->fetchColumn();
            
            // Total revenue
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE p.vendor_id = ? AND o.status IN ('delivered', 'processing', 'shipped')
            ");
            $stmt->execute([$vendor_id]);
            $stats['total_revenue'] = round((float)$stmt->fetchColumn(), 2);
            
            // Recent orders
            $stmt = $conn->prepare("
                SELECT DISTINCT o.id, o.order_number, o.status, o.created_at,
                       u.full_name as customer_name,
                       SUM(oi.quantity * oi.price) as order_total
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                WHERE p.vendor_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$vendor_id]);
            $stats['recent_orders'] = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        case 'products':
            if ($method === 'GET') {
                // List vendor's products
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = 20;
                $offset = ($page - 1) * $limit;
                
                $stmt = $conn->prepare("
                    SELECT p.*, c.name as category_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.vendor_id = ?
                    ORDER BY p.created_at DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute([$vendor_id]);
                $products = $stmt->fetchAll();
                
                echo json_encode(['success' => true, 'data' => $products]);
                
            } elseif ($method === 'POST') {
                // Add new product
                $input = json_decode(file_get_contents('php://input'), true);
                
                $name = trim($input['name'] ?? '');
                $description = trim($input['description'] ?? '');
                $price = (float)($input['price'] ?? 0);
                $sale_price = isset($input['sale_price']) && $input['sale_price'] > 0 ? (float)$input['sale_price'] : null;
                $category_id = (int)($input['category_id'] ?? 0);
                $stock = (int)($input['stock'] ?? 0);
                
                if (empty($name) || $price <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Name and price are required']);
                    exit;
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO products (vendor_id, name, description, price, sale_price, category_id, stock, status, verification_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', 'pending')
                ");
                $stmt->execute([$vendor_id, $name, $description, $price, $sale_price, $category_id, $stock]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Product created',
                    'product_id' => $conn->lastInsertId()
                ]);
                
            } elseif ($method === 'PUT') {
                // Update product
                $input = json_decode(file_get_contents('php://input'), true);
                $product_id = (int)($input['product_id'] ?? 0);
                
                // Verify ownership
                $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $vendor_id]);
                if (!$stmt->fetch()) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Product not found']);
                    exit;
                }
                
                $updates = [];
                $params = [];
                
                if (isset($input['name'])) { $updates[] = 'name = ?'; $params[] = trim($input['name']); }
                if (isset($input['description'])) { $updates[] = 'description = ?'; $params[] = trim($input['description']); }
                if (isset($input['price'])) { $updates[] = 'price = ?'; $params[] = (float)$input['price']; }
                if (isset($input['sale_price'])) { $updates[] = 'sale_price = ?'; $params[] = $input['sale_price'] > 0 ? (float)$input['sale_price'] : null; }
                if (isset($input['stock'])) { $updates[] = 'stock = ?'; $params[] = (int)$input['stock']; }
                if (isset($input['category_id'])) { $updates[] = 'category_id = ?'; $params[] = (int)$input['category_id']; }
                
                if (empty($updates)) {
                    echo json_encode(['success' => true, 'message' => 'No changes']);
                    exit;
                }
                
                $params[] = $product_id;
                $stmt = $conn->prepare("UPDATE products SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?");
                $stmt->execute($params);
                
                echo json_encode(['success' => true, 'message' => 'Product updated']);
                
            } elseif ($method === 'DELETE') {
                // Delete product
                $product_id = (int)($_GET['product_id'] ?? 0);
                
                // Verify ownership and delete
                $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $vendor_id]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Product deleted']);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Product not found']);
                }
            }
            break;
            
        case 'orders':
            // Get vendor's orders
            $status = $_GET['status'] ?? null;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;
            
            $where = "p.vendor_id = ?";
            $params = [$vendor_id];
            
            if ($status) {
                $where .= " AND o.status = ?";
                $params[] = $status;
            }
            
            $stmt = $conn->prepare("
                SELECT DISTINCT o.id, o.order_number, o.status, o.payment_method, o.payment_status,
                       o.shipping_address, o.shipping_phone, o.created_at,
                       u.full_name as customer_name, u.email as customer_email,
                       SUM(oi.quantity * oi.price) as vendor_total,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                WHERE $where
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $orders]);
            break;
            
        case 'order-detail':
            // Get order detail with vendor's items
            $order_id = (int)($_GET['order_id'] ?? 0);
            
            // Get order info
            $stmt = $conn->prepare("
                SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            
            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit;
            }
            
            // Get vendor's items only
            $stmt = $conn->prepare("
                SELECT oi.*, p.name, p.image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ? AND p.vendor_id = ?
            ");
            $stmt->execute([$order_id, $vendor_id]);
            $items = $stmt->fetchAll();
            
            if (empty($items)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'No items from your store in this order']);
                exit;
            }
            
            $order['items'] = $items;
            $order['vendor_total'] = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $items));
            
            echo json_encode(['success' => true, 'data' => $order]);
            break;
            
        case 'categories':
            // Get available categories
            $stmt = $conn->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
            $stmt->execute();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
