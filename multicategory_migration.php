<?php
/**
 * Multi-Category Products Migration
 * Creates product_categories junction table for assigning multiple categories to products
 */
require_once 'config/database.php';

try {
    // Create product_categories junction table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS product_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            category_id INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_product_category (product_id, category_id),
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        )
    ");
    echo "✅ product_categories table created<br>";
    
    // Migrate existing products to the new table (copy from category_id to product_categories)
    $products = $conn->query("SELECT id, category_id FROM products WHERE category_id IS NOT NULL");
    $insertStmt = $conn->prepare("INSERT IGNORE INTO product_categories (product_id, category_id) VALUES (?, ?)");
    $count = 0;
    
    foreach ($products as $product) {
        if ($product['category_id']) {
            $insertStmt->execute([$product['id'], $product['category_id']]);
            $count++;
        }
    }
    echo "✅ Migrated $count existing product-category relationships<br>";
    
    echo "<br><strong>Migration complete!</strong>";
    echo "<br><br>Products can now have multiple categories.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
