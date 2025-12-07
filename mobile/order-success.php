<?php
/**
 * Mobile Order Success Page - SASTO Hub
 * Order confirmation after successful placement
 */
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = null;

try {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
} catch (Exception $e) {}

if (!$order) {
    header('Location: orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4CAF50">
    <title>Order Placed - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="success-page">
        <div class="success-content">
            <div class="success-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="#4CAF50" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
            </div>
            <h1>Order Placed Successfully!</h1>
            <p>Thank you for your order. We'll notify you when it ships.</p>
            
            <div class="order-details-card">
                <div class="detail-row">
                    <span>Order ID</span>
                    <span>#<?php echo str_pad($orderId, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="detail-row">
                    <span>Total Amount</span>
                    <span><?php echo formatPrice($order['total_amount'] + $order['shipping_cost']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Payment</span>
                    <span>Cash on Delivery</span>
                </div>
                <div class="detail-row">
                    <span>Status</span>
                    <span class="status-badge pending">Pending</span>
                </div>
            </div>
            
            <div class="success-actions">
                <a href="order.php?id=<?php echo $orderId; ?>" class="btn-outline">View Order Details</a>
                <a href="home.php" class="btn-primary">Continue Shopping</a>
            </div>
        </div>
    </div>
</body>
</html>
