<?php
require_once '../config/config.php';
require_once '../config/database.php';

$page_title = 'Products - SASTO Hub';

// Get filter parameters
$category_slug = $_GET['slug'] ?? null;
$search_query = $_GET['q'] ?? null;
$featured = isset($_GET['featured']) ? 1 : null;
$sale = isset($_GET['sale']) ? 1 : null;
$sort = $_GET['sort'] ?? 'newest';
$view = $_GET['view'] ?? 'grid';
$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;

// Build query
$where = ["p.status = 'active'", "v.status = 'approved'"];
$params = [];

if ($category_slug) {
    $where[] = "c.slug = ?";
    $params[] = $category_slug;
}

if ($search_query) {
    $where[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($featured) {
    $where[] = "p.featured = 1";
}

if ($sale) {
    $where[] = "p.sale_price IS NOT NULL";
}

if ($min_price !== null) {
    $where[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $min_price;
}

if ($max_price !== null) {
    $where[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $max_price;
}

$order_by = match($sort) {
    'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
    'name' => 'p.name ASC',
    'popular' => 'p.featured DESC, p.created_at DESC',
    default => 'p.created_at DESC'
};

$sql = "SELECT p.*, pi.image_path, v.shop_name, c.name as category_name, c.slug as category_slug,
        (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') as avg_rating,
        (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id AND r.status = 'approved') as review_count,
        (SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE oi.product_id = p.id AND o.status != 'cancelled' AND o.payment_status = 'paid') as total_sold
        FROM products p
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        LEFT JOIN vendors v ON p.vendor_id = v.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $order_by";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get all categories for filter
$categories = $conn->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

// Get brands (if you have a brands table, otherwise we'll show dummy data)
$brands_query = "SELECT DISTINCT b.id, b.name FROM brands b 
                 INNER JOIN products p ON p.brand_id = b.id 
                 WHERE p.status = 'active' 
                 ORDER BY b.name LIMIT 10";
$brands = $conn->query($brands_query)->fetchAll();

// Fetch user's wishlist product IDs
$wishlist_product_ids = [];
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wishlist_product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

include '../includes/header.php';
?>

<style>
.sidebar-filter {
    background: white;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}
.filter-section {
    padding: 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}
.filter-section:last-child {
    border-bottom: none;
}
.filter-checkbox {
    display: flex;
    align-items: center;
    padding: 0.5rem 0;
    cursor: pointer;
}
.filter-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    margin-right: 0.75rem;
    cursor: pointer;
    accent-color: #6366f1;
}
.filter-checkbox:hover {
    color: #6366f1;
}
.category-icon-item {
    flex-shrink: 0;
    width: 80px;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
}
.category-icon-item:hover {
    transform: translateY(-2px);
}
.category-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 0.5rem;
    background: #f3f4f6;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    transition: all 0.2s;
}
.category-icon-item:hover .category-icon,
.category-icon-item.active .category-icon {
    background: #eef2ff;
    color: #6366f1;
}
.product-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
    position: relative;
}
.product-card:hover {
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    border-color: #d1d5db;
}
.product-image-wrapper {
    position: relative;
    padding-top: 100%;
    background: #f9fafb;
    overflow: hidden;
}
.product-image-wrapper img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}
.product-card:hover .product-image-wrapper img {
    transform: scale(1.05);
}
.wishlist-btn {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    width: 36px;
    height: 36px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.2s;
    z-index: 10;
}
.wishlist-btn:hover {
    background: #fee2e2;
    color: #ef4444;
}
.rating-stars {
    color: #fbbf24;
    font-size: 0.875rem;
}
.rating-count {
    color: #9ca3af;
    font-size: 0.75rem;
    margin-left: 0.25rem;
}
.price-range-slider {
    -webkit-appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 3px;
    background: #e5e7eb;
    outline: none;
    margin: 1rem 0;
}
.price-range-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #6366f1;
    cursor: pointer;
}
.btn-add-cart {
    width: 100%;
    padding: 0.625rem;
    border: 1.5px solid #6366f1;
    background: white;
    color: #6366f1;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.btn-add-cart:hover {
    background: #6366f1;
    color: white;
}
</style>

