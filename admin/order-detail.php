<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$order_id = $_GET['id'] ?? null;
if (!$order_id) {
    header('Location: /admin/orders.php');
    exit;
}

// Fetch order details
$stmt = $conn->prepare("
    SELECT o.*, u.full_name, u.email, u.phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /admin/orders.php');
    exit;
}

// Fetch order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name as product_name, p.price
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

// Fetch status updates
$stmt = $conn->prepare("
    SELECT * FROM order_status_updates
    WHERE order_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$order_id]);
$status_updates = $stmt->fetchAll();

// Get status options
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// Handle status update
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $new_status = $_POST['new_status'];
        if (in_array($new_status, $statuses)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $order_id])) {
                // Log status update
                $stmt = $conn->prepare("INSERT INTO order_status_updates (order_id, status, notes) VALUES (?, ?, ?)");
                $stmt->execute([$order_id, $new_status, $_POST['notes'] ?? '']);
                $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Order status updated successfully</div>';
                // Refresh page
                header('Refresh: 2; url=/admin/order-detail.php?id=' . $order_id);
            }
        }
    }
}

$page_title = 'Order Details - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <a href="/admin/orders.php" class="text-primary hover:text-indigo-700 mb-6 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Orders
    </a>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Header -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Order #<?php echo htmlspecialchars($order['order_number']); ?></h1>
                        <p class="text-gray-600 mt-1">Placed on <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                    </div>
                    <span class="inline-block px-4 py-2 text-sm rounded-full <?php 
                        echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' :
                             ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' :
                              ($order['status'] === 'processing' ? 'bg-yellow-100 text-yellow-700' :
                               ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')));
                    ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Order Items -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Order Items</h3>
                <div class="space-y-4">
                    <?php foreach ($items as $item): ?>
                        <div class="border-b border-gray-200 pb-4 last:border-b-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                    <p class="text-sm text-gray-600">Qty: <?php echo $item['quantity']; ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-gray-900">₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?></p>
                                    <p class="text-sm text-gray-600">₹<?php echo number_format($item['price'], 2); ?> each</p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Order Summary</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-700">Subtotal:</span>
                        <span class="font-medium">₹<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-700">Shipping:</span>
                        <span class="font-medium">₹<?php echo number_format($order['shipping_cost'] ?? 0, 2); ?></span>
                    </div>
                    <div class="flex justify-between border-t border-gray-200 pt-2 mt-2">
                        <span class="font-bold text-gray-900">Total:</span>
                        <span class="text-2xl font-bold text-primary">₹<?php echo number_format($order['total_amount'] + ($order['shipping_cost'] ?? 0), 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Status Timeline -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Status Timeline</h3>
                <div class="space-y-4">
                    <?php if (empty($status_updates)): ?>
                        <p class="text-gray-600">No status updates yet</p>
                    <?php else: ?>
                        <?php foreach ($status_updates as $update): ?>
                            <div class="flex gap-4">
                                <div class="w-3 h-3 bg-primary rounded-full mt-1 flex-shrink-0"></div>
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo ucfirst($update['status']); ?></p>
                                    <p class="text-sm text-gray-600">
                                        <?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?>
                                    </p>
                                    <?php if ($update['notes']): ?>
                                        <p class="text-sm text-gray-700 mt-1"><?php echo htmlspecialchars($update['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Customer Info -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Customer Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-gray-600">Name</label>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['full_name']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Email</label>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['email']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Phone</label>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Shipping Address</h3>
                <p class="text-gray-700">
                    <?php echo nl2br(htmlspecialchars($order['shipping_address'] ?? 'Not provided')); ?>
                </p>
            </div>

            <!-- Update Status -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Update Status</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                        <select name="new_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo $status; ?>" <?php echo $order['status'] === $status ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add a note about this status update..." 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"></textarea>
                    </div>
                    
                    <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                        Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
