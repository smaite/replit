<?php
require_once 'config/config.php';
require_once 'config/database.php';



header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


$page_title = 'SASTO Hub - Multi-Vendor Shopping Platform';

// Fetch banners

$stmt = $conn->query("SELECT * FROM banners WHERE active = 1 ORDER BY display_order LIMIT 3");$banners = $stmt->fetchAll();

// Fetch categories
$stmt = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name LIMIT 8");
$categories = $stmt->fetchAll();

// Fetch featured products
$stmt = $conn->query("SELECT p.*, pi.image_path, v.shop_name 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      LEFT JOIN vendors v ON p.vendor_id = v.id
                      WHERE p.featured = true AND p.status = 'active' AND v.status = 'approved'
                      ORDER BY p.created_at DESC LIMIT 8");
$featured_products = $stmt->fetchAll();

// Fetch new arrivals
$stmt = $conn->query("SELECT p.*, pi.image_path, v.shop_name 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      LEFT JOIN vendors v ON p.vendor_id = v.id
                      WHERE p.status = 'active' AND v.status = 'approved'
                      ORDER BY p.created_at DESC LIMIT 8");
$new_arrivals = $stmt->fetchAll();

// Update cart count
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['cart_count'] = $stmt->fetch()['count'] ?? 0;
}

require_once 'includes/header.php';
?>

<!-- Hero Slider -->
<section class="bg-white">
    <div class="container mx-auto px-4 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php foreach ($banners as $index => $banner): ?>
                <?php if ($index === 0): ?>
                    <!-- Main Large Banner -->
                    <div class="md:col-span-3 relative rounded-lg overflow-hidden shadow-lg group">
                        <img src="<?php echo htmlspecialchars($banner['image']); ?>" 
                             alt="<?php echo htmlspecialchars($banner['title']); ?>"
                             class="w-full h-80 object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/70 to-transparent flex items-center">
                            <div class="text-white p-12 max-w-2xl">
                                <span class="text-yellow-400 text-sm font-bold">⭐ FEATURED COLLECTION</span>
                                <h2 class="text-5xl font-bold mt-2 mb-4"><?php echo htmlspecialchars($banner['title']); ?></h2>
                                <p class="text-xl mb-6"><?php echo htmlspecialchars($banner['subtitle'] ?? ''); ?></p>
                                <a href="<?php echo htmlspecialchars($banner['link'] ?? '#'); ?>" 
                                   class="inline-block bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium transition">
                                    Explore Collection <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Side Banners -->
                    <div class="relative rounded-lg overflow-hidden shadow-lg group <?php echo $index === 1 ? 'md:col-span-2' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($banner['image']); ?>" 
                             alt="<?php echo htmlspecialchars($banner['title']); ?>"
                             class="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end">
                            <div class="text-white p-6">
                                <h3 class="text-2xl font-bold"><?php echo htmlspecialchars($banner['title']); ?></h3>
                                <p class="text-sm mt-1"><?php echo htmlspecialchars($banner['subtitle'] ?? ''); ?></p>
                                <a href="<?php echo htmlspecialchars($banner['link'] ?? '#'); ?>" 
                                   class="inline-block mt-3 bg-white text-primary px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-100">
                                    Shop Now
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Categories Section -->
<section class="container mx-auto px-4 py-12">
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-gray-900">Shop by Category</h2>
        <p class="text-gray-600 mt-2">Discover thousands of products across all categories</p>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <?php foreach ($categories as $category): ?>
            <a href="/pages/category.php?slug=<?php echo htmlspecialchars($category['slug']); ?>" 
               class="bg-white rounded-lg shadow hover:shadow-xl transition p-6 text-center group">
                <div class="text-5xl mb-3 group-hover:scale-110 transition-transform">
                    <?php 
                    $icons = ['📱', '👕', '🏠', '⚽', '📚', '💄', '🌱', '🏋️'];
                    echo $icons[array_rand($icons)];
                    ?>
                </div>
                <h3 class="font-bold text-gray-900 group-hover:text-primary"><?php echo htmlspecialchars($category['name']); ?></h3>
                <p class="text-sm text-gray-500 mt-1">0 items</p>
            </a>
        <?php endforeach; ?>
    </div>
    
    <div class="text-center mt-8">
        <a href="/pages/products.php" class="inline-block bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-medium">
            View All Categories
        </a>
    </div>
</section>

<!-- Featured Products -->
<?php if (!empty($featured_products)): ?>
<section class="bg-gray-100 py-12">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-3xl font-bold text-gray-900">Featured Products</h2>
                <p class="text-gray-600 mt-2">Top picks from our vendors</p>
            </div>
            <a href="/pages/products.php?featured=1" class="text-primary hover:text-indigo-700 font-medium">
                View All <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ($featured_products as $product): ?>
                <div class="bg-white rounded-lg shadow hover:shadow-xl transition group">
                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                        <div class="relative overflow-hidden rounded-t-lg">
                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300">
                            <?php if ($product['sale_price']): ?>
                                <span class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded">
                                    SALE
                                </span>
                            <?php endif; ?>
                            <?php if ($product['featured']): ?>
                                <span class="absolute top-2 left-2 bg-yellow-400 text-gray-900 text-xs px-2 py-1 rounded">
                                    ⭐ Featured
                                </span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="p-4">
                        <p class="text-xs text-gray-500 mb-1">
                            <i class="fas fa-store"></i> <?php echo htmlspecialchars($product['shop_name'] ?? 'Unknown'); ?>
                        </p>
                        <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                            <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                        </a>
                        <div class="mt-2">
                            <?php if ($product['sale_price']): ?>
                                <span class="text-lg font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></span>
                                <span class="text-sm text-gray-500 line-through ml-2"><?php echo formatPrice($product['price']); ?></span>
                            <?php else: ?>
                                <span class="text-lg font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                                class="w-full mt-3 bg-primary hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- New Arrivals -->
<?php if (!empty($new_arrivals)): ?>
<section class="container mx-auto px-4 py-12">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">New Arrivals</h2>
            <p class="text-gray-600 mt-2">Fresh products just landed</p>
        </div>
        <a href="/pages/products.php" class="text-primary hover:text-indigo-700 font-medium">
            View All <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
        <?php foreach ($new_arrivals as $product): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-xl transition group">
                <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                    <div class="relative overflow-hidden rounded-t-lg">
                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300">
                        <span class="absolute top-2 left-2 bg-green-500 text-white text-xs px-2 py-1 rounded">
                            NEW
                        </span>
                    </div>
                </a>
                <div class="p-4">
                    <p class="text-xs text-gray-500 mb-1">
                        <i class="fas fa-store"></i> <?php echo htmlspecialchars($product['shop_name'] ?? 'Unknown'); ?>
                    </p>
                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                        <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h3>
                    </a>
                    <div class="mt-2">
                        <span class="text-lg font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                    </div>
                    <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                            class="w-full mt-3 bg-primary hover:bg-indigo-700 text-white py-2 rounded-lg text-sm font-medium transition">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Features Section -->
<section class="bg-primary text-white py-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
                <i class="fas fa-truck text-5xl mb-4"></i>
                <h3 class="text-xl font-bold mb-2">Fast Delivery</h3>
                <p class="text-indigo-200">Quick and reliable delivery to your doorstep</p>
            </div>
            <div>
                <i class="fas fa-shield-alt text-5xl mb-4"></i>
                <h3 class="text-xl font-bold mb-2">Secure Payment</h3>
                <p class="text-indigo-200">Your payment information is safe and secure</p>
            </div>
            <div>
                <i class="fas fa-headset text-5xl mb-4"></i>
                <h3 class="text-xl font-bold mb-2">24/7 Support</h3>
                <p class="text-indigo-200">We're here to help you anytime, anywhere</p>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
