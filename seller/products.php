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
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$_GET['delete'], $vendor['id']]);
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

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <a href="/seller/" class="text-primary hover:text-indigo-700 mb-2 inline-block">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="text-3xl font-bold text-gray-900">My Products</h1>
        </div>
        <a href="/seller/add-product.php" class="bg-primary hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-medium">
            <i class="fas fa-plus-circle"></i> Add Product
        </a>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($products)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-box-open text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Products Yet</h3>
                <p class="text-gray-600 mb-6">Start adding products to your shop</p>
                <a href="/seller/add-product.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                    <i class="fas fa-plus-circle"></i> Add Your First Product
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Product</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Category</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Price</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Stock</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/60'); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             class="w-16 h-16 object-cover rounded">
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                            <?php if ($product['sku']): ?>
                                                <p class="text-sm text-gray-500">SKU: <?php echo htmlspecialchars($product['sku']); ?></p>
                                            <?php endif; ?>
                                            <?php if ($product['featured']): ?>
                                                <span class="inline-block mt-1 bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">⭐ Featured</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-gray-700">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <?php if ($product['sale_price']): ?>
                                            <p class="font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></p>
                                            <p class="text-sm text-gray-500 line-through"><?php echo formatPrice($product['price']); ?></p>
                                        <?php else: ?>
                                            <p class="font-bold text-gray-900"><?php echo formatPrice($product['price']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo $product['stock'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs rounded-full <?php 
                                        echo $product['status'] === 'active' ? 'bg-green-100 text-green-700' : 
                                             ($product['status'] === 'inactive' ? 'bg-gray-100 text-gray-700' : 'bg-red-100 text-red-700'); 
                                    ?>">
                                        <?php echo ucfirst($product['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>" 
                                           class="text-blue-600 hover:text-blue-700" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/seller/edit-product.php?id=<?php echo $product['id']; ?>" 
                                           class="text-green-600 hover:text-green-700" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="/seller/products.php?delete=<?php echo $product['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this product?')"
                                           class="text-red-600 hover:text-red-700" title="Delete">
                                            <i class="fas fa-trash"></i>
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

<?php include '../includes/footer.php'; ?>
