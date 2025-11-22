<?php
// Vendor Header - Custom header for vendor panel
if (!defined('SITE_NAME')) {
    require_once dirname(__FILE__) . '/../config/config.php';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SASTO Hub Vendor Dashboard">
    <meta name="theme-color" content="#4F46E5">
    <title><?php echo $page_title ?? 'Vendor Dashboard - SASTO Hub'; ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4F46E5',
                        secondary: '#EC4899',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Vendor Header -->
    <header class="bg-primary text-white sticky top-0 z-50 shadow-lg">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="/vendor/" class="text-2xl font-bold">
                    <i class="fas fa-store"></i> Vendor Dashboard
                </a>
            </div>
            
            <div class="flex items-center gap-6">
                <a href="/" class="hover:text-indigo-200" title="Go to Website">
                    <i class="fas fa-globe text-lg"></i>
                </a>
                <div class="relative group">
                    <button class="flex items-center gap-2 hover:text-indigo-200">
                        <i class="fas fa-user-circle text-2xl"></i>
                        <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                        <i class="fas fa-chevron-down text-sm"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-48 bg-white text-gray-900 rounded-lg shadow-lg hidden group-hover:block z-50">
                        <a href="/vendor/documents.php" class="block px-4 py-2 hover:bg-gray-100 border-b">
                            <i class="fas fa-file-upload mr-2"></i> My Documents
                        </a>
                        <a href="/vendor/settings.php" class="block px-4 py-2 hover:bg-gray-100 border-b">
                            <i class="fas fa-store mr-2"></i> Shop Settings
                        </a>
                        <a href="/pages/settings.php" class="block px-4 py-2 hover:bg-gray-100">
                            <i class="fas fa-cog mr-2"></i> Account Settings
                        </a>
                        <a href="/pages/dashboard.php" class="block px-4 py-2 hover:bg-gray-100">
                            <i class="fas fa-home mr-2"></i> My Dashboard
                        </a>
                        <a href="/auth/logout.php" class="block px-4 py-2 hover:bg-gray-100">
                            <i class="fas fa-sign-out-alt mr-2"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
