<?php
/**
 * eSewa Payment Success Callback - Main Website
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/esewa.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
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
        
        // Extract order ID from transaction_uuid
        if (preg_match('/^SH-(\d+)-/', $transactionUuid, $matches)) {
            $orderId = (int)$matches[1];
        }
        
        if ($status === 'COMPLETE' && $orderId) {
            // Verify payment with eSewa API
            $verification = verifyEsewaPayment($transactionUuid, $totalAmount);
            
            if ($verification['success']) {
                try {
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
                    } else {
                        $error = 'Order not found';
                    }
                } catch (Exception $e) {
                    $error = 'Database error';
                }
            } else {
                $error = 'Payment verification failed';
            }
        } else {
            $error = 'Payment incomplete';
        }
    } else {
        $error = 'Invalid response';
    }
} else {
    $error = 'No payment data';
}

$page_title = ($success ? 'Payment Successful' : 'Payment Failed') . ' - SASTO Hub';
include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto text-center">
            <?php if ($success): ?>
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
                    <p class="text-gray-600 mb-6">Your payment has been received. Order #<?php echo $orderId; ?> is being processed.</p>
                    <a href="/pages/order.php?id=<?php echo $orderId; ?>" 
                       class="inline-block bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium">
                        View Order
                    </a>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl shadow-lg p-8">
                    <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Failed</h1>
                    <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($error); ?></p>
                    <a href="/pages/orders.php" 
                       class="inline-block bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium">
                        View Orders
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
