<?php
/**
 * Mobile Categories Page - SASTO Hub
 * Grid view of all product categories
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Fetch all active categories
try {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Categories - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-page">
        <!-- Header -->
        <header class="page-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1>Categories</h1>
            <div class="header-spacer"></div>
        </header>
        
        <!-- Categories Grid -->
        <div class="categories-full-grid">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>" class="category-full-card">
                    <div class="category-image">
                        <?php if (!empty($category['image'])): ?>
                            <img src="../uploads/categories/<?php echo htmlspecialchars($category['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($category['name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-img">
                                <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Default categories if none in database -->
                <a href="category.php?type=women" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #fce4ec, #f8bbd9);">
                        <span style="font-size:40px;">👗</span>
                    </div>
                    <span>Women Clothing & Fashion</span>
                </a>
                <a href="category.php?type=men" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #e3f2fd, #bbdefb);">
                        <span style="font-size:40px;">👔</span>
                    </div>
                    <span>Men Clothing & Fashion</span>
                </a>
                <a href="category.php?type=computer" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #263238, #455a64);">
                        <span style="font-size:40px;">💻</span>
                    </div>
                    <span>Computer & Accessories</span>
                </a>
                <a href="category.php?type=auto" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #e0f2f1, #b2dfdb);">
                        <span style="font-size:40px;">🚗</span>
                    </div>
                    <span>Automobile & Motorcycle</span>
                </a>
                <a href="category.php?type=kids" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #fff9c4, #fff59d);">
                        <span style="font-size:40px;">🧸</span>
                    </div>
                    <span>Kids & toy</span>
                </a>
                <a href="category.php?type=sports" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #f5f5f5, #eeeeee);">
                        <span style="font-size:40px;">⚽</span>
                    </div>
                    <span>Sports & outdoor</span>
                </a>
                <a href="category.php?type=jewelry" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #fbe9e7, #ffccbc);">
                        <span style="font-size:40px;">💎</span>
                    </div>
                    <span>Jewelry & Watches</span>
                </a>
                <a href="category.php?type=phones" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #e8eaf6, #c5cae9);">
                        <span style="font-size:40px;">📱</span>
                    </div>
                    <span>Cellphones & Tabs</span>
                </a>
                <a href="category.php?type=beauty" class="category-full-card">
                    <div class="category-image" style="background: linear-gradient(135deg, #e0f7fa, #b2ebf2);">
                        <span style="font-size:40px;">💄</span>
                    </div>
                    <span>Beauty, Health & Hair</span>
                </a>
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
            <a href="categories.php" class="nav-item active">
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
