<?php
require_once 'config/database.php';

// Check if products table has image column
echo "<h3>Product ID 1:</h3>";
$r = $conn->query("SELECT id, name, image FROM products WHERE id = 1");
$p = $r->fetch(PDO::FETCH_ASSOC);
print_r($p);

echo "<h3>Product Images table:</h3>";
$r = $conn->query("SELECT * FROM product_images LIMIT 5");
print_r($r->fetchAll(PDO::FETCH_ASSOC));

echo "<h3>Describe products table:</h3>";
$r = $conn->query("DESCRIBE products");
foreach ($r as $row) {
    echo $row['Field'] . " - " . $row['Type'] . "<br>";
}
