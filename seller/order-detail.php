<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check that user is logged in as vendor
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

$vendor_id = $_SESSION['vendor_id'] ?? null;
if (!$vendor_id) {
    redirect('/seller/index.php');
}

$stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();

$error = '';
$success = '';

// Get order ID
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$order_id) {
    redirect('/seller/orders.php');
}

// Fetch order with vendor's items only
try {
    $stmt = $conn->prepare("
        SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        $_SESSION['error'] = 'Order not found.';
        redirect('/seller/orders.php');
    }

    // Get vendor's items in this order
    $stmt = $conn->prepare("
        SELECT oi.*, p.name,
               (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
               oi.vendor_status, oi.cancel_reason
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ? AND p.vendor_id = ?
    ");
    $stmt->execute([$order_id, $vendor_id]);
    $order_items = $stmt->fetchAll();

    if (empty($order_items)) {
        $_SESSION['error'] = 'You do not have any items in this order.';
        redirect('/seller/orders.php');
    }

    // Calculate vendor total
    $vendor_total = 0;
    foreach ($order_items as $item) {
        $vendor_total += $item['quantity'] * $item['price'];
    }

} catch (Exception $e) {
    $error = 'Failed to load order: ' . $e->getMessage();
    $order = null;
    $order_items = [];
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $reason = sanitize($_POST['cancel_reason']);
        if (empty($reason)) {
            $error = 'Please provide a reason for cancellation.';
        } else {
            try {
                // Update vendor status to cancelled and save reason
                $stmt = $conn->prepare("
                    UPDATE order_items oi
                    JOIN products p ON oi.product_id = p.id
                    SET oi.vendor_status = 'cancelled', oi.cancel_reason = ?
                    WHERE oi.order_id = ? AND p.vendor_id = ?
                ");
                $stmt->execute([$reason, $order_id, $vendor_id]);

                $success = 'Order cancelled successfully.';

                // Reload items
                $stmt = $conn->prepare("
                    SELECT oi.*, p.name,
                           (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
                           oi.vendor_status, oi.cancel_reason
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ? AND p.vendor_id = ?
                ");
                $stmt->execute([$order_id, $vendor_id]);
                $order_items = $stmt->fetchAll();

            } catch (Exception $e) {
                $error = 'Failed to cancel order: ' . $e->getMessage();
            }
        }
    }
}

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $new_status = sanitize($_POST['status']);
        try {
            $stmt = $conn->prepare("
                UPDATE order_items oi
                JOIN products p ON oi.product_id = p.id
                SET oi.vendor_status = ?
                WHERE oi.order_id = ? AND p.vendor_id = ?
            ");
            $stmt->execute([$new_status, $order_id, $vendor_id]);
            $success = 'Order status updated successfully.';

            // Reload items
            $stmt = $conn->prepare("
                SELECT oi.*, p.name,
                       (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image,
                       oi.vendor_status, oi.cancel_reason
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ? AND p.vendor_id = ?
            ");
            $stmt->execute([$order_id, $vendor_id]);
            $order_items = $stmt->fetchAll();
        } catch (Exception $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    }
}

$page_title = 'Order #' . htmlspecialchars($order['order_number'] ?? '') . ' - Seller Dashboard';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/seller/" class="hover:text-primary">Seller Dashboard</a>
            <span class="mx-2">/</span>
            <a href="/seller/orders.php" class="hover:text-primary">My Orders</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Order #<?php echo htmlspecialchars($order['order_number'] ?? ''); ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-2xl font-bold border border-indigo-200">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Shop Dashboard</p>
                                <h3 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($vendor['shop_name']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <nav class="p-4 space-y-1">
                        <a href="/seller/" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-chart-line w-5 group-hover:text-primary transition-colors"></i> Dashboard
                        </a>
                        <a href="/seller/products.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-box w-5 group-hover:text-primary transition-colors"></i> My Products
                        </a>
                        <a href="/seller/add-product.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-plus-circle w-5 group-hover:text-primary transition-colors"></i> Add Product
                        </a>
                        <a href="/seller/orders.php" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-bold rounded-lg transition-colors">
                            <i class="fas fa-shopping-cart w-5"></i> Orders
                        </a>
                        <a href="/seller/settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-cog w-5 group-hover:text-primary transition-colors"></i> Shop Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                            Order #<?php echo htmlspecialchars($order['order_number'] ?? ''); ?>
                            <span class="text-base font-normal text-gray-500">
                                via <?php echo strtoupper($order['payment_method']); ?>
                            </span>
                        </h1>
                        <p class="text-gray-500 mt-1">Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                    <a href="/seller/orders.php" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition shadow-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Orders
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Order Items Column -->
                    <div class="md:col-span-2 space-y-6">
                        <!-- Items Card -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                                <h2 class="font-bold text-gray-900 text-lg">Order Items</h2>
                                <div class="text-sm">
                                    <span class="text-gray-500">Vendor Status:</span>
                                    <?php
                                    $status = $order_items[0]['vendor_status'] ?? 'pending';
                                    $status_classes = [
                                        'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                        'processing' => 'bg-blue-100 text-blue-800 border-blue-200',
                                        'shipped' => 'bg-purple-100 text-purple-800 border-purple-200',
                                        'delivered' => 'bg-green-100 text-green-800 border-green-200',
                                        'cancelled' => 'bg-red-100 text-red-800 border-red-200'
                                    ];
                                    ?>
                                    <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $status_classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </div>
                            </div>

                            <div class="divide-y divide-gray-100">
                                <?php foreach ($order_items as $item): ?>
                                    <div class="p-6 flex gap-4 items-start">
                                        <div class="w-20 h-20 bg-gray-100 rounded-lg border border-gray-200 overflow-hidden flex-shrink-0">
                                            <img src="<?php echo htmlspecialchars($item['image'] ?? '/assets/images/placeholder.jpg'); ?>"
                                                 class="w-full h-full object-cover">
                                        </div>
                                        <div class="flex-1">
                                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($item['name']); ?></h3>
                                            <p class="text-sm text-gray-500 mt-1">Quantity: <?php echo $item['quantity']; ?></p>

                                            <?php if ($item['cancel_reason']): ?>
                                                <div class="mt-2 text-sm text-red-600 bg-red-50 p-2 rounded-lg inline-block">
                                                    <strong>Cancelled:</strong> <?php echo htmlspecialchars($item['cancel_reason']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-gray-900"><?php echo formatPrice($item['price'] * $item['quantity']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo formatPrice($item['price']); ?> / unit</p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="bg-gray-50 p-6 flex justify-between items-center">
                                <span class="font-bold text-gray-700">Subtotal (Your Items)</span>
                                <span class="font-bold text-xl text-primary"><?php echo formatPrice($vendor_total); ?></span>
                            </div>
                        </div>

                        <!-- Update Status Card -->
                        <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="font-bold text-gray-900 text-lg mb-4">Update Status</h2>
                            <form method="POST" class="flex gap-4 items-end">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="update_status" value="1">

                                <div class="flex-1">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Change Order Status</label>
                                    <select name="status" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none cursor-pointer">
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    </select>
                                </div>
                                <button type="submit" class="px-6 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-lg shadow-indigo-200">
                                    Update
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="space-y-6">
                        <!-- Customer Info -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="font-bold text-gray-900 text-lg mb-4 flex items-center gap-2">
                                <i class="fas fa-user text-primary"></i> Customer
                            </h2>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-lg">
                                    <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                    <p class="text-sm text-gray-500">Customer</p>
                                </div>
                            </div>
                            <div class="space-y-3 text-sm">
                                <div class="flex items-center gap-3 text-gray-600">
                                    <i class="fas fa-envelope w-5 text-center"></i>
                                    <a href="mailto:<?php echo htmlspecialchars($order['customer_email']); ?>" class="hover:text-primary"><?php echo htmlspecialchars($order['customer_email']); ?></a>
                                </div>
                                <div class="flex items-center gap-3 text-gray-600">
                                    <i class="fas fa-phone w-5 text-center"></i>
                                    <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" class="hover:text-primary"><?php echo htmlspecialchars($order['customer_phone']); ?></a>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Info -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="font-bold text-gray-900 text-lg mb-4 flex items-center gap-2">
                                <i class="fas fa-truck text-primary"></i> Shipping Details
                            </h2>
                            <div class="space-y-3 text-sm">
                                <div>
                                    <span class="block text-gray-500 text-xs uppercase font-bold tracking-wide">Contact</span>
                                    <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                                </div>
                                <div>
                                    <span class="block text-gray-500 text-xs uppercase font-bold tracking-wide">Address</span>
                                    <p class="text-gray-900 font-medium leading-relaxed bg-gray-50 p-3 rounded-lg mt-1 border border-gray-100">
                                        <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Cancellation Action -->
                        <?php if ($status !== 'cancelled' && $status !== 'delivered'): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="font-bold text-red-600 text-lg mb-4 flex items-center gap-2">
                                <i class="fas fa-ban"></i> Cancel Order
                            </h2>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? This cannot be undone.');">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="cancel_order" value="1">

                                <div class="mb-3">
                                    <textarea name="cancel_reason" rows="3" required
                                              class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-red-500 transition-colors"
                                              placeholder="Reason for cancellation..."></textarea>
                                </div>
                                <button type="submit" class="w-full px-4 py-2 bg-red-50 text-red-600 border border-red-100 font-bold rounded-lg hover:bg-red-100 transition">
                                    Cancel Order
                                </button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
