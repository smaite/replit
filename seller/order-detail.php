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
        SELECT oi.*, p.name, p.image, oi.vendor_status
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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        try {
            $new_status = sanitize($_POST['vendor_status']);
            
            // Update vendor status for all vendor's items in this order
            $stmt = $conn->prepare("
                UPDATE order_items oi
                JOIN products p ON oi.product_id = p.id
                SET oi.vendor_status = ?
                WHERE oi.order_id = ? AND p.vendor_id = ?
            ");
            $stmt->execute([$new_status, $order_id, $vendor_id]);
            
            $success = 'Order status updated successfully!';
            
            // Reload items
            $stmt = $conn->prepare("
                SELECT oi.*, p.name, p.image, oi.vendor_status
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
include '../includes/seller_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Order Details</h1>
                <p class="text-gray-600 mt-1">
                    Order #<span class="font-mono font-medium text-primary"><?php echo htmlspecialchars($order['order_number'] ?? ''); ?></span>
                </p>
            </div>
            <a href="/seller/orders.php" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-6 py-2 rounded-lg font-medium">
                <i class="fas fa-arrow-left mr-2"></i> Back to Orders
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-lg mb-6">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($order): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Status Card -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-gray-900">
                            <i class="fas fa-clipboard-check text-primary"></i> Order Status
                        </h2>
                        <?php
                        $status = $order['status'];
                        $status_classes = [
                            'pending' => 'bg-yellow-100 text-yellow-700',
                            'processing' => 'bg-blue-100 text-blue-700',
                            'shipped' => 'bg-purple-100 text-purple-700',
                            'delivered' => 'bg-green-100 text-green-700',
                            'cancelled' => 'bg-red-100 text-red-700'
                        ];
                        ?>
                        <span class="px-4 py-2 rounded-full font-medium <?php echo $status_classes[$status] ?? 'bg-gray-100 text-gray-700'; ?>">
                            <?php echo ucfirst($status); ?>
                        </span>
                    </div>
                    
                    <!-- Update Your Status -->
                    <form method="POST" class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="update_status" value="1">
                        
                        <label class="font-medium text-gray-700">Your Status:</label>
                        <select name="vendor_status" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                            <?php $current_status = $order_items[0]['vendor_status'] ?? 'pending'; ?>
                            <option value="pending" <?php echo $current_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $current_status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="ready" <?php echo $current_status === 'ready' ? 'selected' : ''; ?>>Ready to Ship</option>
                            <option value="shipped" <?php echo $current_status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        </select>
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                            Update
                        </button>
                    </form>
                </div>
                
                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-box text-primary"></i> Your Items in This Order
                    </h2>
                    
                    <div class="space-y-4">
                        <?php foreach ($order_items as $item): ?>
                            <div class="flex items-center gap-4 p-4 border rounded-lg">
                                <img src="<?php echo htmlspecialchars($item['image'] ?? '/assets/images/placeholder.png'); ?>" 
                                     class="w-16 h-16 object-cover rounded-lg">
                                <div class="flex-1">
                                    <h4 class="font-medium"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p class="text-sm text-gray-500">Qty: <?php echo $item['quantity']; ?> × Rs. <?php echo number_format($item['price'], 2); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold">Rs. <?php echo number_format($item['quantity'] * $item['price'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-4 pt-4 border-t flex justify-between items-center">
                        <span class="font-semibold text-gray-700">Your Total:</span>
                        <span class="text-2xl font-bold text-primary">Rs. <?php echo number_format($vendor_total, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Customer Info -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-user text-primary"></i> Customer
                    </h2>
                    <div class="space-y-3">
                        <p><span class="text-gray-500">Name:</span> <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                        <p><span class="text-gray-500">Email:</span> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                        <?php if ($order['customer_phone']): ?>
                            <p><span class="text-gray-500">Phone:</span> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Shipping Info -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-truck text-primary"></i> Shipping
                    </h2>
                    <div class="space-y-3">
                        <p><span class="text-gray-500">Phone:</span> <?php echo htmlspecialchars($order['shipping_phone'] ?? 'N/A'); ?></p>
                        <p><span class="text-gray-500">Address:</span></p>
                        <p class="text-sm bg-gray-50 p-3 rounded"><?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'N/A')); ?></p>
                    </div>
                </div>
                
                <!-- Payment Info -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-credit-card text-primary"></i> Payment
                    </h2>
                    <div class="space-y-3">
                        <p>
                            <span class="text-gray-500">Method:</span> 
                            <strong><?php echo strtoupper($order['payment_method'] ?? 'COD'); ?></strong>
                        </p>
                        <p>
                            <span class="text-gray-500">Status:</span>
                            <?php
                            $payment_status = $order['payment_status'] ?? 'pending';
                            $ps_classes = [
                                'pending' => 'bg-yellow-100 text-yellow-700',
                                'paid' => 'bg-green-100 text-green-700',
                                'failed' => 'bg-red-100 text-red-700'
                            ];
                            ?>
                            <span class="px-2 py-1 rounded text-sm <?php echo $ps_classes[$payment_status] ?? 'bg-gray-100 text-gray-700'; ?>">
                                <?php echo ucfirst($payment_status); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <!-- Order Date -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-calendar text-primary"></i> Timeline
                    </h2>
                    <div class="space-y-2 text-sm">
                        <p>
                            <span class="text-gray-500">Ordered:</span>
                            <?php echo date('M j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                        </p>
                        <?php if ($order['updated_at']): ?>
                            <p>
                                <span class="text-gray-500">Updated:</span>
                                <?php echo date('M j, Y \a\t g:i A', strtotime($order['updated_at'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
