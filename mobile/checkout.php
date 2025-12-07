<?php
/**
 * Mobile Checkout Page - SASTO Hub
 * Order placement with address and payment
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Require login for checkout
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = '/mobile/checkout.php';
    header('Location: login.php?checkout=1');
    exit;
}

$userId = $_SESSION['user_id'];
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cartItems = [];
$subtotal = 0;
$error = '';
$success = '';

// Fetch cart products
if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND status = 'active'");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $qty = $cart[$product['id']]['quantity'];
            $itemTotal = $product['price'] * $qty;
            $subtotal += $itemTotal;
            
            $cartItems[] = [
                'product' => $product,
                'quantity' => $qty,
                'total' => $itemTotal
            ];
        }
    } catch (Exception $e) {}
}

// Single product buy now
if (isset($_GET['product'])) {
    $productId = (int)$_GET['product'];
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if ($product) {
            $cartItems = [[
                'product' => $product,
                'quantity' => 1,
                'total' => $product['price']
            ]];
            $subtotal = $product['price'];
        }
    } catch (Exception $e) {}
}

$shipping = 100;
$total = $subtotal + $shipping;

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        if (empty($name) || empty($phone) || empty($address) || empty($city)) {
            $error = 'Please fill all required fields';
        } else {
            $fullAddress = "$name\n$phone\n$address\n$city";
            if ($notes) $fullAddress .= "\nNotes: $notes";
            
            try {
                $conn->beginTransaction();
                
                // Create order
                $stmt = $conn->prepare("
                    INSERT INTO orders (user_id, total_amount, shipping_cost, shipping_address, payment_status, status, created_at) 
                    VALUES (?, ?, ?, ?, 'pending', 'pending', NOW())
                ");
                $stmt->execute([$userId, $subtotal, $shipping, $fullAddress]);
                $orderId = $conn->lastInsertId();
                
                // Create order items
                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                foreach ($cartItems as $item) {
                    $stmt->execute([
                        $orderId,
                        $item['product']['id'],
                        $item['quantity'],
                        $item['product']['price']
                    ]);
                }
                
                $conn->commit();
                
                // Clear cart
                $_SESSION['cart'] = [];
                
                header("Location: order-success.php?id=$orderId");
                exit;
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Order failed. Please try again.';
            }
        }
    }
}

// Get user info for prefill
$userData = null;
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Checkout - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-page">
        <!-- Header -->
        <header class="page-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1>Checkout</h1>
            <div class="header-spacer"></div>
        </header>
        
        <?php if ($error): ?>
        <div class="alert alert-error" style="margin: 16px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <div class="checkout-content">
            <!-- Order Summary -->
            <div class="checkout-section">
                <h3>Order Summary</h3>
                <div class="order-items-mini">
                    <?php foreach ($cartItems as $item): ?>
                    <div class="order-item-mini">
                        <span class="item-qty"><?php echo $item['quantity']; ?>x</span>
                        <span class="item-name"><?php echo htmlspecialchars($item['product']['name']); ?></span>
                        <span class="item-total"><?php echo formatPrice($item['total']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="checkout-totals">
                    <div class="total-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="total-row">
                        <span>Shipping</span>
                        <span><?php echo formatPrice($shipping); ?></span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Address Form -->
            <form method="POST" action="" class="checkout-form">
                <?php echo csrfField(); ?>
                
                <div class="checkout-section">
                    <h3>Shipping Address</h3>
                    
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($userData['full_name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" placeholder="+977 9800000000" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Address *</label>
                        <textarea name="address" rows="2" placeholder="Street address, House number" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>City *</label>
                        <input type="text" name="city" placeholder="Kathmandu" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Order Notes (Optional)</label>
                        <textarea name="notes" rows="2" placeholder="Special instructions for delivery"></textarea>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="checkout-section">
                    <h3>Payment Method</h3>
                    <div class="payment-options">
                        <label class="payment-option active">
                            <input type="radio" name="payment" value="cod" checked>
                            <span class="option-icon">💵</span>
                            <span class="option-text">Cash on Delivery</span>
                        </label>
                        <label class="payment-option disabled">
                            <input type="radio" name="payment" value="esewa" disabled>
                            <span class="option-icon">📱</span>
                            <span class="option-text">eSewa (Coming Soon)</span>
                        </label>
                    </div>
                </div>
                
                <!-- Place Order Button -->
                <div class="place-order-section">
                    <button type="submit" name="place_order" class="place-order-btn">
                        Place Order - <?php echo formatPrice($total); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
