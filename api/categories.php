<?php
/**
 * Categories API Endpoint
 * GET /api/categories - List all categories
 * GET /api/categories/:id - Get category details
 */

require_once '../config/database.php';
require_once './config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($path, '/'));

// Extract ID if present
$categoryId = null;
if (count($pathSegments) > 2 && is_numeric($pathSegments[2])) {
    $categoryId = (int)$pathSegments[2];
}

try {
    if ($method === 'GET') {
        if ($categoryId) {
            // Get single category with products
            $stmt = $conn->prepare("
                SELECT 
                    id, 
                    name,
                    slug,
                    image,
                    description,
                    created_at
                FROM categories
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($category) {
                // Get product count
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                $countStmt->execute([$categoryId]);
                $category['product_count'] = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];

                echo ApiResponse::success($category, 'Category fetched successfully');
            } else {
                echo ApiResponse::error('Category not found', 404);
            }
        } else {
            // List all categories
            $stmt = $conn->prepare("
                SELECT 
                    id, 
                    name,
                    slug,
                    image,
                    description,
                    created_at
                FROM categories
                ORDER BY name ASC
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add product count to each category
            foreach ($categories as &$category) {
                $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE category_id = ?");
                $countStmt->execute([$category['id']]);
                $category['product_count'] = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            }

            echo ApiResponse::success($categories, 'Categories fetched successfully');
        }
    } else {
        echo ApiResponse::error('Method not allowed', 405);
    }
} catch (Exception $e) {
    echo ApiResponse::error('Server error: ' . $e->getMessage(), 500);
}
