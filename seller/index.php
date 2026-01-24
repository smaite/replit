<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

// Get vendor info
$stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('/auth/become-vendor.php');
}

// Show status message if not verified or rejected
if ($vendor['status'] === 'rejected') {
    redirect('/auth/become-vendor.php?status=rejected&reason=' . urlencode($vendor['rejection_reason'] ?? 'Not specified'));
} elseif ($vendor['status'] !== 'approved') {
    redirect('/auth/become-vendor.php?status=pending');
}

// Get vendor statistics
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE vendor_id = ?");
$stmt->execute([$vendor['id']]);
$product_count = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items oi
                        JOIN orders o ON oi.order_id = o.id
                        WHERE oi.vendor_id = ? AND o.status != 'cancelled'");
$stmt->execute([$vendor['id']]);
$order_count = $stmt->fetch()['count'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(oi.subtotal), 0) as total FROM order_items oi
                        JOIN orders o ON oi.order_id = o.id
                        WHERE oi.vendor_id = ? AND o.payment_status = 'paid'");
$stmt->execute([$vendor['id']]);
$total_sales = $stmt->fetch()['total'];

// Get recent products
$stmt = $conn->prepare("SELECT p.*, pi.image_path FROM products p
                        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                        WHERE p.vendor_id = ? ORDER BY p.created_at DESC LIMIT 5");
$stmt->execute([$vendor['id']]);
$recent_products = $stmt->fetchAll();

$page_title = 'Seller Dashboard - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Seller Dashboard</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <!-- Shop Info -->
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-2xl font-bold border border-indigo-200">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Shop Dashboard</p>
                                <h3 class="font-bold text-gray-900 truncate" title="<?php echo htmlspecialchars($vendor['shop_name']); ?>">
                                    <?php echo htmlspecialchars($vendor['shop_name']); ?>
                                </h3>
                            </div>
                        </div>
                    </div>

                    <nav class="p-4 space-y-1">
                        <a href="/seller/" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-bold rounded-lg transition-colors">
                            <i class="fas fa-chart-line w-5"></i> Dashboard
                        </a>
                        <a href="/seller/products.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-box w-5 group-hover:text-primary transition-colors"></i> My Products
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

                        <div class="pt-4 mt-4 border-t border-gray-100">
                            <a href="/pages/dashboard.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                                <i class="fas fa-user w-5 group-hover:text-primary transition-colors"></i> Buyer Dashboard
                            </a>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-8">
                <!-- Welcome Banner -->
                <div class="bg-gradient-to-r from-primary to-indigo-700 rounded-2xl shadow-lg text-white p-8 relative overflow-hidden">
                    <div class="relative z-10">
                        <h1 class="text-3xl font-bold mb-2">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
                        <p class="text-indigo-100 text-lg">Here's what's happening with your store today.</p>
                    </div>
                    <!-- Decorative circles -->
                    <div class="absolute top-0 right-0 -mr-16 -mt-16 w-64 h-64 rounded-full bg-white opacity-10"></div>
                    <div class="absolute bottom-0 right-0 -mr-8 -mb-8 w-32 h-32 rounded-full bg-white opacity-10"></div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Sales -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="bg-green-100 text-green-600 p-3 rounded-lg group-hover:bg-green-600 group-hover:text-white transition-colors">
                                <i class="fas fa-dollar-sign text-xl"></i>
                            </div>
                            <span class="text-xs font-bold text-green-600 bg-green-50 px-2 py-1 rounded-full">+0% this week</span>
                        </div>
                        <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide">Total Revenue</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo formatPrice($total_sales); ?></p>
                    </div>

                    <!-- Total Orders -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="bg-blue-100 text-blue-600 p-3 rounded-lg group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                <i class="fas fa-shopping-bag text-xl"></i>
                            </div>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-1 rounded-full">Lifetime</span>
                        </div>
                        <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide">Total Orders</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($order_count); ?></p>
                    </div>

                    <!-- Total Products -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow group">
                        <div class="flex items-center justify-between mb-4">
                            <div class="bg-purple-100 text-purple-600 p-3 rounded-lg group-hover:bg-purple-600 group-hover:text-white transition-colors">
                                <i class="fas fa-box text-xl"></i>
                            </div>
                            <a href="/seller/add-product.php" class="text-xs font-bold text-purple-600 hover:text-purple-800">Add New</a>
                        </div>
                        <h3 class="text-gray-500 text-sm font-medium uppercase tracking-wide">Active Products</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo number_format($product_count); ?></p>
                    </div>
                </div>

                <!-- Recent Products Section -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-900 text-lg">Recent Products</h3>
                        <a href="/seller/products.php" class="text-sm font-bold text-primary hover:text-indigo-700 flex items-center gap-1 transition-colors">
                            View All Products <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>

                    <?php if (empty($recent_products)): ?>
                        <div class="text-center py-16">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-box-open text-3xl text-gray-400"></i>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-2">No products added yet</h3>
                            <p class="text-gray-500 mb-6 max-w-sm mx-auto">Start building your inventory to reach customers.</p>
                            <a href="/seller/add-product.php" class="inline-flex items-center px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition transform hover:-translate-y-0.5 shadow-lg shadow-indigo-200">
                                <i class="fas fa-plus mr-2"></i> Add First Product
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                    <tr>
                                        <th class="px-6 py-4 font-semibold rounded-tl-lg">Product Name</th>
                                        <th class="px-6 py-4 font-semibold">Price</th>
                                        <th class="px-6 py-4 font-semibold">Stock</th>
                                        <th class="px-6 py-4 font-semibold">Status</th>
                                        <th class="px-6 py-4 font-semibold text-right rounded-tr-lg">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($recent_products as $product): ?>
                                    <tr class="hover:bg-gray-50 transition-colors group">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 rounded-lg bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                                    <img src="<?php echo htmlspecialchars($product['image_path'] ?? '/assets/images/placeholder.jpg'); ?>"
                                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                         class="w-full h-full object-cover">
                                                </div>
                                                <div>
                                                    <h4 class="font-bold text-gray-900 text-sm group-hover:text-primary transition-colors line-clamp-1">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </h4>
                                                    <span class="text-xs text-gray-500">ID: #<?php echo $product['id']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            <?php echo formatPrice($product['price']); ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($product['stock'] <= 5): ?>
                                                <span class="text-red-600 font-bold text-sm flex items-center gap-1">
                                                    <i class="fas fa-exclamation-circle text-xs"></i> <?php echo $product['stock']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-900 font-medium"><?php echo $product['stock']; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold
                                                <?php echo $product['status'] === 'active'
                                                    ? 'bg-green-50 text-green-700 border border-green-200'
                                                    : 'bg-gray-100 text-gray-600 border border-gray-200'; ?>">
                                                <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?php echo $product['status'] === 'active' ? 'bg-green-500' : 'bg-gray-500'; ?>"></span>
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <a href="/seller/edit-product.php?id=<?php echo $product['id']; ?>"
                                                   class="p-2 text-gray-500 hover:text-primary hover:bg-primary/5 rounded-lg transition-colors"
                                                   title="Edit Product">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>"
                                                   target="_blank"
                                                   class="p-2 text-gray-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors"
                                                   title="View Live">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
