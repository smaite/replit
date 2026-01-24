<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check vendor access
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

$error = '';
$success = '';
$warning = '';

// Get vendor info
$stmt = $conn->prepare("SELECT v.*, u.email, u.phone FROM vendors v
                       JOIN users u ON v.user_id = u.id
                       WHERE v.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('/auth/become-vendor.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $shop_name = sanitize($_POST['shop_name'] ?? '');
        $shop_description = sanitize($_POST['shop_description'] ?? '');
        $business_website = sanitize($_POST['business_website'] ?? '');
        $business_phone = sanitize($_POST['business_phone'] ?? '');
        $business_location = sanitize($_POST['business_location'] ?? '');
        $business_city = sanitize($_POST['business_city'] ?? '');
        $business_state = sanitize($_POST['business_state'] ?? '');
        $business_postal_code = sanitize($_POST['business_postal_code'] ?? '');

        // Check if shop name changed
        $shop_name_changed = ($shop_name !== $vendor['shop_name']);

        if (empty($shop_name) || empty($shop_description) || empty($business_phone)) {
            $error = 'Please fill in all required fields';
        } else {
            try {
                // If shop name changed, mark for verification
                if ($shop_name_changed) {
                    $stmt = $conn->prepare("UPDATE vendors SET shop_name = ?, shop_description = ?,
                                                              business_website = ?, business_phone = ?,
                                                              business_location = ?, business_city = ?,
                                                              business_state = ?, business_postal_code = ?,
                                                              status = 'pending'
                                           WHERE user_id = ?");
                    $warning = 'Your shop name has changed. Your profile is now pending verification.';
                } else {
                    $stmt = $conn->prepare("UPDATE vendors SET shop_name = ?, shop_description = ?,
                                                              business_website = ?, business_phone = ?,
                                                              business_location = ?, business_city = ?,
                                                              business_state = ?, business_postal_code = ?
                                           WHERE user_id = ?");
                }

                $result = $stmt->execute([
                    $shop_name, $shop_description, $business_website, $business_phone,
                    $business_location, $business_city, $business_state, $business_postal_code,
                    $_SESSION['user_id']
                ]);

                if ($result) {
                    // Refresh vendor data
                    $stmt = $conn->prepare("SELECT v.*, u.email, u.phone FROM vendors v
                                           JOIN users u ON v.user_id = u.id
                                           WHERE v.user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $vendor = $stmt->fetch();

                    $success = 'Shop settings updated successfully!';
                } else {
                    $error = 'Failed to update settings. Please try again.';
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Shop Settings - Vendor Dashboard';
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
            <span class="text-gray-900 font-medium">Shop Settings</span>
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
                                <h3 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($vendor['shop_name']); ?></h3>
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
                        <a href="/seller/settings.php" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-bold rounded-lg transition-colors">
                            <i class="fas fa-cog w-5"></i> Shop Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Shop Settings</h1>
                    <p class="text-gray-600 mt-1">Manage your shop information and business details</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($warning): ?>
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle text-xl"></i>
                        <div><?php echo $warning; ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="space-y-8">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                    <input type="hidden" name="save_settings" value="1">

                    <!-- Shop Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-store text-primary"></i> Shop Information
                        </h2>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Shop Name <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input type="text" name="shop_name" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($vendor['shop_name']); ?>">
                                    <?php if ($vendor['shop_name'] !== ($vendor['shop_name'] ?? '')): ?>
                                        <div class="absolute right-3 top-3 text-yellow-500" title="Pending Verification">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Changing your shop name will require admin re-verification.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Shop Description <span class="text-red-500">*</span></label>
                                <textarea name="shop_description" rows="4" required
                                          class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"><?php echo htmlspecialchars($vendor['shop_description']); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Business Website</label>
                                <input type="url" name="business_website"
                                       class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                       placeholder="https://yourwebsite.com"
                                       value="<?php echo htmlspecialchars($vendor['business_website'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Contact Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-address-book text-primary"></i> Contact Information
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($vendor['email']); ?>" readonly
                                       class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 cursor-not-allowed">
                                <p class="text-xs text-gray-500 mt-2">Contact support to change email.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Business Phone <span class="text-red-500">*</span></label>
                                <input type="tel" name="business_phone" required
                                       class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                       value="<?php echo htmlspecialchars($vendor['business_phone']); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-map-marker-alt text-primary"></i> Business Location
                        </h2>

                        <div class="space-y-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Full Address</label>
                                <textarea name="business_location" rows="2"
                                          class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"><?php echo htmlspecialchars($vendor['business_location'] ?? ''); ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">City</label>
                                    <input type="text" name="business_city"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($vendor['business_city'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">State/Province</label>
                                    <input type="text" name="business_state"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($vendor['business_state'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Postal Code</label>
                                    <input type="text" name="business_postal_code"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($vendor['business_postal_code'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bank Details (Read Only) -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-university text-primary"></i> Bank Details
                        </h2>

                        <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 mb-6">
                            <p class="text-sm text-gray-600 flex items-start gap-2">
                                <i class="fas fa-lock mt-1"></i>
                                Bank details are locked for security. Please contact support to update your payout information.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 opacity-75">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Account Holder Name</label>
                                <input type="text" readonly
                                       class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 cursor-not-allowed"
                                       value="<?php echo htmlspecialchars($vendor['bank_account_name'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Account Number</label>
                                <input type="text" readonly
                                       class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-500 cursor-not-allowed"
                                       value="•••• •••• •••• <?php echo htmlspecialchars(substr($vendor['bank_account_number'] ?? '', -4)); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="px-8 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
