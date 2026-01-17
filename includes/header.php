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
        
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js').then(function(registration) {
                console.log('Service Worker registered successfully:', registration);
            }).catch(function(error) {
                console.log('Service Worker registration failed:', error);
            });
        }
        
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
    <style>
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            display: none;
            z-index: 40;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
        }
        
        @media (max-width: 768px) {
            .bottom-nav {
                display: flex;
            }
            
            body {
                padding-bottom: 70px;
            }
            
            .nav-item {
                flex: 1;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 8px 4px;
                text-decoration: none;
                color: #6b7280;
                font-size: 11px;
                border-radius: 0;
                transition: all 0.3s ease;
            }
            
            .nav-item:hover, .nav-item.active {
                color: #4F46E5;
                background: #f3f4f6;
            }
            
            .nav-item i {
                font-size: 22px;
                margin-bottom: 4px;
            }
            
            .nav-item span {
                display: block;
                text-align: center;
            }
            
            .top-bar {
                display: none;
            }
            
            .main-nav {
                padding: 12px 16px !important;
            }
            
            .search-bar {
                display: none;
            }
            
            .logo {
                font-size: 20px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="top-bar bg-primary text-white py-2">
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
                            <a href="/seller/" class="hover:text-gray-200"><i class="fas fa-store"></i> Seller Dashboard</a>
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
        
        <nav class="main-nav container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <a href="/" class="logo text-2xl md:text-3xl font-bold text-primary whitespace-nowrap">
                    <i class="fas fa-shopping-bag"></i> SASTO HUB
                </a>
                
                <div class="search-bar flex-1 mx-4 md:mx-8 max-w-2xl hidden md:block">
                    <form action="/pages/search.php" method="GET" class="relative">
                        <input type="text" name="q" placeholder="Search products..." 
                               class="w-full px-4 py-3 pr-12 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                        <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                
                <div class="header-icons flex items-center gap-4 md:gap-6">
                    <a href="/pages/cart.php" class="relative text-gray-700 hover:text-primary text-xl" title="Cart">
                        <i class="fas fa-shopping-cart"></i>
                        <?php if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo $_SESSION['cart_count']; ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <?php if (isLoggedIn()): ?>
                        <a href="/pages/dashboard.php" class="text-gray-700 hover:text-primary text-xl hidden sm:block" title="Dashboard">
                            <i class="fas fa-user-circle"></i>
                        </a>
                    <?php else: ?>
                        <a href="/auth/login.php" class="text-gray-700 hover:text-primary text-xl hidden sm:block" title="Login">
                            <i class="fas fa-user"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        
        <div class="md:hidden px-4 pb-3">
            <form action="/pages/search.php" method="GET" class="relative">
                <input type="text" name="q" placeholder="Search..." 
                       class="w-full px-4 py-2 pr-10 border-2 border-gray-300 rounded-lg focus:outline-none focus:border-primary text-sm">
                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-primary">
                    <i class="fas fa-search"></i>
                </button>
            </form>
        </div>
    </header>
    
    <div class="hidden md:block bg-gray-100 border-b">
        <div class="container mx-auto px-4">
            <div class="flex gap-8 py-2 text-sm font-medium flex-wrap">
                <a href="/" class="text-gray-700 hover:text-primary"><i class="fas fa-home"></i> Home</a>
                <a href="/pages/products.php" class="text-gray-700 hover:text-primary"><i class="fas fa-th"></i> All Products</a>
                <a href="/pages/products.php?featured=1" class="text-gray-700 hover:text-primary"><i class="fas fa-star"></i> Featured</a>
                <a href="/pages/products.php?sale=1" class="text-red-600 hover:text-red-700"><i class="fas fa-fire"></i> Flash Sale</a>
                <?php if (isLoggedIn() && !isVendor() && !isAdmin()): ?>
                    <a href="/auth/become-vendor.php" class="text-gray-700 hover:text-primary"><i class="fas fa-store"></i> Become Vendor</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <nav class="bottom-nav">
        <a href="/" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="/pages/products.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'products') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-th"></i>
            <span>Shop</span>
        </a>
        <a href="/pages/search.php" class="nav-item">
            <i class="fas fa-search"></i>
            <span>Search</span>
        </a>
        <a href="/pages/cart.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'cart') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Cart</span>
        </a>
        <?php if (isLoggedIn()): ?>
            <a href="/pages/dashboard.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Account</span>
            </a>
        <?php else: ?>
            <a href="/auth/login.php" class="nav-item">
                <i class="fas fa-sign-in-alt"></i>
                <span>Login</span>
            </a>
        <?php endif; ?>
    </nav>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInputs = document.querySelectorAll('input[name="q"]');
            
            searchInputs.forEach(input => {
                // Create suggestions container
                const container = document.createElement('div');
                container.id = 'search-suggestions-' + Math.random().toString(36).substr(2, 9);
                container.className = 'absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-b-lg shadow-lg z-50 hidden max-h-96 overflow-y-auto';
                input.parentNode.appendChild(container);
                
                let timeout = null;
                
                input.addEventListener('input', function() {
                    const query = this.value.trim();
                    const currentContainer = this.parentNode.querySelector('div[id^="search-suggestions-"]');
                    
                    clearTimeout(timeout);
                    
                    if (query.length < 2) {
                        currentContainer.classList.add('hidden');
                        currentContainer.innerHTML = '';
                        return;
                    }
                    
                    timeout = setTimeout(() => {
                        fetch(`/api/search_suggestions.php?q=${encodeURIComponent(query)}`)
                            .then(response => response.json())
                            .then(data => {
                                if ((data.categories && data.categories.length > 0) || (data.products && data.products.length > 0)) {
                                    let html = '';
                                    
                                    // Categories
                                    if (data.categories.length > 0) {
                                        html += '<div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Categories</div>';
                                        data.categories.forEach(cat => {
                                            const imageHtml = cat.image 
                                                ? `<img src="${cat.image}" class="w-8 h-8 object-cover rounded-full mr-3 border border-gray-200">`
                                                : `<div class="w-8 h-8 bg-gray-100 rounded-full mr-3 flex items-center justify-center text-gray-400 border border-gray-200"><i class="fas fa-th text-xs"></i></div>`;
                                                
                                            html += `
                                                <a href="/pages/products.php?slug=${cat.slug}" class="flex items-center px-4 py-2 hover:bg-gray-100 text-gray-800 border-b last:border-0">
                                                    ${imageHtml}
                                                    <span class="font-medium">${cat.name}</span>
                                                </a>
                                            `;
                                        });
                                    }
                                    
                                    // Products
                                    if (data.products.length > 0) {
                                        html += '<div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500 uppercase">Products</div>';
                                        data.products.forEach(prod => {
                                            const price = new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(prod.sale_price || prod.price);
                                            html += `
                                                <a href="/pages/product-detail.php?slug=${prod.slug}" class="flex items-center px-4 py-2 hover:bg-gray-100 border-b last:border-0">
                                                    <img src="${prod.image_path || 'https://via.placeholder.com/40'}" class="w-10 h-10 object-cover rounded mr-3">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">${prod.name}</div>
                                                        <div class="text-xs text-primary font-bold">${price}</div>
                                                    </div>
                                                </a>
                                            `;
                                        });
                                    }
                                    
                                    currentContainer.innerHTML = html;
                                    currentContainer.classList.remove('hidden');
                                } else {
                                    currentContainer.classList.add('hidden');
                                }
                            })
                            .catch(err => console.error('Search error:', err));
                    }, 300);
                });
                
                // Hide on click outside
                document.addEventListener('click', function(e) {
                    if (!input.contains(e.target) && !container.contains(e.target)) {
                        container.classList.add('hidden');
                    }
                });
            });
        });
    </script>
    <main class="min-h-screen">
