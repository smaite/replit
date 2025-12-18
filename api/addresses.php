<?php
/**
 * User Addresses API - Add/Edit/Delete/List user addresses
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

// Get user from auth header
$user = getAuthUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}
$user_id = $user['id'];

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get user's addresses
            $stmt = $conn->prepare("
                SELECT id, address_type, full_name, phone, address_line1, address_line2,
                       city, state, postal_code, country, is_default, created_at
                FROM user_addresses
                WHERE user_id = ?
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$user_id]);
            $addresses = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'data' => $addresses,
                'count' => count($addresses)
            ]);
            break;
            
        case 'POST':
            // Add new address
            $input = json_decode(file_get_contents('php://input'), true);
            
            $full_name = trim($input['full_name'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $address_line1 = trim($input['address_line1'] ?? '');
            $address_line2 = trim($input['address_line2'] ?? '');
            $city = trim($input['city'] ?? '');
            $state = trim($input['state'] ?? '');
            $postal_code = trim($input['postal_code'] ?? '');
            $country = trim($input['country'] ?? 'Nepal');
            $address_type = $input['address_type'] ?? 'home';
            $is_default = (bool)($input['is_default'] ?? false);
            
            if (empty($full_name) || empty($phone) || empty($address_line1) || empty($city)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name, phone, address, and city are required']);
                exit;
            }
            
            // If setting as default, unset other defaults
            if ($is_default) {
                $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
            }
            
            // Check if this is first address, make it default
            $stmt = $conn->prepare("SELECT COUNT(*) FROM user_addresses WHERE user_id = ?");
            $stmt->execute([$user_id]);
            if ($stmt->fetchColumn() == 0) {
                $is_default = true;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO user_addresses (user_id, address_type, full_name, phone, address_line1, address_line2, city, state, postal_code, country, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $address_type, $full_name, $phone, $address_line1, $address_line2, $city, $state, $postal_code, $country, $is_default ? 1 : 0]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Address added successfully',
                'address_id' => $conn->lastInsertId()
            ]);
            break;
            
        case 'PUT':
            // Update existing address
            $input = json_decode(file_get_contents('php://input'), true);
            $address_id = (int)($input['id'] ?? 0);
            
            if (!$address_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Address ID required']);
                exit;
            }
            
            // Verify ownership
            $stmt = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Address not found']);
                exit;
            }
            
            $updates = [];
            $params = [];
            
            if (isset($input['full_name'])) { $updates[] = 'full_name = ?'; $params[] = trim($input['full_name']); }
            if (isset($input['phone'])) { $updates[] = 'phone = ?'; $params[] = trim($input['phone']); }
            if (isset($input['address_line1'])) { $updates[] = 'address_line1 = ?'; $params[] = trim($input['address_line1']); }
            if (isset($input['address_line2'])) { $updates[] = 'address_line2 = ?'; $params[] = trim($input['address_line2']); }
            if (isset($input['city'])) { $updates[] = 'city = ?'; $params[] = trim($input['city']); }
            if (isset($input['state'])) { $updates[] = 'state = ?'; $params[] = trim($input['state']); }
            if (isset($input['postal_code'])) { $updates[] = 'postal_code = ?'; $params[] = trim($input['postal_code']); }
            if (isset($input['country'])) { $updates[] = 'country = ?'; $params[] = trim($input['country']); }
            if (isset($input['address_type'])) { $updates[] = 'address_type = ?'; $params[] = $input['address_type']; }
            
            if (isset($input['is_default']) && $input['is_default']) {
                // Unset other defaults first
                $conn->prepare("UPDATE user_addresses SET is_default = 0 WHERE user_id = ?")->execute([$user_id]);
                $updates[] = 'is_default = 1';
            }
            
            if (empty($updates)) {
                echo json_encode(['success' => true, 'message' => 'No changes']);
                exit;
            }
            
            $params[] = $address_id;
            $stmt = $conn->prepare("UPDATE user_addresses SET " . implode(', ', $updates) . " WHERE id = ?");
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Address updated']);
            break;
            
        case 'DELETE':
            // Delete address
            $address_id = (int)($_GET['id'] ?? 0);
            
            if (!$address_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Address ID required']);
                exit;
            }
            
            // Verify ownership and delete
            $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = ? AND user_id = ?");
            $stmt->execute([$address_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Address deleted']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Address not found']);
            }
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
