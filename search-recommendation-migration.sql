-- Search and Recommendation System Migration
-- SASTO Hub - Mobile Customer Website

-- 1. Create search_history table to store user searches
CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    query VARCHAR(255) NOT NULL,
    session_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_query (query),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 2. Create product_views table to track what users view
CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    product_id INT NOT NULL,
    session_id VARCHAR(100) NULL,
    view_count INT DEFAULT 1,
    last_viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_product (user_id, product_id),
    INDEX idx_user_id (user_id),
    INDEX idx_product_id (product_id),
    INDEX idx_last_viewed (last_viewed_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- 3. Add tags column to products table
ALTER TABLE products ADD COLUMN IF NOT EXISTS tags VARCHAR(500) NULL AFTER description;

-- 4. Create index on tags for faster search
CREATE INDEX IF NOT EXISTS idx_products_tags ON products(tags);

-- 5. View for trending searches (most popular queries in last 7 days)
CREATE OR REPLACE VIEW trending_searches AS
SELECT 
    query,
    COUNT(*) as search_count
FROM search_history
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY query
ORDER BY search_count DESC
LIMIT 20;
