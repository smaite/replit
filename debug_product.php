<?php
require_once 'config/config.php';
require_once 'config/database.php';

echo "<h3>Debug: Testing Product Query</h3>";

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 3;
echo "Looking for product ID: " . $productId . "<br><br>";

// Test 1: Simple query
echo "<b>Test 1 - Simple query:</b><br>";
try {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $result = $stmt->fetch();
    if ($result) {
        echo "Found: " . $result['name'] . " (status: " . $result['status'] . ")<br>";
    } else {
        echo "Not found with simple query<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 2: List all products
echo "<br><b>Test 2 - All products:</b><br>";
try {
    $stmt = $conn->query("SELECT id, name, status FROM products LIMIT 10");
    $all = $stmt->fetchAll();
    foreach ($all as $p) {
        echo $p['id'] . " | " . $p['name'] . " | " . $p['status'] . "<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test 3: Same query as product.php
echo "<br><b>Test 3 - Product.php query:</b><br>";
try {
    $stmt = $conn->prepare("
        SELECT p.*, c.name as category_name, v.business_name as vendor_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        LEFT JOIN vendors v ON p.vendor_id = v.id
        WHERE p.id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if ($product) {
        echo "Found: " . $product['name'] . "<br>";
    } else {
        echo "NOT FOUND with JOIN query<br>";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
?>
