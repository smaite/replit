<?php
/**
 * Sasto Hub API Configuration
 * Secure API with API key authentication
 */

// Start session for user auth
session_start();

// Prevent direct browser access - API only
header('Content-Type: application/json; charset=utf-8');

// CORS Headers - Allow from any origin for mobile app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// API SECURITY CONFIGURATION
// ============================================

// Secret API Key - CHANGE THIS TO YOUR OWN SECRET!
define('API_SECRET_KEY', 'SH_API_2024_x8K9mN2pQ5rT7wY3'); // Generate your own random key

// API Key header name
define('API_KEY_HEADER', 'X-API-Key');

// Rate limiting (requests per minute per IP)
define('RATE_LIMIT', 60);
define('RATE_LIMIT_WINDOW', 60); // seconds

// ============================================
// DATABASE CONNECTION
// ============================================
require_once dirname(__DIR__) . '/config/database.php';

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Validate API Key from request header
 * @return bool
 */
function validateApiKey() {
    // Allow if user is logged in via session (for website frontend)
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    $headers = getallheaders();
    $apiKey = $headers[API_KEY_HEADER] ?? $headers[strtolower(API_KEY_HEADER)] ?? null;
    
    if (!$apiKey || $apiKey !== API_SECRET_KEY) {
        jsonResponse(['error' => 'Unauthorized'], 401);
        return false;
    }
    return true;
}

/**
 * Simple rate limiting by IP
 * @return bool
 */
function checkRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $cacheFile = sys_get_temp_dir() . '/api_rate_' . md5($ip) . '.json';
    
    $data = ['count' => 0, 'reset' => time() + RATE_LIMIT_WINDOW];
    
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if ($data['reset'] < time()) {
            $data = ['count' => 0, 'reset' => time() + RATE_LIMIT_WINDOW];
        }
    }
    
    $data['count']++;
    file_put_contents($cacheFile, json_encode($data));
    
    if ($data['count'] > RATE_LIMIT) {
        jsonResponse(['error' => 'Rate limit exceeded. Try again later.'], 429);
        return false;
    }
    
    return true;
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send success response
 */
function jsonSuccess($data, $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Send error response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message
    ], $statusCode);
}

/**
 * Get JSON body from POST request
 */
function getJsonBody() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            jsonError("Missing required field: $field");
        }
    }
}

/**
 * Get authenticated user from token
 */
function getAuthUser() {
    global $conn;
    
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    // Debug: Log auth header
    error_log("Auth Header: " . substr($authHeader, 0, 50) . "...");
    
    if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = $matches[1];
        error_log("Token extracted: " . substr($token, 0, 20) . "...");
        
        try {
            // Don't require active status - check any user with this token
            $stmt = $conn->prepare("SELECT * FROM users WHERE api_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                error_log("User found: " . $user['email']);
                return $user;
            } else {
                error_log("No user found with this token");
            }
        } catch (Exception $e) {
            error_log("Auth error: " . $e->getMessage());
        }
    } else {
        error_log("No Bearer token found in header");
        
        // Fallback to session auth
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    return $user;
                }
            } catch (Exception $e) {
                error_log("Session auth error: " . $e->getMessage());
            }
        }
    }
    
    return null;
}

/**
 * Require authenticated user
 */
function requireAuth() {
    $user = getAuthUser();
    if (!$user) {
        jsonError('Authentication required. Please login.', 401);
    }
    return $user;
}

/**
 * Generate secure API token for user
 */
function generateApiToken() {
    return bin2hex(random_bytes(32));
}

// ============================================
// VALIDATE API KEY ON EVERY REQUEST
// ============================================
if (!validateApiKey()) {
    exit;
}

if (!checkRateLimit()) {
    exit;
}
