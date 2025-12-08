<?php
/**
 * Mobile Categories Page - SASTO Hub
 * Grid view of all product categories with subcategories
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Fetch all active categories (parent categories first)
try {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY parent_id, name");
    $stmt->execute();
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize into parent and sub categories
    $parentCategories = [];
    $subCategories = [];
    foreach ($allCategories as $cat) {
        if (empty($cat['parent_id'])) {
            $parentCategories[] = $cat;
        } else {
            $subCategories[$cat['parent_id']][] = $cat;
        }
    }
} catch (Exception $e) {
    $parentCategories = [];
    $subCategories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Categories - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
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
            <?php if (!empty($parentCategories)): ?>
                <?php foreach ($parentCategories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>" class="category-full-card">
                    <div class="category-image">
                        <?php if (!empty($category['image'])): ?>
                            <img src="../uploads/categories/<?php echo htmlspecialchars($category['image']); ?>" 
                                 alt="<?php echo htmlspecialchars($category['name']); ?>">
                        <?php else: ?>
                            <div class="placeholder-img" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                                <span style="font-size: 24px; color: white;"><?php echo mb_substr($category['name'], 0, 1); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                </a>
                
                <?php // Show subcategories if any ?>
                <?php if (isset($subCategories[$category['id']])): ?>
                    <?php foreach ($subCategories[$category['id']] as $subCat): ?>
                    <a href="category.php?id=<?php echo $subCat['id']; ?>" class="category-full-card sub-category">
                        <div class="category-image">
                            <?php if (!empty($subCat['image'])): ?>
                                <img src="../uploads/categories/<?php echo htmlspecialchars($subCat['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($subCat['name']); ?>">
                            <?php else: ?>
                                <div class="placeholder-img" style="background: linear-gradient(135deg, #a5b4fc, #c4b5fd);">
                                    <span style="font-size: 20px; color: white;"><?php echo mb_substr($subCat['name'], 0, 1); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <span><?php echo htmlspecialchars($subCat['name']); ?></span>
                    </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state" style="grid-column: 1/-1; padding: 40px; text-align: center;">
                    <p style="color: #666;">No categories available</p>
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
