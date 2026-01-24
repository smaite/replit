<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Get filtering and sorting parameters
$status_filter = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'latest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query based on filters
$where_clause = "WHERE user_id = ?";
$params = [$_SESSION['user_id']];

if ($status_filter !== 'all') {
    $where_clause .= " AND status = ?";
    $params[] = $status_filter;
}

// Get total count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders $where_clause");
$stmt->execute($params);
$total_orders = $stmt->fetch()['total'];
$total_pages = ceil($total_orders / $limit);

// Build sort clause
$order_by = "ORDER BY created_at DESC";
if ($sort === 'oldest') {
    $order_by = "ORDER BY created_at ASC";
} elseif ($sort === 'amount_high') {
    $order_by = "ORDER BY total_amount DESC";
} elseif ($sort === 'amount_low') {
    $order_by = "ORDER BY total_amount ASC";
}

// Fetch orders
$stmt = $conn->prepare("SELECT * FROM orders $where_clause $order_by LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$page_title = 'Order History - SASTO Hub';
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
            <span class="text-gray-900 font-medium">Order History</span>
        </nav>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Order History</h1>
                <p class="text-sm text-gray-500 mt-1">Check the status of recent orders, manage returns, and discover similar products.</p>
            </div>

            <!-- Filters -->
            <div class="flex gap-4">
                <form method="GET" action="" class="flex gap-4">
                    <div class="relative">
                        <select name="status" onchange="this.form.submit()"
                                class="appearance-none pl-4 pr-10 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer shadow-sm">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>

                    <div class="relative">
                        <select name="sort" onchange="this.form.submit()"
                                class="appearance-none pl-4 pr-10 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary cursor-pointer shadow-sm">
                            <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="amount_high" <?php echo $sort === 'amount_high' ? 'selected' : ''; ?>>Price High</option>
                            <option value="amount_low" <?php echo $sort === 'amount_low' ? 'selected' : ''; ?>>Price Low</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-500">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center max-w-2xl mx-auto">
                <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-box-open text-3xl text-gray-300"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">No Orders Found</h2>
                <p class="text-gray-500 mb-8">
                    <?php echo $status_filter !== 'all' ? 'We couldn\'t find any orders with this status.' : 'You haven\'t placed any orders yet.'; ?>
                </p>
                <a href="/pages/products.php" class="inline-flex items-center justify-center px-6 py-2.5 bg-primary text-white font-medium rounded-lg hover:bg-indigo-700 transition">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
                        <!-- Order Header -->
                        <div class="p-6 border-b border-gray-100 bg-gray-50/50 flex flex-wrap gap-6 justify-between items-center">
                            <div class="flex gap-8">
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Order Placed</p>
                                    <p class="text-sm font-medium text-gray-900"><?php echo date('d F Y', strtotime($order['created_at'])); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Total</p>
                                    <p class="text-sm font-medium text-gray-900"><?php echo formatPrice($order['total_amount']); ?></p>
                                </div>
                                <div class="hidden sm:block">
                                    <p class="text-xs text-gray-500 uppercase font-bold mb-1">Ship To</p>
                                    <div class="relative group cursor-help">
                                        <p class="text-sm font-medium text-primary border-b border-dashed border-primary/30 inline-block">
                                            <?php echo htmlspecialchars(explode(',', $order['shipping_address'])[0]); ?>
                                            <i class="fas fa-chevron-down text-[10px] ml-1"></i>
                                        </p>
                                        <!-- Tooltip -->
                                        <div class="absolute top-full left-0 mt-2 w-64 bg-gray-900 text-white text-xs rounded p-3 hidden group-hover:block z-10 shadow-lg">
                                            <p class="font-bold mb-1">Shipping Address:</p>
                                            <?php echo htmlspecialchars($order['shipping_address']); ?><br>
                                            Phone: <?php echo htmlspecialchars($order['shipping_phone']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <span class="text-sm font-medium text-gray-500">Order # <?php echo htmlspecialchars($order['order_number']); ?></span>
                                <a href="/pages/order-details.php?id=<?php echo $order['id']; ?>" class="text-sm font-medium text-primary hover:text-indigo-700 border border-gray-200 bg-white px-4 py-2 rounded-lg hover:bg-gray-50 transition">
                                    Manage Order
                                </a>
                            </div>
                        </div>

                        <!-- Order Body -->
                        <div class="p-6">
                            <div class="flex flex-col sm:flex-row gap-6 justify-between items-start sm:items-center">
                                <div class="flex-1">
                                    <h3 class="font-bold text-lg text-gray-900 mb-2 capitalize">
                                        <?php echo $order['status']; ?>
                                        <span class="text-xs font-normal text-gray-500 ml-2">
                                            <?php if ($order['status'] === 'delivered'): ?>
                                                Delivered on <?php echo date('M d', strtotime($order['updated_at'])); ?>
                                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                                Cancelled on <?php echo date('M d', strtotime($order['updated_at'])); ?>
                                            <?php else: ?>
                                                Estimated Delivery: <?php echo date('M d', strtotime($order['created_at'] . ' + 3 days')); ?>
                                            <?php endif; ?>
                                        </span>
                                    </h3>

                                    <!-- Progress Bar -->
                                    <?php
                                    $status_steps = ['pending', 'processing', 'shipped', 'delivered'];
                                    $current_step = array_search($order['status'], $status_steps);
                                    if ($current_step === false) $current_step = -1; // Cancelled or other
                                    ?>

                                    <?php if ($order['status'] !== 'cancelled'): ?>
                                    <div class="w-full max-w-md bg-gray-100 rounded-full h-2 mb-2">
                                        <div class="bg-green-500 h-2 rounded-full transition-all duration-500"
                                             style="width: <?php echo max(5, ($current_step + 1) * 25); ?>%"></div>
                                    </div>
                                    <?php else: ?>
                                        <div class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            Order Cancelled
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex gap-3">
                                    <?php if ($order['status'] === 'delivered'): ?>
                                        <a href="/pages/products.php" class="inline-flex items-center justify-center px-4 py-2 bg-primary text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                            <i class="fas fa-redo-alt mr-2"></i> Buy Again
                                        </a>
                                    <?php elseif ($order['status'] === 'pending'): ?>
                                         <button disabled class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-400 text-sm font-medium rounded-lg cursor-not-allowed">
                                            Processing
                                        </button>
                                    <?php endif; ?>
                                    <a href="#" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 bg-white text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">
                                        View Invoice
                                    </a>
                                </div>
                            </div>

                            <!-- Payment Info Badge -->
                             <div class="mt-4 flex items-center gap-2">
                                <span class="text-xs text-gray-500">Payment:</span>
                                <span class="text-xs font-medium text-gray-900 bg-gray-100 px-2 py-1 rounded">
                                    <?php
                                        if ($order['payment_method'] == 'cod') echo 'Cash on Delivery';
                                        elseif ($order['payment_method'] == 'esewa') echo 'eSewa Wallet';
                                        elseif ($order['payment_method'] == 'qr') echo 'QR Payment';
                                        else echo ucfirst($order['payment_method']);
                                    ?>
                                </span>
                                <span class="text-xs font-medium px-2 py-1 rounded <?php echo $order['payment_status'] == 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center">
                    <nav class="flex items-center gap-1 bg-white p-1 rounded-xl shadow-sm border border-gray-200">
                        <?php if ($page > 1): ?>
                            <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>"
                               class="p-2 w-10 h-10 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 hover:text-primary transition">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);

                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>"
                               class="w-10 h-10 flex items-center justify-center rounded-lg text-sm font-medium transition <?php echo $i === $page ? 'bg-primary text-white shadow-md' : 'text-gray-700 hover:bg-gray-50'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>"
                               class="p-2 w-10 h-10 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-50 hover:text-primary transition">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
