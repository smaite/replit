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
                        primary: '#4f46e5', // Indigo-600
                        secondary: '#F5841F', // App Orange
                        dark: '#1e293b'
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
                color: #4f46e5;
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
        }

        /* Custom Scrollbar for dropdowns */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }
    </style>
</head>
<?php $contact_phone = getSetting('contact_phone');?>
<body class="bg-gray-50 font-sans text-gray-800">

    <!-- Main Header -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-20 gap-8">

                <!-- Logo -->
                <a href="/" class="flex-shrink-0 flex items-center gap-2">
                    <span class="text-3xl font-bold text-gray-900 tracking-tight">sasto<span class="text-primary">hub</span></span>
                    <div class="w-1.5 h-1.5 bg-primary rounded-full mt-3"></div>
                    <div class="w-1.5 h-1.5 bg-secondary rounded-full mt-3"></div>
                </a>

                <!-- Search Bar -->
                <div class="flex-1 max-w-3xl hidden md:block">
                    <form action="/pages/search.php" method="GET" class="relative group">
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-primary">
                                <i class="fas fa-sparkles"></i>
                            </span>
                            <input type="text" name="q" placeholder="Discover new products..."
                                   class="w-full pl-10 pr-14 py-3 bg-gray-50 border border-gray-200 rounded-full focus:outline-none focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 transition-all text-sm">
                            <button type="submit" class="absolute right-1.5 top-1.5 bottom-1.5 w-10 bg-primary hover:bg-indigo-700 text-white rounded-full flex items-center justify-center transition">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <!-- Search Suggestions Container (populated by JS) -->
                    </form>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center gap-6 flex-shrink-0">

                    <!-- Location -->
                    <?php
                    $delivery_location = getUserLocation();
                    if (isLoggedIn()) {
                        try {
                            $stmt = $conn->prepare("SELECT city FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC LIMIT 1");
                            $stmt->execute([$_SESSION['user_id']]);
                            $loc = $stmt->fetch();
                            if ($loc) $delivery_location = $loc['city'];
                        } catch (Exception $e) {}
                    }
                    ?>
                    <a href="/pages/address-book.php" class="hidden xl:flex items-center gap-3 text-left hover:bg-gray-50 p-2 rounded-lg transition group">
                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 group-hover:text-primary group-hover:bg-primary/10 transition">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <span class="block text-[10px] text-gray-500 leading-tight">Delivering to</span>
                            <span class="block text-xs font-bold text-gray-900 truncate max-w-[100px]" data-location-display><?php echo htmlspecialchars($delivery_location); ?></span>
                        </div>
                    </a>

                    <!-- Country/Currency -->
                    <div class="hidden lg:flex items-center gap-2 border-l border-r border-gray-100 px-4 h-8">
                        <img src="https://flagcdn.com/w20/np.png" alt="Nepal" class="w-5 h-auto rounded-sm shadow-sm">
                        <span class="text-sm font-medium text-gray-700">NP</span>
                    </div>

                    <!-- Cart -->
                    <a href="/pages/cart.php" class="flex items-center gap-3 hover:text-primary transition group relative">
                        <div class="relative">
                            <i class="fas fa-shopping-cart text-xl text-gray-700 group-hover:text-primary transition"></i>
                            <?php if (isset($_SESSION['cart_count']) && $_SESSION['cart_count'] > 0): ?>
                                <span class="absolute -top-2.5 -right-2.5 bg-secondary text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center border-2 border-white shadow-sm">
                                    <?php echo $_SESSION['cart_count']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="hidden sm:block">
                            <span class="block text-xs text-gray-500">Cart</span>
                            <span class="block text-sm font-bold text-gray-900">Items</span>
                        </div>
                    </a>

                    <!-- Account -->
                    <?php if (isLoggedIn()): ?>
                        <div class="relative group cursor-pointer">
                            <a href="/pages/dashboard.php" class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold border border-primary/20">
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                </div>
                                <div class="hidden sm:block text-left">
                                    <span class="block text-xs text-gray-500">Welcome</span>
                                    <span class="block text-sm font-bold text-gray-900 max-w-[100px] truncate"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                                </div>
                            </a>
                            <!-- Dropdown -->
                            <div class="absolute right-0 top-full mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 hidden group-hover:block py-2">
                                <?php if (isAdmin()): ?>
                                    <a href="/admin/" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary"><i class="fas fa-cog w-5"></i> Admin Panel</a>
                                <?php elseif (isVendor()): ?>
                                    <a href="/seller/" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary"><i class="fas fa-store w-5"></i> Seller Panel</a>
                                <?php endif; ?>
                                <a href="/pages/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary"><i class="fas fa-user-circle w-5"></i> Dashboard</a>
                                <a href="/pages/order-history.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary"><i class="fas fa-box w-5"></i> My Orders</a>
                                <div class="border-t border-gray-100 my-1"></div>
                                <a href="/auth/logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt w-5"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="/auth/login.php" class="flex items-center gap-3 hover:text-primary transition group">
                            <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 group-hover:text-primary group-hover:bg-primary/10 transition">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="hidden sm:block">
                                <span class="block text-xs text-gray-500">Account</span>
                                <span class="block text-sm font-bold text-gray-900">Sign In</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mobile Search (Visible only on mobile) -->
        <div class="md:hidden px-4 pb-4 border-b border-gray-100">
            <form action="/pages/search.php" method="GET" class="relative">
                <input type="text" name="q" placeholder="Search products..."
                       class="w-full pl-10 pr-4 py-2.5 bg-gray-100 border-none rounded-lg focus:ring-1 focus:ring-primary text-sm">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <i class="fas fa-search"></i>
                </span>
            </form>
        </div>
    </header>

    <!-- Secondary Nav (Categories Strip) -->
    <div class="hidden md:block border-b border-gray-200 bg-white">
        <div class="container mx-auto px-4">
            <div class="flex items-center gap-8">
                <!-- All Categories Dropdown Trigger -->
                <div class="relative group py-3 cursor-pointer">
                    <div class="flex items-center gap-2 text-sm font-bold text-gray-900">
                        <i class="fas fa-th-large text-primary"></i>
                        <span>All Categories</span>
                        <i class="fas fa-chevron-down text-xs text-gray-400 group-hover:text-primary transition"></i>
                    </div>
                    <!-- Dropdown Content (Mock for now, could be dynamic) -->
                    <div class="absolute top-full left-0 w-56 bg-white shadow-xl rounded-b-xl border border-gray-100 hidden group-hover:block z-40 py-2">
                        <a href="/pages/products.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Browse All</a>
                        <a href="/pages/products.php?sort=newest" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">New Arrivals</a>
                        <a href="/pages/products.php?featured=1" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-primary">Featured</a>
                    </div>
                </div>

                <!-- Nav Links -->
                <div class="flex items-center gap-6 text-sm font-medium text-gray-600">
                    <a href="/pages/products.php?category=electronics" class="hover:text-primary transition">Electronics</a>
                    <a href="/pages/products.php?category=fashion" class="hover:text-primary transition">Fashion</a>
                    <a href="/pages/products.php?category=home" class="hover:text-primary transition">Home & Living</a>
                    <a href="/pages/products.php?featured=1" class="hover:text-primary transition">Featured</a>
                    <a href="/pages/products.php?sale=1" class="text-red-600 hover:text-red-700 flex items-center gap-1">
                        <i class="fas fa-bolt"></i> Flash Deals
                    </a>
                </div>

                <div class="flex-1"></div>

                <!-- Right Side Links -->
                <div class="flex items-center gap-6 text-sm font-medium text-gray-500">
                     <?php if (isLoggedIn() && !isVendor() && !isAdmin()): ?>
                        <a href="/auth/become-vendor.php" class="hover:text-primary transition">Become a Seller</a>
                    <?php endif; ?>
                    <a href="tel:<?php echo htmlspecialchars($contact_phone); ?>" class="hover:text-primary transition flex items-center gap-1">
                        <i class="fas fa-headset"></i> Support
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="/" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="/pages/products.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'products') !== false) ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
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
                container.className = 'absolute top-full left-0 right-0 bg-white border border-gray-200 rounded-2xl shadow-xl z-50 hidden max-h-96 overflow-y-auto mt-2';
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
                                        html += '<div class="bg-gray-50 px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Categories</div>';
                                        data.categories.forEach(cat => {
                                            const imageHtml = cat.image
                                                ? `<img src="${cat.image}" class="w-8 h-8 object-cover rounded-lg mr-3 border border-gray-200">`
                                                : `<div class="w-8 h-8 bg-white rounded-lg mr-3 flex items-center justify-center text-gray-400 border border-gray-200"><i class="fas fa-th text-xs"></i></div>`;

                                            html += `
                                                <a href="/pages/products.php?slug=${cat.slug}" class="flex items-center px-5 py-3 hover:bg-gray-50 text-gray-800 border-b border-gray-50 last:border-0 transition">
                                                    ${imageHtml}
                                                    <span class="font-medium">${cat.name}</span>
                                                </a>
                                            `;
                                        });
                                    }

                                    // Products
                                    if (data.products.length > 0) {
                                        html += '<div class="bg-gray-50 px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Products</div>';
                                        data.products.forEach(prod => {
                                            const price = new Intl.NumberFormat('en-NP', { style: 'currency', currency: 'NPR' }).format(prod.sale_price || prod.price);
                                            html += `
                                                <a href="/pages/product-detail.php?slug=${prod.slug}" class="flex items-center px-5 py-3 hover:bg-gray-50 border-b border-gray-50 last:border-0 transition">
                                                    <img src="${prod.image_path || 'https://via.placeholder.com/40'}" class="w-10 h-10 object-cover rounded-lg mr-3 border border-gray-200">
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

        // Browser Geolocation - asks for permission and sends to API
        (function() {
            // Only request if we don't have a cached location or it's from IP
            const hasGeoPermission = sessionStorage.getItem('geoRequested');
            
            if (!hasGeoPermission && navigator.geolocation) {
                sessionStorage.setItem('geoRequested', 'true');
                
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Success - send coordinates to API
                        fetch('/api/geolocation.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                lat: position.coords.latitude,
                                lng: position.coords.longitude
                            })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.city) {
                                // Update the location display without page reload
                                const locSpan = document.querySelector('[data-location-display]');
                                if (locSpan) locSpan.textContent = data.city;
                                console.log('📍 Location updated to:', data.city);
                            }
                        })
                        .catch(err => console.log('Geolocation API error:', err));
                    },
                    function(error) {
                        console.log('Geolocation denied or unavailable:', error.message);
                    },
                    { timeout: 10000, maximumAge: 600000 }
                );
            }
        })();
    </script>
    <main class="min-h-screen">
