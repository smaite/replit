<?php
require_once 'config/database.php';

try {
    $conn->exec("ALTER TABLE products ADD COLUMN verification_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved' AFTER status");
    echo "✅ verification_status column added successfully!";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "⚡ Column already exists (OK)";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
