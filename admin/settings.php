<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('/auth/login.php');
}

$error = '';
$success = '';

// Get current settings from database
$settings = [];
try {
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
    $stmt->execute();
    $db_settings = $stmt->fetchAll();
    foreach ($db_settings as $setting) {
        $settings[$setting['setting_key']] = $setting['setting_value'];
    }
} catch (Exception $e) {
    $error = "Could not load settings: " . $e->getMessage();
}

// Default settings if database is empty
if (empty($settings)) {
    $settings = [
        'website_name' => 'SASTO Hub',
        'website_tagline' => 'Your Online Marketplace',
        'header_logo' => '/assets/images/logo.png',
        'footer_logo' => '/assets/images/logo.png',
        'footer_name' => 'SASTO Hub',
        'copyright_text' => '© 2025 SASTO Hub. All rights reserved.',
        'primary_color' => '#4f46e5',
        'contact_email' => 'info@sastohub.com',
        'contact_phone' => '+977 1234567890',
        'address' => 'Kathmandu, Nepal',
        'facebook_url' => '',
        'twitter_url' => '',
        'instagram_url' => '',
        'youtube_url' => '',
        'payment_cod_enabled' => '1',
        'payment_esewa_enabled' => '0',
        'payment_qr_enabled' => '0',
        'payment_qr_image' => '',
        'payment_qr_instructions' => 'Scan the QR code to pay'
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        try {
            $new_settings = $settings;
            
            // Update text fields
            $new_settings['website_name'] = sanitize($_POST['website_name'] ?? '');
            $new_settings['website_tagline'] = sanitize($_POST['website_tagline'] ?? '');
            $new_settings['footer_name'] = sanitize($_POST['footer_name'] ?? '');
            $new_settings['copyright_text'] = sanitize($_POST['copyright_text'] ?? '');
            $new_settings['contact_email'] = sanitize($_POST['contact_email'] ?? '');
            $new_settings['contact_phone'] = sanitize($_POST['contact_phone'] ?? '');
            $new_settings['address'] = sanitize($_POST['address'] ?? '');
            $new_settings['primary_color'] = sanitize($_POST['primary_color'] ?? '');
            $new_settings['facebook_url'] = sanitize($_POST['facebook_url'] ?? '');
            $new_settings['twitter_url'] = sanitize($_POST['twitter_url'] ?? '');
            $new_settings['instagram_url'] = sanitize($_POST['instagram_url'] ?? '');
            $new_settings['youtube_url'] = sanitize($_POST['youtube_url'] ?? '');
            
            // Payment settings
            $new_settings['payment_cod_enabled'] = isset($_POST['payment_cod_enabled']) ? '1' : '0';
            $new_settings['payment_esewa_enabled'] = isset($_POST['payment_esewa_enabled']) ? '1' : '0';
            $new_settings['payment_qr_enabled'] = isset($_POST['payment_qr_enabled']) ? '1' : '0';
            $new_settings['payment_qr_instructions'] = sanitize($_POST['payment_qr_instructions'] ?? '');
            
            // Handle file uploads
            if (!empty($_FILES['header_logo']['name'])) {
                $upload_dir = '../assets/images/';
                $file = $_FILES['header_logo'];
                
                if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
                    $filename = 'logo_header_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        $new_settings['header_logo'] = '/assets/images/' . $filename;
                    } else {
                        $error = 'Failed to upload header logo';
                    }
                } else {
                    $error = 'Header logo must be JPG, PNG, or WebP';
                }
            }
            
            if (!empty($_FILES['footer_logo']['name']) && empty($error)) {
                $upload_dir = '../assets/images/';
                $file = $_FILES['footer_logo'];
                
                if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
                    $filename = 'logo_footer_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        $new_settings['footer_logo'] = '/assets/images/' . $filename;
                    } else {
                        $error = 'Failed to upload footer logo';
                    }
                } else {
                    $error = 'Footer logo must be JPG, PNG, or WebP';
                }
            }
            
            // Handle QR code image upload
            if (!empty($_FILES['payment_qr_image']['name']) && empty($error)) {
                $upload_dir = '../assets/images/';
                $file = $_FILES['payment_qr_image'];
                
                if (in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
                    $filename = 'payment_qr_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
                        $new_settings['payment_qr_image'] = '/assets/images/' . $filename;
                    } else {
                        $error = 'Failed to upload QR code image';
                    }
                } else {
                    $error = 'QR code must be JPG, PNG, or WebP';
                }
            }
            
            // Save settings to database if no error
            if (empty($error)) {
                $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                
                foreach ($new_settings as $key => $value) {
                    $stmt->execute([$key, $value, $value]);
                }
                
                $settings = $new_settings;
                $success = 'Settings saved successfully!';
            }
        } catch (Exception $e) {
            $error = 'Failed to save settings: ' . $e->getMessage();
        }
    }
}

