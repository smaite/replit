<?php
/**
 * Orders API Endpoint
 * GET /api/orders - List user orders
 * GET /api/orders/:id - Get order details
 */

require_once '../config/database.php';
require_once './config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($path, '/'));

// Extract ID if present
$orderId = null;
if (count($pathSegments) > 2 && is_numeric($pathSegments[2])) {
    $orderId = (int)$pathSegments[2];
}

// Check authentication
$userId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
    // In a real app, validate the JWT token
    // For now, accept any bearer token
    $userId = ApiRequest::getParameter('user_id');
}

try {
    if ($method === 'GET') {
        if (!$userId) {
            echo ApiResponse::error('Unauthorized', 401);
            exit;
        }

        if ($orderId) {
            // Get single order with items
            $stmt = $conn->prepare("
                SELECT 
                    o.id,
                    o.order_code,
                    o.user_id,
                    o.total_price,
                    o.status,
                    o.shipping_address,
                    o.created_at,
                    o.updated_at
                FROM orders o
                WHERE o.id = ? AND o.user_id = ?
                LIMIT 1
            ");
            $stmt->execute([$orderId, $userId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($order) {
                // Get order items
                $itemsStmt = $conn->prepare("
                    SELECT 
                        id,
                        product_id,
                        quantity,
                        price
                    FROM order_items
                    WHERE order_id = ?
                ");
                $itemsStmt->execute([$orderId]);
                $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                // Convert types
                $order['total_price'] = (float)$order['total_price'];
                foreach ($order['items'] as &$item) {
                    $item['quantity'] = (int)$item['quantity'];
                    $item['price'] = (float)$item['price'];
                }

                echo ApiResponse::success($order, 'Order fetched successfully');
            } else {
                echo ApiResponse::error('Order not found', 404);
            }
        } else {
            // List user orders with pagination
            $page = max(1, (int)ApiRequest::getParameter('page', 1));
            $limit = min(50, max(1, (int)ApiRequest::getParameter('limit', 10)));
            $offset = ($page - 1) * $limit;

            $stmt = $conn->prepare("
                SELECT 
                    id,
                    order_code,
                    user_id,
                    total_price,
                    status,
                    created_at,
                    updated_at
                FROM orders
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convert types
            foreach ($orders as &$order) {
                $order['total_price'] = (float)$order['total_price'];
            }

            // Get total count
            $countStmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
            $countStmt->execute([$userId]);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            echo ApiResponse::success([
                'items' => $orders,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ], 'Orders fetched successfully');
        }
    } else {
        echo ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    echo ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
