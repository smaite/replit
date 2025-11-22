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

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/pages/dashboard.php" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">Order History</h1>
        <p class="text-gray-600 mt-2">View and manage all your orders</p>
    </div>

    <!-- Filters and Sort -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Status Filter -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                <form method="GET" action="">
                    <select name="status" onchange="this.form.submit()" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                        <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </form>
            </div>

            <!-- Sort -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Sort by</label>
                <form method="GET" action="">
                    <select name="sort" onchange="this.form.submit()" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <option value="latest" <?php echo $sort === 'latest' ? 'selected' : ''; ?>>Latest Orders</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest Orders</option>
                        <option value="amount_high" <?php echo $sort === 'amount_high' ? 'selected' : ''; ?>>Highest Amount</option>
                        <option value="amount_low" <?php echo $sort === 'amount_low' ? 'selected' : ''; ?>>Lowest Amount</option>
                    </select>
                </form>
            </div>

            <!-- Results Count -->
            <div class="flex items-end">
                <p class="text-gray-600">
                    <span class="font-bold text-gray-900"><?php echo $total_orders; ?></span> order<?php echo $total_orders !== 1 ? 's' : ''; ?> found
                </p>
            </div>
        </div>
    </div>

    <!-- Orders List -->
    <?php if (empty($orders)): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">No Orders Found</h2>
            <p class="text-gray-600 mb-6">
                <?php echo $status_filter !== 'all' ? 'No orders with this status. Try another filter.' : 'Start shopping and your orders will appear here'; ?>
            </p>
            <a href="/pages/products.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                <i class="fas fa-shopping-bag"></i> Browse Products
            </a>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($orders as $order): ?>
                <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Order Number</p>
                            <p class="font-bold text-lg text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Order Date</p>
                            <p class="text-gray-900 font-medium"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></p>
                            <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Total Amount</p>
                            <p class="font-bold text-lg text-primary"><?php echo formatPrice($order['total_amount']); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 uppercase font-semibold mb-2">Status</p>
                            <span class="inline-block px-4 py-2 text-sm rounded-full font-medium <?php 
                                echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' : 
                                     ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 
                                      ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' : 
                                       ($order['status'] === 'processing' ? 'bg-orange-100 text-orange-700' : 'bg-yellow-100 text-yellow-700'))); 
                            ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="border-t pt-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Shipping Address</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars(substr($order['shipping_address'], 0, 50)); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 uppercase font-semibold mb-1">Payment Method</p>
                                <p class="text-gray-900 font-medium"><?php echo ucfirst($order['payment_method']); ?></p>
                                <span class="inline-block mt-1 px-2 py-1 text-xs rounded <?php 
                                    echo $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; 
                                ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                            </div>
                            <div class="text-right">
                                <a href="/pages/order-details.php?id=<?php echo $order['id']; ?>" 
                                   class="inline-block bg-primary hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-medium transition">
                                    View Details <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center items-center gap-2">
                <?php if ($page > 1): ?>
                    <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=1" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-step-backward"></i>
                    </a>
                    <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page - 1; ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
                       class="px-4 py-2 rounded-lg border <?php echo $i === $page ? 'bg-primary text-white border-primary' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page + 1; ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <a href="?status=<?php echo $status_filter; ?>&sort=<?php echo $sort; ?>&page=<?php echo $total_pages; ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-step-forward"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
