<?php
/**
 * Google Authentication API for Flutter App
 * Handles Google Sign-In token verification
 */

// CORS Headers MUST come first, before anything else
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Accept');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include config after CORS headers
require_once dirname(__DIR__) . '/config/database.php';

// API Key validation
$headers = getallheaders();
$apiKey = $headers['X-API-Key'] ?? $headers['x-api-key'] ?? null;
$expectedKey = 'SH_API_2024_x8K9mN2pQ5rT7wY3';

if (!$apiKey || $apiKey !== $expectedKey) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Invalid API key']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get request data
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?: $_POST;

// Log for debugging
error_log("Google Auth Request: " . $rawInput);

$email = $data['email'] ?? '';
$name = $data['name'] ?? '';
$googleId = $data['google_id'] ?? '';

if (empty($googleId) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Google ID and email are required']);
    exit;
}

try {
    // Check if google_id column exists, if not create it
    try {
        $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'google_id'");
        if ($checkColumn->rowCount() == 0) {
            $conn->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER email");
            $conn->exec("ALTER TABLE users ADD INDEX idx_google_id (google_id)");
        }
    } catch (Exception $e) {
        // Column might already exist, continue
    }
    
    // Check if user already exists with this Google ID or email
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
    $stmt->execute([$googleId, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Update existing user's Google ID if not set
        if (empty($user['google_id'])) {
            $updateStmt = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $updateStmt->execute([$googleId, $user['id']]);
        }
        
        // Generate API token
        $apiToken = bin2hex(random_bytes(32));
        $updateToken = $conn->prepare("UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?");
        $updateToken->execute([$apiToken, $user['id']]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'email' => $user['email'],
                    'name' => $user['full_name'],
                    'phone' => $user['phone'] ?? '',
                    'address' => $user['address'] ?? '',
                ],
                'token' => $apiToken,
            ]
        ]);
    } else {
        // Create new user from Google data
        $stmt = $conn->prepare("INSERT INTO users (email, full_name, google_id, password, role, status, created_at) VALUES (?, ?, ?, ?, 'user', 'active', NOW())");
        
        // Generate a random password (user won't use it for Google login)
        $randomPassword = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $stmt->execute([$email, $name, $googleId, $randomPassword]);
        $userId = $conn->lastInsertId();
        
        // Generate API token
        $apiToken = bin2hex(random_bytes(32));
        $updateToken = $conn->prepare("UPDATE users SET api_token = ?, last_login = NOW() WHERE id = ?");
        $updateToken->execute([$apiToken, $userId]);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => (int)$userId,
                    'email' => $email,
                    'name' => $name,
                    'phone' => '',
                    'address' => '',
                ],
                'token' => $apiToken,
            ]
        ]);
    }
} catch (Exception $e) {
    error_log("Google Auth Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
