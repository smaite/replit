<?php
/**
 * eSewa Payment Migration
 * Adds payment_method and transaction_id columns to orders table
 */
require_once 'config/database.php';

echo "<h2>eSewa Payment Migration</h2>";

try {
    // Add payment_method column
    try {
        $conn->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(50) DEFAULT 'cod' AFTER shipping_address");
        echo "✅ Added payment_method column<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ payment_method column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Add transaction_id column for eSewa/online payments
    try {
        $conn->exec("ALTER TABLE orders ADD COLUMN transaction_id VARCHAR(100) NULL AFTER payment_method");
        echo "✅ Added transaction_id column<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "ℹ️ transaction_id column already exists<br>";
        } else {
            throw $e;
        }
    }
    
    // Update existing orders to have 'cod' as payment method
    $conn->exec("UPDATE orders SET payment_method = 'cod' WHERE payment_method IS NULL OR payment_method = ''");
    echo "✅ Updated existing orders with default payment method<br>";
    
    echo "<br><strong style='color: green;'>✅ Migration complete!</strong>";
    echo "<br><br><strong>eSewa Integration is now ready.</strong>";
    echo "<br><br>Files created:";
    echo "<ul>";
    echo "<li><code>/includes/esewa.php</code> - eSewa helper functions</li>";
    echo "<li><code>/mobile/esewa-pay.php</code> - Payment initiation page</li>";
    echo "<li><code>/mobile/esewa-success.php</code> - Success callback</li>";
    echo "<li><code>/mobile/esewa-failure.php</code> - Failure callback</li>";
    echo "<li><code>/mobile/checkout.php</code> - Updated with eSewa option</li>";
    echo "</ul>";
    
    echo "<br><strong>Important:</strong> Update the merchant credentials in <code>/includes/esewa.php</code>:";
    echo "<pre style='background:#f5f5f5;padding:10px;border-radius:5px;'>";
    echo "define('ESEWA_MERCHANT_CODE', 'YOUR_MERCHANT_CODE');\n";
    echo "define('ESEWA_SECRET_KEY', 'YOUR_SECRET_KEY');\n";
    echo "define('ESEWA_SANDBOX', false); // Set to false for production";
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
