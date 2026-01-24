<?php
require_once '../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['lat']) || !isset($input['lng'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing coordinates']);
    exit;
}

$lat = floatval($input['lat']);
$lng = floatval($input['lng']);

// Reverse geocode using OpenStreetMap Nominatim (free, no API key needed)
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lng}&zoom=10&addressdetails=1";

$opts = [
    'http' => [
        'method' => 'GET',
        'header' => 'User-Agent: SASTOHub/1.0'
    ]
];
$context = stream_context_create($opts);

try {
    $json = file_get_contents($url, false, $context);
    $data = json_decode($json, true);
    
    $city = null;
    
    if ($data && isset($data['address'])) {
        $addr = $data['address'];
        // Try different fields for city name
        $city = $addr['city'] 
            ?? $addr['town'] 
            ?? $addr['municipality'] 
            ?? $addr['village'] 
            ?? $addr['county'] 
            ?? $addr['state_district']
            ?? null;
    }
    
    if ($city) {
        $_SESSION['detected_city'] = $city;
        $_SESSION['geo_source'] = 'browser';
        echo json_encode(['success' => true, 'city' => $city]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Could not determine city', 'raw' => $data]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
