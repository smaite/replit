<?php
/**
 * Reviews API - Add/List product reviews with images
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once '../config/database.php';
require_once 'config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API key verification is handled by config.php

$method = $_SERVER['REQUEST_METHOD'];

try {
    // Create reviews table if not exists
    $conn->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Create review images table
    $conn->exec("
        CREATE TABLE IF NOT EXISTS review_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            review_id INT NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
        )
    ");
    
    switch ($method) {
        case 'GET':
            // Get reviews for a product
            $product_id = (int)($_GET['product_id'] ?? 0);
            
            if (!$product_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Product ID required']);
                exit;
            }
            
            // Get reviews with user info and images
            $stmt = $conn->prepare("
                SELECT r.id, r.rating, r.comment, r.created_at,
                       u.id as user_id, u.full_name as user_name
                FROM reviews r
                JOIN users u ON r.user_id = u.id
                WHERE r.product_id = ? AND r.status = 'approved'
                ORDER BY r.created_at DESC
            ");
            $stmt->execute([$product_id]);
            $reviews = $stmt->fetchAll();
            
            // Get images for each review
            foreach ($reviews as &$review) {
                $stmt = $conn->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                $stmt->execute([$review['id']]);
                $review['images'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Calculate averages
            $stmt = $conn->prepare("
                SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                FROM reviews 
                WHERE product_id = ? AND status = 'approved'
            ");
            $stmt->execute([$product_id]);
            $stats = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'average_rating' => round($stats['avg_rating'], 1),
                    'total_reviews' => (int)$stats['total_reviews']
                ]
            ]);
            break;
            
        case 'POST':
            // Add a review (requires auth)
            $user = getAuthUser();
            if (!$user) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Authentication required']);
                exit;
            }
            $user_id = $user['id'];
            
            // Handle multipart form data for image uploads
            $product_id = (int)($_POST['product_id'] ?? 0);
            $rating = (int)($_POST['rating'] ?? 0);
            $comment = trim($_POST['comment'] ?? '');
            
            if (!$product_id || $rating < 1 || $rating > 5) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Product ID and valid rating (1-5) required']);
                exit;
            }
            
            // Check if user already reviewed this product
            $stmt = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
            $stmt->execute([$product_id, $user_id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'You have already reviewed this product']);
                exit;
            }
            
            // Check if user has purchased this product
            $stmt = $conn->prepare("
                SELECT oi.id FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
                LIMIT 1
            ");
            $stmt->execute([$user_id, $product_id]);
            $has_purchased = $stmt->fetch();
            
            // Insert review
            $stmt = $conn->prepare("
                INSERT INTO reviews (product_id, user_id, rating, comment) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$product_id, $user_id, $rating, $comment]);
            $review_id = $conn->lastInsertId();
            
            // Handle image uploads
            $uploaded_images = [];
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = '../uploads/reviews/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_type = $_FILES['images']['type'][$key];
                        if (in_array($file_type, ['image/jpeg', 'image/png', 'image/webp'])) {
                            $filename = 'review_' . $review_id . '_' . uniqid() . '.' . pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                            if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                                $image_path = '/uploads/reviews/' . $filename;
                                $stmt = $conn->prepare("INSERT INTO review_images (review_id, image_path) VALUES (?, ?)");
                                $stmt->execute([$review_id, $image_path]);
                                $uploaded_images[] = $image_path;
                            }
                        }
                    }
                }
            }
            
            // Update product average rating
            $stmt = $conn->prepare("
                UPDATE products SET 
                    rating = (SELECT AVG(rating) FROM reviews WHERE product_id = ? AND status = 'approved'),
                    rating_count = (SELECT COUNT(*) FROM reviews WHERE product_id = ? AND status = 'approved')
                WHERE id = ?
            ");
            $stmt->execute([$product_id, $product_id, $product_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Review submitted successfully',
                'review_id' => $review_id,
                'images' => $uploaded_images,
                'verified_purchase' => (bool)$has_purchased
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
