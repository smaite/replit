<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$page_title = 'Checkout - SASTO Hub';

// Fetch cart items
$stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.sale_price, p.stock, pi.image_path, v.id as vendor_id
                        FROM cart c
                        JOIN products p ON c.product_id = p.id
                        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                        JOIN vendors v ON p.vendor_id = v.id
                        WHERE c.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    redirect('/pages/cart.php');
}

$total = 0;
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?? $item['price'];
    $total += $price * $item['quantity'];
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $shipping_address = validateInput($_POST['address'] ?? '', 'text', 500);
        $shipping_phone = validateInput($_POST['phone'] ?? '', 'text', 20);
        $payment_method = validateInput($_POST['payment_method'] ?? 'cod', 'text', 50);
        $notes = validateInput($_POST['notes'] ?? '', 'text', 1000);
        
        if (!$shipping_address || !$shipping_phone) {
            $error = 'Please fill in all required fields';
        } else {
            // Validate stock availability before checkout
            $stock_error = false;
            foreach ($cart_items as $item) {
                $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ? AND status = 'active'");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                
                if (!$product || $product['stock'] < $item['quantity']) {
                    $stock_error = true;
                    $error = 'Some items in your cart are out of stock. Please update your cart.';
                    break;
                }
            }
            
            if (!$stock_error) {
                // Generate order number
                $order_number = 'ORD-' . strtoupper(uniqid());
                
                try {
                    $conn->beginTransaction();
                    
                    // Create order
                    $stmt = $conn->prepare("INSERT INTO orders (user_id, order_number, total_amount, payment_method, shipping_address, shipping_phone, notes) 
                                            VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$_SESSION['user_id'], $order_number, $total, $payment_method, $shipping_address, $shipping_phone, $notes]);
                    $order_id = $conn->lastInsertId();
                    
                    // Create order items and update stock
                    foreach ($cart_items as $item) {
                        $price = $item['sale_price'] ?? $item['price'];
                        $subtotal = $price * $item['quantity'];
                        
                        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, vendor_id, quantity, price, subtotal) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$order_id, $item['product_id'], $item['vendor_id'], $item['quantity'], $price, $subtotal]);
                        
                        // Update product stock with validation
                        $stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                        $affected = $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                        
                        if ($stmt->rowCount() === 0) {
                            throw new Exception('Stock validation failed');
                        }
                    }
                    
                    // Clear cart
                    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    $conn->commit();
                    
                    $_SESSION['cart_count'] = 0;
                    
                    // Redirect based on payment method
                    if ($payment_method === 'esewa') {
                        // Redirect to eSewa payment page
                        redirect('/pages/esewa-pay.php?order=' . $order_id);
                    } else {
                        // COD - go to success page
                        redirect('/pages/order-success.php?order=' . $order_number);
                    }
                    
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Order failed. Please try again.';
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Checkout Form -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Shipping Information</h2>
                
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Full Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($_SESSION['user_name']); ?>" disabled
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Phone Number *</label>
                            <input type="tel" name="phone" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="+977-XXX-XXXX">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Shipping Address *</label>
                            <textarea name="address" rows="4" required
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                      placeholder="Enter your complete shipping address"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Order Notes (Optional)</label>
                            <textarea name="notes" rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                      placeholder="Any special instructions for your order"></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Payment Method *</label>
                            <div class="space-y-3">
                                <label class="payment-option flex items-center gap-3 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition" onclick="selectPaymentMethod(this)">
                                    <input type="radio" name="payment_method" value="cod" checked class="w-5 h-5 text-primary">
                                    <span class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center text-2xl">💵</span>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">Cash on Delivery</p>
                                        <p class="text-sm text-gray-600">Pay when you receive your order</p>
                                    </div>
                                    <span class="payment-check w-6 h-6 bg-primary text-white rounded-full hidden items-center justify-center text-sm">✓</span>
                                </label>
                                <label class="payment-option flex items-center gap-3 p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition" onclick="selectPaymentMethod(this)">
                                    <input type="radio" name="payment_method" value="esewa" class="w-5 h-5 text-primary">
                                    <span class="w-12 h-12 rounded-lg flex items-center justify-center" style="background: #60BB46;">
                                        <img src="https://esewa.com.np/common/images/esewa_logo.png" alt="eSewa" 
                                             class="w-8 h-8 object-contain"
                                             onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 32 32%22><text x=%2216%22 y=%2220%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2210%22 font-weight=%22bold%22>eSewa</text></svg>'">
                                    </span>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-900">eSewa</p>
                                        <p class="text-sm text-gray-600">Pay instantly with eSewa wallet</p>
                                    </div>
                                    <span class="payment-check w-6 h-6 bg-primary text-white rounded-full hidden items-center justify-center text-sm">✓</span>
                                </label>
                            </div>
                        </div>
                        
                        <style>
                        .payment-option:has(input:checked) { border-color: #6366f1; background: #f5f3ff; }
                        .payment-option:has(input:checked) .payment-check { display: flex; }
                        </style>
                        <script>
                        function selectPaymentMethod(el) {
                            document.querySelectorAll('.payment-option').forEach(opt => opt.classList.remove('selected'));
                            el.classList.add('selected');
                            el.querySelector('input').checked = true;
                        }
                        </script>
                    </div>
                    
                    <button type="submit" 
                            class="w-full mt-6 bg-primary hover:bg-indigo-700 text-white py-4 rounded-lg font-bold text-lg transition">
                        <i class="fas fa-check-circle"></i> Place Order
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                <h3 class="font-bold text-xl text-gray-900 mb-4">Order Summary</h3>
                
                <div class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                    <?php foreach ($cart_items as $item): ?>
                        <?php $item_price = $item['sale_price'] ?? $item['price']; ?>
                        <div class="flex gap-3 pb-3 border-b">
                            <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/60'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 class="w-16 h-16 object-cover rounded">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($item['name']); ?></p>
                                <p class="text-sm text-gray-600">Qty: <?php echo $item['quantity']; ?></p>
                                <p class="text-sm font-bold text-primary"><?php echo formatPrice($item_price * $item['quantity']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="space-y-3 mb-4 pt-4 border-t">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="font-medium"><?php echo formatPrice($total); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping</span>
                        <span class="font-medium text-green-600">FREE</span>
                    </div>
                    <div class="border-t pt-3 flex justify-between text-lg font-bold">
                        <span>Total</span>
                        <span class="text-primary"><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg text-sm">
                    <p class="text-blue-800">
                        <i class="fas fa-shield-alt"></i> Your payment is secure. We never store your payment details.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
