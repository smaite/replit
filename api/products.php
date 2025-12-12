<?php
/**
 * Sasto Hub Products API
 * Endpoints for product data
 */
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet();
        break;
    default:
        jsonError('Method not allowed', 405);
}

function handleGet() {
    global $conn;
    
    try {
        // Single product by ID
        if (isset($_GET['id'])) {
            $productId = (int)$_GET['id'];
            getProductById($productId);
            return;
        }
        
        // Build query based on filters
        $where = ["p.status = 'active'", "(p.verification_status = 'approved' OR p.verification_status IS NULL)"];
        $params = [];
        
        // Filter by category
        if (isset($_GET['category'])) {
            $where[] = "p.category_id = ?";
            $params[] = (int)$_GET['category'];
        }
        
        // Search query
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = '%' . $_GET['search'] . '%';
            $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Featured products - check if column exists
        if (isset($_GET['featured'])) {
            // Just return some products without the is_featured filter
            // Change to this if you have is_featured column: $where[] = "p.is_featured = 1";
        }
        
        // Deals (products with sale price)
        if (isset($_GET['deals'])) {
            $where[] = "p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price";
        }
        
        // Pagination
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        // Sorting
        $sortOptions = [
            'newest' => 'p.created_at DESC',
            'price_low' => 'COALESCE(p.sale_price, p.price) ASC',
            'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
            'popular' => 'p.view_count DESC',
            'name' => 'p.name ASC'
        ];
        $sort = $sortOptions[$_GET['sort'] ?? 'newest'] ?? $sortOptions['newest'];
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) FROM products p WHERE $whereClause";
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        // Get products
        $sql = "
            SELECT 
                p.id,
                p.name,
                p.slug,
                p.description,
                p.price,
                p.sale_price,
                p.stock,
                p.category_id,
                c.name as category_name,
                p.created_at,
                (SELECT image_path FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as image
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE $whereClause
            ORDER BY $sort
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format prices
        foreach ($products as &$product) {
            $product['price'] = (float)$product['price'];
            $product['sale_price'] = $product['sale_price'] ? (float)$product['sale_price'] : null;
            $product['stock'] = (int)$product['stock'];
            $product['image'] = $product['image'] ?: '/uploads/products/placeholder.jpg';
        }
        
        jsonSuccess([
            'products' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        jsonError('Failed to fetch products');
    }
}

function getProductById($id) {
    global $conn;
    
    try {
        // Get product - simplified query without vendors
        $stmt = $conn->prepare("
            SELECT 
                p.id,
                p.name,
                p.slug,
                p.description,
                p.price,
                p.sale_price,
                p.stock,
                p.category_id,
                c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.id = ? AND p.status = 'active'
        ");
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            jsonError('Product not found', 404);
            return;
        }
        
        // Get all images
        $imgStmt = $conn->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC");
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $product['images'] = $images ?: ['/uploads/products/placeholder.jpg'];
        $product['price'] = (float)$product['price'];
        $product['sale_price'] = $product['sale_price'] ? (float)$product['sale_price'] : null;
        $product['stock'] = (int)$product['stock'];
        
        // Try to increment view count if column exists
        try {
            $conn->prepare("UPDATE products SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?")->execute([$id]);
        } catch (Exception $e) {
            // view_count column may not exist, ignore
        }
        
        jsonSuccess($product);
        
    } catch (Exception $e) {
        error_log("getProductById Error: " . $e->getMessage());
        jsonError('Failed to fetch product: ' . $e->getMessage());
    }
}
