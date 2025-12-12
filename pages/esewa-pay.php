<?php
/**
 * eSewa Payment Initiation - Main Website
 */
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/esewa.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$orderId = isset($_GET['order']) ? (int)$_GET['order'] : 0;

if (!$orderId) {
    redirect('/pages/orders.php');
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
        redirect('/pages/orders.php');
    }
    
    $shippingCost = $order['shipping_cost'] ?? 0;
    $totalAmount = $order['total_amount'] + $shippingCost;
    
    // Generate eSewa payment data
    $payment = initiateEsewaPayment(
        $orderId,
        $order['total_amount'],
        0,
        0,
        $shippingCost
    );
    
    // Store for verification
    $_SESSION['esewa_order'] = [
        'order_id' => $orderId,
        'total_amount' => $totalAmount,
        'transaction_uuid' => $payment['transaction_uuid']
    ];
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error processing payment';
    redirect('/pages/orders.php');
}

$page_title = 'Pay with eSewa - SASTO Hub';
include '../includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-br from-green-50 to-emerald-100 py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto">
            <div class="bg-white rounded-2xl shadow-xl p-8 text-center">
                <!-- eSewa Logo -->
                <img src="https://esewa.com.np/common/images/esewa-logo.png" alt="eSewa" 
                     class="h-12 mx-auto mb-6"
                     onerror="this.outerHTML='<h2 class=\'text-2xl font-bold text-green-600 mb-6\'>eSewa</h2>'">
                
                <h1 class="text-2xl font-bold text-gray-900 mb-2">Pay with eSewa</h1>
                <p class="text-gray-600 mb-8">Secure digital payment</p>
                
                <div class="bg-green-50 rounded-xl p-6 mb-8">
                    <p class="text-sm text-gray-600 mb-2">Amount to Pay</p>
                    <p class="text-4xl font-bold text-green-700">Rs. <?php echo number_format($totalAmount, 2); ?></p>
                    <p class="text-sm text-gray-500 mt-2">Order #<?php echo $orderId; ?></p>
                </div>
                
                <!-- eSewa Payment Form -->
                <form id="esewa-form" action="<?php echo $payment['form_url']; ?>" method="POST">
                    <?php foreach ($payment['form_data'] as $key => $value): ?>
                        <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
                    <?php endforeach; ?>
                    
                    <button type="submit" onclick="showLoading()" 
                            class="w-full bg-green-500 hover:bg-green-600 text-white py-4 rounded-xl font-bold text-lg transition flex items-center justify-center gap-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Pay Securely with eSewa
                    </button>
                </form>
                
                <a href="/pages/order.php?id=<?php echo $orderId; ?>" class="inline-block mt-6 text-gray-500 hover:text-gray-700">
                    Cancel and go back
                </a>
                
                <div class="flex items-center justify-center gap-2 mt-8 text-gray-500 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Secure payment powered by eSewa
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading" class="fixed inset-0 bg-white/95 z-50 hidden items-center justify-center flex-col">
    <div class="w-12 h-12 border-4 border-gray-200 border-t-green-500 rounded-full animate-spin"></div>
    <p class="mt-4 text-gray-600">Connecting to eSewa...</p>
</div>

<script>
function showLoading() {
    document.getElementById('loading').classList.remove('hidden');
    document.getElementById('loading').classList.add('flex');
}
</script>

<?php include '../includes/footer.php'; ?>
