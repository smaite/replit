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
include '../includes/vendor_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-3xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Shop Settings</h1>
            <p class="text-gray-600 mt-2">Manage your shop information and details</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-lg mb-6">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($warning): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-6 py-4 rounded-lg mb-6">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $warning; ?>
            </div>
        <?php endif; ?>
        
        <!-- Status Alert -->
        <?php if ($vendor['status'] === 'pending'): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
                <div class="flex items-start gap-4">
                    <i class="fas fa-info-circle text-2xl text-blue-600 mt-1"></i>
                    <div>
                        <h3 class="font-bold text-blue-900 mb-1">Verification Pending</h3>
                        <p class="text-blue-700">Your shop is awaiting admin verification. You cannot edit most settings until approved.</p>
                    </div>
                </div>
            </div>
        <?php elseif ($vendor['status'] === 'rejected'): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-8">
                <div class="flex items-start gap-4">
                    <i class="fas fa-times-circle text-2xl text-red-600 mt-1"></i>
                    <div>
                        <h3 class="font-bold text-red-900 mb-1">Application Rejected</h3>
                        <p class="text-red-700">Your vendor application was rejected. Please contact support for more details.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-lg p-8">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="save_settings" value="1">
                
                <!-- Shop Information Section -->
                <div class="mb-8 pb-8 border-b">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-store text-primary"></i> Shop Information
                    </h2>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Shop Name *</label>
                        <div class="flex items-center gap-3">
                            <input type="text" name="shop_name" value="<?php echo htmlspecialchars($vendor['shop_name']); ?>" required
                                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Your shop name">
                            <?php if ($vendor['shop_name'] !== sanitize($_POST['shop_name'] ?? '')): ?>
                                <span class="text-xs bg-orange-100 text-orange-800 px-3 py-1 rounded-full whitespace-nowrap">
                                    <i class="fas fa-lock"></i> Protected
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-orange-600 mt-2">
                            <i class="fas fa-info-circle"></i> Changing shop name requires re-verification
                        </p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Shop Description *</label>
                        <textarea name="shop_description" rows="4" required
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                  placeholder="Describe your shop and products"><?php echo htmlspecialchars($vendor['shop_description']); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Max 500 characters</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Business Website</label>
                        <input type="url" name="business_website" value="<?php echo htmlspecialchars($vendor['business_website'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="https://yourshop.com">
                    </div>
                </div>
                
                <!-- Contact Information Section -->
                <div class="mb-8 pb-8 border-b">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-phone text-primary"></i> Contact Information
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Email Address</label>
                            <input type="email" value="<?php echo htmlspecialchars($vendor['email']); ?>" readonly
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                   disabled>
                            <p class="text-xs text-gray-500 mt-1">Cannot be changed</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Phone Number *</label>
                            <input type="tel" name="business_phone" value="<?php echo htmlspecialchars($vendor['business_phone']); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="+977 XXXXXXXXX">
                        </div>
                    </div>
                </div>
                
                <!-- Location Information Section -->
                <div class="mb-8 pb-8 border-b">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-map-marker-alt text-primary"></i> Business Location
                    </h2>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Full Address</label>
                        <textarea name="business_location" rows="3"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                  placeholder="Street address, building name, etc."><?php echo htmlspecialchars($vendor['business_location'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">City</label>
                            <input type="text" name="business_city" value="<?php echo htmlspecialchars($vendor['business_city'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="City">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">State/Province</label>
                            <input type="text" name="business_state" value="<?php echo htmlspecialchars($vendor['business_state'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="State/Province">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Postal Code</label>
                            <input type="text" name="business_postal_code" value="<?php echo htmlspecialchars($vendor['business_postal_code'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Postal code">
                        </div>
                    </div>
                </div>
                
                <!-- Bank Information (Read-only) -->
                <div class="mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">
                        <i class="fas fa-university text-primary"></i> Bank Account Information
                    </h2>
                    
                    <div class="bg-gray-50 p-6 rounded-lg">
                        <p class="text-gray-600 mb-4">Your bank details were verified during registration and cannot be changed here. Contact support if you need to update your bank information.</p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Account Holder Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($vendor['bank_account_name'] ?? ''); ?>" readonly
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                       disabled>
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Account Number</label>
                                <input type="text" value="<?php echo htmlspecialchars(substr($vendor['bank_account_number'] ?? '', -4)); ?>" readonly
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                       disabled
                                       placeholder="****">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex gap-3">
                    <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium transition">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="/seller/" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-8 py-3 rounded-lg font-medium transition">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
