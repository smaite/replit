<?php
/**
 * eSewa Payment Gateway Integration
 * For SASTO Hub E-commerce
 * 
 * Documentation: https://developer.esewa.com.np
 */

// eSewa Configuration
define('ESEWA_MERCHANT_CODE', 'EPAYTEST'); // Replace with your merchant code
define('ESEWA_SECRET_KEY', '8gBm/:&EnhH.1/q'); // Replace with your secret key

// eSewa URLs (Sandbox for testing)
define('ESEWA_SANDBOX', true);

if (ESEWA_SANDBOX) {
    define('ESEWA_PAYMENT_URL', 'https://rc-epay.esewa.com.np/api/epay/main/v2/form');
    define('ESEWA_VERIFY_URL', 'https://rc.esewa.com.np/api/epay/transaction/status/');
} else {
    // Production URLs
    define('ESEWA_PAYMENT_URL', 'https://epay.esewa.com.np/api/epay/main/v2/form');
    define('ESEWA_VERIFY_URL', 'https://esewa.com.np/api/epay/transaction/status/');
}

/**
 * Generate HMAC signature for eSewa payment (V2 API)
 * 
 * @param string $total_amount Total amount including taxes
 * @param string $transaction_uuid Unique transaction ID
 * @param string $product_code Merchant product code
 * @return string Base64 encoded HMAC signature
 */
function generateEsewaSignature($total_amount, $transaction_uuid, $product_code = ESEWA_MERCHANT_CODE) {
    $message = "total_amount={$total_amount},transaction_uuid={$transaction_uuid},product_code={$product_code}";
    $signature = base64_encode(hash_hmac('sha256', $message, ESEWA_SECRET_KEY, true));
    return $signature;
}

/**
 * Initiate eSewa Payment
 * 
 * @param int $orderId Order ID from database
 * @param float $amount Payment amount
 * @param float $taxAmount Tax amount (default 0)
 * @param float $productServiceCharge Service charge (default 0)
 * @param float $productDeliveryCharge Delivery charge
 * @return array Form data for eSewa submission
 */
function initiateEsewaPayment($orderId, $amount, $taxAmount = 0, $productServiceCharge = 0, $productDeliveryCharge = 0, $callbackPath = null) {
    $transactionUuid = 'SH-' . $orderId . '-' . time();
    $totalAmount = $amount + $taxAmount + $productServiceCharge + $productDeliveryCharge;
    
    $signature = generateEsewaSignature($totalAmount, $transactionUuid);
    
    // Get current domain for callback URLs
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') 
               . '://' . $_SERVER['HTTP_HOST'];
    
    // Auto-detect callback path based on current request
    if ($callbackPath === null) {
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($currentPath, '/mobile/') !== false) {
            $callbackPath = '/mobile';
        } else {
            $callbackPath = '/pages';
        }
    }
    
    $formData = [
        'amount' => $amount,
        'tax_amount' => $taxAmount,
        'total_amount' => $totalAmount,
        'transaction_uuid' => $transactionUuid,
        'product_code' => ESEWA_MERCHANT_CODE,
        'product_service_charge' => $productServiceCharge,
        'product_delivery_charge' => $productDeliveryCharge,
        'success_url' => $baseUrl . $callbackPath . '/esewa-success.php',
        'failure_url' => $baseUrl . $callbackPath . '/esewa-failure.php',
        'signed_field_names' => 'total_amount,transaction_uuid,product_code',
        'signature' => $signature
    ];
    
    return [
        'form_url' => ESEWA_PAYMENT_URL,
        'form_data' => $formData,
        'transaction_uuid' => $transactionUuid
    ];
}

/**
 * Verify eSewa Payment
 * 
 * @param string $transactionUuid Transaction UUID
 * @param float $totalAmount Total amount paid
 * @return array Verification result
 */
function verifyEsewaPayment($transactionUuid, $totalAmount) {
    $url = ESEWA_VERIFY_URL . '?product_code=' . ESEWA_MERCHANT_CODE 
           . '&total_amount=' . $totalAmount 
           . '&transaction_uuid=' . $transactionUuid;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return [
            'success' => ($result['status'] ?? '') === 'COMPLETE',
            'data' => $result,
            'message' => $result['status'] ?? 'Unknown status'
        ];
    }
    
    return [
        'success' => false,
        'data' => null,
        'message' => 'Verification failed'
    ];
}

/**
 * Decode eSewa response data
 * 
 * @param string $data Base64 encoded response data
 * @return array|null Decoded data
 */
function decodeEsewaResponse($data) {
    $decoded = base64_decode($data);
    if ($decoded) {
        return json_decode($decoded, true);
    }
    return null;
}
