<?php
require_once 'config/database.php';

// First, create default vendor if not exists
try {
    $stmt = $conn->prepare("INSERT IGNORE INTO vendors (user_id, shop_name) VALUES (1, 'SASTO Hub')");
    $stmt->execute();
} catch (Exception $e) {
    // Vendor might already exist
}

// Sample products data
$products = [
    [
        'name' => 'Premium Headphones',
        'slug' => 'premium-headphones',
        'price' => 4999,
        'sale_price' => 3499,
        'category_id' => 1,
        'vendor_id' => 1,
        'description' => 'High-quality wireless headphones with noise cancellation',
        'stock' => 50,
        'featured' => 1
    ],
    [
        'name' => 'Smart Watch',
        'slug' => 'smart-watch',
        'price' => 8999,
        'sale_price' => 6999,
        'category_id' => 1,
        'vendor_id' => 1,
        'description' => 'Latest smart watch with health tracking and fitness features',
        'stock' => 35,
        'featured' => 1
    ],
    [
        'name' => 'Laptop Stand',
        'slug' => 'laptop-stand',
        'price' => 1499,
        'sale_price' => 999,
        'category_id' => 1,
        'vendor_id' => 1,
        'description' => 'Ergonomic aluminum laptop stand for better posture',
        'stock' => 100,
        'featured' => 1
    ],
    [
        'name' => 'USB-C Cable',
        'slug' => 'usb-c-cable',
        'price' => 299,
        'sale_price' => 199,
        'category_id' => 1,
        'vendor_id' => 1,
        'description' => 'Durable and fast charging USB-C cable',
        'stock' => 200,
        'featured' => 0
    ],
    [
        'name' => 'Wireless Mouse',
        'slug' => 'wireless-mouse',
        'price' => 999,
        'sale_price' => 599,
        'category_id' => 1,
        'vendor_id' => 1,
        'description' => 'Silent wireless mouse with precision tracking',
        'stock' => 75,
        'featured' => 1
    ],
    [
        'name' => 'Phone Case',
        'slug' => 'phone-case',
        'price' => 499,
        'sale_price' => 299,
        'category_id' => 2,
        'vendor_id' => 1,
        'description' => 'Protective and stylish phone case',
        'stock' => 150,
        'featured' => 0
    ],
    [
        'name' => 'Screen Protector',
        'slug' => 'screen-protector',
        'price' => 399,
        'sale_price' => 199,
        'category_id' => 2,
        'vendor_id' => 1,
        'description' => 'Tempered glass screen protector for all phones',
        'stock' => 300,
        'featured' => 1
    ],
    [
        'name' => 'Power Bank',
        'slug' => 'power-bank',
        'price' => 1999,
        'sale_price' => 1299,
        'category_id' => 1,
        'vendor_id' => 1,
        'description' => '20000mAh fast charging power bank',
        'stock' => 80,
        'featured' => 1
    ]
];

try {
    // Insert products
    $inserted = 0;
    foreach ($products as $product) {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO products 
            (name, slug, price, sale_price, category_id, vendor_id, description, stock, featured) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $product['name'],
            $product['slug'],
            $product['price'],
            $product['sale_price'],
            $product['category_id'],
            $product['vendor_id'],
            $product['description'],
            $product['stock'],
            $product['featured']
        ]);
        
        $inserted++;
    }
    
    echo json_encode(['success' => true, 'message' => "Inserted $inserted products"]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
