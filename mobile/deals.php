<?php
/**
 * Mobile Flash Deals Page - SASTO Hub
 * Time-limited deals with countdown
 */
require_once '../config/config.php';
require_once '../config/database.php';

$products = [];

try {
    // Get products with sale price (flash deals)
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'active' AND p.sale_price IS NOT NULL AND p.sale_price > 0
        ORDER BY (p.price - p.sale_price) / p.price DESC
        LIMIT 20
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Cart count
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF5722">
    <title>Flash Deals - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
    <link rel="stylesheet" href="css/skeleton.css">
</head>
<body>
    <div class="app-page">
        <!-- Header -->
        <header class="app-header" style="background: linear-gradient(135deg, #FF5722, #FF9800);">
            <button class="back-btn" onclick="window.history.back()" style="color: white;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1 style="color: white; font-size: 18px;">⚡ Flash Deals</h1>
            <div></div>
        </header>
        
        <!-- Timer Banner -->
        <div style="background: linear-gradient(135deg, #FF5722, #FF9800); padding: 16px; text-align: center;">
            <p style="color: rgba(255,255,255,0.9); font-size: 12px; margin-bottom: 8px;">Deals end in</p>
            <div class="deal-timer" style="display: flex; justify-content: center; gap: 8px;">
                <div style="background: rgba(0,0,0,0.2); padding: 8px 16px; border-radius: 8px; color: white;">
                    <span id="hours" style="font-size: 24px; font-weight: bold;">12</span>
                    <p style="font-size: 10px; margin-top: 2px;">Hours</p>
                </div>
                <div style="background: rgba(0,0,0,0.2); padding: 8px 16px; border-radius: 8px; color: white;">
                    <span id="minutes" style="font-size: 24px; font-weight: bold;">34</span>
                    <p style="font-size: 10px; margin-top: 2px;">Mins</p>
                </div>
                <div style="background: rgba(0,0,0,0.2); padding: 8px 16px; border-radius: 8px; color: white;">
                    <span id="seconds" style="font-size: 24px; font-weight: bold;">56</span>
                    <p style="font-size: 10px; margin-top: 2px;">Secs</p>
                </div>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="products-grid" style="padding: 16px;">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): 
                    $discount = round((($product['price'] - $product['sale_price']) / $product['price']) * 100);
                ?>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-card">
                    <div class="product-image">
                        <span class="discount-badge">-<?php echo $discount; ?>%</span>
                        <?php if (!empty($product['image'])): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder-img"></div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                        <div class="price-row">
                            <span class="price" style="color: #FF5722;"><?php echo formatPrice($product['sale_price']); ?></span>
                            <span class="original-price" style="text-decoration: line-through; color: #999; font-size: 12px; margin-left: 8px;">
                                <?php echo formatPrice($product['price']); ?>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state full-width">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" width="64" height="64">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                    <h3>No Flash Deals</h3>
                    <p>Check back later for amazing deals!</p>
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
                <?php if ($cartCount > 0): ?>
                    <span class="badge"><?php echo $cartCount; ?></span>
                <?php endif; ?>
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
    
    <script>
    // Countdown timer
    function updateTimer() {
        const now = new Date();
        const end = new Date();
        end.setHours(23, 59, 59, 999);
        
        const diff = end - now;
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
        document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
        document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
    }
    
    updateTimer();
    setInterval(updateTimer, 1000);
    </script>
</body>
</html>
