<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Get order ID
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    redirect('/pages/order-history.php');
}

// Fetch order
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    redirect('/pages/order-history.php');
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if ($order['status'] === 'pending') {
        $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$order_id]);

        // Update all items status
        $stmt = $conn->prepare("UPDATE order_items SET vendor_status = 'cancelled' WHERE order_id = ?");
        $stmt->execute([$order_id]);

        // Refresh order
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $order = $stmt->fetch();

        $success = "Order cancelled successfully.";
    } else {
        $error = "Order cannot be cancelled at this stage.";
    }
}

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.slug, pi.image_path, v.shop_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

// Get order status timeline
$status_timeline = [
    'pending' => 'Order Placed',
    'confirmed' => 'Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered'
];

$status_order = array_keys($status_timeline);
$current_status_index = array_search($order['status'], $status_order);
if ($current_status_index === false) $current_status_index = -1; // Cancelled

$page_title = 'Order Details - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/pages/dashboard.php" class="hover:text-primary">My Account</a>
            <span class="mx-2">/</span>
            <a href="/pages/order-history.php" class="hover:text-primary">Orders</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Order #<?php echo htmlspecialchars($order['order_number']); ?></span>
        </nav>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Order Details</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Placed on <?php echo date('d F Y, h:i A', strtotime($order['created_at'])); ?>
                </p>
            </div>

            <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'delivered'): ?>
                <div class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-700 rounded-lg text-sm font-medium">
                    Estimated Delivery: <?php echo date('M d', strtotime($order['created_at'] . ' + 3 days')); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($success)): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle text-xl"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Items & Timeline -->
            <div class="lg:col-span-2 space-y-8">

                <!-- Timeline -->
                <?php if ($order['status'] !== 'cancelled'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                    <h2 class="text-lg font-bold text-gray-900 mb-8">Order Status</h2>
                    <div class="relative">
                        <!-- Line -->
                        <div class="absolute top-1/2 left-0 right-0 h-1 bg-gray-100 -translate-y-1/2 rounded-full hidden md:block"></div>
                        <div class="absolute top-1/2 left-0 h-1 bg-primary -translate-y-1/2 rounded-full hidden md:block transition-all duration-1000"
                             style="width: <?php echo ($current_status_index / (count($status_order) - 1)) * 100; ?>%"></div>

                        <!-- Steps -->
                        <div class="relative flex flex-col md:flex-row justify-between gap-6 md:gap-0">
                            <?php foreach ($status_timeline as $key => $label): ?>
                                <?php
                                $step_index = array_search($key, $status_order);
                                $is_completed = $step_index <= $current_status_index;
                                $is_current = $step_index === $current_status_index;
                                ?>
                                <div class="flex md:flex-col items-center gap-4 md:gap-2 z-10">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center border-4 transition-colors duration-300 flex-shrink-0
                                        <?php echo $is_completed ? 'bg-primary border-primary text-white' : 'bg-white border-gray-200 text-gray-300'; ?>">
                                        <?php if ($is_completed): ?>
                                            <i class="fas fa-check text-sm"></i>
                                        <?php else: ?>
                                            <span class="text-sm font-bold"><?php echo $step_index + 1; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="md:text-center">
                                        <p class="text-sm font-bold <?php echo $is_completed ? 'text-gray-900' : 'text-gray-400'; ?>">
                                            <?php echo $label; ?>
                                        </p>
                                        <?php if ($is_current): ?>
                                            <p class="text-xs text-primary font-medium">In Progress</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-6 flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-100 text-red-600 rounded-full flex items-center justify-center text-xl">
                        <i class="fas fa-times"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-red-800 text-lg">Order Cancelled</h3>
                        <p class="text-red-600 text-sm">This order was cancelled on <?php echo date('M d, Y', strtotime($order['updated_at'])); ?>.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Order Items -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <h2 class="text-lg font-bold text-gray-900">Items (<?php echo count($order_items); ?>)</h2>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($order_items as $item): ?>
                            <div class="p-6 flex flex-col sm:flex-row gap-6">
                                <div class="w-24 h-24 bg-gray-100 rounded-xl overflow-hidden border border-gray-200 flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/150'); ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start mb-2">
                                        <div>
                                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>"
                                               class="font-bold text-gray-900 hover:text-primary text-lg line-clamp-2">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                            <p class="text-sm text-gray-500 mt-1">Sold by: <?php echo htmlspecialchars($item['shop_name']); ?></p>
                                        </div>
                                        <p class="font-bold text-lg text-gray-900"><?php echo formatPrice($item['price'] * $item['quantity']); ?></p>
                                    </div>

                                    <div class="flex items-center gap-6 text-sm text-gray-600 mt-4">
                                        <div class="bg-gray-100 px-3 py-1 rounded-lg">
                                            Price: <span class="font-medium text-gray-900"><?php echo formatPrice($item['price']); ?></span>
                                        </div>
                                        <div class="bg-gray-100 px-3 py-1 rounded-lg">
                                            Qty: <span class="font-medium text-gray-900"><?php echo $item['quantity']; ?></span>
                                        </div>
                                    </div>

                                    <?php if ($order['status'] === 'delivered'): ?>
                                        <div class="mt-4 pt-4 border-t border-gray-50 flex gap-4">
                                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>#reviews"
                                               class="text-sm font-medium text-primary hover:text-indigo-700">
                                                Write a Review
                                            </a>
                                            <a href="/pages/products.php" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                                                Buy Again
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Summary & Info -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Order Summary -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-gray-900 mb-6">Order Summary</h2>

                    <div class="space-y-3 pb-6 border-b border-gray-100">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span>Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span class="text-green-600">Free</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Tax</span>
                            <span>Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between text-gray-600">
                            <span>Discount</span>
                            <span>-Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center py-4 mb-4">
                        <span class="font-bold text-lg text-gray-900">Total</span>
                        <span class="font-bold text-xl text-primary">Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>

                    <!-- Payment Info -->
                    <div class="bg-blue-50 rounded-xl p-4 mb-6">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-8 h-8 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-credit-card text-xs"></i>
                            </div>
                            <span class="font-bold text-gray-900 text-sm">Payment Info</span>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Method:</span>
                            <span class="font-medium text-gray-900">
                                <?php
                                    if ($order['payment_method'] == 'cod') echo 'Cash on Delivery';
                                    elseif ($order['payment_method'] == 'esewa') echo 'eSewa Wallet';
                                    elseif ($order['payment_method'] == 'qr') echo 'QR Payment';
                                    else echo ucfirst($order['payment_method']);
                                ?>
                            </span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Status:</span>
                            <span class="font-medium <?php echo $order['payment_status'] == 'paid' ? 'text-green-600' : 'text-yellow-600'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </div>
                    </div>

                    <button onclick="window.print()" class="w-full bg-gray-900 hover:bg-gray-800 text-white py-3 rounded-lg font-medium transition flex items-center justify-center gap-2 mb-3">
                        <i class="fas fa-file-invoice"></i> Download Invoice
                    </button>

                    <?php if ($order['status'] === 'pending'): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.');">
                            <input type="hidden" name="cancel_order" value="1">
                            <button type="submit" class="w-full bg-white border border-red-200 text-red-600 hover:bg-red-50 py-3 rounded-lg font-medium transition">
                                Cancel Order
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <!-- Shipping Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-6">Delivery Address</h2>

                    <div class="flex gap-4">
                        <div class="w-10 h-10 bg-gray-100 text-gray-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-900 text-sm mb-1">Shipping Address</h4>
                            <p class="text-sm text-gray-600 leading-relaxed mb-2">
                                <?php echo htmlspecialchars($order['shipping_address']); ?>
                            </p>
                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                <i class="fas fa-phone text-xs"></i>
                                <?php echo htmlspecialchars($order['shipping_phone']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
