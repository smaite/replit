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
    'pending' => 'Order Received',
    'confirmed' => 'Order Confirmed',
    'processing' => 'Processing',
    'shipped' => 'Shipped',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled'
];

$status_order = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
$current_status_index = array_search($order['status'], $status_order);

$page_title = 'Order Details - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/pages/order-history.php" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Order History
        </a>
        <h1 class="text-4xl font-bold text-gray-900">Order Details</h1>
    </div>

    <!-- Order Status Timeline -->
    <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-bold text-gray-900 mb-6">Order Status</h2>
            <div class="flex justify-between items-center relative">
                <!-- Timeline Line -->
                <div class="absolute top-6 left-0 right-0 h-1 bg-gray-200">
                    <div class="h-full bg-primary" style="width: <?php echo ($current_status_index / (count($status_order) - 1)) * 100; ?>%"></div>
                </div>

                <!-- Status Steps -->
                <div class="relative flex justify-between w-full">
                    <?php foreach ($status_order as $index => $status): ?>
                        <div class="flex flex-col items-center <?php echo $index <= $current_status_index ? 'text-primary' : 'text-gray-400'; ?>">
                            <div class="w-12 h-12 rounded-full border-4 <?php echo $index <= $current_status_index ? 'bg-primary border-primary text-white' : 'bg-white border-gray-300'; ?> flex items-center justify-center font-bold mb-2">
                                <?php if ($index < $current_status_index): ?>
                                    <i class="fas fa-check"></i>
                                <?php elseif ($index === $current_status_index): ?>
                                    <i class="fas fa-spinner animate-spin"></i>
                                <?php else: ?>
                                    <?php echo $index + 1; ?>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-center font-medium"><?php echo $status_timeline[$status]; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Content -->
        <div class="lg:col-span-2">
            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Order Summary</h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase font-semibold mb-1">Order Number</p>
                        <p class="font-bold text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase font-semibold mb-1">Order Date</p>
                        <p class="font-bold text-gray-900"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase font-semibold mb-1">Status</p>
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?php 
                            echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' : 
                                 ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 
                                  ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700')); 
                        ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 uppercase font-semibold mb-1">Payment Status</p>
                        <span class="inline-block px-2 py-1 text-xs rounded font-medium <?php 
                            echo $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; 
                        ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Order Items</h2>
                
                <div class="space-y-4">
                    <?php foreach ($order_items as $item): ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex gap-4 mb-4">
                                <!-- Product Image -->
                                <div class="flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/100'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-24 h-24 object-cover rounded-lg">
                                </div>

                                <!-- Product Details -->
                                <div class="flex-1">
                                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>" 
                                       class="text-lg font-bold text-gray-900 hover:text-primary">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-store"></i> <?php echo htmlspecialchars($item['shop_name'] ?? 'Unknown'); ?>
                                    </p>
                                    <div class="flex gap-8 mt-3 text-sm">
                                        <div>
                                            <span class="text-gray-600">Unit Price:</span>
                                            <span class="font-bold text-gray-900 ml-2"><?php echo formatPrice($item['price']); ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Quantity:</span>
                                            <span class="font-bold text-gray-900 ml-2"><?php echo $item['quantity']; ?></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600">Total:</span>
                                            <span class="font-bold text-primary ml-2"><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Shipping & Delivery -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Shipping Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600 uppercase font-semibold mb-2">Shipping Address</p>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($order['shipping_address']); ?></p>
                            <?php if ($order['shipping_phone']): ?>
                                <p class="text-gray-600 text-sm mt-2">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm text-gray-600 uppercase font-semibold mb-2">Billing Address</p>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p class="text-gray-900 font-medium">
                                <?php echo htmlspecialchars($order['billing_address'] ?? $order['shipping_address']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Sidebar -->
        <div class="lg:col-span-1">
            <!-- Order Total -->
            <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                <h2 class="text-lg font-bold text-gray-900 mb-4">Order Total</h2>
                
                <div class="space-y-3 mb-4 pb-4 border-b">
                    <?php
                    // Calculate subtotal from order items
                    $subtotal = array_reduce($order_items, function($carry, $item) {
                        return $carry + ($item['price'] * $item['quantity']);
                    }, 0);
                    
                    $shipping = $order['shipping_cost'] ?? 0;
                    $tax = $order['tax_amount'] ?? 0;
                    $discount = $order['discount_amount'] ?? 0;
                    ?>
                    
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    
                    <?php if ($shipping > 0): ?>
                        <div class="flex justify-between text-gray-600">
                            <span>Shipping</span>
                            <span><?php echo formatPrice($shipping); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($tax > 0): ?>
                        <div class="flex justify-between text-gray-600">
                            <span>Tax</span>
                            <span><?php echo formatPrice($tax); ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($discount > 0): ?>
                        <div class="flex justify-between text-green-600">
                            <span>Discount</span>
                            <span>-<?php echo formatPrice($discount); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="flex justify-between font-bold text-lg text-gray-900 mb-6">
                    <span>Total Amount</span>
                    <span class="text-primary"><?php echo formatPrice($order['total_amount']); ?></span>
                </div>

                <!-- Payment Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="text-xs text-gray-600 uppercase font-semibold mb-2">Payment Method</p>
                    <p class="font-bold text-gray-900"><?php echo ucfirst($order['payment_method']); ?></p>
                </div>

                <!-- Actions -->
                <div class="space-y-2">
                    <button onclick="window.print()" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
                        <i class="fas fa-print"></i> Print Order
                    </button>
                    <button onclick="alert('Invoice will be sent to your email')" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
                        <i class="fas fa-download"></i> Download Invoice
                    </button>
                    
                    <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                        <a href="/pages/order-history.php" class="block w-full bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-medium transition text-center">
                            <i class="fas fa-times"></i> Cancel Order
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
