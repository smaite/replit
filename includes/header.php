<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SASTO Hub - Multi-Vendor Shopping Platform">
    <meta name="theme-color" content="#4F46E5">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $page_title ?? 'SASTO Hub - Shop from Multiple Vendors'; ?></title>
    
    <!-- PWA Manifest & Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="/assets/images/icon-192.png">
    <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SASTO Hub">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#4F46E5">
    <meta name="msapplication-TileColor" content="#4F46E5">
    <meta name="msapplication-TileImage" content="/assets/images/icon-144.png">
    
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
        
        // Service Worker Registration for PWA
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js').then(function(registration) {
                console.log('Service Worker registered successfully:', registration);
            }).catch(function(error) {
                console.log('Service Worker registration failed:', error);
            });
        }
        
        // Show install prompt on install button click
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            const installBtn = document.getElementById('install-btn');
            if (installBtn) {
                installBtn.style.display = 'block';
            }
        });
        
        function installApp() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                });
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <!-- Top Bar -->
        <div class="bg-primary text-white py-2">
            <div class="container mx-auto px-4 flex justify-between items-center text-sm">
                <div class="flex items-center gap-4">
                    <span><i class="fas fa-phone"></i> +977-14000000</span>
                    <span><i class="fas fa-envelope"></i> info@sastohub.com</span>
                </div>
                <div class="flex items-center gap-4">
                    <?php if (isLoggedIn()): ?>
                        <span>Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</span>
                        <?php if (isAdmin()): ?>
                            <a href="/admin/" class="hover:text-gray-200"><i class="fas fa-cog"></i> Admin Panel</a>
                        <?php elseif (isVendor()): ?>
                            <a href="/vendor/" class="hover:text-gray-200"><i class="fas fa-store"></i> Vendor Dashboard</a>
                        <?php else: ?>
                            <a href="/pages/dashboard.php" class="hover:text-gray-200"><i class="fas fa-user-circle"></i> Dashboard</a>
                        <?php endif; ?>
                        <a href="/auth/logout.php" class="hover:text-gray-200"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    <?php else: ?>
                        <a href="/auth/login.php" class="hover:text-gray-200"><i class="fas fa-sign-in-alt"></i> Login</a>
                        <a href="/auth/register.php" class="hover:text-gray-200"><i class="fas fa-user-plus"></i> Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Main Navigation -->
        <nav class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <!-- Logo -->
                <a href="/" class="text-3xl font-bold text-primary">
                    <i class="fas fa-shopping-bag"></i> SASTO Hub
                </a>
                
                <!-- Search Bar -->
                <div class="flex-1 mx-8 max-w-2xl">
                    <form action="/pages/search.php" method="GET" class="relative">
                        <input type="text" name="q" placeholder="Search products..." 
                               class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <!-- Icons -->
                <div class="flex items-center gap-6">
                    <a href="/pages/cart.php" class="relative text-gray-700 hover:text-primary text-xl">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo $_SESSION['cart_count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="/pages/dashboard.php" class="text-gray-700 hover:text-primary text-xl" title="Dashboard">
                            <i class="fas fa-user-circle"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Categories Menu -->
            <div class="mt-4 border-t pt-4">
                <div class="flex items-center gap-6 text-sm">
                    <a href="/" class="text-gray-700 hover:text-primary font-medium"><i class="fas fa-home"></i> Home</a>
                    <a href="/pages/products.php" class="text-gray-700 hover:text-primary font-medium">All Products</a>
                    <a href="/pages/products.php?featured=1" class="text-gray-700 hover:text-primary font-medium">Featured</a>
                    <a href="/pages/products.php?sale=1" class="text-red-600 hover:text-red-700 font-medium"><i class="fas fa-fire"></i> Flash Sale</a>
                    <?php if (isLoggedIn() && !isVendor() && !isAdmin()): ?>
                        <a href="/auth/become-vendor.php" class="text-gray-700 hover:text-primary font-medium"><i class="fas fa-store"></i> Become a Vendor</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>
    
    <!-- Main Content -->
    <main class="min-h-screen">
