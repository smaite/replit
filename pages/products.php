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

$order_by = match($sort) {
    'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
    'name' => 'p.name ASC',
    default => 'p.created_at DESC'
};

$sql = "SELECT p.*, pi.image_path, v.shop_name, c.name as category_name 
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

include '../includes/header.php';
?>

<div class="bg-white py-6 border-b">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl font-bold text-gray-900">
            <?php 
            if ($search_query) {
                echo "Search Results for: " . htmlspecialchars($search_query);
            } elseif ($featured) {
                echo "Featured Products";
            } elseif ($sale) {
                echo "Flash Sale";
            } elseif ($category_slug) {
                echo htmlspecialchars($products[0]['category_name'] ?? 'Category');
            } else {
                echo "All Products";
            }
            ?>
        </h1>
        <p class="text-gray-600 mt-1"><?php echo count($products); ?> products found</p>
    </div>
</div>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row gap-8">
        <!-- Filters Sidebar -->
        <aside class="md:w-64">
            <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                <h3 class="font-bold text-lg mb-4">Filters</h3>
                
                <!-- Categories -->
                <!-- Categories Removed as per request -->
                
                <!-- Sort -->
                <div>
                    <h4 class="font-medium text-gray-900 mb-3">Sort By</h4>
                    <select onchange="window.location.href=updateQueryParam('sort', this.value)" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                    </select>
                </div>
            </div>
        </aside>
        
        <!-- Products Grid -->
        <div class="flex-1">
            <?php if (empty($products)): ?>
                <div class="bg-white rounded-lg shadow p-12 text-center">
                    <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No Products Found</h3>
                    <p class="text-gray-600 mb-4">Try adjusting your filters or search query</p>
                    <a href="/pages/products.php" class="inline-block bg-primary text-white px-6 py-3 rounded-lg hover:bg-indigo-700">
                        View All Products
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($products as $product): ?>
                        <div class="bg-white rounded-lg shadow hover:shadow-xl transition group">
                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($product['slug']); ?>">
                                <div class="relative overflow-hidden rounded-t-lg">
                                    <img src="<?php echo htmlspecialchars($product['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="w-full h-48 object-cover group-hover:scale-110 transition-transform duration-300">
                                    <?php if ($product['sale_price']): ?>
                                        <span class="absolute top-2 right-2 bg-red-500 text-white text-xs px-2 py-1 rounded">
                                            <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($product['featured']): ?>
                                        <span class="absolute top-2 left-2 bg-yellow-400 text-gray-900 text-xs px-2 py-1 rounded">
                                            ⭐
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <div class="p-4">

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
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function updateQueryParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    return url.toString();
}
</script>

<?php include '../includes/footer.php'; ?>
