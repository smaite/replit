<?php
/**
 * Mobile Search Page - SASTO Hub
 * Enhanced with search history, trending searches, and recommendations
 */
require_once '../config/config.php';
require_once '../config/database.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$products = [];
$searchHistory = [];
$trendingSearches = [];

// Get user ID or session ID for tracking
$userId = isLoggedIn() ? $_SESSION['user_id'] : null;
$sessionId = session_id();

// Save search query to history
if (!empty($query)) {
    try {
        // Save to search history
        $stmt = $conn->prepare("
            INSERT INTO search_history (user_id, query, session_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $query, $sessionId]);
        
        // Search products
        $searchTerm = "%$query%";
        $stmt = $conn->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            AND (p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ? OR p.tags LIKE ?)
            ORDER BY 
                CASE WHEN p.name LIKE ? THEN 1
                     WHEN p.tags LIKE ? THEN 2
                     ELSE 3 END,
                p.name ASC
            LIMIT 50
        ");
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Get user's search history (if logged in or has session)
try {
    if ($userId) {
        $stmt = $conn->prepare("
            SELECT DISTINCT query 
            FROM search_history 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT query 
            FROM search_history 
            WHERE session_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$sessionId]);
    }
    $searchHistory = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Get trending searches (popular queries from last 7 days)
try {
    $stmt = $conn->query("
        SELECT query, COUNT(*) as search_count 
        FROM search_history 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY query 
        ORDER BY search_count DESC 
        LIMIT 12
    ");
    $trendingSearches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// If no trending searches, use defaults
if (empty($trendingSearches)) {
    $trendingSearches = [
        ['query' => 'Fashion', 'search_count' => 0],
        ['query' => 'Electronics', 'search_count' => 0],
        ['query' => 'Mobile', 'search_count' => 0],
        ['query' => 'Shoes', 'search_count' => 0],
        ['query' => 'Watches', 'search_count' => 0],
        ['query' => 'Beauty', 'search_count' => 0],
    ];
}

// Handle clear history
if (isset($_GET['clear_history'])) {
    try {
        if ($userId) {
            $stmt = $conn->prepare("DELETE FROM search_history WHERE user_id = ?");
            $stmt->execute([$userId]);
        } else {
            $stmt = $conn->prepare("DELETE FROM search_history WHERE session_id = ?");
            $stmt->execute([$sessionId]);
        }
    } catch (Exception $e) {}
    header('Location: search.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Search - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
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
                       placeholder="Search products..." autofocus id="searchInput">
                <button type="submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </form>
        </header>
        
        <?php if (!empty($query)): ?>
        <!-- Search Results -->
        <div class="search-results-info">
            <span><?php echo count($products); ?> results for "<?php echo htmlspecialchars($query); ?>"</span>
        </div>
        
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
                        <?php if (!empty($product['tags'])): ?>
                            <div class="product-tags">
                                <?php 
                                $tags = array_slice(explode(',', $product['tags']), 0, 2);
                                foreach ($tags as $tag): 
                                    $tag = trim($tag);
                                    if (!empty($tag)):
                                ?>
                                    <span class="mini-tag"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="price-row">
                            <span class="price"><?php echo formatPrice($product['price']); ?></span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state full-width">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <h3>No products found</h3>
                    <p>Try a different search term</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Empty State - Show History & Trending -->
        
        <?php if (!empty($searchHistory)): ?>
        <!-- Recent Searches -->
        <div class="search-section">
            <div class="section-header-inline">
                <h3>Recent Searches</h3>
                <a href="?clear_history=1" class="clear-link">Clear</a>
            </div>
            <div class="history-list">
                <?php foreach ($searchHistory as $historyQuery): ?>
                <a href="?q=<?php echo urlencode($historyQuery); ?>" class="history-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                        <polyline points="1 4 1 10 7 10"></polyline>
                        <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                    </svg>
                    <span><?php echo htmlspecialchars($historyQuery); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Trending Searches -->
        <div class="search-section">
            <h3>🔥 Trending Searches</h3>
            <div class="suggestion-tags">
                <?php foreach ($trendingSearches as $trending): ?>
                <a href="?q=<?php echo urlencode($trending['query']); ?>" class="tag">
                    <?php echo htmlspecialchars($trending['query']); ?>
                    <?php if ($trending['search_count'] > 5): ?>
                        <span class="hot-badge">Hot</span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Popular Categories for Discovery -->
        <div class="search-section">
            <h3>Browse by Category</h3>
            <div class="category-chips">
                <?php
                try {
                    $catStmt = $conn->query("SELECT id, name FROM categories WHERE status = 'active' LIMIT 8");
                    $cats = $catStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($cats as $cat):
                ?>
                    <a href="category.php?id=<?php echo $cat['id']; ?>" class="category-chip">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </a>
                <?php endforeach; } catch (Exception $e) {} ?>
            </div>
        </div>
        
        <?php endif; ?>
        
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
