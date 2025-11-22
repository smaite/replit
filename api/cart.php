<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle GET request for cart count
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'count') {
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    echo json_encode(['count' => $result['count'] ?? 0]);
    exit;
}

// Handle POST request to add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
    
    if ($action === 'add') {
        $product_id = $data['product_id'] ?? 0;
        $quantity = $data['quantity'] ?? 1;
        
        if ($product_id > 0) {
            // Check if product exists
            $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$product_id]);
            
            if ($stmt->fetch()) {
                // Check if already in cart
                $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Update quantity
                    $new_qty = $existing['quantity'] + $quantity;
                    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                    $stmt->execute([$new_qty, $existing['id']]);
                } else {
                    // Insert new cart item
                    $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $product_id, $quantity]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Product added to cart']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
