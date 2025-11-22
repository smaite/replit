<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

// Get filtering parameters
$status_filter = $_GET['status'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
$where = "1=1";
$params = [];

if ($status_filter !== 'all') {
    $where .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($user_filter !== 'all') {
    $where .= " AND o.user_id = ?";
    $params[] = $user_filter;
}

if ($search) {
    $where .= " AND (o.order_number LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pages = ceil($total / $limit);

// Fetch orders
$stmt = $conn->prepare("
    SELECT o.*, u.full_name, u.email, u.phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE $where
    ORDER BY o.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get status options
$statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $order_id = (int)$_POST['order_id'];
        $new_status = $_POST['new_status'];
        if (in_array($new_status, $statuses)) {
            $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $order_id])) {
                header('Location: /admin/orders.php');
                exit;
            }
        }
    }
}

$page_title = 'Manage Orders - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/admin/dashboard.php" class="text-primary hover:text-indigo-700 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">
            <i class="fas fa-shopping-bag text-primary"></i> Order Management
        </h1>
        <p class="text-gray-600 mt-2">Track and manage customer orders</p>
    </div>

    <!-- Search & Filter -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Orders</label>
                <form method="GET" class="relative">
                    <input type="text" name="search" placeholder="Order # or customer..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                <select onchange="location.href='?status=' + this.value + '<?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Results</label>
                <p class="px-4 py-2 text-gray-700 font-medium"><?php echo $total; ?> orders</p>
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <?php if (empty($orders)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-package text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Orders Found</h3>
                <p class="text-gray-600">Try adjusting your search or filter</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Order #</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Customer</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Amount</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Date</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <p class="font-mono font-bold text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($order['full_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($order['email']); ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-900 font-bold">
                                    ₹<?php echo number_format($order['total_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-block px-3 py-1 text-xs rounded-full <?php 
                                        echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' :
                                             ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' :
                                              ($order['status'] === 'processing' ? 'bg-yellow-100 text-yellow-700' :
                                               ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')));
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="/admin/order-detail.php?id=<?php echo $order['id']; ?>" class="text-primary hover:text-indigo-700 text-sm font-medium">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <button onclick="openStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
                <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex justify-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded <?php echo $i === $page ? 'bg-primary text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Next</a>
                        <a href="?page=<?php echo $pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Status Update Modal -->
<div id="statusModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-sm w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Update Order Status</h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" id="orderId" name="order_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">New Status</label>
                <select id="statusSelect" name="new_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                    Update
                </button>
                <button type="button" onclick="closeStatusModal()" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openStatusModal(orderId, currentStatus) {
    document.getElementById('orderId').value = orderId;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('statusModal').classList.remove('hidden');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
}

document.getElementById('statusModal').addEventListener('click', function(e) {
    if (e.target === this) closeStatusModal();
});
</script>

<?php include '../includes/footer.php'; ?>
