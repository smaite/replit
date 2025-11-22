<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    redirect('/');
}

// Fetch order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE order_number = ? AND user_id = ?");
$stmt->execute([$order_number, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/');
}

$page_title = 'Order Success - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-xl p-8 text-center">
        <div class="mb-6">
            <div class="w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check text-5xl text-green-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Order Placed Successfully!</h1>
            <p class="text-gray-600">Thank you for your purchase</p>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-6 mb-6">
            <p class="text-sm text-gray-600 mb-2">Order Number</p>
            <p class="text-2xl font-bold text-primary"><?php echo htmlspecialchars($order['order_number']); ?></p>
        </div>
        
        <div class="text-left mb-6 space-y-3">
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-600">Order Total:</span>
                <span class="font-bold text-gray-900"><?php echo formatPrice($order['total_amount']); ?></span>
            </div>
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-600">Payment Method:</span>
                <span class="font-medium text-gray-900"><?php echo ucfirst($order['payment_method']); ?></span>
            </div>
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-600">Status:</span>
                <span class="px-3 py-1 bg-yellow-100 text-yellow-700 rounded-full text-sm font-medium">
                    <?php echo ucfirst($order['status']); ?>
                </span>
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-left">
            <p class="text-blue-800 mb-2">
                <i class="fas fa-info-circle"></i> <strong>What's Next?</strong>
            </p>
            <ul class="text-blue-700 space-y-1 ml-6 list-disc">
                <li>We'll send you an order confirmation email</li>
                <li>Your order will be processed within 1-2 business days</li>
                <li>You can track your order status from your profile</li>
                <li>Expected delivery: 3-5 business days</li>
            </ul>
        </div>
        
        <div class="flex gap-4">
            <a href="/" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 text-center py-3 rounded-lg font-medium transition">
                <i class="fas fa-home"></i> Back to Home
            </a>
            <a href="/pages/profile.php" class="flex-1 bg-primary hover:bg-indigo-700 text-white text-center py-3 rounded-lg font-medium transition">
                <i class="fas fa-user"></i> View Profile
            </a>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
