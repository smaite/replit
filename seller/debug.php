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

$page_title = 'Debug Info - Seller Dashboard';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/seller/" class="hover:text-primary">Seller Dashboard</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Debug Info</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
             <!-- Sidebar Navigation -->
             <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-2xl font-bold border border-indigo-200">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Shop Dashboard</p>
                                <h3 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($vendor['shop_name'] ?? 'Unknown Shop'); ?></h3>
                            </div>
                        </div>
                    </div>
                    <nav class="p-4 space-y-1">
                        <a href="/seller/" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-chart-line w-5 group-hover:text-primary transition-colors"></i> Dashboard
                        </a>
                        <a href="/seller/products.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-box w-5 group-hover:text-primary transition-colors"></i> My Products
                        </a>
                        <a href="/seller/add-product.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-plus-circle w-5 group-hover:text-primary transition-colors"></i> Add Product
                        </a>
                        <a href="/seller/orders.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-shopping-cart w-5 group-hover:text-primary transition-colors"></i> Orders
                        </a>
                        <a href="/seller/settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-cog w-5 group-hover:text-primary transition-colors"></i> Shop Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-wrench text-gray-400"></i> Debug Information
                    </h1>
                    <p class="text-gray-600 mt-1">Session and vendor details for troubleshooting</p>
                </div>

                <div class="space-y-6">
                    <!-- Session Data -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-key text-blue-500"></i> Session Data ($_SESSION)
                        </h2>
                        <div class="bg-gray-900 rounded-xl p-4 overflow-x-auto mb-6">
                            <pre class="text-sm text-green-400 font-mono"><?php print_r($_SESSION); ?></pre>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                                <div class="text-2xl font-bold text-blue-600"><?php echo $user_id ?? 'NULL'; ?></div>
                                <div class="text-sm font-bold text-blue-800 uppercase tracking-wide">user_id</div>
                            </div>
                            <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                                <div class="text-2xl font-bold text-green-600"><?php echo $vendor_id ?? 'NULL'; ?></div>
                                <div class="text-sm font-bold text-green-800 uppercase tracking-wide">vendor_id</div>
                            </div>
                            <div class="bg-purple-50 rounded-xl p-4 text-center border border-purple-100">
                                <div class="text-2xl font-bold text-purple-600"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'NULL'); ?></div>
                                <div class="text-sm font-bold text-purple-800 uppercase tracking-wide">user_role</div>
                            </div>
                            <div class="bg-orange-50 rounded-xl p-4 text-center border border-orange-100">
                                <div class="text-2xl font-bold text-orange-600"><?php echo $_SESSION['user_verified'] ?? false ? 'YES' : 'NO'; ?></div>
                                <div class="text-sm font-bold text-orange-800 uppercase tracking-wide">verified</div>
                            </div>
                        </div>
                    </div>

                    <!-- User Data -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-user text-green-500"></i> User Data
                        </h2>
                        <?php if ($user): ?>
                            <div class="overflow-hidden rounded-xl border border-gray-200">
                                <table class="w-full text-sm text-left">
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($user as $key => $value): ?>
                                            <?php if ($key === 'password') continue; ?>
                                            <tr class="bg-white hover:bg-gray-50 transition">
                                                <td class="px-4 py-3 font-bold text-gray-700 bg-gray-50/50 w-1/3"><?php echo htmlspecialchars($key); ?></td>
                                                <td class="px-4 py-3 text-gray-900 font-mono"><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl flex items-center gap-3">
                                <i class="fas fa-exclamation-circle text-xl"></i> User not found in database!
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Vendor Data -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-store text-purple-500"></i> Vendor Data
                        </h2>
                        <?php if ($vendor): ?>
                            <div class="overflow-hidden rounded-xl border border-gray-200 mb-6">
                                <table class="w-full text-sm text-left">
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($vendor as $key => $value): ?>
                                            <tr class="bg-white hover:bg-gray-50 transition">
                                                <td class="px-4 py-3 font-bold text-gray-700 bg-gray-50/50 w-1/3"><?php echo htmlspecialchars($key); ?></td>
                                                <td class="px-4 py-3 text-gray-900 font-mono"><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (!$vendor_id && $vendor): ?>
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-xl mb-4">
                                    <div class="flex items-start gap-3">
                                        <i class="fas fa-exclamation-triangle text-xl mt-1"></i>
                                        <div>
                                            <p class="font-bold">Session Mismatch</p>
                                            <p class="text-sm mt-1">Vendor exists in database (ID: <?php echo $vendor['id']; ?>) but <code>$_SESSION['vendor_id']</code> is not set.</p>
                                            <p class="text-sm mt-2"><strong>Fix:</strong> <a href="/auth/logout.php" class="underline font-bold hover:text-yellow-900">Logout</a> and login again to refresh session.</p>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($vendor['status'] !== 'approved'): ?>
                                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-times-circle text-xl"></i>
                                        <div>
                                            <span class="font-bold">Vendor Not Approved:</span> Status is "<?php echo htmlspecialchars($vendor['status']); ?>".
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl flex items-center gap-3">
                                <i class="fas fa-exclamation-circle text-xl"></i> No vendor record found for this user!
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Stats -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-bar text-orange-500"></i> Database Counts
                        </h2>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-blue-50 rounded-xl p-4 text-center border border-blue-100">
                                <div class="text-3xl font-bold text-blue-600"><?php echo $product_count; ?></div>
                                <div class="text-gray-600 font-medium">Products</div>
                            </div>
                            <div class="bg-green-50 rounded-xl p-4 text-center border border-green-100">
                                <div class="text-3xl font-bold text-green-600"><?php echo $order_count; ?></div>
                                <div class="text-gray-600 font-medium">Orders</div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2">
                            <i class="fas fa-tools text-gray-500"></i> Troubleshooting Actions
                        </h2>
                        <div class="flex flex-wrap gap-4">
                            <a href="/auth/logout.php" class="px-6 py-3 bg-red-50 text-red-600 border border-red-200 font-bold rounded-xl hover:bg-red-100 transition flex items-center gap-2">
                                <i class="fas fa-sign-out-alt"></i> Logout & Re-login
                            </a>
                            <a href="/seller/products.php" class="px-6 py-3 bg-white text-gray-700 border border-gray-200 font-bold rounded-xl hover:bg-gray-50 transition flex items-center gap-2">
                                <i class="fas fa-box"></i> Check Products
                            </a>
                            <a href="/seller/" class="px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5 flex items-center gap-2">
                                <i class="fas fa-home"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
