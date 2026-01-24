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
} catch (Exception $e) {
}

$page_title = 'My Orders - Seller Dashboard';
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
            <span class="text-gray-900 font-medium">My Orders</span>
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
                        <h1 class="text-3xl font-bold text-gray-900">My Orders</h1>
                        <p class="text-gray-500 mt-1">Manage and track your customer orders</p>
                    </div>
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

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Filters -->
                    <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex flex-col sm:flex-row gap-4 justify-between items-center">
                        <div class="flex overflow-x-auto pb-2 sm:pb-0 gap-2 w-full sm:w-auto no-scrollbar">
                            <a href="?status=" class="px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-colors <?php echo !$status_filter ? 'bg-primary text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                                All Orders
                            </a>
                            <a href="?status=pending" class="px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-colors <?php echo $status_filter === 'pending' ? 'bg-yellow-500 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                                Pending
                            </a>
                            <a href="?status=processing" class="px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-colors <?php echo $status_filter === 'processing' ? 'bg-blue-500 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                                Processing
                            </a>
                            <a href="?status=shipped" class="px-4 py-2 rounded-lg text-sm font-bold whitespace-nowrap transition-colors <?php echo $status_filter === 'shipped' ? 'bg-purple-500 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'; ?>">
                                Shipped
                            </a>
                        </div>

                        <form class="relative w-full sm:w-64" method="GET">
                            <?php if ($status_filter): ?>
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                            <?php endif; ?>
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                   placeholder="Search orders..."
                                   class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                        </form>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="p-16 text-center">
                            <div class="w-24 h-24 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-shopping-bag text-4xl text-primary/50"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No Orders Found</h3>
                            <p class="text-gray-500 mb-6 max-w-md mx-auto">
                                <?php echo $search ? "No orders matched your search criteria." : "You haven't received any orders yet."; ?>
                            </p>
                            <?php if ($search || $status_filter): ?>
                                <a href="/seller/orders.php" class="text-primary font-bold hover:underline">Clear Filters</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold">Order ID</th>
                                        <th class="px-6 py-4 font-semibold">Customer</th>
                                        <th class="px-6 py-4 font-semibold">Date</th>
                                        <th class="px-6 py-4 font-semibold">Total</th>
                                        <th class="px-6 py-4 font-semibold">Status</th>
                                        <th class="px-6 py-4 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="hover:bg-gray-50 transition-colors group">
                                            <td class="px-6 py-4">
                                                <span class="font-mono font-bold text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                                                <div class="text-xs text-gray-500 mt-0.5"><?php echo $order['item_count']; ?> Items</div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 text-xs font-bold">
                                                        <?php echo strtoupper(substr($order['customer_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($order['customer_email']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600">
                                                <?php echo date('M j, Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 font-bold text-gray-900">
                                                <?php echo formatPrice($order['vendor_total']); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php
                                                $status = $order['vendor_status'];
                                                $status_classes = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    'processing' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    'shipped' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    'delivered' => 'bg-green-100 text-green-800 border-green-200',
                                                    'cancelled' => 'bg-red-100 text-red-800 border-red-200'
                                                ];
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $status_classes[$status] ?? 'bg-gray-100 text-gray-800 border-gray-200'; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="/seller/order-detail.php?id=<?php echo $order['id']; ?>"
                                                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-400 hover:text-primary hover:border-primary hover:bg-indigo-50 transition-colors"
                                                   title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between">
                                <p class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> orders</p>
                                <div class="flex gap-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search); ?>"
                                           class="px-3 py-1 border border-gray-200 rounded-lg bg-white text-gray-600 hover:bg-gray-50 transition font-medium text-sm">
                                            Previous
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo htmlspecialchars($search); ?>"
                                           class="px-3 py-1 border border-gray-200 rounded-lg bg-white text-gray-600 hover:bg-gray-50 transition font-medium text-sm">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
