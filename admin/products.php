<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

// Handle verification status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $product_id = (int)$_POST['product_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE products SET verification_status = 'approved' WHERE id = ?");
        $stmt->execute([$product_id]);
        $_SESSION['flash_success'] = "Product approved successfully!";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE products SET verification_status = 'rejected' WHERE id = ?");
        $stmt->execute([$product_id]);
        $_SESSION['flash_success'] = "Product rejected!";
    } elseif ($action === 'toggle_status') {
        $new_status = $_POST['new_status'];
        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $product_id]);
        $_SESSION['flash_success'] = "Product status updated to " . ucfirst($new_status);
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Get filtering parameters
$vendor_filter = $_GET['vendor'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get vendors for dropdown
$stmt = $conn->prepare("SELECT id, shop_name FROM vendors WHERE status = 'approved' ORDER BY shop_name");
$stmt->execute();
$vendors = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $conn->prepare("SELECT id, name FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

// Build query
$where = "1=1";
$params = [];

if ($vendor_filter !== 'all') {
    $where .= " AND p.vendor_id = ?";
    $params[] = $vendor_filter;
}

if ($category_filter !== 'all') {
    $where .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter !== 'all') {
    $where .= " AND p.verification_status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

// Count total
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products p WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pages = ceil($total / $limit);

// Count pending products
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE verification_status = 'pending'");
$stmt->execute();
$pending_count = $stmt->fetch()['count'];

// Fetch products
$stmt = $conn->prepare("
    SELECT p.*, 
           c.name as category_name, 
           v.shop_name,
           COUNT(pi.id) as image_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN product_images pi ON p.id = pi.product_id
    WHERE $where
    GROUP BY p.id
    ORDER BY 
        CASE WHEN p.verification_status = 'pending' THEN 0 ELSE 1 END,
        p.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$products = $stmt->fetchAll();

$page_title = 'Manage Products - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/admin/dashboard.php" class="text-primary hover:text-indigo-700 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">
            <i class="fas fa-boxes text-primary"></i> Product Management
        </h1>
        <p class="text-gray-600 mt-2">View and manage all products on the platform</p>
    </div>

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
        </div>
    <?php endif; ?>

    <!-- Pending Alert -->
    <?php if ($pending_count > 0): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <p><strong><?php echo $pending_count; ?> product(s)</strong> are awaiting verification. 
                   <a href="?status=pending" class="underline font-bold">View Pending Products</a></p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                <form method="GET" class="relative">
                    <input type="hidden" name="vendor" value="<?php echo $vendor_filter; ?>">
                    <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                    <input type="text" name="search" placeholder="Product name..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Verification Status</label>
                <select onchange="location.href='?status=' + this.value + '&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>✅ Approved</option>
                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>❌ Rejected</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
                <select onchange="location.href='?vendor=' + this.value + '&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <option value="all" <?php echo $vendor_filter === 'all' ? 'selected' : ''; ?>>All Vendors</option>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?php echo $vendor['id']; ?>" <?php echo $vendor_filter === (string)$vendor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($vendor['shop_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select onchange="location.href='?vendor=<?php echo $vendor_filter; ?>&category=' + this.value + '&status=<?php echo $status_filter; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter === (string)$category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Results</label>
                <p class="px-4 py-2 text-gray-700 font-medium"><?php echo $total; ?> products</p>
            </div>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <?php if (empty($products)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-box text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Products Found</h3>
                <p class="text-gray-600">Try adjusting your search or filter</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Product</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Vendor</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Category</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Price</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Status</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Added</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50 transition <?php echo ($product['verification_status'] ?? '') === 'pending' ? 'bg-yellow-50' : ''; ?>">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                        <p class="text-xs text-gray-500">ID: <?php echo $product['id']; ?> • <?php echo $product['image_count']; ?> images</p>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    <?php echo htmlspecialchars($product['shop_name'] ?? 'Deleted'); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                </td>
                                <td class="px-6 py-4 text-gray-900 font-medium">
                                    ₹<?php echo number_format($product['price'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php 
                                        $status = $product['verification_status'] ?? 'approved';
                                        $statusClass = $status === 'approved' ? 'bg-green-100 text-green-700' : 
                                                      ($status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                                        $statusIcon = $status === 'approved' ? '✅' : ($status === 'pending' ? '⏳' : '❌');
                                    ?>
                                    <span class="inline-block px-3 py-1 text-xs rounded-full <?php echo $statusClass; ?>">
                                        <?php echo $statusIcon . ' ' . ucfirst($status); ?>
                                    </span>
                                    <div class="mt-1">
                                        <?php 
                                            $isActive = ($product['status'] ?? 'inactive') === 'active';
                                            echo $isActive 
                                                ? '<span class="text-xs text-green-600 font-medium">● Active</span>' 
                                                : '<span class="text-xs text-gray-500">○ Inactive</span>';
                                        ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <?php if (($product['verification_status'] ?? '') === 'pending'): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-medium">
                                                    ✓ Approve
                                                </button>
                                            </form>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-medium">
                                                    ✗ Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" class="inline ml-2" onsubmit="return confirm('Change status?');">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="new_status" value="<?php echo ($product['status'] ?? 'inactive') === 'active' ? 'inactive' : 'active'; ?>">
                                            <button type="submit" class="text-xs font-medium px-2 py-1 rounded border <?php echo ($product['status'] ?? 'inactive') === 'active' ? 'border-red-300 text-red-600 hover:bg-red-50' : 'border-green-300 text-green-600 hover:bg-green-50'; ?>">
                                                <?php echo ($product['status'] ?? 'inactive') === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <a href="/admin/product-detail.php?id=<?php echo $product['id']; ?>" class="text-primary hover:text-indigo-700 text-sm font-medium ml-2" title="View Details">
                                            <i class="fas fa-eye"></i> View
                                        </a>
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
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded <?php echo $i === $page ? 'bg-primary text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Next</a>
                        <a href="?page=<?php echo $pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