<div class="bg-gray-50 min-h-screen">
    <!-- Page Header -->
    <div class="bg-white border-b">
        <div class="container mx-auto px-4 py-4">
            <h1 class="text-2xl font-bold text-gray-900">
                <?php 
                if ($search_query) {
                    echo "Search: " . htmlspecialchars($search_query);
                } elseif ($category_slug) {
                    echo htmlspecialchars($products[0]['category_name'] ?? 'Products');
                } else {
                    echo "Electronics & Gadgets";
                }
                ?>
            </h1>
        </div>
    </div>

    <!-- Category Icons Scroll -->
    <div class="bg-white border-b py-4">
        <div class="container mx-auto px-4">
            <div class="flex gap-4 overflow-x-auto pb-2" style="scrollbar-width: thin;">
                <a href="/pages/products.php" class="category-icon-item <?php echo !$category_slug ? 'active' : ''; ?>">
                    <div class="category-icon">📱</div>
                    <div class="text-xs text-gray-700">All</div>
                </a>
                <?php foreach (array_slice($categories, 0, 8) as $cat): ?>
                <a href="/pages/products.php?slug=<?php echo htmlspecialchars($cat['slug']); ?>" 
                   class="category-icon-item <?php echo $category_slug === $cat['slug'] ? 'active' : ''; ?>">
                    <div class="category-icon">
                        <?php 
                        $icons = ['💻', '🎧', '📺', '🔊', '⌚', '📷', '🎮', '🖨️'];
                        echo $icons[array_rand($icons)];
                        ?>
                    </div>
                    <div class="text-xs text-gray-700"><?php echo htmlspecialchars($cat['name']); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 py-6">
        <div class="flex gap-6">
            <!-- Left Sidebar Filters -->
            <aside class="w-64 flex-shrink-0">
                <div class="sidebar-filter sticky top-20">
                    <!-- Categories Filter -->
                    <div class="filter-section">
                        <h3 class="font-bold text-gray-900 mb-3 flex items-center justify-between">
                            Categories
                            <i class="fas fa-chevron-up text-xs text-gray-400"></i>
                        </h3>
                        <div class="space-y-1">
                            <label class="filter-checkbox">
                                <input type="checkbox" <?php echo !$category_slug ? 'checked' : ''; ?> 
                                       onchange="window.location.href='/pages/products.php'">
                                <span class="text-sm">All categories</span>
                            </label>
                            <?php foreach (array_slice($categories, 0, 6) as $cat): ?>
                            <label class="filter-checkbox">
                                <input type="checkbox" <?php echo $category_slug === $cat['slug'] ? 'checked' : ''; ?>
                                       onchange="window.location.href='/pages/products.php?slug=<?php echo $cat['slug']; ?>'">
                                <span class="text-sm"><?php echo htmlspecialchars($cat['name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Brands Filter -->
                    <?php if (!empty($brands)): ?>
                    <div class="filter-section">
                        <h3 class="font-bold text-gray-900 mb-3 flex items-center justify-between">
                            Brands
                            <i class="fas fa-chevron-up text-xs text-gray-400"></i>
                        </h3>
                        <div class="space-y-1">
                            <?php foreach ($brands as $brand): ?>
                            <label class="filter-checkbox">
                                <input type="checkbox">
                                <span class="text-sm"><?php echo htmlspecialchars($brand['name']); ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Condition Filter -->
                    <div class="filter-section">
                        <h3 class="font-bold text-gray-900 mb-3 flex items-center justify-between">
                            Condition
                            <i class="fas fa-chevron-up text-xs text-gray-400"></i>
                        </h3>
                        <div class="space-y-1">
                            <label class="filter-checkbox">
                                <input type="radio" name="condition" checked>
                                <span class="text-sm">Any condition</span>
                            </label>
                            <label class="filter-checkbox">
                                <input type="radio" name="condition">
                                <span class="text-sm">New</span>
                            </label>
                            <label class="filter-checkbox">
                                <input type="radio" name="condition">
                                <span class="text-sm">Used - Like New</span>
                            </label>
                            <label class="filter-checkbox">
                                <input type="radio" name="condition">
                                <span class="text-sm">Used - Good</span>
                            </label>
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-section">
                        <h3 class="font-bold text-gray-900 mb-3 flex items-center justify-between">
                            Price range
                            <i class="fas fa-chevron-up text-xs text-gray-400"></i>
                        </h3>
                        <div class="flex gap-2 mb-2">
                            <input type="number" placeholder="Min" id="minPrice" value="<?php echo $min_price ?? ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                            <input type="number" placeholder="Max" id="maxPrice" value="<?php echo $max_price ?? ''; ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                        </div>
                        <button onclick="applyPriceFilter()" 
                                class="w-full bg-indigo-600 text-white py-2 rounded text-sm font-medium hover:bg-indigo-700">
                            Apply
                        </button>
                    </div>

                    <!-- Location -->
                    <div class="filter-section">
                        <h3 class="font-bold text-gray-900 mb-3">Location</h3>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded text-sm">
                            <option>All locations</option>
                            <option>Kathmandu</option>
                            <option>Pokhara</option>
                            <option>Lalitpur</option>
                        </select>
                    </div>
                </div>
            </aside>

            <!-- Main Product Area -->
            <div class="flex-1">
                <!-- Toolbar -->
                <div class="flex items-center justify-between mb-4">
                    <div class="text-sm text-gray-600">
                        Showing <strong><?php echo count($products); ?></strong> results
                    </div>
                    <div class="flex items-center gap-4">
                        <select onchange="window.location.href=updateQueryParam('sort', this.value)" 
                                class="px-4 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:border-indigo-500">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Latest match</option>
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most popular</option>
                            <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Lowest price</option>
                            <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Highest price</option>
                        </select>
                        
                        <div class="flex border border-gray-300 rounded">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'grid'])); ?>" 
                               class="px-3 py-2 <?php echo $view === 'grid' ? 'bg-gray-100' : ''; ?>">
                                <i class="fas fa-th text-gray-600"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['view' => 'list'])); ?>" 
                               class="px-3 py-2 border-l border-gray-300 <?php echo $view === 'list' ? 'bg-gray-100' : ''; ?>">
                                <i class="fas fa-list text-gray-600"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <?php if (empty($products)): ?>
                    <div class="bg-white rounded-lg shadow p-16 text-center">
                        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Products Found</h3>
                        <p class="text-gray-600">Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <?php foreach ($products as $product): ?>
                            <div class="product-card">
                                <div class="product-image-wrapper">
                                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                                        <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>"
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    </a>
                                    <?php
                                    $in_wishlist = in_array($product['id'], $wishlist_product_ids);
                                    ?>
                                    <div class="wishlist-btn" onclick="toggleWishlist(<?php echo $product['id']; ?>, this)">
                                        <i class="<?php echo $in_wishlist ? 'fas text-red-500' : 'far'; ?> fa-heart"></i>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                                        <h3 class="text-sm text-gray-900 font-medium mb-2 line-clamp-2 hover:text-indigo-600">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </h3>
                                    </a>
                                    
                                    <div class="flex items-center mb-3">
                                        <div class="rating-stars flex text-yellow-400 text-xs">
                                            <?php
                                            $avg_rating = round($product['avg_rating'] ?? 0, 1);
                                            $total_sold = $product['total_sold'] ?? 0;
                                            for($i = 1; $i <= 5; $i++):
                                            ?>
                                                <?php if($i <= $avg_rating): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php elseif($i - 0.5 <= $avg_rating): ?>
                                                    <i class="fas fa-star-half-alt"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-gray-300"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-count text-xs text-gray-500 ml-1">(<?php echo $total_sold; ?> orders)</span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <?php if ($product['sale_price']): ?>
                                            <div class="text-xl font-bold text-gray-900">
                                                Rs <?php echo number_format($product['sale_price'], 2); ?>
                                            </div>
                                            <div class="text-sm text-gray-400 line-through">
                                                Rs <?php echo number_format($product['price'], 2); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-xl font-bold text-gray-900">
                                                Rs <?php echo number_format($product['price'], 2); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button onclick="addToCart(<?php echo $product['id']; ?>)" class="btn-add-cart">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span>Add to cart</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateQueryParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    return url.toString();
}

function applyPriceFilter() {
    const min = document.getElementById('minPrice').value;
    const max = document.getElementById('maxPrice').value;
    const url = new URL(window.location);
    if (min) url.searchParams.set('min_price', min);
    if (max) url.searchParams.set('max_price', max);
    window.location.href = url.toString();
}
</script>

<?php include '../includes/footer.php'; ?>
