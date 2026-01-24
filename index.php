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

<style>
    /* Mall UI Specific Styles */
    .mall-category-item {
        transition: transform 0.2s;
    }
    .mall-category-item:hover {
        transform: translateY(-5px);
        color: var(--primary-color);
    }
    .mall-card {
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .mall-card:hover {
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        transform: translateY(-2px);
    }
    .scrollbar-hide::-webkit-scrollbar {
        display: none;
    }
    .scrollbar-hide {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<!-- 1. CATEGORY NAV BAR (Flipkart Style) -->
<div class="bg-white shadow-sm border-b sticky top-[72px] z-30">
    <div class="container mx-auto px-2 md:px-4">
        <div class="flex overflow-x-auto scrollbar-hide py-3 gap-4 md:gap-8 justify-start md:justify-center">
            <?php foreach ($categories as $cat): ?>
                <a href="/pages/products.php?slug=<?php echo $cat['slug']; ?>" class="mall-category-item flex flex-col items-center min-w-[64px] cursor-pointer group">
                    <div class="w-32 h-32 md:w-16 md:h-16 rounded-full bg-gray-100 overflow-hidden mb-2 border group-hover:border-primary">
                        <?php if (!empty($cat['image'])): ?>
                            <img src="uploads\categories\<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <img src="/assets/images/placeholder-category.png" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="w-full h-full object-cover opacity-50">
                        <?php endif; ?>
                    </div>
                    <span class="text-xs md:text-sm font-medium text-gray-700 group-hover:text-primary whitespace-nowrap">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </span>
                </a>
            <?php endforeach; ?>
            <a href="/pages/products.php" class="mall-category-item flex flex-col items-center min-w-[64px] cursor-pointer group">
                <div class="w-12 h-12 md:w-16 md:h-16 rounded-full bg-gray-100 flex items-center justify-center text-xl md:text-2xl mb-2 border group-hover:border-primary">
                    🔥
                </div>
                <span class="text-xs md:text-sm font-medium text-gray-700 group-hover:text-primary whitespace-nowrap">
                    Offers
                </span>
            </a>
        </div>
    </div>
</div>

<div class="bg-gray-100 min-h-screen pb-12">
    <!-- 2. HERO SECTION (Daraz Style) -->
    <div class="container mx-auto px-4 py-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <!-- Main Slider (66%) -->
            <div class="md:col-span-8 lg:col-span-9">
                <div class="relative rounded-xl overflow-hidden shadow-lg h-48 md:h-[340px] lg:h-[400px] group">
                    <?php if (!empty($banners[0])): ?>
                        <img src="<?php echo htmlspecialchars($banners[0]['image']); ?>" 
                             alt="Banner" 
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/50 to-transparent flex items-center p-8 md:p-12">
                            <div class="text-white max-w-lg">
                                <h2 class="text-3xl md:text-5xl font-bold mb-4 leading-tight"><?php echo htmlspecialchars($banners[0]['title'] ?? 'Big Sale!'); ?></h2>
                                <p class="text-lg md:text-xl mb-6 opacity-90"><?php echo htmlspecialchars($banners[0]['subtitle'] ?? 'Up to 50% Off'); ?></p>
                                <a href="<?php echo htmlspecialchars($banners[0]['link'] ?? '/pages/products.php'); ?>" 
                                   class="bg-primary hover:bg-indigo-700 text-white px-6 py-3 rounded-lg font-bold transition shadow-lg">
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
            
            <!-- Side Banners (33%) - Hidden on mobile -->
            <div class="hidden md:flex md:col-span-4 lg:col-span-3 flex-col gap-4">
                <?php for($i=1; $i<=2; $i++): ?>
                    <div class="flex-1 rounded-xl overflow-hidden shadow-md relative group h-[162px] lg:h-[192px]">
                        <?php if (!empty($banners[$i])): ?>
                            <img src="<?php echo htmlspecialchars($banners[$i]['image']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-200 flex items-center justify-center text-gray-400">Promo <?php echo $i; ?></div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- 3. FLASH SALE (Amazon Style) -->
    <?php if (!empty($flash_sale_products)): ?>
    <div class="container mx-auto px-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
            <div class="flex justify-between items-center mb-4 border-b pb-4">
                <div class="flex items-center gap-4">
                    <h2 class="text-xl md:text-2xl font-bold text-orange-600">⚡ Flash Sale</h2>
                    <div class="hidden md:flex items-center gap-2 text-sm font-medium bg-orange-100 text-orange-700 px-3 py-1 rounded">
                        <span>Ending in</span>
                        <span class="font-bold">12:45:30</span>
                    </div>
                </div>
                <a href="/pages/products.php?sale=1" class="text-primary font-semibold hover:underline">See All ></a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($flash_sale_products as $product): ?>
                    <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>" class="group block">
                        <div class="relative rounded-lg overflow-hidden mb-2 bg-gray-50 p-2">
                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/150'); ?>" 
                                 class="w-full h-32 md:h-40 object-contain mix-blend-multiply group-hover:scale-105 transition duration-300">
                            <span class="absolute top-2 right-2 bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                -<?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>%
                            </span>
                        </div>
                        <div class="px-1">
                            <p class="text-lg font-bold text-gray-900"><?php echo formatPrice($product['sale_price']); ?></p>
                            <p class="text-xs text-gray-500 line-through"><?php echo formatPrice($product['price']); ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 4. OFFICIAL STORES / BRAND MALL -->
    <?php if (!empty($official_stores)): ?>
    <div class="container mx-auto px-4 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center gap-2">
                <i class="fas fa-store-alt text-primary"></i> Official Stores
            </h2>
            <a href="/pages/products.php" class="text-primary font-semibold hover:underline">See All ></a>
        </div>
        <div class="grid grid-cols-3 md:grid-cols-6 lg:grid-cols-12 gap-3 md:gap-4">
            <?php foreach ($official_stores as $store): ?>
                <a href="/pages/products.php?vendor=<?php echo $store['id']; ?>" class="bg-white rounded-lg shadow-sm p-3 flex flex-col items-center justify-center hover:shadow-md transition h-24 md:h-28">
                    <?php if (!empty($store['shop_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($store['shop_logo']); ?>" class="w-12 h-12 md:w-16 md:h-16 object-contain mb-1">
                    <?php else: ?>
                        <div class="w-12 h-12 md:w-16 md:h-16 bg-gray-100 rounded-full flex items-center justify-center text-xl">🏪</div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 5. JUST FOR YOU (Infinite Feed Style) -->
    <div class="container mx-auto px-4">
        <h2 class="text-xl md:text-2xl font-bold text-gray-800 mb-6 text-center">Just For You</h2>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3 md:gap-4">
            <?php foreach ($just_for_you as $product): ?>
                <div class="mall-card bg-white rounded-xl overflow-hidden border border-transparent hover:border-gray-200 group relative">
                    <a href="/pages/product-detail.php?slug=<?php echo $product['slug']; ?>" class="block">
                        <div class="relative pt-[100%] bg-gray-50"> <!-- 1:1 Aspect Ratio -->
                            <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                                 class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        </div>
                        <div class="p-3">
                            <h3 class="text-sm text-gray-800 font-medium line-clamp-2 h-10 mb-1 leading-snug">
                                <?php echo htmlspecialchars($product['name']); ?>
                            </h3>
                            
                            <div class="flex items-baseline gap-2 mb-1">
                                <?php if ($product['sale_price']): ?>
                                    <span class="text-base md:text-lg font-bold text-primary"><?php echo formatPrice($product['sale_price']); ?></span>
                                    <span class="text-xs text-gray-400 line-through"><?php echo formatPrice($product['price']); ?></span>
                                <?php else: ?>
                                    <span class="text-base md:text-lg font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Rating -->
                            <div class="flex items-center gap-1 text-xs text-gray-500">
                                <div class="flex text-yellow-400">
                                    <?php
                                    $avg = round($product['avg_rating'] ?? 0, 1);
                                    for($i=1; $i<=5; $i++):
                                        if($i <= $avg) echo '<i class="fas fa-star"></i>';
                                        elseif($i-0.5 <= $avg) echo '<i class="fas fa-star-half-alt"></i>';
                                        else echo '<i class="far fa-star text-gray-300"></i>';
                                    endfor;
                                    ?>
                                </div>
                                <span>(<?php echo $product['total_sold'] ?? 0; ?> sold)</span>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Quick Add Button (Visible on Hover) -->
                    <button onclick="addToCart(<?php echo $product['id']; ?>)" 
                            class="absolute bottom-24 right-2 w-8 h-8 md:w-10 md:h-10 bg-primary text-white rounded-full shadow-lg flex items-center justify-center opacity-0 group-hover:opacity-100 translate-y-4 group-hover:translate-y-0 transition duration-300 hover:bg-indigo-700 z-10">
                        <i class="fas fa-cart-plus"></i>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-8 md:mt-12">
            <a href="/pages/products.php" class="inline-block bg-white border border-gray-300 text-gray-700 px-8 py-3 rounded-full font-medium hover:bg-gray-50 hover:border-gray-400 transition shadow-sm">
                Load More Products
            </a>
        </div>
    </div>
</div>

<!-- Features Section (Footer Top) -->
<div class="bg-white border-t py-8 md:py-12 mt-8">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div class="p-4">
                <i class="fas fa-check-circle text-3xl text-primary mb-3"></i>
                <h4 class="font-bold text-gray-800">100% Authentic</h4>
                <p class="text-xs text-gray-500 mt-1">Original products guaranteed</p>
            </div>
            <div class="p-4">
                <i class="fas fa-shield-alt text-3xl text-primary mb-3"></i>
                <h4 class="font-bold text-gray-800">Secure Payment</h4>
                <p class="text-xs text-gray-500 mt-1">100% secure payment</p>
            </div>
            <div class="p-4">
                <i class="fas fa-undo text-3xl text-primary mb-3"></i>
                <h4 class="font-bold text-gray-800">Easy Return</h4>
                <p class="text-xs text-gray-500 mt-1">7 days return policy</p>
            </div>
            <div class="p-4">
                <i class="fas fa-headset text-3xl text-primary mb-3"></i>
                <h4 class="font-bold text-gray-800">24/7 Support</h4>
                <p class="text-xs text-gray-500 mt-1">Dedicated support</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
