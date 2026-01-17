<?php
/**
 * Sasto Hub Cart API
 * Cart management endpoints
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
    case 'PUT':
        handleUpdate();
        break;
    case 'DELETE':
        handleDelete();
        break;
    default:
        jsonError('Method not allowed', 405);
}

function handleGet() {
    global $conn;
    
    $user = requireAuth();
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.id as cart_id,
                c.quantity,
                p.id as product_id,
                p.name,
                p.price,
                p.sale_price,
                p.stock,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ? AND p.status = 'active'
        ");
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $subtotal = 0;
        foreach ($items as &$item) {
            $item['price'] = (float)$item['price'];
            $item['sale_price'] = $item['sale_price'] ? (float)$item['sale_price'] : null;
            $item['quantity'] = (int)$item['quantity'];
            $item['stock'] = (int)$item['stock'];
            $item['image'] = $item['image'] ?: '/uploads/products/placeholder.jpg';
            
            $itemPrice = $item['sale_price'] ?? $item['price'];
            $item['item_total'] = $itemPrice * $item['quantity'];
            $subtotal += $item['item_total'];
        }
        
        jsonSuccess([
            'items' => $items,
            'subtotal' => $subtotal,
            'item_count' => count($items)
        ]);
        
    } catch (Exception $e) {
        jsonError('Failed to fetch cart');
    }
}

function handlePost() {
    global $conn;
    
    $user = requireAuth();
    $data = getJsonBody();
    validateRequired($data, ['product_id']);
    
    $productId = (int)$data['product_id'];
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    
    try {
        // Check if product exists and has stock
        $stmt = $conn->prepare("SELECT id, stock, vendor_id FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            jsonError('Product not found', 404);
        }
        
        // Check if user is the vendor of this product (prevent self-purchase)
        if ($product['vendor_id'] == $user['id']) {
            jsonError('You cannot purchase your own product');
        }
        
        // Also check if user has a vendor record that matches
        $vendorCheck = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? AND id = ?");
        $vendorCheck->execute([$user['id'], $product['vendor_id']]);
        if ($vendorCheck->fetch()) {
            jsonError('You cannot purchase your own product');
        }
        
        if ($product['stock'] < $quantity) {
            jsonError('Not enough stock available');
        }
        
        // Check if already in cart
        $cartStmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $cartStmt->execute([$user['id'], $productId]);
        $existing = $cartStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update quantity
            $newQty = $existing['quantity'] + $quantity;
            if ($newQty > $product['stock']) {
                $newQty = $product['stock'];
            }
            $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $updateStmt->execute([$newQty, $existing['id']]);
        } else {
            // Add new item
            $insertStmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity, created_at) VALUES (?, ?, ?, NOW())");
            $insertStmt->execute([$user['id'], $productId, $quantity]);
        }
        
        // Get updated cart count
        $countStmt = $conn->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
        $countStmt->execute([$user['id']]);
        $cartCount = (int)$countStmt->fetchColumn();
        
        jsonSuccess(['cart_count' => $cartCount], 'Added to cart');
        
    } catch (Exception $e) {
        jsonError('Failed to add to cart');
    }
}

function handleUpdate() {
    global $conn;
    
    $user = requireAuth();
    $data = getJsonBody();
    validateRequired($data, ['cart_id', 'quantity']);
    
    $cartId = (int)$data['cart_id'];
    $quantity = max(1, (int)$data['quantity']);
    
    try {
        // Verify ownership and get product stock
        $stmt = $conn->prepare("
            SELECT c.*, p.stock FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.id = ? AND c.user_id = ?
        ");
        $stmt->execute([$cartId, $user['id']]);
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cartItem) {
            jsonError('Cart item not found', 404);
        }
        
        if ($quantity > $cartItem['stock']) {
            jsonError('Not enough stock available');
        }
        
        $updateStmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $updateStmt->execute([$quantity, $cartId]);
        
        jsonSuccess(null, 'Cart updated');
        
    } catch (Exception $e) {
        jsonError('Failed to update cart');
    }
}

function handleDelete() {
    global $conn;
    
    $user = requireAuth();
    
    // Delete specific item or clear cart
    if (isset($_GET['id'])) {
        $cartId = (int)$_GET['id'];
        
        try {
            $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cartId, $user['id']]);
            
            jsonSuccess(null, 'Item removed from cart');
            
        } catch (Exception $e) {
            jsonError('Failed to remove item');
        }
    } else {
        // Clear entire cart
        try {
            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            
            jsonSuccess(null, 'Cart cleared');
            
        } catch (Exception $e) {
            jsonError('Failed to clear cart');
        }
    }
}
