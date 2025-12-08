<?php
/**
 * Categories Schema Migration
 * Add status column and parent_id for subcategories
 */
require_once 'config/database.php';

try {
    // Add status column if not exists
    try {
        $conn->exec("ALTER TABLE categories ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
        echo "✅ Added status column<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ status column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Add parent_id column for subcategories if not exists
    try {
        $conn->exec("ALTER TABLE categories ADD COLUMN parent_id INT NULL AFTER id");
        echo "✅ Added parent_id column<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ parent_id column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Add image column if not exists
    try {
        $conn->exec("ALTER TABLE categories ADD COLUMN image VARCHAR(255) NULL");
        echo "✅ Added image column<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ image column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Set all existing categories to active
    $conn->exec("UPDATE categories SET status = 'active' WHERE status IS NULL");
    echo "✅ Set all categories to active<br>";
    
    // Show current categories
    echo "<br><h3>Current Categories:</h3>";
    $stmt = $conn->query("SELECT id, name, status, parent_id, image FROM categories ORDER BY parent_id, name");
    $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($cats, true) . "</pre>";
    
    echo "<br><strong>Migration complete!</strong>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
