<?php
// This file demonstrates how to load and use website settings in your PHP code
// Place this file for reference - you don't need to execute it

// Example: How to load and use settings in any PHP file

// Method 1: Load from settings.json file
function getWebsiteSettings() {
    $settings_file = dirname(__FILE__) . '/config/settings.json';
    
    if (file_exists($settings_file)) {
        return json_decode(file_get_contents($settings_file), true);
    }
    
    // Default settings if file doesn't exist yet
    return [
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

// Get all settings
$settings = getWebsiteSettings();

// Now you can use settings throughout your templates:
// Example in header.php:
// <title><?php echo $settings['website_name']; ?> - Your Store</title>

// Example in footer.php:
// <p><?php echo $settings['copyright_text']; ?></p>

// Example for logo:
// <img src="<?php echo $settings['header_logo']; ?>" alt="Logo">

// Example for social links:
// <?php if (!empty($settings['facebook_url'])): ?>
//     <a href="<?php echo $settings['facebook_url']; ?>">Facebook</a>
// <?php endif; ?>

// =========================================
// IMPORTANT IMPLEMENTATION NOTE
// =========================================
// 
// The /includes/header.php and /includes/footer.php files should be updated to use these settings.
// Here's what needs to be added:
//
// In header.php (after opening PHP tag):
// <?php
// $settings = getWebsiteSettings();
// ... rest of header code ...
// Then use: <?php echo $settings['website_name']; ?>
//
// In footer.php:
// <?php
// $settings = getWebsiteSettings();
// ... rest of footer code ...
// Then use: <?php echo $settings['copyright_text']; ?>
//
// =========================================

// Function to get a specific setting
function getSetting($key, $default = null) {
    $settings = getWebsiteSettings();
    return $settings[$key] ?? $default;
}

// Usage:
// $website_name = getSetting('website_name', 'SASTO Hub');
// $primary_color = getSetting('primary_color', '#4f46e5');

// =========================================
// HOW TO INTEGRATE IN HEADER/FOOTER
// =========================================
?>

<!-- EXAMPLE: How to update header.php -->
<!--
<?php
require_once dirname(__FILE__) . '/../config/config.php';
$settings = getWebsiteSettings();
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($settings['website_name']); ?> - Shop</title>
    <meta name="theme-color" content="<?php echo $settings['primary_color']; ?>">
</head>
<body>
    <header>
        <img src="<?php echo htmlspecialchars($settings['header_logo']); ?>" alt="<?php echo htmlspecialchars($settings['website_name']); ?>">
        <h1><?php echo htmlspecialchars($settings['website_name']); ?></h1>
    </header>
-->

<!-- EXAMPLE: How to update footer.php -->
<!--
<?php
$settings = getWebsiteSettings();
?>
    <footer>
        <div class="footer-content">
            <img src="<?php echo htmlspecialchars($settings['footer_logo']); ?>" alt="Logo">
            <p><?php echo htmlspecialchars($settings['footer_name']); ?></p>
            <p><?php echo htmlspecialchars($settings['copyright_text']); ?></p>
            
            <div class="contact-info">
                <p>📧 <?php echo htmlspecialchars($settings['contact_email']); ?></p>
                <p>📞 <?php echo htmlspecialchars($settings['contact_phone']); ?></p>
                <p>📍 <?php echo htmlspecialchars($settings['address']); ?></p>
            </div>
            
            <div class="social-links">
                <?php if (!empty($settings['facebook_url'])): ?>
                    <a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>" target="_blank">
                        <i class="fab fa-facebook"></i> Facebook
                    </a>
                <?php endif; ?>
                <?php if (!empty($settings['twitter_url'])): ?>
                    <a href="<?php echo htmlspecialchars($settings['twitter_url']); ?>" target="_blank">
                        <i class="fab fa-twitter"></i> Twitter
                    </a>
                <?php endif; ?>
                <?php if (!empty($settings['instagram_url'])): ?>
                    <a href="<?php echo htmlspecialchars($settings['instagram_url']); ?>" target="_blank">
                        <i class="fab fa-instagram"></i> Instagram
                    </a>
                <?php endif; ?>
                <?php if (!empty($settings['youtube_url'])): ?>
                    <a href="<?php echo htmlspecialchars($settings['youtube_url']); ?>" target="_blank">
                        <i class="fab fa-youtube"></i> YouTube
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</body>
-->

<!-- EXAMPLE: How to use in theme color CSS -->
<!--
<style>
    :root {
        --primary-color: <?php echo $settings['primary_color']; ?>;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
    }
</style>
-->

<?php
/*
===========================================
SETTINGS FILE LOCATION & STRUCTURE
===========================================

File: /config/settings.json

Structure:
{
  "website_name": "SASTO Hub",
  "website_tagline": "Your Online Marketplace",
  "header_logo": "/assets/images/logo_header_1701234567.png",
  "footer_logo": "/assets/images/logo_footer_1701234568.png",
  "footer_name": "SASTO Hub",
  "copyright_text": "© 2025 SASTO Hub. All rights reserved.",
  "primary_color": "#4f46e5",
  "contact_email": "info@sastohub.com",
  "contact_phone": "+977 1234567890",
  "address": "Kathmandu, Nepal",
  "facebook_url": "https://facebook.com/sastohub",
  "twitter_url": "https://twitter.com/sastohub",
  "instagram_url": "https://instagram.com/sastohub",
  "youtube_url": "https://youtube.com/sastohub"
}

===========================================
ACCESSING SETTINGS
===========================================

In any PHP file:

1. Add function to config.php or include it:
   require_once dirname(__FILE__) . '/config/config.php';

2. Get all settings:
   $settings = getWebsiteSettings();

3. Get specific setting:
   $name = getSetting('website_name', 'Default Name');

4. Use in template:
   <h1><?php echo htmlspecialchars($settings['website_name']); ?></h1>

===========================================
MODIFYING SETTINGS
===========================================

Only admins can modify through:
/admin/settings.php

The form:
- Validates all input
- Handles file uploads
- Updates JSON file
- Maintains backups (optional)

===========================================
CACHE CONSIDERATIONS
===========================================

If you implement caching later:

1. Cache settings in _SESSION:
   $_SESSION['settings'] = getWebsiteSettings();

2. Clear cache when settings change:
   unset($_SESSION['settings']);

3. Or cache in static variable:
   static $cached_settings = null;
   if (!$cached_settings) {
       $cached_settings = getWebsiteSettings();
   }
   return $cached_settings;

===========================================
*/
?>
