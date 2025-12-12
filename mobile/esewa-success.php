<?php
/**
 * eSewa Payment Success Callback
 * Handles successful payment redirect from eSewa
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/esewa.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = false;
$orderId = null;

// eSewa V2 returns base64 encoded JSON data
if (isset($_GET['data'])) {
    $responseData = decodeEsewaResponse($_GET['data']);
    
    if ($responseData) {
        $status = $responseData['status'] ?? '';
        $transactionUuid = $responseData['transaction_uuid'] ?? '';
        $totalAmount = $responseData['total_amount'] ?? 0;
        $transactionCode = $responseData['transaction_code'] ?? '';
        
        // Extract order ID from transaction_uuid (format: SH-{orderId}-{timestamp})
        if (preg_match('/^SH-(\d+)-/', $transactionUuid, $matches)) {
            $orderId = (int)$matches[1];
        }
        
        if ($status === 'COMPLETE' && $orderId) {
            // Verify payment with eSewa API
            $verification = verifyEsewaPayment($transactionUuid, $totalAmount);
            
            if ($verification['success']) {
                try {
                    // Update order payment status
                    $stmt = $conn->prepare("
                        UPDATE orders 
                        SET payment_status = 'paid', 
                            payment_method = 'esewa',
                            transaction_id = ?,
                            updated_at = NOW() 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$transactionCode, $orderId, $_SESSION['user_id']]);
                    
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                        // Clear cart after successful payment
                        $_SESSION['cart'] = [];
                    } else {
                        $error = 'Order not found or unauthorized';
                    }
                } catch (Exception $e) {
                    $error = 'Database error occurred';
                }
            } else {
                $error = 'Payment verification failed';
            }
        } else {
            $error = 'Payment was not completed';
        }
    } else {
        $error = 'Invalid payment response';
    }
} else {
    $error = 'No payment data received';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Payment <?php echo $success ? 'Successful' : 'Failed'; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
    <style>
        .payment-result {
            padding: 60px 20px;
            text-align: center;
        }
        .result-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .result-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        .result-icon.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }
        .result-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }
        .result-message {
            color: #666;
            margin-bottom: 32px;
        }
        .result-btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-page">
        <header class="page-header">
            <div class="header-spacer"></div>
            <h1>Payment Result</h1>
            <div class="header-spacer"></div>
        </header>
        
        <div class="payment-result">
            <?php if ($success): ?>
                <div class="result-icon success">✓</div>
                <h2 class="result-title">Payment Successful!</h2>
                <p class="result-message">
                    Your payment has been received.<br>
                    Order #<?php echo $orderId; ?> is being processed.
                </p>
                <a href="order.php?id=<?php echo $orderId; ?>" class="result-btn">View Order</a>
            <?php else: ?>
                <div class="result-icon error">✕</div>
                <h2 class="result-title">Payment Failed</h2>
                <p class="result-message"><?php echo htmlspecialchars($error); ?></p>
                <a href="orders.php" class="result-btn">View Orders</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
