<?php
/**
 * Products API Endpoint
 * GET /api/products - List all products
 * GET /api/products/:id - Get product details
 */

require_once '../config/database.php';
require_once './config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($path, '/'));

// Extract ID if present (e.g., /api/products/123)
$productId = null;
if (count($pathSegments) > 2 && is_numeric($pathSegments[2])) {
    $productId = (int)$pathSegments[2];
}

try {
    if ($method === 'GET') {
        if ($productId) {
            // Get single product
            $stmt = $conn->prepare("
                SELECT 
                    p.id, 
                    p.name,
                    p.slug,
                    p.description,
                    p.price,
                    p.sale_price,
                    p.image,
                    p.category_id,
                    p.seller_id,
                    p.in_stock,
                    p.ratings,
                    p.created_at,
                    c.name as category_name,
                    u.name as seller_name,
                    u.avatar as seller_avatar
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN users u ON p.seller_id = u.id
                WHERE p.id = ?
                LIMIT 1
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                // Convert data types
                $product['price'] = (float)$product['price'];
                $product['sale_price'] = (float)$product['sale_price'];
                $product['in_stock'] = (int)$product['in_stock'];
                $product['ratings'] = (float)$product['ratings'];
                
                echo ApiResponse::success($product, 'Product fetched successfully');
            } else {
                echo ApiResponse::error('Product not found', 404);
            }
        } else {
            // List products with pagination
            $page = max(1, (int)ApiRequest::getParameter('page', 1));
            $limit = min(100, max(1, (int)ApiRequest::getParameter('limit', 20)));
            $offset = ($page - 1) * $limit;
            $category = ApiRequest::getParameter('category');
            $search = ApiRequest::getParameter('search');

            $query = "SELECT 
                        p.id, 
                        p.name,
                        p.slug,
                        p.price,
                        p.sale_price,
                        p.image,
                        p.category_id,
                        p.seller_id,
                        p.in_stock,
                        p.ratings,
                        c.name as category_name,
                        u.name as seller_name
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    LEFT JOIN users u ON p.seller_id = u.id
                    WHERE 1=1";

            $params = [];

            if ($category) {
                $query .= " AND p.category_id = ?";
                $params[] = $category;
            }

            if ($search) {
                $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
                $searchTerm = "%$search%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convert data types
            foreach ($products as &$product) {
                $product['price'] = (float)$product['price'];
                $product['sale_price'] = (float)$product['sale_price'];
                $product['in_stock'] = (int)$product['in_stock'];
                $product['ratings'] = (float)$product['ratings'];
            }

            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM products p WHERE 1=1";
            if ($category) {
                $countQuery .= " AND p.category_id = ?";
            }
            if ($search) {
                $countQuery .= " AND (p.name LIKE ? OR p.description LIKE ?)";
            }

            $countStmt = $conn->prepare($countQuery);
            $countParams = [];
            if ($category) {
                $countParams[] = $category;
            }
            if ($search) {
                $countParams[] = "%$search%";
                $countParams[] = "%$search%";
            }
            $countStmt->execute($countParams);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            echo ApiResponse::success([
                'items' => $products,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$totalCount,
                    'pages' => ceil($totalCount / $limit)
                ]
            ], 'Products fetched successfully');
        }
    } else {
        echo ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    echo ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
