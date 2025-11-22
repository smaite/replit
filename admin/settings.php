<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('/auth/login.php');
}

$error = '';
$success = '';

// Get current settings
$settings_file = '../config/settings.json';
$settings = [];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
} else {
    // Default settings
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
        'youtube_url' => ''
    ];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
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
        
        if (!empty($_FILES['footer_logo']['name'])) {
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
        
        // Save settings if no error
        if (empty($error)) {
            if (file_put_contents($settings_file, json_encode($new_settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))) {
                $settings = $new_settings;
                $success = 'Settings saved successfully!';
            } else {
                $error = 'Failed to save settings. Please check file permissions.';
            }
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

<?php include '../includes/footer.php'; ?>
