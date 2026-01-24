<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = '/pages/checkout.php';
    redirect('/auth/login.php');
}

$page_title = 'Checkout - SASTO Hub';

// 1. Fetch System Settings (Payment Methods, etc.)
$settings = [];
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings");
    $stmt->execute();
    $db_settings = $stmt->fetchAll();
    foreach ($db_settings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    // Fallback defaults if DB fails
}

// Payment Methods Status
// Default to COD enabled if settings are missing/empty for safety, others disabled
$cod_enabled = ($settings['payment_cod_enabled'] ?? '1') == '1';
$esewa_enabled = ($settings['payment_esewa_enabled'] ?? '0') == '1';
$qr_enabled = ($settings['payment_qr_enabled'] ?? '0') == '1';

// 2. Fetch Cart Items & Addresses
$user_id = $_SESSION['user_id'];
$cart_items = [];
$saved_addresses = [];

try {
    // Fetch Cart
    $stmt = $conn->prepare("
        SELECT c.*, p.name, p.price, p.sale_price, p.stock, p.sku,
               pi.image_path, v.id as vendor_id, v.shop_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        JOIN vendors v ON p.vendor_id = v.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();

    // Fetch Saved Addresses
    $stmt_addr = $conn->prepare("
        SELECT * FROM user_addresses 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $stmt_addr->execute([$user_id]);
    $saved_addresses = $stmt_addr->fetchAll();

} catch (Exception $e) {
    // Handle error
}

if (empty($cart_items)) {
    redirect('/pages/cart.php');
}

// Calculate Totals
$subtotal = 0;
$shipping = 0; // Free shipping logic for now
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?? $item['price'];
    $subtotal += $price * $item['quantity'];
}
$total = $subtotal + $shipping;

// 3. Handle Form Submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Session expired. Please try again.';
    } else {
        $shipping_name = sanitize($_POST['shipping_name'] ?? '');
        $shipping_phone = sanitize($_POST['shipping_phone'] ?? '');
        $shipping_address = sanitize($_POST['shipping_address'] ?? '');
        $shipping_city = sanitize($_POST['shipping_city'] ?? '');
        $payment_method = sanitize($_POST['payment_method'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');

        // Validation
        if (empty($shipping_name) || empty($shipping_phone) || empty($shipping_address) || empty($shipping_city)) {
            $error = 'Please fill in all required shipping fields.';
        } elseif (empty($payment_method)) {
            $error = 'Please select a payment method.';
        } else {
            // Verify Stock Again
            $stock_error = false;
            foreach ($cart_items as $item) {
                $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ? AND status = 'active'");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();

                if (!$product || $product['stock'] < $item['quantity']) {
                    $stock_error = true;
                    $error = "Product '{$item['name']}' is out of stock or requested quantity unavailable.";
                    break;
                }
            }

            if (!$stock_error) {
                try {
                    $conn->beginTransaction();

                    // Create Order
                    $order_number = 'ORD-' . strtoupper(uniqid());
                    $full_address = $shipping_address . ', ' . $shipping_city;

                    $stmt = $conn->prepare("
                        INSERT INTO orders (
                            user_id, order_number, total_amount, payment_method,
                            shipping_address, shipping_phone, notes, status, payment_status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
                    ");
                    $stmt->execute([
                        $user_id, $order_number, $total, $payment_method,
                        $full_address, $shipping_phone, $notes
                    ]);
                    $order_id = $conn->lastInsertId();

                    // Create Order Items
                    $stmt_item = $conn->prepare("
                        INSERT INTO order_items (order_id, product_id, vendor_id, quantity, price, subtotal)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");

                    $stmt_stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

                    foreach ($cart_items as $item) {
                        $price = $item['sale_price'] ?? $item['price'];
                        $item_subtotal = $price * $item['quantity'];

                        // Insert Item
                        $stmt_item->execute([
                            $order_id, $item['product_id'], $item['vendor_id'],
                            $item['quantity'], $price, $item_subtotal
                        ]);

                        // Reduce Stock
                        $stmt_stock->execute([$item['quantity'], $item['product_id']]);
                    }

                    // Clear Cart
                    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    $conn->commit();

                    // Reset cart count in session
                    $_SESSION['cart_count'] = 0;

                    // Redirect based on payment
                    if ($payment_method === 'esewa') {
                         redirect('/pages/esewa-pay.php?order=' . $order_id);
                    } else {
                         redirect('/pages/order-success.php?order=' . $order_number);
                    }

                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = 'Order processing failed: ' . $e->getMessage();
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/pages/cart.php" class="hover:text-primary">Shopping Cart</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Checkout</span>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

        <form method="POST" class="grid grid-cols-1 lg:grid-cols-12 gap-8" id="checkoutForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

            <!-- Left Column: Shipping & Payment (8 cols) -->
            <div class="lg:col-span-8 space-y-6">

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Shipping Information -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm">1</span>
                            Shipping Address
                        </h2>
                        <?php if (!empty($saved_addresses)): ?>
                        <button type="button" onclick="toggleAddressList()" class="text-sm text-primary font-medium hover:underline">
                            Change Address
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="p-6">
                        
                        <!-- Saved Addresses Grid -->
                        <?php if (!empty($saved_addresses)): ?>
                        <div id="savedAddresses" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            <?php foreach ($saved_addresses as $addr): ?>
                            <div class="address-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-indigo-50 transition-all relative group"
                                 onclick='selectAddress(<?php echo json_encode($addr); ?>, this)'>
                                <div class="flex justify-between items-start mb-2">
                                    <span class="px-2 py-1 bg-gray-100 text-xs font-bold rounded uppercase tracking-wide text-gray-600">
                                        <?php echo htmlspecialchars($addr['address_type']); ?>
                                    </span>
                                    <?php if ($addr['is_default']): ?>
                                    <span class="text-xs text-primary font-medium"><i class="fas fa-check-circle"></i> Default</span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($addr['full_name']); ?></h4>
                                <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($addr['phone']); ?></p>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?php echo htmlspecialchars($addr['address_line1']); ?>, 
                                    <?php echo htmlspecialchars($addr['city']); ?>
                                </p>
                                <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 text-primary transition-opacity check-icon">
                                    <i class="fas fa-check-circle text-lg"></i>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Add New Address Card -->
                            <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 cursor-pointer hover:border-primary hover:bg-gray-50 transition-all flex flex-col items-center justify-center text-gray-500 hover:text-primary min-h-[140px]"
                                 onclick="openAddressModal()">
                                <i class="fas fa-plus-circle text-2xl mb-2"></i>
                                <span class="font-medium">Add New Address</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Manual Address Form (Hidden by default if addresses exist) -->
                        <div id="manualAddressForm" class="<?php echo !empty($saved_addresses) ? 'hidden' : ''; ?>">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-gray-700">Enter Shipping Details</h3>
                                <?php if (!empty($saved_addresses)): ?>
                                <button type="button" onclick="toggleAddressList()" class="text-sm text-primary hover:underline">
                                    Cancel
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                    <input type="text" name="shipping_name" id="shipping_name" required
                                           value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?>"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary transition-colors">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                    <input type="tel" name="shipping_phone" id="shipping_phone" required placeholder="98XXXXXXXX"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary transition-colors">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Street Address</label>
                                    <input type="text" name="shipping_address" id="shipping_address" required placeholder="House No, Street Name, Tole"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary transition-colors">
                                </div>
                                
                                <!-- Location Dropdowns -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Province</label>
                                    <select name="shipping_province" id="shipping_province" required onchange="updateCities()"
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary transition-colors bg-white">
                                        <option value="">Select Province</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                                    <select name="shipping_city" id="shipping_city" required
                                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary transition-colors bg-white">
                                        <option value="">Select City</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Order Notes (Optional)</label>
                                    <input type="text" name="notes" placeholder="Landmarks, delivery time preference..."
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary transition-colors">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Payment Method -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center text-sm">2</span>
                            Payment Method
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                            <?php
                            $has_payment_method = false;
                            ?>

                            <!-- COD -->
                            <?php if ($cod_enabled): $has_payment_method = true; ?>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="payment_method" value="cod" class="peer sr-only" checked>
                                <div class="p-4 rounded-xl border-2 border-gray-200 peer-checked:border-primary peer-checked:bg-indigo-50 transition-all h-full">
                                    <div class="flex flex-col items-center text-center gap-3">
                                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                            <i class="fas fa-money-bill-wave text-xl"></i>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-gray-900">Cash on Delivery</span>
                                            <span class="text-xs text-gray-500">Pay at your doorstep</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary transition-opacity">
                                        <i class="fas fa-check-circle text-lg"></i>
                                    </div>
                                </div>
                            </label>
                            <?php endif; ?>

                            <!-- eSewa -->
                            <?php if ($esewa_enabled): $has_payment_method = true; ?>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="payment_method" value="esewa" class="peer sr-only" <?php echo !$cod_enabled ? 'checked' : ''; ?>>
                                <div class="p-4 rounded-xl border-2 border-gray-200 peer-checked:border-primary peer-checked:bg-indigo-50 transition-all h-full">
                                    <div class="flex flex-col items-center text-center gap-3">
                                        <div class="w-12 h-12 rounded-full bg-green-600 flex items-center justify-center">
                                            <span class="text-white font-bold">e</span>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-gray-900">eSewa Wallet</span>
                                            <span class="text-xs text-gray-500">Instant digital payment</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary transition-opacity">
                                        <i class="fas fa-check-circle text-lg"></i>
                                    </div>
                                </div>
                            </label>
                            <?php endif; ?>

                            <!-- QR Code -->
                            <?php if ($qr_enabled): $has_payment_method = true; ?>
                            <label class="relative cursor-pointer group">
                                <input type="radio" name="payment_method" value="qr" class="peer sr-only" onchange="toggleQrDetails(this)">
                                <div class="p-4 rounded-xl border-2 border-gray-200 peer-checked:border-primary peer-checked:bg-indigo-50 transition-all h-full">
                                    <div class="flex flex-col items-center text-center gap-3">
                                        <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                            <i class="fas fa-qrcode text-xl"></i>
                                        </div>
                                        <div>
                                            <span class="block font-bold text-gray-900">Scan & Pay</span>
                                            <span class="text-xs text-gray-500">Bank App / Wallets</span>
                                        </div>
                                    </div>
                                    <div class="absolute top-3 right-3 opacity-0 peer-checked:opacity-100 text-primary transition-opacity">
                                        <i class="fas fa-check-circle text-lg"></i>
                                    </div>
                                </div>
                            </label>
                            <?php endif; ?>

                            <?php if (!$has_payment_method): ?>
                                <div class="col-span-3 p-4 bg-yellow-50 text-yellow-700 rounded-lg">
                                    <i class="fas fa-exclamation-circle"></i> No payment methods available. Please contact support.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- QR Details Panel -->
                        <?php if ($qr_enabled): ?>
                        <div id="qr-details" class="hidden mt-6 bg-blue-50 rounded-xl p-6 border border-blue-100">
                            <div class="flex flex-col md:flex-row items-center gap-6">
                                <?php if (!empty($settings['payment_qr_image'])): ?>
                                <div class="bg-white p-3 rounded-lg shadow-sm">
                                    <img src="<?php echo htmlspecialchars($settings['payment_qr_image']); ?>" alt="Scan to Pay" class="w-40 h-40 object-contain">
                                </div>
                                <?php endif; ?>
                                <div class="flex-1 text-center md:text-left">
                                    <h4 class="font-bold text-blue-900 text-lg mb-2">Scan to Pay</h4>
                                    <p class="text-blue-800 text-sm mb-4 whitespace-pre-line"><?php echo htmlspecialchars($settings['payment_qr_instructions'] ?? 'Please scan the QR code using your mobile banking app.'); ?></p>
                                    <div class="inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-3 py-1.5 rounded-full text-xs font-medium">
                                        <i class="fas fa-info-circle"></i> Your order will be verified after payment
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-4">
                    <a href="/pages/cart.php" class="text-gray-600 hover:text-primary font-medium flex items-center gap-2">
                        <i class="fas fa-arrow-left"></i> Return to Cart
                    </a>
                </div>
            </div>

            <!-- Right Column: Summary (4 cols) -->
            <div class="lg:col-span-4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 sticky top-24">
                    <div class="p-6 border-b border-gray-100 bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-900">Order Summary</h2>
                    </div>

                    <div class="p-6">
                        <!-- Items List -->
                        <div class="space-y-4 mb-6 max-h-[300px] overflow-y-auto custom-scrollbar pr-2">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="flex gap-3">
                                <div class="w-16 h-16 rounded-md bg-gray-100 border border-gray-200 overflow-hidden flex-shrink-0">
                                    <img src="<?php echo htmlspecialchars($item['image_path'] ?? '/assets/images/no-image.png'); ?>"
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <p class="text-xs text-gray-500">Qty: <?php echo $item['quantity']; ?></p>
                                    <div class="text-sm font-bold text-gray-900 mt-1">
                                        Rs. <?php echo number_format(($item['sale_price'] ?? $item['price']) * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="space-y-3 pt-4 border-t border-gray-100">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span>Rs. <?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Shipping</span>
                                <span class="text-green-600 font-medium">Free</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Tax</span>
                                <span>Rs. 0.00</span>
                            </div>
                            <div class="flex justify-between items-center pt-3 border-t border-gray-100">
                                <span class="text-lg font-bold text-gray-900">Total</span>
                                <span class="text-xl font-bold text-primary">Rs. <?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>

                        <button type="submit" class="w-full mt-6 bg-primary hover:bg-indigo-700 text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-indigo-200 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <span>Place Order</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>

                        <div class="mt-4 flex items-center justify-center gap-2 text-xs text-gray-500">
                            <i class="fas fa-shield-alt text-green-500"></i> SSL Secure Payment
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Load location data
    const locationData = <?php echo file_get_contents('../location.json'); ?>;

    function updateCities() {
        const provinceSelect = document.getElementById('shipping_province');
        const citySelect = document.getElementById('shipping_city');
        const selectedProvince = provinceSelect.value;
        
        // Clear current cities
        citySelect.innerHTML = '<option value="">Select City</option>';
        
        if (selectedProvince && locationData[selectedProvince]) {
            locationData[selectedProvince].forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });
        }
    }

    // Initialize Provinces
    document.addEventListener('DOMContentLoaded', () => {
        const provinceSelect = document.getElementById('shipping_province');
        if (provinceSelect) {
            Object.keys(locationData).forEach(province => {
                const option = document.createElement('option');
                option.value = province;
                option.textContent = province;
                provinceSelect.appendChild(option);
            });
        }
    });
    function toggleQrDetails(radio) {
        const qrSection = document.getElementById('qr-details');
        if (qrSection) {
            if (radio.value === 'qr' && radio.checked) {
                qrSection.classList.remove('hidden');
            } else {
                qrSection.classList.add('hidden');
            }
        }
    }

    // Address Selection Logic
    function selectAddress(addr, element) {
        // Visual selection
        document.querySelectorAll('.address-card').forEach(el => {
            el.classList.remove('border-primary', 'bg-indigo-50');
            el.classList.add('border-gray-200');
            el.querySelector('.check-icon').classList.remove('opacity-100');
            el.querySelector('.check-icon').classList.add('opacity-0');
        });
        
        element.classList.remove('border-gray-200');
        element.classList.add('border-primary', 'bg-indigo-50');
        element.querySelector('.check-icon').classList.remove('opacity-0');
        element.querySelector('.check-icon').classList.add('opacity-100');

        // Fill form
        document.getElementById('shipping_name').value = addr.full_name;
        document.getElementById('shipping_phone').value = addr.phone;
        document.getElementById('shipping_address').value = addr.address_line1;
        document.getElementById('shipping_city').value = addr.city;
        
        // Show form if hidden (optional, but good for verification)
        document.getElementById('manualAddressForm').classList.remove('hidden');
        document.getElementById('savedAddresses').classList.add('hidden');
    }

    function showNewAddressForm() {
        // Clear form
        document.getElementById('shipping_name').value = '';
        document.getElementById('shipping_phone').value = '';
        document.getElementById('shipping_address').value = '';
        document.getElementById('shipping_city').value = '';
        
        document.getElementById('manualAddressForm').classList.remove('hidden');
        document.getElementById('savedAddresses').classList.add('hidden');
    }

    function toggleAddressList() {
        const list = document.getElementById('savedAddresses');
        const form = document.getElementById('manualAddressForm');
        
        if (list.classList.contains('hidden')) {
            list.classList.remove('hidden');
            form.classList.add('hidden');
        } else {
            list.classList.add('hidden');
            form.classList.remove('hidden');
        }
    }

    // Auto-toggle on load if QR is selected
    document.addEventListener('DOMContentLoaded', () => {
        const qrRadio = document.querySelector('input[name="payment_method"][value="qr"]');
        if (qrRadio && qrRadio.checked) {
            toggleQrDetails(qrRadio);
        }

        // Add change listeners to other radios to hide QR
        const otherRadios = document.querySelectorAll('input[name="payment_method"]:not([value="qr"])');
        otherRadios.forEach(r => {
            r.addEventListener('change', () => {
                const qrSection = document.getElementById('qr-details');
                if (qrSection) qrSection.classList.add('hidden');
            });
        });

        // Initialize Modal Provinces
        const modalProvince = document.getElementById('modal_province');
        if (modalProvince) {
            Object.keys(locationData).forEach(province => {
                const option = document.createElement('option');
                option.value = province;
                option.textContent = province;
                modalProvince.appendChild(option);
            });
        }
    });

    // Modal Logic
    function openAddressModal() {
        document.getElementById('addressModal').classList.remove('hidden');
    }

    function closeAddressModal() {
        document.getElementById('addressModal').classList.add('hidden');
    }

    function updateModalCities() {
        const provinceSelect = document.getElementById('modal_province');
        const citySelect = document.getElementById('modal_city');
        const selectedProvince = provinceSelect.value;
        
        citySelect.innerHTML = '<option value="">Select City</option>';
        
        if (selectedProvince && locationData[selectedProvince]) {
            locationData[selectedProvince].forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });
        }
    }

    function selectLabel(label) {
        document.getElementById('modal_label_home').classList.remove('ring-2', 'ring-primary', 'bg-indigo-50');
        document.getElementById('modal_label_office').classList.remove('ring-2', 'ring-primary', 'bg-indigo-50');
        
        if (label === 'home') {
            document.getElementById('modal_label_home').classList.add('ring-2', 'ring-primary', 'bg-indigo-50');
            document.getElementById('address_type').value = 'home';
        } else {
            document.getElementById('modal_label_office').classList.add('ring-2', 'ring-primary', 'bg-indigo-50');
            document.getElementById('address_type').value = 'office';
        }
    }

    function saveAddress() {
        const data = {
            full_name: document.getElementById('modal_name').value,
            phone: document.getElementById('modal_phone').value,
            state: document.getElementById('modal_province').value,
            city: document.getElementById('modal_city').value,
            address_line2: document.getElementById('modal_area').value, // Area
            address_line1: document.getElementById('modal_address').value,
            address_type: document.getElementById('address_type').value,
            is_default: false
        };

        if (!data.full_name || !data.phone || !data.state || !data.city || !data.address_line1) {
            alert('Please fill in all required fields');
            return;
        }

        fetch('/api/addresses.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // Reload page to show new address (simplest way for now)
                window.location.reload();
            } else {
                alert(result.error || 'Failed to save address');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the address');
        });
    }
</script>

<!-- Add Address Modal -->
<div id="addressModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex justify-between items-center">
            <h3 class="text-xl font-bold text-gray-900">Add new shipping Address</h3>
            <button onclick="closeAddressModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Full name</label>
                    <input type="text" id="modal_name" placeholder="Enter your first and last name"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                </div>
                
                <!-- Region -->
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Region</label>
                    <select id="modal_province" onchange="updateModalCities()"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary bg-white">
                        <option value="">Select Region</option>
                    </select>
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Phone Number</label>
                    <input type="tel" id="modal_phone" placeholder="Please enter your phone number"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                </div>

                <!-- City -->
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">City</label>
                    <select id="modal_city"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary bg-white">
                        <option value="">Select City</option>
                    </select>
                </div>

                <!-- Building/Street (Address Line 1) -->
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Building / House No / Floor / Street</label>
                    <input type="text" id="modal_address" placeholder="Please enter"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                </div>

                <!-- Area (Address Line 2) -->
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-2">Area</label>
                    <input type="text" id="modal_area" placeholder="Please choose your area"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                </div>

                <!-- Colony/Landmark (Optional - mapped to nothing for now or appended) -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-600 mb-2">Address</label>
                    <input type="text" disabled placeholder="For Example: House# 123, Street# 123, ABC Road"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-400">
                    <p class="text-xs text-gray-400 mt-1">This is a preview of your full address</p>
                </div>
            </div>

            <!-- Label -->
            <div>
                <label class="block text-sm font-medium text-gray-600 mb-3">Select a label for effective delivery:</label>
                <input type="hidden" id="address_type" value="home">
                <div class="flex gap-4">
                    <button type="button" id="modal_label_office" onclick="selectLabel('office')"
                            class="flex items-center gap-2 px-6 py-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        <i class="fas fa-briefcase text-gray-500"></i>
                        <span class="font-medium text-gray-700">OFFICE</span>
                    </button>
                    <button type="button" id="modal_label_home" onclick="selectLabel('home')"
                            class="flex items-center gap-2 px-6 py-3 border border-gray-300 rounded-lg ring-2 ring-primary bg-indigo-50 transition">
                        <i class="fas fa-home text-gray-500"></i>
                        <span class="font-medium text-gray-700">HOME</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="p-6 border-t border-gray-100 flex justify-end gap-4 bg-gray-50">
            <button onclick="closeAddressModal()" class="px-6 py-2.5 bg-gray-200 text-gray-700 font-medium rounded hover:bg-gray-300 transition">
                CANCEL
            </button>
            <button onclick="saveAddress()" class="px-8 py-2.5 bg-[#0095A0] text-white font-bold rounded hover:bg-[#007f8a] transition shadow-lg">
                SAVE
            </button>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
