<?php
/**
 * eSewa Payment Failure Callback - Main Website
 */
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$orderId = null;

// Try to extract order ID
if (isset($_GET['data'])) {
    $decoded = base64_decode($_GET['data']);
    if ($decoded) {
        $data = json_decode($decoded, true);
        $transactionUuid = $data['transaction_uuid'] ?? '';
        if (preg_match('/^SH-(\d+)-/', $transactionUuid, $matches)) {
            $orderId = (int)$matches[1];
            
            // Update order status
            try {
                $stmt = $conn->prepare("
                    UPDATE orders SET payment_status = 'failed', status = 'cancelled'
                    WHERE id = ? AND user_id = ? AND payment_status = 'pending'
                ");
                $stmt->execute([$orderId, $_SESSION['user_id']]);
            } catch (Exception $e) {}
        }
    }
}

$page_title = 'Payment Cancelled - SASTO Hub';
include '../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto text-center">
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-10 h-10 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Cancelled</h1>
                <p class="text-gray-600 mb-6">Your payment was cancelled or could not be processed. No amount has been deducted.</p>
                <div class="space-y-3">
                    <a href="/pages/cart.php" 
                       class="block bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium">
                        Try Again
                    </a>
                    <a href="/" class="block text-gray-600 hover:text-gray-800">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
