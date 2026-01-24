<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

$stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('/auth/become-vendor.php');
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $productId = $_GET['delete'];
    $vendorId = $vendor['id'];

    // Verify ownership
    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$productId, $vendorId]);

    if ($stmt->fetch()) {
        try {
            // Try hard delete
            $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            $_SESSION['success'] = "Product deleted successfully.";
        } catch (PDOException $e) {
            // If foreign key constraint fails (e.g. has orders), soft delete
            if ($e->getCode() == '23000') {
                $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$productId]);
                $_SESSION['warning'] = "Product has existing orders and cannot be fully deleted. It has been marked as 'Inactive' instead.";
            } else {
                $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
            }
        }
    }
    redirect('/seller/products.php');
}

// Fetch vendor's products
$stmt = $conn->prepare("SELECT p.*, pi.image_path, c.name as category_name
                        FROM products p
                        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.vendor_id = ?
                        ORDER BY p.created_at DESC");
$stmt->execute([$vendor['id']]);
$products = $stmt->fetchAll();

$page_title = 'My Products - SASTO Hub';
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
            <span class="text-gray-900 font-medium">My Products</span>
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
                        <a href="/seller/products.php" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-bold rounded-lg transition-colors">
                            <i class="fas fa-box w-5"></i> My Products
                        </a>
                        <a href="/seller/add-product.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-plus-circle w-5 group-hover:text-primary transition-colors"></i> Add Product
                        </a>
                        <a href="/seller/orders.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-shopping-cart w-5 group-hover:text-primary transition-colors"></i> Orders
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
                        <h1 class="text-3xl font-bold text-gray-900">My Products</h1>
                        <p class="text-gray-500 mt-1">Manage your product inventory</p>
                    </div>
                    <a href="/seller/add-product.php" class="inline-flex items-center px-6 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                        <i class="fas fa-plus mr-2"></i> Add New Product
                    </a>
                </div>

                <!-- Alerts -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div><?php echo $_SESSION['success']; ?></div>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['warning'])): ?>
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                        <div><?php echo $_SESSION['warning']; ?></div>
                        <?php unset($_SESSION['warning']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <div><?php echo $_SESSION['error']; ?></div>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Filters (Visual Only for now) -->
                    <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex flex-wrap gap-3 items-center justify-between">
                        <div class="relative flex-1 min-w-[200px] max-w-md">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" placeholder="Search products..."
                                   class="w-full pl-10 pr-4 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div class="flex gap-3">
                            <select class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary cursor-pointer">
                                <option>All Categories</option>
                            </select>
                            <select class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-600 focus:outline-none focus:border-primary cursor-pointer">
                                <option>Status: All</option>
                                <option>Active</option>
                                <option>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <?php if (empty($products)): ?>
                        <div class="p-16 text-center">
                            <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-box-open text-4xl text-gray-300"></i>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">No Products Found</h3>
                            <p class="text-gray-500 mb-8 max-w-md mx-auto">Your inventory is empty. Start adding products to sell to customers.</p>
                            <a href="/seller/add-product.php" class="inline-flex items-center px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition transform hover:-translate-y-0.5 shadow-lg shadow-indigo-200">
                                <i class="fas fa-plus-circle mr-2"></i> Add Your First Product
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-100">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold">Product Name</th>
                                        <th class="px-6 py-4 font-semibold">Category</th>
                                        <th class="px-6 py-4 font-semibold">Stock</th>
                                        <th class="px-6 py-4 font-semibold">Price</th>
                                        <th class="px-6 py-4 font-semibold">Status</th>
                                        <th class="px-6 py-4 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($products as $product): ?>
                                        <tr class="hover:bg-gray-50 transition-colors group">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-4">
                                                    <div class="w-16 h-16 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0 relative">
                                                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? '/assets/images/placeholder.jpg'); ?>"
                                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                             class="w-full h-full object-cover">
                                                        <?php if ($product['featured']): ?>
                                                            <div class="absolute top-0 right-0 bg-yellow-400 text-white text-[10px] px-1 font-bold rounded-bl shadow-sm">
                                                                <i class="fas fa-star"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h4 class="font-bold text-gray-900 text-sm group-hover:text-primary transition-colors mb-0.5 line-clamp-1">
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                        </h4>
                                                        <?php if ($product['sku']): ?>
                                                            <p class="text-xs text-gray-500 font-mono">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($product['stock'] <= 5): ?>
                                                    <span class="text-red-600 font-bold text-sm flex items-center gap-1.5 bg-red-50 px-2 py-1 rounded-lg w-fit">
                                                        <i class="fas fa-exclamation-circle text-xs"></i> <?php echo $product['stock']; ?> Low Stock
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-gray-900 font-medium"><?php echo $product['stock']; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($product['sale_price']): ?>
                                                    <div class="flex flex-col">
                                                        <span class="font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></span>
                                                        <span class="text-xs text-gray-400 line-through"><?php echo formatPrice($product['price']); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="font-bold text-gray-900"><?php echo formatPrice($product['price']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                                    <?php echo $product['status'] === 'active'
                                                        ? 'bg-green-50 text-green-700 border border-green-200'
                                                        : ($product['status'] === 'inactive' ? 'bg-gray-100 text-gray-600 border border-gray-200' : 'bg-red-50 text-red-700 border border-red-200'); ?>">
                                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?php echo $product['status'] === 'active' ? 'bg-green-500' : ($product['status'] === 'inactive' ? 'bg-gray-500' : 'bg-red-500'); ?>"></span>
                                                    <?php echo ucfirst($product['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>"
                                                       target="_blank"
                                                       class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="View Live">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    <a href="/seller/edit-product.php?id=<?php echo $product['id']; ?>"
                                                       class="p-2 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="/seller/products.php?delete=<?php echo $product['id']; ?>"
                                                       onclick="return confirm('Are you sure you want to delete this product?')"
                                                       class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination (Visual Only for now) -->
                        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50 flex items-center justify-between">
                            <p class="text-sm text-gray-500">Showing <span class="font-bold text-gray-900"><?php echo count($products); ?></span> products</p>
                            <div class="flex gap-2">
                                <button class="px-3 py-1 border border-gray-200 rounded-lg bg-white text-gray-400 cursor-not-allowed" disabled>
                                    <i class="fas fa-chevron-left text-xs"></i>
                                </button>
                                <button class="px-3 py-1 border border-primary bg-primary text-white rounded-lg font-bold text-sm">1</button>
                                <button class="px-3 py-1 border border-gray-200 rounded-lg bg-white text-gray-400 cursor-not-allowed" disabled>
                                    <i class="fas fa-chevron-right text-xs"></i>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
