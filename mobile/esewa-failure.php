<?php
/**
 * eSewa Payment Failure Callback
 * Handles failed payment redirect from eSewa
 */
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$orderId = null;

// Try to extract order ID from transaction_uuid in response
if (isset($_GET['data'])) {
    $responseData = base64_decode($_GET['data']);
    if ($responseData) {
        $data = json_decode($responseData, true);
        $transactionUuid = $data['transaction_uuid'] ?? '';
        if (preg_match('/^SH-(\d+)-/', $transactionUuid, $matches)) {
            $orderId = (int)$matches[1];
            
            // Update order status to cancelled
            try {
                $stmt = $conn->prepare("
                    UPDATE orders 
                    SET payment_status = 'failed',
                        status = 'cancelled',
                        updated_at = NOW() 
                    WHERE id = ? AND user_id = ? AND payment_status = 'pending'
                ");
                $stmt->execute([$orderId, $_SESSION['user_id']]);
            } catch (Exception $e) {}
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Payment Cancelled - <?php echo SITE_NAME; ?></title>
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
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
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
        .result-btns {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 280px;
            margin: 0 auto;
        }
        .result-btn {
            display: block;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
        }
        .result-btn.primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }
        .result-btn.secondary {
            background: #f3f4f6;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="app-page">
        <header class="page-header">
            <div class="header-spacer"></div>
            <h1>Payment Cancelled</h1>
            <div class="header-spacer"></div>
        </header>
        
        <div class="payment-result">
            <div class="result-icon">!</div>
            <h2 class="result-title">Payment Cancelled</h2>
            <p class="result-message">
                Your payment was cancelled or could not be processed.<br>
                No amount has been deducted from your account.
            </p>
            <div class="result-btns">
                <a href="cart.php" class="result-btn primary">Try Again</a>
                <a href="home.php" class="result-btn secondary">Continue Shopping</a>
            </div>
        </div>
    </div>
</body>
</html>
