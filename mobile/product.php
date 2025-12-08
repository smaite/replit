<?php
/**
 * Mobile Product Detail Page - SASTO Hub
 * Individual product with images, description, add to cart
 */
require_once '../config/config.php';
require_once '../config/database.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$relatedProducts = [];

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $qty = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$productId])) {
        $_SESSION['cart'][$productId]['quantity'] += $qty;
    } else {
        $_SESSION['cart'][$productId] = [
            'product_id' => $productId,
            'quantity' => $qty
        ];
    }
    
    header('Location: cart.php?added=1');
    exit;
}

// Handle add to wishlist
if (isset($_GET['wishlist']) && isLoggedIn()) {
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO wishlist (user_id, product_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $productId]);
    } catch (Exception $e) {}
    header("Location: product.php?id=$productId&wishlisted=1");
    exit;
}

try {
    // Get product with image from product_images table
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, v.shop_name as vendor_name,
               pi.image_path as image
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN vendors v ON p.vendor_id = v.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Track product view for recommendations
        $userId = isLoggedIn() ? $_SESSION['user_id'] : null;
        $sessionId = session_id();
        
        try {
            $stmt = $conn->prepare("
                INSERT INTO product_views (user_id, product_id, session_id, view_count, created_at, last_viewed_at)
                VALUES (?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE view_count = view_count + 1, last_viewed_at = NOW()
            ");
            $stmt->execute([$userId, $productId, $sessionId]);
        } catch (Exception $e) {}
        
        // Get similar products using TAGS + CATEGORY algorithm
        $similarProducts = [];
        $productTags = !empty($product['tags']) ? explode(',', $product['tags']) : [];
        
        if (!empty($productTags)) {
            // Build tag matching query
            $tagConditions = [];
            $tagParams = [];
            foreach ($productTags as $tag) {
                $tag = trim($tag);
                if (!empty($tag)) {
                    $tagConditions[] = "p.tags LIKE ?";
                    $tagParams[] = "%$tag%";
                }
            }
            
            if (!empty($tagConditions)) {
                $tagQuery = "
                    SELECT p.*, 
                           (" . implode(" + ", array_fill(0, count($tagConditions), "(CASE WHEN p.tags LIKE ? THEN 1 ELSE 0 END)")) . ") as tag_score
                    FROM products p 
                    WHERE p.id != ? 
                    AND p.status = 'active'
                    AND (" . implode(" OR ", $tagConditions) . " OR p.category_id = ?)
                    ORDER BY tag_score DESC, RAND()
                    LIMIT 6
                ";
                
                // Double the params for scoring + filtering
                $allParams = array_merge($tagParams, [$productId], $tagParams, [$product['category_id']]);
                $stmt = $conn->prepare($tagQuery);
                $stmt->execute($allParams);
                $similarProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
        // Fallback to category-based if no tag matches
        if (empty($similarProducts)) {
            $stmt = $conn->prepare("
                SELECT * FROM products 
                WHERE category_id = ? AND id != ? AND status = 'active'
                ORDER BY RAND() LIMIT 6
            ");
            $stmt->execute([$product['category_id'], $productId]);
            $similarProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $relatedProducts = $similarProducts;
    }
} catch (Exception $e) {
    $product = null;
}

// If no product found, show a nice message instead of redirecting
if (!$product) {
    // Check if product ID was passed
    if ($productId == 0) {
        header('Location: home.php');
        exit;
    }
}

$inWishlist = false;
if ($product && isLoggedIn()) {
    try {
        $stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $productId]);
        $inWishlist = $stmt->fetch() !== false;
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title><?php echo $product ? htmlspecialchars($product['name']) : 'Product Not Found'; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
</head>
<body>
    <?php if (!$product): ?>
    <div class="app-page">
        <header class="page-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1>Product Not Found</h1>
            <div class="header-spacer"></div>
        </header>
        <div class="empty-state" style="padding-top: 60px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" style="width:80px;height:80px;">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <h3>Product not found</h3>
            <p>This product may have been removed or is no longer available.</p>
            <a href="home.php" class="btn-primary">Back to Home</a>
        </div>
    </div>
    <?php else: ?>
    <div class="product-detail-page">
        <!-- Header -->
        <header class="product-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <div class="header-actions">
                <a href="?id=<?php echo $productId; ?>&wishlist=1" class="wishlist-btn <?php echo $inWishlist ? 'active' : ''; ?>">
                    <svg viewBox="0 0 24 24" fill="<?php echo $inWishlist ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </a>
                <a href="cart.php" class="cart-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                </a>
            </div>
        </header>
        
        <!-- Product Image -->
        <div class="product-image-container">
            <?php if (!empty($product['image'])): ?>
                <img src="..<?php echo htmlspecialchars($product['image']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-main-image">
            <?php else: ?>
                <div class="placeholder-img large">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info -->
        <div class="product-details">
            <div class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="product-price-section">
                <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                <?php if (isset($product['original_price']) && $product['original_price'] > $product['price']): ?>
                    <span class="original-price"><?php echo formatPrice($product['original_price']); ?></span>
                    <span class="discount-tag">
                        <?php echo round(100 - ($product['price'] / $product['original_price'] * 100)); ?>% OFF
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($product['vendor_name'])): ?>
            <div class="vendor-info">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                </svg>
                <span>Sold by: <?php echo htmlspecialchars($product['vendor_name']); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="product-description">
                <h3>Description</h3>
                <p><?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available.')); ?></p>
            </div>
            
            <!-- Quantity Selector -->
            <div class="quantity-section">
                <span>Quantity:</span>
                <div class="quantity-selector">
                    <button type="button" onclick="decreaseQty()">-</button>
                    <input type="number" id="quantity" value="1" min="1" max="99" readonly>
                    <button type="button" onclick="increaseQty()">+</button>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
        <div class="related-section">
            <h3>Related Products</h3>
            <div class="related-scroll">
                <?php foreach ($relatedProducts as $related): ?>
                <a href="product.php?id=<?php echo $related['id']; ?>" class="related-card">
                    <?php if (!empty($related['image'])): ?>
                        <img src="../uploads/products/<?php echo htmlspecialchars($related['image']); ?>" alt="">
                    <?php else: ?>
                        <div class="placeholder-img small"></div>
                    <?php endif; ?>
                    <span class="related-price"><?php echo formatPrice($related['price']); ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Add to Cart Button -->
        <div class="add-to-cart-bar">
            <form method="POST" action="" class="cart-form">
                <input type="hidden" name="quantity" id="cartQuantity" value="1">
                <button type="submit" name="add_to_cart" class="add-to-cart-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    Add to Cart
                </button>
            </form>
            <a href="checkout.php?product=<?php echo $productId; ?>" class="buy-now-btn">Buy Now</a>
        </div>
    </div>
    
    <script>
        function decreaseQty() {
            const input = document.getElementById('quantity');
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
                document.getElementById('cartQuantity').value = input.value;
            }
        }
        
        function increaseQty() {
            const input = document.getElementById('quantity');
            if (parseInt(input.value) < 99) {
                input.value = parseInt(input.value) + 1;
                document.getElementById('cartQuantity').value = input.value;
            }
        }
    </script>
    </div>
    <?php endif; ?>
</body>
</html>

