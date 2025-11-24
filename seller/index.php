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

<div class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-store text-primary"></i> Seller Dashboard
            </h1>
            <p class="text-gray-600">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
            <p class="text-sm text-gray-500 mt-1">Shop: <strong><?php echo htmlspecialchars($vendor['shop_name']); ?></strong></p>
        </div>
        
        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Products</p>
                        <p class="text-3xl font-bold text-primary mt-1"><?php echo $product_count; ?></p>
                    </div>
                    <div class="bg-primary/10 p-4 rounded-full">
                        <i class="fas fa-box text-primary text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Orders</p>
                        <p class="text-3xl font-bold text-green-600 mt-1"><?php echo $order_count; ?></p>
                    </div>
                    <div class="bg-green-100 p-4 rounded-full">
                        <i class="fas fa-shopping-cart text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Sales</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-1"><?php echo formatPrice($total_sales); ?></p>
                    </div>
                    <div class="bg-yellow-100 p-4 rounded-full">
                        <i class="fas fa-dollar-sign text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="/seller/add-product.php" class="bg-primary hover:bg-indigo-700 text-white text-center py-4 rounded-lg font-medium transition">
                    <i class="fas fa-plus-circle text-2xl mb-2 block"></i>
                    Add Product
                </a>
                <a href="/seller/products.php" class="bg-green-600 hover:bg-green-700 text-white text-center py-4 rounded-lg font-medium transition">
                    <i class="fas fa-box text-2xl mb-2 block"></i>
                    My Products
                </a>
                <a href="/seller/orders.php" class="bg-yellow-600 hover:bg-yellow-700 text-white text-center py-4 rounded-lg font-medium transition">
                    <i class="fas fa-shopping-cart text-2xl mb-2 block"></i>
                    Orders
                </a>
                <a href="/seller/settings.php" class="bg-gray-600 hover:bg-gray-700 text-white text-center py-4 rounded-lg font-medium transition">
                    <i class="fas fa-cog text-2xl mb-2 block"></i>
                    Settings
                </a>
            </div>
        </div>
        
        <!-- Recent Products -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-900">Recent Products</h2>
                <a href="/seller/products.php" class="text-primary hover:text-indigo-700 font-medium">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (empty($recent_products)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                    <p class="text-gray-600">No products yet</p>
                    <a href="/seller/add-product.php" class="inline-block mt-4 bg-primary text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                        <i class="fas fa-plus"></i> Add Your First Product
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Product</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Price</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Stock</th>
                                <th class="px-4 py-3 text-left text-sm font-medium text-gray-600">Status</th>
                                <th class="px-4 py-3 text-right text-sm font-medium text-gray-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php foreach ($recent_products as $product): ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/50'); ?>" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 class="w-12 h-12 object-cover rounded">
                                            <span class="font-medium"><?php echo htmlspecialchars($product['name']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><?php echo formatPrice($product['price']); ?></td>
                                    <td class="px-4 py-3"><?php echo $product['stock']; ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $product['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                            <?php echo ucfirst($product['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="/seller/edit-product.php?id=<?php echo $product['id']; ?>" class="text-blue-600 hover:text-blue-700 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>" class="text-gray-600 hover:text-gray-700">
                                            <i class="fas fa-eye"></i>
                                        </a>
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

<?php include '../includes/footer.php'; ?>
