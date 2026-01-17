<?php
/**
 * Sasto Hub Orders API
 * Order management endpoints
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    case 'POST':
        handlePost();
        break;
    default:
        jsonError('Method not allowed', 405);
}

function handleGet() {
    global $conn;
    
    $user = requireAuth();
    
    // Single order by ID
    if (isset($_GET['id'])) {
        getOrderById((int)$_GET['id'], $user['id']);
        return;
    }
    
    // List all orders
    try {
        $stmt = $conn->prepare("
            SELECT 
                id,
                order_number,
                total_amount,
                shipping_cost,
                payment_method,
                payment_status,
                payment_reference,
                status,
                created_at
            FROM orders 
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as &$order) {
            $order['total_amount'] = (float)$order['total_amount'];
            $order['shipping_cost'] = (float)($order['shipping_cost'] ?? 0);
        }
        
        jsonSuccess(['orders' => $orders]);
        
    } catch (Exception $e) {
        jsonError('Failed to fetch orders');
    }
}

function getOrderById($orderId, $userId) {
    global $conn;
    
    try {
        // Get order
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$orderId, $userId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            jsonError('Order not found', 404);
        }
        
        // Get order items
        $itemsStmt = $conn->prepare("
            SELECT 
                oi.*,
                p.name,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            $item['price'] = (float)$item['price'];
            $item['quantity'] = (int)$item['quantity'];
            $item['image'] = $item['image'] ?: '/uploads/products/placeholder.jpg';
        }
        
        $order['items'] = $items;
        $order['total_amount'] = (float)$order['total_amount'];
        $order['shipping_cost'] = (float)($order['shipping_cost'] ?? 0);
        
        jsonSuccess($order);
        
    } catch (Exception $e) {
        jsonError('Failed to fetch order');
    }
}

function handlePost() {
    global $conn;
    
    $user = requireAuth();
    $data = getJsonBody();
    validateRequired($data, ['shipping_address', 'shipping_phone', 'payment_method']);
    
    try {
        $conn->beginTransaction();
        
        // Get cart items
        $cartStmt = $conn->prepare("
            SELECT c.*, p.name, p.price, p.sale_price, p.stock, p.vendor_id
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ? AND p.status = 'active'
        ");
        $cartStmt->execute([$user['id']]);
        $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($cartItems)) {
            jsonError('Cart is empty');
        }
        
        // Calculate total
        $total = 0;
        foreach ($cartItems as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $total += $price * $item['quantity'];
            
            // Check stock
            if ($item['stock'] < $item['quantity']) {
                $conn->rollBack();
                jsonError("Not enough stock for: {$item['name']}");
            }
        }
        
        // Generate order number
        $orderNumber = 'ORD-' . strtoupper(uniqid());
        $shippingCost = (float)($data['shipping_cost'] ?? 0);
        
        // Create order
        $orderStmt = $conn->prepare("
            INSERT INTO orders (user_id, order_number, total_amount, shipping_cost, payment_method, 
                               shipping_address, shipping_phone, notes, payment_status, payment_reference, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 'pending', NOW())
        ");
        $orderStmt->execute([
            $user['id'],
            $orderNumber,
            $total,
            $shippingCost,
            $data['payment_method'],
            $data['shipping_address'],
            $data['shipping_phone'],
            $data['notes'] ?? null,
            $data['reference_number'] ?? null
        ]);
        $orderId = $conn->lastInsertId();
        
        // Create order items and update stock
        $itemStmt = $conn->prepare("
            INSERT INTO order_items (order_id, product_id, vendor_id, quantity, price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
        
        foreach ($cartItems as $item) {
            $price = $item['sale_price'] ?? $item['price'];
            $subtotal = $price * $item['quantity'];
            
            $itemStmt->execute([
                $orderId,
                $item['product_id'],
                $item['vendor_id'],
                $item['quantity'],
                $price,
                $subtotal
            ]);
            
            $stockStmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Clear cart
        $clearStmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $clearStmt->execute([$user['id']]);
        
        $conn->commit();
        
        jsonSuccess([
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'total' => $total + $shippingCost
        ], 'Order placed successfully');
        
    } catch (Exception $e) {
        $conn->rollBack();
        jsonError('Failed to place order. Please try again.');
    }
}
