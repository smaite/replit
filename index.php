<?php
require_once 'config/config.php';
require_once 'config/database.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$page_title = getSetting('website_name', 'SASTO Hub') . ' - Multi-Vendor Shopping Platform';

// Fetch banners
$stmt = $conn->query("SELECT * FROM banners WHERE active = 1 ORDER BY display_order LIMIT 5");
$banners = $stmt->fetchAll();

// Fetch categories
$stmt = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name LIMIT 12");
$categories = $stmt->fetchAll();

// Fetch featured products
$stmt = $conn->query("SELECT p.*, pi.image_path, v.shop_name 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      LEFT JOIN vendors v ON p.vendor_id = v.id
                      WHERE p.featured = true AND p.status = 'active' AND v.status = 'approved'
                      ORDER BY RAND() LIMIT 12");
$featured_products = $stmt->fetchAll();

// Fetch flash sale products (with sale_price)
$stmt = $conn->query("SELECT p.*, pi.image_path, v.shop_name 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      LEFT JOIN vendors v ON p.vendor_id = v.id
                      WHERE p.sale_price IS NOT NULL AND p.status = 'active' AND v.status = 'approved'
                      ORDER BY RAND() LIMIT 12");
$flash_sale_products = $stmt->fetchAll();

// Fetch new arrivals
$stmt = $conn->query("SELECT p.*, pi.image_path, v.shop_name 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      LEFT JOIN vendors v ON p.vendor_id = v.id
                      WHERE p.status = 'active' AND v.status = 'approved'
                      ORDER BY p.created_at DESC LIMIT 12");
$new_arrivals = $stmt->fetchAll();

// Update cart count
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['cart_count'] = $stmt->fetch()['count'] ?? 0;
}

require_once 'includes/header.php';
?>

<!-- HERO BANNER SECTION - BIG AND IMPRESSIVE -->
<section class="bg-gradient-to-b from-primary/5 to-white">
    <div class="container mx-auto px-4 py-2">
        <!-- Main Hero Banner -->
        <div class="relative rounded-2xl overflow-hidden shadow-2xl mb-6">
            <?php if (!empty($banners[0])): ?>
                <img src="<?php echo htmlspecialchars($banners[0]['image']); ?>" 
                     alt="<?php echo htmlspecialchars($banners[0]['title']); ?>"
                     class="w-full h-96 md:h-[500px] lg:h-[600px] object-cover">
                <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/40 to-transparent flex items-center">
                    <div class="text-white p-8 md:p-16 max-w-3xl">
                        <span class="text-yellow-400 text-sm md:text-lg font-bold uppercase tracking-wide">
                            🎉 <?php echo getSetting('website_tagline', 'Welcome to SASTO Hub'); ?>
                        </span>
                        <h1 class="text-4xl md:text-6xl lg:text-7xl font-black mt-3 mb-4 leading-tight">
                            <?php echo htmlspecialchars($banners[0]['title'] ?? 'Amazing Deals Await'); ?>
                        </h1>
                        <p class="text-lg md:text-2xl text-gray-200 mb-8 max-w-2xl">
                            <?php echo htmlspecialchars($banners[0]['subtitle'] ?? 'Explore thousands of products from trusted vendors'); ?>
                        </p>
                        <div class="flex gap-4">
                            <a href="/pages/products.php" 
                               class="inline-block bg-primary hover:bg-indigo-700 text-white px-8 md:px-10 py-3 md:py-4 rounded-xl font-bold text-lg transition transform hover:scale-105">
                                Shop Now <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                            <a href="/pages/products.php?featured=1" 
                               class="inline-block bg-white/90 hover:bg-white text-primary px-8 md:px-10 py-3 md:py-4 rounded-xl font-bold text-lg transition">
                                Featured Deals
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="w-full h-96 md:h-[500px] lg:h-[600px] bg-gradient-to-r from-primary via-purple-600 to-pink-600 flex items-center justify-center">
                    <div class="text-white text-center">
                        <h1 class="text-5xl md:text-7xl font-black mb-4">Welcome to <?php echo htmlspecialchars(getSetting('website_name', 'SASTO Hub')); ?></h1>
                        <p class="text-2xl md:text-3xl mb-8">Your Ultimate Multi-Vendor Marketplace</p>
                        <a href="/pages/products.php" class="inline-block bg-white text-primary px-10 py-4 rounded-xl font-bold text-xl hover:bg-gray-100">
                            Explore Products
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Secondary Banners Row -->
        <?php if (count($banners) > 1): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <?php for ($i = 1; $i < min(4, count($banners)); $i++): ?>
                <div class="relative rounded-xl overflow-hidden shadow-lg group cursor-pointer">
                    <img src="<?php echo htmlspecialchars($banners[$i]['image']); ?>" 
                         alt="<?php echo htmlspecialchars($banners[$i]['title']); ?>"
                         class="w-full h-48 md:h-56 object-cover group-hover:scale-110 transition-transform duration-300">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex flex-col justify-end p-4">
                        <h3 class="text-white font-bold text-lg"><?php echo htmlspecialchars($banners[$i]['title']); ?></h3>
                        <a href="<?php echo htmlspecialchars($banners[$i]['link'] ?? '#'); ?>" 
                           class="text-primary font-semibold mt-2 inline-flex items-center w-fit">
                            Explore <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- QUICK CATEGORY SECTION -->
<section class="bg-white py-8 md:py-12 border-b">
    <div class="container mx-auto px-4">
        <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6 flex items-center">
            <i class="fas fa-th text-primary mr-3"></i> Shop by Category
        </h2>
        
        <div class="grid grid-cols-3 md:grid-cols-6 lg:grid-cols-12 gap-2 md:gap-4">
            <?php 
            $category_icons = [
                'Electronics' => '📱',
                'Fashion' => '👕',
                'Home' => '🏠',
                'Sports' => '⚽',
                'Books' => '📚',
                'Beauty' => '💄',
                'Garden' => '🌱',
                'Fitness' => '🏋️'
            ];
            
            foreach ($categories as $index => $category):
                $icon = $category_icons[$category['name']] ?? ($index % 2 === 0 ? '🛍️' : '✨');
            ?>
                <a href="/pages/products.php?slug=<?php echo htmlspecialchars($category['slug']); ?>" 
                   class="bg-gradient-to-br from-gray-50 to-gray-100 hover:from-primary/10 hover:to-primary/5 rounded-lg p-4 text-center transition transform hover:scale-105 shadow-sm hover:shadow-md">
                    <div class="text-3xl md:text-4xl mb-2"><?php echo $icon; ?></div>
                    <p class="font-semibold text-gray-800 text-sm md:text-base"><?php echo htmlspecialchars($category['name']); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FLASH SALE SECTION -->
<?php if (!empty($flash_sale_products)): ?>
<section class="container mx-auto px-4 py-8 md:py-12">
    <div class="bg-gradient-to-r from-red-500 to-pink-600 rounded-2xl p-6 md:p-10 text-white mb-8 shadow-lg">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl md:text-4xl font-black">🔥 FLASH SALE</h2>
                <p class="text-lg text-red-100 mt-2">Limited time offers on top products</p>
            </div>
            <div class="text-right">
                <p class="text-5xl font-black">UP TO</p>
                <p class="text-6xl md:text-7xl font-black">70%</p>
                <p class="text-lg text-red-100">OFF</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 md:gap-4">
        <?php foreach ($flash_sale_products as $product): 
            $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
        ?>
            <div class="bg-white rounded-lg shadow hover:shadow-xl transition group h-full flex flex-col">
                <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>" class="flex-1 flex flex-col">
                    <div class="relative overflow-hidden rounded-t-lg flex-1">
                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                        <span class="absolute top-2 right-2 bg-red-600 text-white text-xs md:text-sm font-bold px-2 py-1 rounded">
                            -<?php echo $discount; ?>%
                        </span>
                    </div>
                    <div class="p-3 flex-1 flex flex-col">
                        <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2 text-sm">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h3>
                        <div class="mt-2 mt-auto">
                            <span class="text-base md:text-lg font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></span>
                            <span class="text-xs text-gray-500 line-through ml-1"><?php echo formatPrice($product['price']); ?></span>
                        </div>
                    </div>
                </a>
                <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                        class="w-full bg-primary hover:bg-indigo-700 text-white py-2 rounded-b-lg text-xs md:text-sm font-medium transition">
                    <i class="fas fa-cart-plus"></i> Add
                </button>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- FEATURED PRODUCTS SECTION -->
<?php if (!empty($featured_products)): ?>
<section class="bg-gradient-to-b from-white to-gray-50 py-8 md:py-12">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 flex items-center">
                    <i class="fas fa-star text-yellow-400 mr-3"></i> Featured Products
                </h2>
                <p class="text-gray-600 mt-2">Handpicked bestsellers from our top vendors</p>
            </div>
            <a href="/pages/products.php?featured=1" class="text-primary hover:text-indigo-700 font-bold hidden md:inline-flex items-center">
                View All <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-4">
            <?php foreach ($featured_products as $product): ?>
                <div class="bg-white rounded-lg shadow hover:shadow-xl transition group">
                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                        <div class="relative overflow-hidden rounded-t-lg">
                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 class="w-full h-40 md:h-48 object-cover group-hover:scale-110 transition-transform duration-300">
                            <span class="absolute top-2 left-2 bg-yellow-400 text-gray-900 font-bold px-2 py-1 rounded text-xs">
                                ⭐ FEATURED
                            </span>
                        </div>
                    </a>
                    <div class="p-3">
                        <p class="text-xs text-gray-500 mb-1"><i class="fas fa-store"></i> <?php echo htmlspecialchars(substr($product['shop_name'] ?? 'Shop', 0, 12)); ?></p>
                        <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                            <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2 text-sm">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                        </a>
                        <div class="mt-2">
                            <?php if ($product['sale_price']): ?>
                                <span class="text-sm md:text-base font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></span>
                                <span class="text-xs text-gray-500 line-through ml-1"><?php echo formatPrice($product['price']); ?></span>
                            <?php else: ?>
                                <span class="text-sm md:text-base font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                            <?php endif; ?>
                        </div>
                        <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                                class="w-full mt-2 bg-primary hover:bg-indigo-700 text-white py-1.5 rounded text-xs font-medium transition">
                            <i class="fas fa-cart-plus"></i> Add
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- NEW ARRIVALS SECTION -->
<?php if (!empty($new_arrivals)): ?>
<section class="container mx-auto px-4 py-8 md:py-12">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 flex items-center">
                <i class="fas fa-sparkles text-green-500 mr-3"></i> New Arrivals
            </h2>
            <p class="text-gray-600 mt-2">Latest products just added to our marketplace</p>
        </div>
        <a href="/pages/products.php" class="text-primary hover:text-indigo-700 font-bold hidden md:inline-flex items-center">
            View All <i class="fas fa-arrow-right ml-2"></i>
        </a>
    </div>
    
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-4">
        <?php foreach ($new_arrivals as $product): ?>
            <div class="bg-white rounded-lg shadow hover:shadow-xl transition group">
                <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                    <div class="relative overflow-hidden rounded-t-lg">
                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             class="w-full h-40 md:h-48 object-cover group-hover:scale-110 transition-transform duration-300">
                        <span class="absolute top-2 left-2 bg-green-500 text-white font-bold px-2 py-1 rounded text-xs">
                            NEW
                        </span>
                    </div>
                </a>
                <div class="p-3">
                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-store"></i> <?php echo htmlspecialchars(substr($product['shop_name'] ?? 'Shop', 0, 12)); ?></p>
                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                        <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2 text-sm">
                            <?php echo htmlspecialchars($product['name']); ?>
                        </h3>
                    </a>
                    <div class="mt-2">
                        <span class="text-sm md:text-base font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                    </div>
                    <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                            class="w-full mt-2 bg-primary hover:bg-indigo-700 text-white py-1.5 rounded text-xs font-medium transition">
                        <i class="fas fa-cart-plus"></i> Add
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
