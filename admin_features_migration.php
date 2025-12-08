<?php
/**
 * Admin Features Migration
 * - Category requests table
 * - Fix existing categories without status
 */
require_once 'config/database.php';

try {
    // Create category_requests table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS category_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            category_name VARCHAR(100) NOT NULL,
            description TEXT,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            admin_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
        )
    ");
    echo "✅ category_requests table created<br>";
    
    // Fix existing categories without status
    $conn->exec("UPDATE categories SET status = 'active' WHERE status IS NULL OR status = ''");
    echo "✅ Existing categories set to active<br>";
    
    // Set default verification_status for existing products
    $conn->exec("UPDATE products SET verification_status = 'approved' WHERE verification_status IS NULL");
    echo "✅ Existing products set to approved<br>";
    
    echo "<br><strong>Migration complete!</strong>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
