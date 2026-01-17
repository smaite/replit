<?php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';

if (strlen($query) < 2) {
    echo json_encode(['categories' => [], 'products' => []]);
    exit;
}

try {
    // Search Categories
    $stmt = $conn->prepare("SELECT id, name, slug, image FROM categories WHERE name LIKE ? LIMIT 5");
    $stmt->execute(["%$query%"]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Search Products
    $stmt = $conn->prepare("SELECT p.id, p.name, p.slug, p.price, p.sale_price, pi.image_path 
                            FROM products p 
                            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = true
                            WHERE p.name LIKE ? AND p.status = 'active' 
                            LIMIT 5");
    $stmt->execute(["%$query%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'categories' => $categories,
        'products' => $products
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
