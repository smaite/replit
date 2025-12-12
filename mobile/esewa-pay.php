<?php
/**
 * eSewa Payment Initiation
 * Redirects user to eSewa payment gateway
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/esewa.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$orderId = isset($_GET['order']) ? (int)$_GET['order'] : 0;

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

// Fetch order details
try {
    $stmt = $conn->prepare("
        SELECT * FROM orders 
        WHERE id = ? AND user_id = ? AND payment_status = 'pending'
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found or already paid';
        header('Location: orders.php');
        exit;
    }
    
    $totalAmount = $order['total_amount'] + $order['shipping_cost'];
    
    // Store order info in session for verification
    $_SESSION['esewa_order'] = [
        'order_id' => $orderId,
        'total_amount' => $totalAmount
    ];
    
    // Generate eSewa payment data
    $payment = initiateEsewaPayment(
        $orderId,
        $order['total_amount'],  // Product amount
        0,                        // Tax amount
        0,                        // Service charge
        $order['shipping_cost']   // Delivery charge
    );
    
    // Store transaction UUID for verification
    $_SESSION['esewa_order']['transaction_uuid'] = $payment['transaction_uuid'];
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error processing payment';
    header('Location: orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#60BB46">
    <title>Pay with eSewa - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
    <style>
        .esewa-page {
            padding: 40px 20px;
            text-align: center;
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            min-height: 100vh;
        }
        .esewa-logo {
            width: 120px;
            height: auto;
            margin-bottom: 24px;
        }
        .esewa-card {
            background: white;
            border-radius: 20px;
            padding: 32px 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            max-width: 360px;
            margin: 0 auto;
        }
        .esewa-title {
            font-size: 22px;
            font-weight: 700;
            color: #15803d;
            margin-bottom: 8px;
        }
        .esewa-subtitle {
            color: #666;
            margin-bottom: 24px;
        }
        .order-amount {
            font-size: 36px;
            font-weight: 800;
            color: #166534;
            margin-bottom: 8px;
        }
        .order-id {
            color: #888;
            font-size: 14px;
            margin-bottom: 32px;
        }
        .esewa-btn {
            display: block;
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #60BB46, #4ade80);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .esewa-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(96, 187, 70, 0.4);
        }
        .cancel-link {
            color: #888;
            text-decoration: none;
            font-size: 14px;
        }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 24px;
            color: #666;
            font-size: 13px;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.95);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .loading-overlay.active {
            display: flex;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #e5e7eb;
            border-top-color: #60BB46;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="esewa-page">
        <div class="esewa-card">
            <!-- eSewa Logo -->
            <img src="https://esewa.com.np/common/images/esewa-logo.png" alt="eSewa" class="esewa-logo" 
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 40%22><text x=%2250%22 y=%2225%22 text-anchor=%22middle%22 fill=%22%2360BB46%22 font-size=%2220%22 font-weight=%22bold%22>eSewa</text></svg>'">
            
            <h1 class="esewa-title">Pay with eSewa</h1>
            <p class="esewa-subtitle">Secure digital payment</p>
            
            <div class="order-amount">Rs. <?php echo number_format($totalAmount, 2); ?></div>
            <div class="order-id">Order #<?php echo $orderId; ?></div>
            
            <!-- eSewa Payment Form (Auto-submit or Manual) -->
            <form id="esewa-form" action="<?php echo $payment['form_url']; ?>" method="POST">
                <?php foreach ($payment['form_data'] as $key => $value): ?>
                    <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endforeach; ?>
                
                <button type="submit" class="esewa-btn" onclick="showLoading()">
                    🔒 Pay Securely with eSewa
                </button>
            </form>
            
            <a href="order.php?id=<?php echo $orderId; ?>" class="cancel-link">Cancel and go back</a>
            
            <div class="secure-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0110 0v4"></path>
                </svg>
                Secure payment powered by eSewa
            </div>
        </div>
    </div>
    
    <div class="loading-overlay" id="loading">
        <div class="spinner"></div>
        <p style="margin-top: 20px; color: #666;">Connecting to eSewa...</p>
    </div>
    
    <script>
    function showLoading() {
        document.getElementById('loading').classList.add('active');
    }
    </script>
</body>
</html>
