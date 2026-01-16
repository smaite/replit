<?php

/**
 * Seller Debug Page - Shows all session and vendor information for debugging
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check that user is logged in as vendor
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

$user_id = $_SESSION['user_id'] ?? null;
$vendor_id = $_SESSION['vendor_id'] ?? null;

// Fetch user data
$user = null;
if ($user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Fetch vendor data
$vendor = null;
if ($vendor_id) {
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
} elseif ($user_id) {
    // Try to find vendor by user_id
    $stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $vendor = $stmt->fetch();
}

// Stats
$product_count = 0;
$order_count = 0;
if ($vendor_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $product_count = $stmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT o.id) 
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE p.vendor_id = ?
    ");
    $stmt->execute([$vendor_id]);
    $order_count = $stmt->fetchColumn();
}

$page_title = 'Debug Info - Seller Portal';
include '../includes/vendor_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">🔧 Debug Information</h1>
            <p class="text-gray-600 mt-1">Session and vendor details for debugging</p>
        </div>

        <!-- Session Data -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-key text-blue-500"></i> Session Data ($_SESSION)
            </h2>
            <div class="bg-gray-100 rounded-lg p-4 overflow-x-auto">
                <pre class="text-sm"><?php print_r($_SESSION); ?></pre>
            </div>

            <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $user_id ?? 'NULL'; ?></div>
                    <div class="text-sm text-gray-600">user_id</div>
                </div>
                <div class="bg-green-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo $vendor_id ?? 'NULL'; ?></div>
                    <div class="text-sm text-gray-600">vendor_id</div>
                </div>
                <div class="bg-purple-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-purple-600"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'NULL'); ?></div>
                    <div class="text-sm text-gray-600">user_role</div>
                </div>
                <div class="bg-orange-50 rounded-lg p-3 text-center">
                    <div class="text-2xl font-bold text-orange-600"><?php echo $_SESSION['user_verified'] ?? false ? 'YES' : 'NO'; ?></div>
                    <div class="text-sm text-gray-600">verified</div>
                </div>
            </div>
        </div>

        <!-- User Data -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-user text-green-500"></i> User Data (from database)
            </h2>
            <?php if ($user): ?>
                <div class="bg-gray-100 rounded-lg p-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                            <?php foreach ($user as $key => $value): ?>
                                <?php if ($key === 'password') continue; ?>
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 font-medium text-gray-700 w-1/3"><?php echo htmlspecialchars($key); ?></td>
                                    <td class="py-2 text-gray-900"><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-lg">
                    <i class="fas fa-exclamation-circle"></i> User not found in database!
                </div>
            <?php endif; ?>
        </div>

        <!-- Vendor Data -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-store text-purple-500"></i> Vendor Data (from database)
            </h2>
            <?php if ($vendor): ?>
                <div class="bg-gray-100 rounded-lg p-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                            <?php foreach ($vendor as $key => $value): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="py-2 font-medium text-gray-700 w-1/3"><?php echo htmlspecialchars($key); ?></td>
                                    <td class="py-2 text-gray-900"><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!$vendor_id && $vendor): ?>
                    <div class="mt-4 bg-yellow-50 text-yellow-700 p-4 rounded-lg">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Issue Found:</strong> Vendor exists in database (ID: <?php echo $vendor['id']; ?>) but
                        <code>$_SESSION['vendor_id']</code> is not set!
                        <br><br>
                        <strong>Cause:</strong> You logged in before the fix was applied. Please <a href="/auth/logout.php" class="underline font-bold">logout</a> and login again.
                    </div>
                <?php endif; ?>

                <?php if ($vendor['status'] !== 'approved'): ?>
                    <div class="mt-4 bg-red-50 text-red-700 p-4 rounded-lg">
                        <i class="fas fa-times-circle"></i>
                        <strong>Vendor Not Approved:</strong> Status is "<?php echo htmlspecialchars($vendor['status']); ?>".
                        Vendor must be approved to access seller features.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="bg-red-50 text-red-700 p-4 rounded-lg">
                    <i class="fas fa-exclamation-circle"></i> No vendor record found for this user!
                    <br><br>
                    This user may not have completed vendor registration or their vendor account was deleted.
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-chart-bar text-orange-500"></i> Quick Stats
            </h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-blue-600"><?php echo $product_count; ?></div>
                    <div class="text-gray-600">Products</div>
                </div>
                <div class="bg-green-50 rounded-lg p-4 text-center">
                    <div class="text-3xl font-bold text-green-600"><?php echo $order_count; ?></div>
                    <div class="text-gray-600">Orders</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-4">
                <i class="fas fa-tools text-red-500"></i> Debug Actions
            </h2>
            <div class="flex flex-wrap gap-4">
                <a href="/auth/logout.php" class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout & Re-login
                </a>
                <a href="/seller/" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-home mr-2"></i> Back to Dashboard
                </a>
                <a href="/seller/products.php" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-3 rounded-lg font-medium">
                    <i class="fas fa-box mr-2"></i> My Products
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>