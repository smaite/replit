<?php
/**
 * Mobile Brands Page - SASTO Hub
 * Browse products by vendor/brand
 */
require_once '../config/config.php';
require_once '../config/database.php';

$vendors = [];

try {
    // Get approved vendors with product count
    $stmt = $conn->prepare("
        SELECT v.*, 
               COUNT(p.id) as product_count,
               u.full_name as owner_name
        FROM vendors v 
        LEFT JOIN products p ON v.id = p.vendor_id AND p.status = 'active'
        LEFT JOIN users u ON v.user_id = u.id
        WHERE v.status = 'approved'
        GROUP BY v.id
        HAVING product_count > 0
        ORDER BY product_count DESC
        LIMIT 30
    ");
    $stmt->execute();
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Top Brands - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
</head>
<body>
    <div class="app-page">
        <!-- Header -->
        <header class="app-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1>Top Brands & Sellers</h1>
            <div></div>
        </header>
        
        <!-- Brands Grid -->
        <div style="padding: 16px;">
            <?php if (!empty($vendors)): ?>
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                    <?php foreach ($vendors as $vendor): ?>
                    <a href="category.php?vendor=<?php echo $vendor['id']; ?>" 
                       style="background: white; border-radius: 12px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; display: block;">
                        <div style="width: 60px; height: 60px; margin: 0 auto 12px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <?php if (!empty($vendor['logo'])): ?>
                                <img src="../uploads/vendors/<?php echo htmlspecialchars($vendor['logo']); ?>" 
                                     alt="<?php echo htmlspecialchars($vendor['shop_name']); ?>"
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                            <?php else: ?>
                                <span style="color: white; font-size: 24px; font-weight: bold;">
                                    <?php echo strtoupper(substr($vendor['shop_name'], 0, 1)); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3 style="font-size: 14px; font-weight: 600; color: #212121; margin-bottom: 4px;">
                            <?php echo htmlspecialchars($vendor['shop_name']); ?>
                        </h3>
                        <p style="font-size: 12px; color: #9e9e9e;">
                            <?php echo $vendor['product_count']; ?> Products
                        </p>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5" width="64" height="64" style="margin: 0 auto 16px;">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                    <h3 style="font-size: 18px; color: #616161; margin-bottom: 8px;">No Brands Yet</h3>
                    <p style="color: #9e9e9e;">Check back soon for top sellers!</p>
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
</body>
</html>
