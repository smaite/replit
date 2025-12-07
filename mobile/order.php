<?php
/**
 * Mobile Order Detail Page - SASTO Hub
 * Single order details with items and status
 */
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;
$orderItems = [];

try {
    // Get order
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if ($order) {
        // Get order items
        $stmt = $conn->prepare("
            SELECT oi.*, p.name, p.image 
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}

if (!$order) {
    header('Location: orders.php');
    exit;
}

$statusSteps = ['pending', 'processing', 'shipped', 'delivered'];
$currentStep = array_search($order['status'], $statusSteps);
if ($currentStep === false) $currentStep = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
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
            <h1>Order #<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></h1>
            <div class="header-spacer"></div>
        </header>
        
        <div class="order-detail-content">
            <!-- Order Status Timeline -->
            <div class="status-timeline">
                <?php foreach ($statusSteps as $index => $step): ?>
                <div class="timeline-step <?php echo $index <= $currentStep ? 'completed' : ''; ?> <?php echo $index === $currentStep ? 'current' : ''; ?>">
                    <div class="step-circle">
                        <?php if ($index < $currentStep): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        <?php else: ?>
                            <span><?php echo $index + 1; ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="step-label"><?php echo ucfirst($step); ?></span>
                </div>
                <?php if ($index < count($statusSteps) - 1): ?>
                <div class="timeline-line <?php echo $index < $currentStep ? 'completed' : ''; ?>"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <!-- Order Items -->
            <div class="order-section">
                <h3>Order Items</h3>
                <?php foreach ($orderItems as $item): ?>
                <div class="order-item-card">
                    <div class="item-image">
                        <?php if (!empty($item['image'])): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($item['image']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder-img"></div>
                        <?php endif; ?>
                    </div>
                    <div class="item-info">
                        <h4><?php echo htmlspecialchars($item['name'] ?? 'Product'); ?></h4>
                        <div class="item-meta">
                            <span class="qty">Qty: <?php echo $item['quantity']; ?></span>
                            <span class="price"><?php echo formatPrice($item['price']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Order Summary -->
            <div class="order-section">
                <h3>Order Summary</h3>
                <div class="summary-card">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($order['total_amount']); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo formatPrice($order['shipping_cost'] ?? 0); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span><?php echo formatPrice($order['total_amount'] + ($order['shipping_cost'] ?? 0)); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Address -->
            <div class="order-section">
                <h3>Shipping Address</h3>
                <div class="address-card">
                    <p><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'Not provided')); ?></p>
                </div>
            </div>
            
            <!-- Order Info -->
            <div class="order-section">
                <h3>Order Information</h3>
                <div class="info-card">
                    <div class="info-row">
                        <span>Order Date</span>
                        <span><?php echo date('F d, Y h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span>Payment Method</span>
                        <span>Cash on Delivery</span>
                    </div>
                    <div class="info-row">
                        <span>Payment Status</span>
                        <span class="status-badge <?php echo $order['payment_status'] ?? 'pending'; ?>">
                            <?php echo ucfirst($order['payment_status'] ?? 'Pending'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
