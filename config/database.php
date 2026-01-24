<?php
// Database Configuration
// Public db
// define('DB_HOST', '192.250.229.92');
// define('DB_USER', 'glorious_maxv');
// define('DB_PASS', 'glorious_maxv');
// define('DB_NAME', 'glorious_maxv');
//localdb
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sastohub');
// Database Configuration - Using Replit PostgreSQL
$database_url = getenv('DATABASE_URL');
// Create connection
try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
