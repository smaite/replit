<?php

/**
 * Seller API - For vendor product and order management in mobile app
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

require_once '../config/database.php';
require_once 'config.php';

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// API key verification is handled by config.php

// Get user from auth header - must be a vendor
$user = getAuthUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}
$user_id = $user['id'];

// Check if user is a vendor
$stmt = $conn->prepare("SELECT id FROM vendors WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$user_id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Vendor access required']);
    exit;
}

$vendor_id = $vendor['id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'dashboard';

try {
    switch ($action) {
        case 'dashboard':
            // Get vendor dashboard stats
            $stats = [];

            // Total products
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
            $stmt->execute([$vendor_id]);
            $stats['total_products'] = (int)$stmt->fetchColumn();

            // Active products
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND status = 'active'");
            $stmt->execute([$vendor_id]);
            $stats['active_products'] = (int)$stmt->fetchColumn();

            // Total orders (items from vendor)
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT o.id) 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.vendor_id = ?
            ");
            $stmt->execute([$vendor_id]);
            $stats['total_orders'] = (int)$stmt->fetchColumn();

            // Pending orders
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT o.id) 
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE p.vendor_id = ? AND o.status = 'pending'
            ");
            $stmt->execute([$vendor_id]);
            $stats['pending_orders'] = (int)$stmt->fetchColumn();

            // Total revenue
            $stmt = $conn->prepare("
                SELECT COALESCE(SUM(oi.quantity * oi.price), 0)
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN orders o ON oi.order_id = o.id
                WHERE p.vendor_id = ? AND o.status IN ('delivered', 'processing', 'shipped')
            ");
            $stmt->execute([$vendor_id]);
            $stats['total_revenue'] = round((float)$stmt->fetchColumn(), 2);

            // Recent orders
            $stmt = $conn->prepare("
                SELECT DISTINCT o.id, o.order_number, o.status, o.created_at,
                       u.full_name as customer_name,
                       SUM(oi.quantity * oi.price) as order_total
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                WHERE p.vendor_id = ?
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$vendor_id]);
            $stats['recent_orders'] = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        case 'products':
            if ($method === 'GET') {
                // List vendor's products
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = 20;
                $offset = ($page - 1) * $limit;

                $stmt = $conn->prepare("
                    SELECT p.*, c.name as category_name,
                           (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as image
                    FROM products p
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE p.vendor_id = ?
                    ORDER BY p.created_at DESC
                    LIMIT $limit OFFSET $offset
                ");
                $stmt->execute([$vendor_id]);
                $products = $stmt->fetchAll();

                echo json_encode(['success' => true, 'data' => $products]);
            } elseif ($method === 'POST' || $method === 'PUT') {
                // Handle Create/Update
                // For PUT requests, parse JSON body
                $inputData = $_POST;
                if ($method === 'PUT') {
                    $jsonInput = json_decode(file_get_contents('php://input'), true);
                    if ($jsonInput) {
                        $inputData = array_merge($inputData, $jsonInput);
                    }
                }
                
                $product_id = (int)($inputData['product_id'] ?? 0);

                // Validation
                if (empty($inputData['name']) || empty($inputData['price'])) {
                    echo json_encode(['success' => false, 'error' => 'Name and Price are required']);
                    exit;
                }

                // Generate slug
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', trim($inputData['name'])), '-'));
                $slug = $slug . '-' . time(); // Ensure uniqueness

                $data = [
                    'vendor_id' => $vendor_id,
                    'name' => trim($inputData['name']),
                    'slug' => $slug,
                    'description' => trim($inputData['description'] ?? ''),
                    'price' => (float)$inputData['price'],
                    'sale_price' => !empty($inputData['sale_price']) ? (float)$inputData['sale_price'] : null,
                    'category_id' => (int)$inputData['category_id'],
                    'brand_id' => !empty($inputData['brand_id']) ? (int)$inputData['brand_id'] : null,
                    'stock' => (int)($inputData['stock'] ?? 0),
                    'sku' => trim($inputData['sku'] ?? ''),
                    'tags' => trim($inputData['tags'] ?? ''),
                    'condition' => $inputData['condition'] ?? 'new',
                    'flash_sale_eligible' => (int)($inputData['flash_sale_eligible'] ?? 0),
                    'shipping_weight' => !empty($inputData['shipping_weight']) ? (float)$inputData['shipping_weight'] : null,
                    'handling_days' => !empty($inputData['handling_days']) ? (int)$inputData['handling_days'] : null,
                    'shipping_profile_id' => !empty($inputData['shipping_profile_id']) ? (int)$inputData['shipping_profile_id'] : null,
                    'free_shipping' => (int)($inputData['free_shipping'] ?? 0),
                    'return_policy_id' => !empty($inputData['return_policy_id']) ? (int)$inputData['return_policy_id'] : null,
                    'video_url' => trim($inputData['video_url'] ?? ''),
                    'featured' => (int)($inputData['is_featured'] ?? 0),
                    'dimensions_length' => !empty($inputData['length']) ? (float)$inputData['length'] : null,
                    'dimensions_width' => !empty($inputData['width']) ? (float)$inputData['width'] : null,
                    'dimensions_height' => !empty($inputData['height']) ? (float)$inputData['height'] : null,
                    'bullet_points' => !empty($inputData['bullet_points']) ? $inputData['bullet_points'] : null,
                    'status' => 'active',
                    'verification_status' => 'pending'
                ];

                if ($product_id > 0) {
                    // UPDATE
                    // Verify ownership
                    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
                    $stmt->execute([$product_id, $vendor_id]);
                    if (!$stmt->fetch()) {
                        http_response_code(404);
                        echo json_encode(['success' => false, 'error' => 'Product not found']);
                        exit;
                    }

                    $fields = [];
                    $values = [];
                    foreach ($data as $key => $value) {
                        $fields[] = "$key = ?";
                        $values[] = $value;
                    }
                    $values[] = $product_id;

                    $stmt = $conn->prepare("UPDATE products SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?");
                    $stmt->execute($values);
                    $message = 'Product updated successfully';
                } else {
                    // INSERT
                    $columns = implode(', ', array_keys($data));
                    $placeholders = implode(', ', array_fill(0, count($data), '?'));
                    $stmt = $conn->prepare("INSERT INTO products ($columns, created_at, updated_at) VALUES ($placeholders, NOW(), NOW())");
                    $stmt->execute(array_values($data));
                    $product_id = $conn->lastInsertId();
                    $message = 'Product added successfully';
                }

                // Handle Variants
                if (!empty($inputData['variants'])) {
                    $variants = json_decode($inputData['variants'], true);
                    if (is_array($variants)) {
                        $stmt = $conn->prepare("DELETE FROM product_variants WHERE product_id = ?");
                        $stmt->execute([$product_id]);

                        $stmt = $conn->prepare("INSERT INTO product_variants (product_id, color, size, price, stock, sku) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($variants as $v) {
                            $stmt->execute([
                                $product_id,
                                $v['color'] ?? null,
                                $v['size'] ?? null,
                                $v['price'] ?? 0,
                                $v['stock'] ?? 0,
                                $v['sku'] ?? null
                            ]);
                        }
                    }
                }

                // Handle Images
                if (!empty($_FILES['images']['name'][0])) {
                    $uploadDir = '../uploads/products/';
                    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

                    $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");

                    $check = $conn->prepare("SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_primary = 1");
                    $check->execute([$product_id]);
                    $hasPrimary = $check->fetchColumn() > 0;

                    foreach ($_FILES['images']['name'] as $key => $name) {
                        if ($_FILES['images']['error'][$key] === 0) {
                            $ext = pathinfo($name, PATHINFO_EXTENSION);
                            $filename = uniqid() . '.' . $ext;
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $uploadDir . $filename)) {
                                $isPrimary = !$hasPrimary && $key === 0 ? 1 : 0;
                                $stmt->execute([$product_id, 'uploads/products/' . $filename, $isPrimary]);
                            }
                        }
                    }
                }

                echo json_encode(['success' => true, 'message' => $message, 'product_id' => $product_id]);
            } elseif ($method === 'DELETE') {
                // Delete product
                $product_id = (int)($_GET['product_id'] ?? 0);

                // Verify ownership
                $stmt = $conn->prepare("SELECT id FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $vendor_id]);
                
                if ($stmt->fetch()) {
                    try {
                        // Try hard delete
                        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        echo json_encode(['success' => true, 'message' => 'Product deleted']);
                    } catch (PDOException $e) {
                        // If foreign key constraint fails, soft delete
                        if ($e->getCode() == '23000') {
                            $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
                            $stmt->execute([$product_id]);
                            echo json_encode(['success' => true, 'message' => 'Product archived (has existing orders)']);
                        } else {
                            http_response_code(500);
                            echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()]);
                        }
                    }
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Product not found']);
                }
            }
            break;

        case 'orders':
            // Get vendor's orders
            $status = $_GET['status'] ?? null;
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $where = "p.vendor_id = ?";
            $params = [$vendor_id];

            if ($status) {
                $where .= " AND o.status = ?";
                $params[] = $status;
            }

            $stmt = $conn->prepare("
                SELECT DISTINCT o.id, o.order_number, o.status, o.payment_method, o.payment_status,
                       o.created_at,
                       u.full_name as customer_name,
                       SUM(oi.quantity * oi.price) as vendor_total,
                       COUNT(oi.id) as item_count
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON o.user_id = u.id
                WHERE $where
                GROUP BY o.id
                ORDER BY o.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();

            // Mask customer details for privacy - vendors shouldn't contact customers directly
            foreach ($orders as &$order) {
                $nameParts = explode(' ', $order['customer_name'] ?? '');
                $firstName = $nameParts[0] ?? '';
                $lastInitial = isset($nameParts[1]) ? strtoupper($nameParts[1][0]) . '.' : '';
                $order['customer_name'] = $firstName . ' ' . $lastInitial;
                // Don't expose email, phone, or full address to vendors
                unset($order['customer_email']);
                unset($order['shipping_phone']);
                unset($order['shipping_address']);
            }

            echo json_encode(['success' => true, 'data' => $orders]);
            break;

        case 'order-detail':
            // Get order detail with vendor's items
            $order_id = (int)($_GET['order_id'] ?? 0);

            // Get order info
            $stmt = $conn->prepare("
                SELECT o.*, u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone
                FROM orders o
                JOIN users u ON o.user_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit;
            }

            // Mask customer details for privacy
            $nameParts = explode(' ', $order['customer_name'] ?? '');
            $firstName = $nameParts[0] ?? '';
            $lastInitial = isset($nameParts[1]) ? strtoupper($nameParts[1][0]) . '.' : '';
            $order['customer_name'] = $firstName . ' ' . $lastInitial;
            unset($order['customer_email']);
            unset($order['customer_phone']);
            // Only show city for shipping, not full address
            if (!empty($order['shipping_address'])) {
                $order['shipping_area'] = 'Delivery Area'; // Vendors just need to know there's a delivery
            }
            unset($order['shipping_address']);
            unset($order['shipping_phone']);

            // Get vendor's items only (with image from product_images table)
            $stmt = $conn->prepare("
                SELECT oi.*, p.name, 
                       (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) as image
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ? AND p.vendor_id = ?
            ");
            $stmt->execute([$order_id, $vendor_id]);
            $items = $stmt->fetchAll();

            if (empty($items)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'No items from your store in this order']);
                exit;
            }

            $order['items'] = $items;
            $order['vendor_total'] = array_sum(array_map(fn($i) => $i['quantity'] * $i['price'], $items));

            echo json_encode(['success' => true, 'data' => $order]);
            break;

        case 'categories':
            // Get available categories
            $stmt = $conn->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
            $stmt->execute();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'form-data':
            // Get all data needed for add/edit product form
            $data = [];

            // Categories
            $stmt = $conn->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
            $stmt->execute();
            $data['categories'] = $stmt->fetchAll();

            // Brands
            $stmt = $conn->prepare("SELECT id, name FROM brands WHERE status = 'active' ORDER BY name");
            $stmt->execute();
            $data['brands'] = $stmt->fetchAll();

            // Shipping Profiles
            $stmt = $conn->prepare("SELECT id, name FROM shipping_profiles WHERE vendor_id = ? OR vendor_id IS NULL ORDER BY name");
            $stmt->execute([$vendor_id]);
            $data['shipping_profiles'] = $stmt->fetchAll();

            // Return Policies
            $stmt = $conn->prepare("SELECT id, name, days FROM return_policies WHERE vendor_id = ? OR vendor_id IS NULL ORDER BY days");
            $stmt->execute([$vendor_id]);
            $data['return_policies'] = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
