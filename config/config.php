<?php
// Load PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

// General Configuration
define('SITE_NAME', 'SASTO Hub');
define('SITE_URL', 'http://localhost/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    session_start();
}

// Prevent caching to avoid login/logout state issues
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Google OAuth Configuration (Optional - add your credentials)
define('GOOGLE_CLIENT_ID', '1071187412538-1iucic7t0h2pdrn4mkubohogg7dgk47e.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google-callback.php');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_VENDOR', 'vendor');
define('ROLE_CUSTOMER', 'customer');

// CSRF Protection
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_verified']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function isAdmin() {
    return isLoggedIn() && getUserRole() === ROLE_ADMIN;
}

function isVendor() {
    return isLoggedIn() && getUserRole() === ROLE_VENDOR;
}

function requireAuth() {
    if (!isLoggedIn()) {
        redirect('/auth/login.php');
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        redirect('/');
    }
}

function requireVendor() {
    requireAuth();
    if (!isVendor()) {
        redirect('/');
    }
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function formatPrice($price) {
    return 'Rs. ' . number_format($price, 2);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateInput($data, $type = 'text', $maxLength = 255) {
    $data = trim($data);
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL) ? $data : false;
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT) !== false ? (int)$data : false;
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT) !== false ? (float)$data : false;
        case 'text':
        default:
            $data = strip_tags($data);
            return strlen($data) <= $maxLength ? $data : false;
    }
}

// Website Settings Management
function getWebsiteSettings() {
    $settings_file = __DIR__ . '/settings.json';
    
    if (file_exists($settings_file)) {
        return json_decode(file_get_contents($settings_file), true) ?? getDefaultSettings();
    }
    
    return getDefaultSettings();
}

function getDefaultSettings() {
    return [
        'website_name' => 'SASTO Hub',
        'website_tagline' => 'Your Online Marketplace',
        'header_logo' => '/assets/images/logo.png',
        'footer_logo' => '/assets/images/logo.png',
        'footer_name' => 'SASTO Hub',
        'copyright_text' => '© 2025 SASTO Hub. All rights reserved.',
        'primary_color' => '#4f46e5',
        'contact_email' => 'info@sastohub.com',
        'contact_phone' => '+977 1234567890',
        'address' => 'Kathmandu, Nepal',
        'facebook_url' => '',
        'twitter_url' => '',
        'instagram_url' => '',
        'youtube_url' => ''
    ];
}

function getSetting($key, $default = null) {
    $settings = getWebsiteSettings();
    return $settings[$key] ?? $default;
}

function saveWebsiteSettings($settings) {
    $settings_file = __DIR__ . '/settings.json';
    return file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}
?>