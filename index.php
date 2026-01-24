<?php
require_once 'config/config.php';
require_once 'config/database.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$page_title = getSetting('website_name', 'SASTO Hub') . ' - Online Shopping Mall';

// 1. Fetch Banners
$stmt = $conn->query("SELECT * FROM banners WHERE active = 1 ORDER BY display_order LIMIT 5");
$banners = $stmt->fetchAll();

// 2. Fetch Categories (Top Level)
$stmt = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name LIMIT 10");
$categories = $stmt->fetchAll();

// 3. Fetch Flash Sale Products
$stmt = $conn->query("SELECT p.*, pi.image_path 
                      FROM products p 
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      WHERE p.sale_price IS NOT NULL AND p.status = 'active'
                      ORDER BY RAND() LIMIT 6");
$flash_sale_products = $stmt->fetchAll();

// 4. Fetch Official Stores (Brands/Vendors)
$stmt = $conn->query("SELECT DISTINCT v.id, v.shop_name, v.shop_logo 
                      FROM vendors v 
                      JOIN products p ON v.id = p.vendor_id 
                      WHERE v.status = 'approved' AND p.status = 'active' 
                      LIMIT 12");
$official_stores = $stmt->fetchAll();

// 5. Fetch "Just For You" (Random Mix)
$stmt = $conn->query("SELECT p.*, pi.image_path,
                      (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') as avg_rating,
                      (SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled' AND o.payment_status = 'paid') as total_sold
                      FROM products p
                      LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                      WHERE p.status = 'active'
                      ORDER BY RAND() LIMIT 24");
$just_for_you = $stmt->fetchAll();

// Cart Count
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $_SESSION['cart_count'] = $stmt->fetch()['count'] ?? 0;
}

require_once 'includes/header.php';
?>

<div class="bg-gray-100 min-h-screen pb-12">
    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- 1. Vertical Category Menu (Left - 2 Cols) -->
            <div class="hidden lg:block lg:col-span-3 xl:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden h-full">
                    <div class="p-3 bg-gray-50 border-b border-gray-100 font-bold text-gray-700">
                        <i class="fas fa-bars mr-2"></i> Categories
                    </div>
                    <ul class="divide-y divide-gray-50">
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="/pages/products.php?slug=<?php echo $cat['slug']; ?>" class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50 hover:text-primary transition text-sm text-gray-700">
                                <span class="flex items-center gap-2">
                                    <?php if (!empty($cat['image'])): ?>
                                        <img src="uploads\categories\<?php echo htmlspecialchars($cat['image']); ?>" class="w-5 h-5 object-cover rounded-full">
                                    <?php else: ?>
                                        <i class="fas fa-folder text-gray-300"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </span>
                                <i class="fas fa-chevron-right text-xs text-gray-300"></i>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <li>
                            <a href="/pages/products.php" class="flex items-center justify-between px-4 py-2.5 hover:bg-blue-50 hover:text-primary transition text-sm text-primary font-medium">
                                <span>View All Categories</span>
                                <i class="fas fa-plus-circle"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 2. Main Slider (Middle - 7 Cols) -->
            <div class="lg:col-span-6 xl:col-span-7">
                <div class="relative rounded-lg overflow-hidden shadow-sm h-[300px] md:h-[360px] lg:h-[400px] group">
                    <?php if (!empty($banners[0])): ?>
                        <img src="<?php echo htmlspecialchars($banners[0]['image']); ?>" 
                             alt="Banner" 
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/60 to-transparent flex items-center p-8 md:p-12">
                            <div class="text-white max-w-lg">
                                <span class="bg-yellow-400 text-black text-xs font-bold px-2 py-1 rounded mb-4 inline-block">Latest Trending</span>
                                <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold mb-4 leading-tight"><?php echo htmlspecialchars($banners[0]['title'] ?? 'Electronic Sale'); ?></h2>
                                <p class="text-lg mb-6 opacity-90"><?php echo htmlspecialchars($banners[0]['subtitle'] ?? 'Get up to 50% off'); ?></p>
                                <a href="<?php echo htmlspecialchars($banners[0]['link'] ?? '/pages/products.php'); ?>" 
                                   class="bg-white text-gray-900 hover:bg-gray-100 px-6 py-2.5 rounded-lg font-bold transition shadow-lg inline-block">
                                    Shop Now
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-r from-blue-600 to-purple-600 flex items-center justify-center text-white">
                            <h2 class="text-4xl font-bold">Welcome to SASTO Hub</h2>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. Right Column (User/Promo - 3 Cols) -->
            <div class="hidden lg:block lg:col-span-3">
                <!-- User Card -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-4">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-xl text-gray-400">
                            <?php if (isLoggedIn()): ?>
                                <span class="font-bold text-primary"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?></span>
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Hi, <?php echo isLoggedIn() ? htmlspecialchars($_SESSION['user_name']) : 'user'; ?></p>
                            <p class="font-bold text-gray-900">let's get shopping</p>
                        </div>
                    </div>
                    <?php if (isLoggedIn()): ?>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="/pages/dashboard.php" class="bg-primary text-white text-center py-2 rounded text-sm font-medium hover:bg-indigo-700 transition">Dashboard</a>
                            <a href="/pages/order-history.php" class="bg-blue-50 text-primary text-center py-2 rounded text-sm font-medium hover:bg-blue-100 transition">Orders</a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 gap-2">
                            <a href="/auth/register.php" class="bg-primary text-white text-center py-2 rounded text-sm font-medium hover:bg-indigo-700 transition">Join Now</a>
                            <a href="/auth/login.php" class="bg-blue-50 text-primary text-center py-2 rounded text-sm font-medium hover:bg-blue-100 transition">Log In</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Small Promo Banner -->
                <div class="bg-orange-500 rounded-lg shadow-sm p-4 text-white h-[220px] relative overflow-hidden group">
                    <div class="relative z-10">
                        <p class="text-orange-100 text-sm font-medium mb-1">Weekly Best</p>
                        <h3 class="text-xl font-bold mb-3">Headphones & Audio</h3>
                        <a href="/pages/products.php?cat=electronics" class="inline-block bg-white text-orange-600 px-4 py-1.5 rounded text-sm font-bold hover:bg-gray-100 transition">View More</a>
                    </div>
                    <i class="fas fa-headphones-alt absolute -bottom-4 -right-4 text-9xl text-orange-400 opacity-50 group-hover:scale-110 transition duration-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Deals & Offers (Flash Sale) -->
    <?php if (!empty($flash_sale_products)): ?>
    <div class="container mx-auto px-4 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-col md:flex-row">
                <!-- Timer Column -->
                <div class="md:w-64 bg-white p-6 border-r border-gray-100 flex flex-col justify-center">
                    <h3 class="text-xl font-bold text-gray-900 mb-1">Deals and offers</h3>
                    <p class="text-gray-500 text-sm mb-4">Hygiene equipments</p>
                    
                    <div class="flex gap-2 mb-4">
                        <div class="bg-gray-800 text-white w-10 h-10 rounded flex flex-col items-center justify-center">
                            <span class="font-bold text-sm">04</span>
                            <span class="text-[8px] uppercase">Days</span>
                        </div>
                        <div class="bg-gray-800 text-white w-10 h-10 rounded flex flex-col items-center justify-center">
                            <span class="font-bold text-sm">13</span>
                            <span class="text-[8px] uppercase">Hour</span>
                        </div>
                        <div class="bg-gray-800 text-white w-10 h-10 rounded flex flex-col items-center justify-center">
                            <span class="font-bold text-sm">34</span>
                            <span class="text-[8px] uppercase">Min</span>
                        </div>
                        <div class="bg-gray-800 text-white w-10 h-10 rounded flex flex-col items-center justify-center">
                            <span class="font-bold text-sm">56</span>
                            <span class="text-[8px] uppercase">Sec</span>
                        </div>
                    </div>
                </div>

                <!-- Products Scroll -->
                <div class="flex-1 overflow-x-auto scrollbar-hide">
                    <div class="flex divide-x divide-gray-100">
                        <?php foreach ($flash_sale_products as $product): ?>
                        <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>" class="min-w-[180px] p-4 group hover:bg-gray-50 transition block">
                            <div class="relative w-32 h-32 mx-auto mb-3">
                                <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/150'); ?>" 
                                     class="w-full h-full object-contain mix-blend-multiply group-hover:scale-105 transition">
                                <span class="absolute top-0 right-0 bg-red-100 text-red-600 text-xs font-bold px-1.5 py-0.5 rounded">
                                    -<?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>%
                                </span>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-900 font-medium truncate mb-1"><?php echo htmlspecialchars($product['name']); ?></p>
                                <span class="bg-red-100 text-red-600 text-xs px-2 py-1 rounded-full font-bold">
                                    -<?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>%
                                </span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. Official Stores -->
    <?php if (!empty($official_stores)): ?>
    <div class="container mx-auto px-4 mb-8">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Official Stores</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($official_stores as $store): ?>
                <a href="/pages/products.php?vendor=<?php echo $store['id']; ?>" class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 flex flex-col items-center justify-center hover:shadow-md transition group">
                    <div class="w-16 h-16 mb-3 relative">
                        <?php if (!empty($store['shop_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($store['shop_logo']); ?>" class="w-full h-full object-contain group-hover:scale-110 transition">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-100 rounded-full flex items-center justify-center text-2xl">🏪</div>
                        <?php endif; ?>
                    </div>
                    <h3 class="text-sm font-medium text-gray-900 text-center"><?php echo htmlspecialchars($store['shop_name']); ?></h3>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 6. Recommended Items (Just For You) -->
    <div class="container mx-auto px-4">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Recommended Items</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <?php foreach ($just_for_you as $product): ?>
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition group relative">
                    <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>" class="block">
                        <div class="relative pt-[100%] bg-gray-50 border-b border-gray-50">
                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                                 class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>
                        <div class="p-4">
                            <div class="mb-2">
                                <span class="text-lg font-bold text-gray-900"><?php echo formatPrice($product['sale_price'] ?? $product['price']); ?></span>
                                <?php if ($product['sale_price']): ?>
                                    <span class="text-sm text-gray-400 line-through ml-2"><?php echo formatPrice($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-sm text-gray-600 font-medium line-clamp-2 h-10 mb-2 leading-snug">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            <div class="flex items-center gap-1 text-xs text-gray-400">
                                <i class="fas fa-star text-yellow-400"></i>
                                <span><?php echo round($product['avg_rating'] ?? 0, 1); ?></span>
                                <span class="w-1 h-1 bg-gray-300 rounded-full mx-1"></span>
                                <span><?php echo $product['total_sold'] ?? 0; ?> sold</span>
                            </div>
                        </div>
                    </a>
                    <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                            class="absolute top-3 right-3 w-8 h-8 bg-white text-primary rounded-full shadow border border-gray-100 flex items-center justify-center hover:bg-primary hover:text-white transition z-10">
                        <i class="fas fa-heart"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-8">
            <a href="/pages/products.php" class="inline-block bg-white border border-gray-300 text-gray-700 px-8 py-2.5 rounded-lg font-medium hover:bg-gray-50 transition">
                Load More
            </a>
        </div>
    </div>
</div>

<!-- Features Footer -->
<div class="bg-white border-t py-12 mt-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
            <div>
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-primary text-xl">
                    <i class="fas fa-lock"></i>
                </div>
                <h4 class="font-bold text-gray-900 mb-1">Secure Payment</h4>
                <p class="text-sm text-gray-500">Have you ever finally just</p>
            </div>
            <div>
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-primary text-xl">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <h4 class="font-bold text-gray-900 mb-1">Customer Support</h4>
                <p class="text-sm text-gray-500">Have you ever finally just</p>
            </div>
            <div>
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 text-primary text-xl">
                    <i class="fas fa-truck"></i>
                </div>
                <h4 class="font-bold text-gray-900 mb-1">Free Delivery</h4>
                <p class="text-sm text-gray-500">Have you ever finally just</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
