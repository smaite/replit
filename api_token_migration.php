<?php
/**
 * API Token Migration
 * Adds api_token column to users table for app authentication
 */
require_once 'config/database.php';

echo "<h2>API Token Migration</h2>";

try {
    // Add api_token column
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN api_token VARCHAR(100) NULL AFTER password");
        echo "✅ Added api_token column to users table<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ api_token column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Add last_login column
    try {
        $conn->exec("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
        echo "✅ Added last_login column<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ last_login column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Add index on api_token for fast lookup
    try {
        $conn->exec("CREATE INDEX idx_users_api_token ON users(api_token)");
        echo "✅ Added index on api_token<br>";
    } catch (PDOException $e) {
        echo "ℹ️ Index may already exist<br>";
    }
    
    echo "<br><strong style='color: green;'>✅ Migration complete!</strong>";
    echo "<br><br>API is ready for mobile app authentication.";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
