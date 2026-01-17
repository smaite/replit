<?php
/**
 * Settings API - Get public settings (payment methods, etc.)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Fetch all settings
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $db_settings = $stmt->fetchAll();
    
    $settings = [];
    foreach ($db_settings as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Public settings to expose (only what mobile app needs)
    $public_settings = [
        // Payment methods
        'payment_methods' => [
            'cod' => [
                'enabled' => ($settings['payment_cod_enabled'] ?? '1') === '1',
                'label' => 'Cash on Delivery',
                'description' => 'Pay when you receive'
            ],
            'esewa' => [
                'enabled' => ($settings['payment_esewa_enabled'] ?? '0') === '1',
                'label' => 'eSewa',
                'description' => 'Pay with eSewa wallet'
            ],
            'qr' => [
                'enabled' => ($settings['payment_qr_enabled'] ?? '0') === '1',
                'label' => 'QR Payment',
                'description' => 'Scan and pay',
                'qr_image' => !empty($settings['payment_qr_image']) 
                    ? (str_starts_with($settings['payment_qr_image'], 'http') 
                        ? $settings['payment_qr_image'] 
                        : 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($settings['payment_qr_image'], '/'))
                    : '',
                'instructions' => $settings['payment_qr_instructions'] ?? ''
            ]
        ],
        // Website info
        'website_name' => $settings['website_name'] ?? 'Sasto Hub',
        'contact_phone' => $settings['contact_phone'] ?? '',
        'contact_email' => $settings['contact_email'] ?? ''
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $public_settings
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch settings'
    ]);
}
