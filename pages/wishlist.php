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

$page_title = 'My Wishlist - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/pages/dashboard.php" class="hover:text-primary">My Account</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Wishlist</span>
        </nav>

        <div class="flex items-center justify-between mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Wishlist</h1>
            <span class="text-gray-500 text-sm"><?php echo count($wishlist_items); ?> Items</span>
        </div>

        <?php if (empty($wishlist_items)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-16 text-center max-w-2xl mx-auto">
                <div class="w-24 h-24 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="far fa-heart text-4xl text-red-400"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Your Wishlist is Empty</h2>
                <p class="text-gray-500 mb-8">Tap the heart icon on any product to save it for later.</p>
                <a href="/pages/products.php" class="inline-flex items-center justify-center px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition transform hover:-translate-y-0.5">
                    Start Exploring
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                <?php foreach ($wishlist_items as $item): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-all group relative">
                        <!-- Remove Button (Top Right) -->
                        <form method="POST" action="" class="absolute top-3 right-3 z-10">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="wishlist_id" value="<?php echo $item['wishlist_id']; ?>">
                            <button type="submit" class="w-8 h-8 bg-white/90 backdrop-blur text-red-500 rounded-full shadow-sm flex items-center justify-center hover:bg-red-500 hover:text-white transition" title="Remove from Wishlist">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>

                        <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>" class="block relative pt-[100%] bg-gray-50">
                            <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/300'); ?>"
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            <?php if ($item['sale_price']): ?>
                                <div class="absolute bottom-2 left-2 bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                    -<?php echo round((($item['price'] - $item['sale_price']) / $item['price']) * 100); ?>%
                                </div>
                            <?php endif; ?>
                        </a>

                        <div class="p-4">
                            <div class="mb-1 text-xs text-gray-500"><?php echo htmlspecialchars($item['shop_name']); ?></div>
                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>">
                                <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2 mb-2 text-sm leading-snug min-h-[2.5em]">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </h3>
                            </a>

                            <div class="flex items-baseline gap-2 mb-4">
                                <span class="text-lg font-bold text-gray-900"><?php echo formatPrice($item['sale_price'] ?? $item['price']); ?></span>
                                <?php if ($item['sale_price']): ?>
                                    <span class="text-xs text-gray-400 line-through"><?php echo formatPrice($item['price']); ?></span>
                                <?php endif; ?>
                            </div>

                            <button onclick="addToCart(<?php echo $item['id']; ?>)"
                                    class="w-full py-2.5 rounded-lg border border-primary text-primary font-bold text-sm hover:bg-primary hover:text-white transition flex items-center justify-center gap-2">
                                <i class="fas fa-cart-plus"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
