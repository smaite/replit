<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

// Get filtering parameters
$vendor_filter = $_GET['vendor'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
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

if ($search) {
    $where .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

// Count total
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products p WHERE $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$pages = ceil($total / $limit);

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
    ORDER BY p.created_at DESC
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

    <!-- Search & Filter -->
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                <form method="GET" class="relative">
                    <input type="text" name="search" placeholder="Product name..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Vendor</label>
                <select onchange="location.href='?vendor=' + this.value + '&category=<?php echo $category_filter; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
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
                <select onchange="location.href='?vendor=<?php echo $vendor_filter; ?>&category=' + this.value + '<?php echo $search ? '&search=' . urlencode($search) : ''; ?>'" 
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
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Stock</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Images</th>
                            <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Added</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                        <p class="text-xs text-gray-500">ID: <?php echo $product['id']; ?></p>
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
                                    <span class="inline-block px-3 py-1 text-xs rounded-full <?php 
                                        echo ($product['stock'] ?? 0) > 10 ? 'bg-green-100 text-green-700' :
                                             (($product['stock'] ?? 0) > 0 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                                    ?>">
                                        <?php echo $product['stock'] ?? 0; ?> units
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    <span class="text-sm"><?php echo $product['image_count']; ?> images</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($product['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="/vendor/edit-product.php?id=<?php echo $product['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/admin/product-detail.php?id=<?php echo $product['id']; ?>" class="text-primary hover:text-indigo-700 text-sm font-medium" title="View Details">
                                            <i class="fas fa-eye"></i>
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
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>" 
                           class="px-3 py-2 rounded <?php echo $i === $page ? 'bg-primary text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-100'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Next</a>
                        <a href="?page=<?php echo $pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>&vendor=<?php echo $vendor_filter; ?>&category=<?php echo $category_filter; ?>" 
                           class="px-3 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
