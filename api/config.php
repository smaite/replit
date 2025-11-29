<?php
/**
 * SASTO Hub API Configuration
 * Centralized configuration for API endpoints
 */

// Headers for API responses
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database configuration
require_once __DIR__ . '/../config/database.php';

// Response formatter
class ApiResponse {
    public static function success($data, $message = 'Success', $code = 200) {
        http_response_code($code);
        return json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public static function error($message = 'Error', $code = 400, $errors = null) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

// Request helper
class ApiRequest {
    public static function getParameter($name, $default = null) {
        if (isset($_GET[$name])) {
            return htmlspecialchars($_GET[$name], ENT_QUOTES, 'UTF-8');
        }
        if (isset($_POST[$name])) {
            return htmlspecialchars($_POST[$name], ENT_QUOTES, 'UTF-8');
        }
        return $default;
    }

    public static function getJsonInput() {
        $input = file_get_contents('php://input');
        return json_decode($input, true);
    }
}
