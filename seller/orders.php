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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        try {
            $order_id = (int)$_POST['order_id'];
            $new_status = sanitize($_POST['status']);
            
            // Get order item that belongs to this vendor
            $stmt = $conn->prepare("
                SELECT oi.id 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = ? AND p.vendor_id = ?
                LIMIT 1
            ");
            $stmt->execute([$order_id, $vendor_id]);
            $item = $stmt->fetch();
            
            if ($item) {
                // Update vendor-specific status in order_items
                $stmt = $conn->prepare("
                    UPDATE order_items oi
                    JOIN products p ON oi.product_id = p.id
                    SET oi.vendor_status = ?
                    WHERE oi.order_id = ? AND p.vendor_id = ?
                ");
                $stmt->execute([$new_status, $order_id, $vendor_id]);
                $success = 'Order status updated successfully!';
            } else {
                $error = 'Order not found or you do not have permission.';
            }
        } catch (Exception $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    }
}

// Filters
$status_filter = sanitize($_GET['status'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query - Get orders containing vendor's products
$where = "p.vendor_id = ?";
$params = [$vendor_id];

if ($status_filter) {
    $where .= " AND oi.vendor_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Get orders
try {
    $stmt = $conn->prepare("
        SELECT DISTINCT o.*, 
               u.full_name as customer_name, 
               u.email as customer_email,
               SUM(oi.quantity * oi.price) as vendor_total,
               COUNT(DISTINCT oi.id) as item_count,
               oi.vendor_status
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE $where
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    // Get total count
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.id)
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        JOIN users u ON o.user_id = u.id
        WHERE $where
    ");
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $error = 'Failed to load orders: ' . $e->getMessage();
    $orders = [];
    $total = 0;
    $total_pages = 0;
}

// Get order counts by status
$status_counts = [];
try {
    $stmt = $conn->prepare("
        SELECT oi.vendor_status as status, COUNT(DISTINCT o.id) as count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
        GROUP BY oi.vendor_status
    ");
    $stmt->execute([$vendor_id]);
    $counts = $stmt->fetchAll();
    foreach ($counts as $c) {
        $status_counts[$c['status']] = $c['count'];
    }
} catch (Exception $e) {}

$page_title = 'My Orders - Seller Dashboard';
include '../includes/seller_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">My Orders</h1>
            <p class="text-gray-600 mt-1">View and manage orders for your products</p>
        </div>
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
    
    <!-- Status Tabs -->
    <div class="mb-6 flex flex-wrap gap-2">
        <a href="?status=" class="px-4 py-2 rounded-lg font-medium <?php echo !$status_filter ? 'bg-primary text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            All (<?php echo array_sum($status_counts); ?>)
        </a>
        <a href="?status=pending" class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            Pending (<?php echo $status_counts['pending'] ?? 0; ?>)
        </a>
        <a href="?status=processing" class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'processing' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            Processing (<?php echo $status_counts['processing'] ?? 0; ?>)
        </a>
        <a href="?status=shipped" class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'shipped' ? 'bg-purple-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            Shipped (<?php echo $status_counts['shipped'] ?? 0; ?>)
        </a>
        <a href="?status=delivered" class="px-4 py-2 rounded-lg font-medium <?php echo $status_filter === 'delivered' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'; ?>">
            Delivered (<?php echo $status_counts['delivered'] ?? 0; ?>)
        </a>
    </div>
    
    <!-- Search -->
    <div class="mb-6">
        <form class="flex gap-2" method="GET">
            <?php if ($status_filter): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by order number or customer..."
                   class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
            <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fas fa-search"></i>
            </button>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <?php if (empty($orders)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-lg">No orders found</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Order</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Customer</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Items</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Your Total</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Date</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <span class="font-mono font-medium text-primary">
                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="bg-gray-100 px-2 py-1 rounded text-sm">
                                        <?php echo $order['item_count']; ?> item(s)
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold">
                                    Rs. <?php echo number_format($order['vendor_total'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $status = $order['vendor_status'];
                                    $status_classes = [
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'processing' => 'bg-blue-100 text-blue-700',
                                        'shipped' => 'bg-purple-100 text-purple-700',
                                        'delivered' => 'bg-green-100 text-green-700',
                                        'cancelled' => 'bg-red-100 text-red-700'
                                    ];
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_classes[$status] ?? 'bg-gray-100 text-gray-700'; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M j, Y', strtotime($order['created_at'])); ?><br>
                                    <span class="text-xs"><?php echo date('g:i A', strtotime($order['created_at'])); ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <a href="/seller/order-detail.php?id=<?php echo $order['id']; ?>" 
                                       class="bg-primary text-white px-4 py-2 rounded-lg text-sm hover:bg-indigo-700">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="px-6 py-4 border-t flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> orders
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="px-4 py-2 border rounded-lg hover:bg-gray-100">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search); ?>" 
                               class="px-4 py-2 border rounded-lg hover:bg-gray-100">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
