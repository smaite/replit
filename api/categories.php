<?php
/**
 * Sasto Hub Categories API
 * Endpoints for category data
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
        // Single category by ID
        if (isset($_GET['id'])) {
            $categoryId = (int)$_GET['id'];
            getCategoryById($categoryId);
            return;
        }
        
        // Get all categories with subcategories
        $stmt = $conn->query("
            SELECT 
                c.id,
                c.name,
                c.slug,
                c.image,
                c.parent_id,
                c.status,
                (SELECT COUNT(*) FROM products WHERE category_id = c.id AND status = 'active') as product_count
            FROM categories c
            WHERE c.status = 'active'
            ORDER BY c.name ASC
        ");
        $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organize into parent-child structure
        $parents = [];
        $children = [];
        
        foreach ($allCategories as $cat) {
            $cat['product_count'] = (int)$cat['product_count'];
            $cat['image'] = $cat['image'] ? '/uploads/categories/' . $cat['image'] : null;
            
            if (empty($cat['parent_id'])) {
                $cat['subcategories'] = [];
                $parents[$cat['id']] = $cat;
            } else {
                $children[$cat['parent_id']][] = $cat;
            }
        }
        
        // Attach children to parents
        foreach ($parents as $id => &$parent) {
            if (isset($children[$id])) {
                $parent['subcategories'] = $children[$id];
            }
        }
        
        jsonSuccess([
            'categories' => array_values($parents)
        ]);
        
    } catch (Exception $e) {
        jsonError('Failed to fetch categories');
    }
}

function getCategoryById($id) {
    global $conn;
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                c.*,
                p.name as parent_name
            FROM categories c
            LEFT JOIN categories p ON c.parent_id = p.id
            WHERE c.id = ? AND c.status = 'active'
        ");
        $stmt->execute([$id]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$category) {
            jsonError('Category not found', 404);
        }
        
        $category['image'] = $category['image'] ? '/uploads/categories/' . $category['image'] : null;
        
        // Get subcategories
        $subStmt = $conn->prepare("SELECT id, name, slug, image FROM categories WHERE parent_id = ? AND status = 'active'");
        $subStmt->execute([$id]);
        $category['subcategories'] = $subStmt->fetchAll(PDO::FETCH_ASSOC);
        
        jsonSuccess($category);
        
    } catch (Exception $e) {
        jsonError('Failed to fetch category');
    }
}
