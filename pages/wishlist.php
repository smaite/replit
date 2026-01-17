<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Fetch wishlist items
$stmt = $conn->prepare("
    SELECT w.id as wishlist_id, p.*, pi.image_path, v.shop_name
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
    LEFT JOIN vendors v ON p.vendor_id = v.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$wishlist_items = $stmt->fetchAll();

// Handle remove from wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $wishlist_id = (int)$_POST['wishlist_id'];
        $stmt = $conn->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$wishlist_id, $_SESSION['user_id']]);
        
        // Refresh the page
        redirect('/pages/wishlist.php');
    }
}

$page_title = 'Wishlist - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/pages/dashboard.php" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">
            <i class="fas fa-heart text-red-500"></i> My Wishlist
        </h1>
        <p class="text-gray-600 mt-2">Save your favorite items for later</p>
    </div>

    <?php if (empty($wishlist_items)): ?>
        <!-- Empty State -->
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <i class="fas fa-heart text-6xl text-gray-300 mb-4"></i>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Your Wishlist is Empty</h2>
            <p class="text-gray-600 mb-6">Add products to your wishlist by clicking the heart icon on any product</p>
            <a href="/pages/products.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                <i class="fas fa-shopping-bag"></i> Browse Products
            </a>
        </div>
    <?php else: ?>
        <!-- Wishlist Info -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-800">
                <i class="fas fa-info-circle"></i> You have <strong><?php echo count($wishlist_items); ?></strong> item<?php echo count($wishlist_items) !== 1 ? 's' : ''; ?> in your wishlist
            </p>
        </div>

        <!-- Wishlist Items Grid -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <?php foreach ($wishlist_items as $item): ?>
                <div class="bg-white rounded-lg shadow hover:shadow-xl transition group">
                    <div class="relative overflow-hidden rounded-t-lg">
                        <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300">
                        
                        <?php if ($item['sale_price']): ?>
                            <span class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded font-bold">
                                SALE
                            </span>
                        <?php endif; ?>

                        <!-- Remove from Wishlist Button -->
                        <form method="POST" action="" class="absolute top-2 left-2">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="wishlist_id" value="<?php echo $item['wishlist_id']; ?>">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-full transition">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                    </div>

                    <div class="p-4">

                        <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>">
                            <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </h3>
                        </a>

                        <div class="mt-3 mb-3">
                            <div class="flex items-center gap-2">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <i class="fas fa-star text-yellow-400"></i>
                                <?php endfor; ?>
                                <span class="text-xs text-gray-600">(0)</span>
                            </div>
                        </div>

                        <div class="mt-2 mb-4">
                            <?php if ($item['sale_price']): ?>
                                <span class="text-lg font-bold text-red-600"><?php echo formatPrice($item['sale_price']); ?></span>
                                <span class="text-sm text-gray-500 line-through ml-2"><?php echo formatPrice($item['price']); ?></span>
                            <?php else: ?>
                                <span class="text-lg font-bold text-primary"><?php echo formatPrice($item['price']); ?></span>
                            <?php endif; ?>
                        </div>

                        <button onclick="addToCart(<?php echo $item['id']; ?>)" 
                                class="w-full bg-primary hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Continue Shopping Button -->
        <div class="text-center mb-8">
            <a href="/pages/products.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 px-8 py-3 rounded-lg font-medium">
                <i class="fas fa-arrow-left"></i> Continue Shopping
            </a>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
