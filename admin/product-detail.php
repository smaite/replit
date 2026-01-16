<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$product_id = $_GET['id'] ?? null;
if (!$product_id) {
    header('Location: /admin/products.php');
    exit;
}

// Fetch product details
$stmt = $conn->prepare("
    SELECT p.*, c.name as category_name, v.shop_name, u.full_name as vendor_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN vendors v ON p.vendor_id = v.id
    LEFT JOIN users u ON p.vendor_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: /admin/products.php');
    exit;
}

// Fetch product images
$stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC");
$stmt->execute([$product_id]);
$images = $stmt->fetchAll();

// Fetch product reviews/orders count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?");
$stmt->execute([$product_id]);
$orders_count = $stmt->fetch()['count'];

$page_title = 'Product Details - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <a href="/admin/products.php" class="text-primary hover:text-indigo-700 mb-6 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Products
    </a>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Product Images -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Product Images</h3>
                <?php if (empty($images)): ?>
                    <div class="bg-gray-100 rounded-lg p-8 text-center">
                        <i class="fas fa-image text-6xl text-gray-300 mb-4"></i>
                        <p class="text-gray-600">No images uploaded</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach ($images as $image): ?>
                            <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden">
                                <img src="<?php echo htmlspecialchars($image['image_path'] ?? ''); ?>" alt="Product Image" class="w-full h-full object-cover">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Product Details -->
            <div class="bg-white rounded-lg shadow-sm p-6 mt-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Product Information</h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-600">Name</label>
                        <p class="font-medium text-gray-900 text-lg"><?php echo htmlspecialchars($product['name']); ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Description</label>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm text-gray-600">Price</label>
                            <p class="text-2xl font-bold text-primary">₹<?php echo number_format($product['price'], 2); ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600">Stock</label>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $product['stock'] ?? 0; ?> units</p>
                        </div>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">SKU</label>
                        <p class="font-mono text-gray-900"><?php echo htmlspecialchars($product['sku'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Vendor Info -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Vendor Information</h3>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-gray-600">Shop Name</label>
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($product['shop_name'] ?? 'Deleted'); ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-600">Vendor Name</label>
                        <p class="text-gray-700"><?php echo htmlspecialchars($product['vendor_name'] ?? 'N/A'); ?></p>
                    </div>
                    <?php if ($product['vendor_id']): ?>
                        <a href="/admin/user-detail.php?id=<?php echo $product['vendor_id']; ?>" class="block mt-4 text-primary hover:text-indigo-700 font-medium">
                            <i class="fas fa-external-link-alt"></i> View Vendor
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Category Info -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Category</h3>
                <p class="text-gray-700"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
            </div>

            <!-- Statistics -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Statistics</h3>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Orders</span>
                        <span class="text-2xl font-bold text-primary"><?php echo $orders_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Added</span>
                        <span class="text-sm text-gray-600"><?php echo date('M d, Y', strtotime($product['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Actions</h3>
                <div class="space-y-2">
                    <a href="/seller/edit-product.php?id=<?php echo $product['id']; ?>" class="block w-full px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition font-medium text-center">
                        <i class="fas fa-edit"></i> Edit Product
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
