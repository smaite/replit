<?php
require_once 'config/database.php';

try {
    // $conn is already created in database.php
    if (!isset($conn)) {
        throw new Exception("Database connection not found in config/database.php");
    }
    $db = $conn;

    $sql = file_get_contents('migrations/migration_cancel_reason.sql');

    // Split SQL by semicolon to execute statements individually (PDO doesn't support multiple statements in one go usually)
    // However, for simple migrations, we might need to be careful.
    // Let's try executing the whole block first if the driver supports it, or split it.
    // A safer approach for migrations is to split.

    // Remove comments
    $lines = explode("\n", $sql);
    $cleanSql = "";
    foreach ($lines as $line) {
        if (substr(trim($line), 0, 2) == '--' || trim($line) == '') {
            continue;
        }
        $cleanSql .= $line . "\n";
    }

    $statements = explode(";", $cleanSql);

    foreach ($statements as $statement) {
        if (trim($statement) != '') {
            try {
                $db->exec($statement);
                echo "Executed: " . substr(trim($statement), 0, 50) . "...\n";
            } catch (PDOException $e) {
                // Ignore "duplicate column" or "table exists" errors if we want to be idempotent
                if (strpos($e->getMessage(), "Duplicate column") !== false || strpos($e->getMessage(), "already exists") !== false) {
                    echo "Skipped (already exists): " . substr(trim($statement), 0, 50) . "...\n";
                } else {
                    echo "Error executing: " . substr(trim($statement), 0, 50) . "...\n";
                    echo "Message: " . $e->getMessage() . "\n";
                }
            }
        }
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
