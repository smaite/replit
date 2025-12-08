<?php
// Direct migration execution
require_once 'config/database.php';

echo "<h3>Running Search & Recommendation Migration</h3>";

$queries = [
    "CREATE TABLE IF NOT EXISTS search_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        query VARCHAR(255) NOT NULL,
        session_id VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_query (query),
        INDEX idx_created_at (created_at)
    )",
    
    "CREATE TABLE IF NOT EXISTS product_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        product_id INT NOT NULL,
        session_id VARCHAR(100) NULL,
        view_count INT DEFAULT 1,
        last_viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_product_id (product_id),
        INDEX idx_last_viewed (last_viewed_at)
    )",
    
    "ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"
];

foreach ($queries as $i => $q) {
    try {
        $conn->exec($q);
        echo "<p style='color:green'>✓ Query " . ($i + 1) . " executed</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            echo "<p style='color:blue'>⚡ Query " . ($i + 1) . " - already exists (OK)</p>";
        } else {
            echo "<p style='color:red'>✗ Query " . ($i + 1) . " error: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<br><b>Migration complete!</b>";
