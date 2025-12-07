<?php
/**
 * Mobile Search Page - SASTO Hub
 * Product search with results
 */
require_once '../config/config.php';
require_once '../config/database.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$products = [];

if (!empty($query)) {
    try {
        $searchTerm = "%$query%";
        $stmt = $conn->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)
            ORDER BY p.name ASC
            LIMIT 50
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Search - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-page">
        <!-- Search Header -->
        <header class="search-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <form action="" method="GET" class="search-form">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
                       placeholder="Search products..." autofocus>
                <button type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </form>
        </header>
        
        <?php if (!empty($query)): ?>
        <div class="search-results-info">
            <span><?php echo count($products); ?> results for "<?php echo htmlspecialchars($query); ?>"</span>
        </div>
        <?php endif; ?>
        
        <!-- Search Results -->
        <div class="products-grid" style="padding: 16px;">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-card">
                    <div class="product-image">
                        <?php if (!empty($product['image'])): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder-img"></div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <span class="category-tag"><?php echo htmlspecialchars($product['category_name'] ?? ''); ?></span>
                        <div class="price-row">
                            <span class="price"><?php echo formatPrice($product['price']); ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php elseif (!empty($query)): ?>
                <div class="empty-state full-width">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <h3>No products found</h3>
                    <p>Try a different search term</p>
                </div>
            <?php else: ?>
                <div class="search-suggestions">
                    <h3>Popular Searches</h3>
                    <div class="suggestion-tags">
                        <a href="?q=fashion" class="tag">Fashion</a>
                        <a href="?q=electronics" class="tag">Electronics</a>
                        <a href="?q=mobile" class="tag">Mobile</a>
                        <a href="?q=shoes" class="tag">Shoes</a>
                        <a href="?q=watches" class="tag">Watches</a>
                        <a href="?q=beauty" class="tag">Beauty</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="home.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Home</span>
            </a>
            <a href="categories.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Categories</span>
            </a>
            <a href="cart.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span>Cart</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Profile</span>
            </a>
        </nav>
    </div>
</body>
</html>
