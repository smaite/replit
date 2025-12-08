<?php
/**
 * Mobile Home Page - SASTO Hub
 * Mobile-optimized home with categories, deals, and bottom navigation
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Get user info if logged in
$isGuest = isset($_SESSION['guest_mode']) && !isLoggedIn();
$userName = isLoggedIn() ? ($_SESSION['user_name'] ?? 'User') : 'Guest';

// Fetch categories from database
try {
    $categoriesStmt = $conn->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name LIMIT 6");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Fetch featured/flash deal products (only approved ones)
try {
    $productsStmt = $conn->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.status = 'active' AND (p.verification_status = 'approved' OR p.verification_status IS NULL)
        ORDER BY p.created_at DESC 
        LIMIT 10
    ");
    $productsStmt->execute();
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
}

// Cart count from session
$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo SITE_NAME; ?> - Home</title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
</head>
<body>
    <div class="home-page">
        <!-- Status Bar Simulation -->
        <div class="status-bar">
            <span><?php echo date('g:i'); ?></span>
            <span>●●●●</span>
        </div>
        
        <!-- Search Bar -->
        <div class="search-container">
            <a href="search.php" class="search-bar" style="text-decoration: none;">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <span style="color: #9E9E9E; font-size: 14px;">Search in <?php echo SITE_NAME; ?>...</span>
            </a>
        </div>
        
        <!-- Banner Slider -->
        <div class="banner-slider">
            <div class="banner-container">
                <div class="banner-track" id="bannerTrack">
                    <div class="banner-slide green">
                        <div class="banner-content">
                            <h3>WANT IT !</h3>
                            <p>Exclusive For Your ACTIVE ECOMMERCE</p>
                        </div>
                    </div>
                    <div class="banner-slide pink">
                        <div class="banner-content">
                            <h3>Fashion Sale</h3>
                            <p>Up to 50% off on selected items</p>
                        </div>
                    </div>
                    <div class="banner-slide blue">
                        <div class="banner-content">
                            <h3>New Arrivals</h3>
                            <p>Check out the latest products</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="banner-dots">
                <div class="banner-dot active" data-index="0"></div>
                <div class="banner-dot" data-index="1"></div>
                <div class="banner-dot" data-index="2"></div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="categories.php?type=today" class="action-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                Todays Deal
            </a>
            <a href="deals.php" class="action-pill active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                Flash Deal
            </a>
            <a href="brands.php" class="action-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                Brands
            </a>
            <a href="sellers.php" class="action-pill">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 3h18l-2 13H5L3 3z"></path>
                    <path d="M16 16a2 2 0 0 1 2 2v1H6v-1a2 2 0 0 1 2-2"></path>
                </svg>
                Top Sellers
            </a>
        </div>
        
        <!-- Collection Cards -->
        <div class="collection-section">
            <div class="collection-scroll">
                <a href="categories.php?slug=perfume" class="collection-card" style="background: linear-gradient(135deg, #e8d5f0 0%, #d4b8e5 100%);">
                    <div class="overlay">
                        <h4>PERFUME<br>FOR</h4>
                        <p>Handpicked collection<br>Only in Active eCommerce CMS</p>
                    </div>
                </a>
                <a href="categories.php?slug=fashion" class="collection-card" style="background: linear-gradient(135deg, #ffd6e0 0%, #ffb6c7 100%);">
                    <div class="overlay">
                        <h4>Exclusive<br>Collections</h4>
                        <p>BUY NOW</p>
                    </div>
                </a>
                <a href="categories.php?slug=sale" class="collection-card" style="background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);">
                    <div class="overlay">
                        <h4>END OF<br>SEASON</h4>
                        <p>Big Discounts</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Featured Categories -->
        <div class="section-header">
            <h2>Featured Categories</h2>
            <a href="categories.php">View All</a>
        </div>
        <div class="categories-grid">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>" class="category-card">
                    <div class="category-icon">
                        <?php if (!empty($category['image'])): ?>
                            <img src="../uploads/categories/<?php echo htmlspecialchars($category['image']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default categories if database empty -->
                <a href="category.php?type=women" class="category-card">
                    <div class="category-icon" style="background: #f8e8ff;"></div>
                    <span>Women Clothing & Fashion</span>
                </a>
                <a href="category.php?type=computer" class="category-card">
                    <div class="category-icon" style="background: #e8f4ff;"></div>
                    <span>Computer & Accessories</span>
                </a>
                <a href="category.php?type=kids" class="category-card">
                    <div class="category-icon" style="background: #fff3e8;"></div>
                    <span>Kids & toy</span>
                </a>
                <a href="category.php?type=men" class="category-card">
                    <div class="category-icon" style="background: #e8fff0;"></div>
                    <span>Men Clothing & Fashion</span>
                </a>
                <a href="category.php?type=auto" class="category-card">
                    <div class="category-icon" style="background: #f0f0f0;"></div>
                    <span>Automobile & Motorcycle</span>
                </a>
                <a href="category.php?type=jewelry" class="category-card">
                    <div class="category-icon" style="background: #fff8e8;"></div>
                    <span>Jewelry & Watches</span>
                </a>
            <?php endif; ?>
        </div>
        
        <!-- Flash Deals Section -->
        <div class="flash-deals">
            <div class="flash-header">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#F5841F" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
                <h2>Flash Deals</h2>
                <div class="timer">
                    <span>02</span>
                    <span>14</span>
                    <span>59</span>
                </div>
            </div>
            <p style="color: #FF5722; font-size: 12px; margin: -10px 0 16px;">For limited time in Flash Sale</p>
            
            <div class="deals-scroll">
                <?php if (!empty($products)): ?>
                    <?php foreach (array_slice($products, 0, 4) as $product): ?>
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="deal-card">
                        <span class="badge">-<?php echo rand(20, 50); ?>%</span>
                        <?php if (!empty($product['image'])): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div style="width:100%;height:180px;background:linear-gradient(135deg,#667eea,#764ba2);"></div>
                        <?php endif; ?>
                        <div class="deal-info">
                            <p>For limited time in Flash Sale</p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Default deals if database empty -->
                    <div class="deal-card" style="background: linear-gradient(135deg, #ffd166 0%, #ffa94d 100%);">
                        <span class="badge">End of Season</span>
                        <div style="width:100%;height:180px;background:linear-gradient(135deg,#ff9500,#ff7b00);display:flex;align-items:center;justify-content:center;">
                            <span style="color:white;font-size:24px;">🔥</span>
                        </div>
                        <div class="deal-info">
                            <p>For limited time in Flash Sale</p>
                        </div>
                    </div>
                    <div class="deal-card">
                        <span class="badge">End of Season</span>
                        <div style="width:100%;height:180px;background:linear-gradient(135deg,#00c6ff,#0072ff);display:flex;align-items:center;justify-content:center;">
                            <span style="color:white;font-size:24px;">💎</span>
                        </div>
                        <div class="deal-info">
                            <p>For limited time in Flash Sale</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="home.php" class="nav-item active">
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
            <a href="<?php echo $isGuest ? 'login.php' : 'profile.php'; ?>" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Profile</span>
            </a>
        </nav>
    </div>
    
    <script>
        // Banner Slider with Touch Swipe Support
        const bannerTrack = document.getElementById('bannerTrack');
        const bannerDots = document.querySelectorAll('.banner-dot');
        let currentSlide = 0;
        const totalSlides = 3;
        let autoSlideInterval;
        
        function goToSlide(index) {
            currentSlide = index;
            bannerTrack.style.transform = `translateX(-${index * 100}%)`;
            bannerDots.forEach((dot, i) => {
                dot.classList.toggle('active', i === index);
            });
        }
        
        function startAutoSlide() {
            autoSlideInterval = setInterval(() => {
                currentSlide = (currentSlide + 1) % totalSlides;
                goToSlide(currentSlide);
            }, 4000);
        }
        
        function stopAutoSlide() {
            clearInterval(autoSlideInterval);
        }
        
        // Start auto slide
        startAutoSlide();
        
        // Click on dots
        bannerDots.forEach((dot, i) => {
            dot.addEventListener('click', () => goToSlide(i));
        });
        
        // Touch swipe support
        let touchStartX = 0;
        let touchEndX = 0;
        
        bannerTrack.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
            stopAutoSlide();
        }, { passive: true });
        
        bannerTrack.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
            startAutoSlide();
        }, { passive: true });
        
        function handleSwipe() {
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    // Swipe left - next slide
                    currentSlide = (currentSlide + 1) % totalSlides;
                } else {
                    // Swipe right - previous slide
                    currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
                }
                goToSlide(currentSlide);
            }
        }
        
        // Flash deal timer
        function updateTimer() {
            const timerSpans = document.querySelectorAll('.flash-header .timer span');
            let hours = parseInt(timerSpans[0].textContent);
            let minutes = parseInt(timerSpans[1].textContent);
            let seconds = parseInt(timerSpans[2].textContent);
            
            seconds--;
            if (seconds < 0) {
                seconds = 59;
                minutes--;
            }
            if (minutes < 0) {
                minutes = 59;
                hours--;
            }
            if (hours < 0) {
                hours = 23;
            }
            
            timerSpans[0].textContent = hours.toString().padStart(2, '0');
            timerSpans[1].textContent = minutes.toString().padStart(2, '0');
            timerSpans[2].textContent = seconds.toString().padStart(2, '0');
        }
        setInterval(updateTimer, 1000);
    </script>
</body>
</html>
