<?php
/**
 * Migration: Add Google ID column
 * Run once to add google_id column for Google Sign-In
 */
require_once 'config/database.php';

try {
    // Check if column exists
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
    
    if ($checkColumn->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER email");
        $conn->exec("ALTER TABLE users ADD INDEX idx_google_id (google_id)");
        echo "<h2>✅ Migration Successful!</h2>";
        echo "<p>Added 'google_id' column to users table.</p>";
    } else {
        echo "<h2>ℹ️ Column Already Exists</h2>";
        echo "<p>The 'google_id' column already exists in the users table.</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Migration Failed</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