$page_title = 'Website Settings - Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Website Settings</h1>
            <p class="text-gray-600 mt-2">Manage your website's appearance and information</p>
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
        
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-8">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="save_settings" value="1">
            
            <!-- Website Identity Section -->
            <div class="border-b pb-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-globe text-primary"></i> Website Identity
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Website Name *</label>
                        <input type="text" name="website_name" value="<?php echo htmlspecialchars($settings['website_name']); ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Your website name">
                        <p class="text-xs text-gray-500 mt-1">Displayed in browser tab and header</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Website Tagline</label>
                        <input type="text" name="website_tagline" value="<?php echo htmlspecialchars($settings['website_tagline']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Your tagline (e.g., Your Online Marketplace)">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Primary Color</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color']); ?>"
                                   class="h-12 rounded-lg border border-gray-300 cursor-pointer">
                            <input type="text" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" readonly
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm bg-gray-50">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Used for buttons, links, and accents</p>
                    </div>
                </div>
            </div>
            
            <!-- Logo Section -->
            <div class="border-b pb-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-image text-primary"></i> Logos
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-gray-700 font-medium mb-3">Header Logo</label>
                        <?php if (!empty($settings['header_logo'])): ?>
                            <div class="mb-3 p-4 bg-gray-100 rounded-lg">
                                <img src="<?php echo htmlspecialchars($settings['header_logo']); ?>" alt="Header Logo" class="h-16">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="header_logo" accept="image/jpeg,image/png,image/webp"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG, or WebP | Max: 2MB | Recommended: 200x60px</p>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-3">Footer Logo</label>
                        <?php if (!empty($settings['footer_logo'])): ?>
                            <div class="mb-3 p-4 bg-gray-100 rounded-lg">
                                <img src="<?php echo htmlspecialchars($settings['footer_logo']); ?>" alt="Footer Logo" class="h-16">
                            </div>
                        <?php endif; ?>
                        <input type="file" name="footer_logo" accept="image/jpeg,image/png,image/webp"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG, or WebP | Max: 2MB | Recommended: 150x40px</p>
                    </div>
                </div>
            </div>
            
            <!-- Footer Section -->
            <div class="border-b pb-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-shoe-prints text-primary"></i> Footer Content
                </h2>
                
                <div class="space-y-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Footer Name/Text</label>
                        <input type="text" name="footer_name" value="<?php echo htmlspecialchars($settings['footer_name']); ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Company name for footer">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Copyright Text *</label>
                        <input type="text" name="copyright_text" value="<?php echo htmlspecialchars($settings['copyright_text']); ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="© 2025 Your Company. All rights reserved.">
                        <p class="text-xs text-gray-500 mt-1">Displayed at the bottom of every page</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Section -->
            <div class="border-b pb-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-phone text-primary"></i> Contact Information
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Contact Email</label>
                        <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="info@example.com">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Contact Phone</label>
                        <input type="tel" name="contact_phone" value="<?php echo htmlspecialchars($settings['contact_phone']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="+977 1234567890">
                    </div>
                </div>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Address</label>
                    <textarea name="address" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                              placeholder="Your company address"><?php echo htmlspecialchars($settings['address']); ?></textarea>
                </div>
            </div>
            
            <!-- Social Media Section -->
            <div class="border-b pb-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-share-alt text-primary"></i> Social Media Links
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fab fa-facebook text-blue-600"></i> Facebook
                        </label>
                        <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($settings['facebook_url']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="https://facebook.com/yourpage">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fab fa-twitter text-blue-400"></i> Twitter
                        </label>
                        <input type="url" name="twitter_url" value="<?php echo htmlspecialchars($settings['twitter_url']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="https://twitter.com/yourhandle">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fab fa-instagram text-pink-600"></i> Instagram
                        </label>
                        <input type="url" name="instagram_url" value="<?php echo htmlspecialchars($settings['instagram_url']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="https://instagram.com/yourhandle">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">
                            <i class="fab fa-youtube text-red-600"></i> YouTube
                        </label>
                        <input type="url" name="youtube_url" value="<?php echo htmlspecialchars($settings['youtube_url']); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="https://youtube.com/yourchannel">
                    </div>
                </div>
            </div>
            
            <!-- Payment Methods Section -->
            <div class="border-b pb-8 mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-credit-card text-primary"></i> Payment Methods
                </h2>
                <p class="text-gray-600 mb-6">Enable or disable payment methods for customers in the mobile app and website.</p>
                
                <div class="space-y-6">
                    <!-- Cash on Delivery Toggle -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-money-bill-wave text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Cash on Delivery (COD)</h3>
                                <p class="text-sm text-gray-500">Allow customers to pay when they receive</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="payment_cod_enabled" class="sr-only peer" 
                                   <?php echo ($settings['payment_cod_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>>
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-green-500"></div>
                        </label>
                    </div>
                    
                    <!-- eSewa Toggle -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-green-600 rounded-lg flex items-center justify-center">
                                <span class="text-white font-bold text-xl">e</span>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">eSewa Payment</h3>
                                <p class="text-sm text-gray-500">Allow customers to pay with eSewa wallet</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="payment_esewa_enabled" class="sr-only peer" 
                                   <?php echo ($settings['payment_esewa_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-green-600"></div>
                        </label>
                    </div>
                    
                    <!-- QR Payment Toggle -->
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-qrcode text-white text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">QR Code Payment</h3>
                                <p class="text-sm text-gray-500">Allow customers to scan and pay via QR</p>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="payment_qr_enabled" class="sr-only peer" id="qr_toggle"
                                   <?php echo ($settings['payment_qr_enabled'] ?? '0') == '1' ? 'checked' : ''; ?>>
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-blue-500"></div>
                        </label>
                    </div>
                    
                    <!-- QR Code Settings (shown when QR is enabled) -->
                    <div id="qr_settings" class="ml-16 p-4 bg-blue-50 rounded-lg border border-blue-200 <?php echo ($settings['payment_qr_enabled'] ?? '0') != '1' ? 'hidden' : ''; ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 font-medium mb-3">QR Code Image</label>
                                <?php if (!empty($settings['payment_qr_image'])): ?>
                                    <div class="mb-3 p-4 bg-white rounded-lg border">
                                        <img src="<?php echo htmlspecialchars($settings['payment_qr_image']); ?>" alt="Payment QR" class="h-32 mx-auto">
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="payment_qr_image" accept="image/jpeg,image/png,image/webp"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary bg-white">
                                <p class="text-xs text-gray-500 mt-2">Upload your payment QR code image</p>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-3">Payment Instructions</label>
                                <textarea name="payment_qr_instructions" rows="4"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary bg-white"
                                          placeholder="Instructions for QR payment"><?php echo htmlspecialchars($settings['payment_qr_instructions'] ?? ''); ?></textarea>
                                <p class="text-xs text-gray-500 mt-2">Displayed to customers when they select QR payment</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Save Button -->
            <div class="flex gap-3">
                <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium transition">
                    <i class="fas fa-save"></i> Save All Settings
                </button>
                <a href="/admin/dashboard.php" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-8 py-3 rounded-lg font-medium transition">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </form>
    </div>
</div>

<style>
    input[type="color"] {
        cursor: pointer;
        width: 60px;
    }
</style>

<script>
    // Toggle QR settings visibility
    document.getElementById('qr_toggle')?.addEventListener('change', function() {
        const qrSettings = document.getElementById('qr_settings');
        if (this.checked) {
            qrSettings.classList.remove('hidden');
        } else {
            qrSettings.classList.add('hidden');
        }
    });
</script>

<?php include '../includes/footer.php'; ?>
