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

// Fetch order details with items
$stmt = $conn->prepare("
    SELECT o.*, u.full_name, u.email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.order_number = ? AND o.user_id = ?
");
$stmt->execute([$order_number, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/');
}

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order['id']]);
$items = $stmt->fetchAll();

$page_title = 'Order Success - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto">
            <!-- Success Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
                <div class="bg-primary/10 p-8 text-center border-b border-gray-100">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-check text-4xl text-green-600"></i>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Thank you for your order!</h1>
                    <p class="text-gray-600">The order confirmation has been sent to <?php echo htmlspecialchars($order['email']); ?></p>
                </div>

                <div class="p-8">
                    <div class="flex flex-col md:flex-row justify-between gap-6 mb-8 pb-8 border-b border-gray-100">
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Order Number</p>
                            <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($order['order_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Date</p>
                            <p class="text-lg font-bold text-gray-900"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Total Amount</p>
                            <p class="text-lg font-bold text-primary">Rs. <?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-1">Payment Method</p>
                            <p class="text-lg font-bold text-gray-900 capitalize">
                                <?php
                                if ($order['payment_method'] == 'cod') echo 'Cash on Delivery';
                                elseif ($order['payment_method'] == 'qr') echo 'QR Payment';
                                else echo ucfirst($order['payment_method']);
                                ?>
                            </p>
                        </div>
                    </div>

                    <!-- Order Items -->
                    <div class="space-y-4 mb-8">
                        <h3 class="font-bold text-gray-900 mb-4">Order Items</h3>
                        <?php foreach ($items as $item): ?>
                        <div class="flex gap-4 items-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-lg overflow-hidden flex-shrink-0 border border-gray-200">
                                <img src="<?php echo htmlspecialchars($item['image'] ?? '/assets/images/no-image.png'); ?>"
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></h4>
                                <p class="text-sm text-gray-500">Qty: <?php echo $item['quantity']; ?></p>
                            </div>
                            <div class="font-bold text-gray-900">
                                Rs. <?php echo number_format($item['subtotal'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Shipping Info -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-8 border border-gray-100">
                        <h3 class="font-bold text-gray-900 mb-3">Shipping Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-500">Shipping Address</p>
                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Phone Number</p>
                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-4">
                        <a href="/" class="flex-1 px-6 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg text-center hover:bg-gray-50 transition">
                            Continue Shopping
                        </a>
                        <a href="/pages/profile.php" class="flex-1 px-6 py-3 bg-primary text-white font-medium rounded-lg text-center hover:bg-indigo-700 transition">
                            View My Orders
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
