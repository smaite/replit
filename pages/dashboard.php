<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch recent orders (last 5)
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll();

// Fetch order count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$order_count = $stmt->fetch()['total'];

// Fetch total spent
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_spent = $stmt->fetch()['total'] ?? 0;

// Fetch pending orders
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ? AND status IN ('pending', 'confirmed', 'processing', 'shipped')");
$stmt->execute([$_SESSION['user_id']]);
$pending_orders = $stmt->fetch()['total'];

// Fetch wishlist count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$wishlist_count = $stmt->fetch()['total'];

$page_title = 'Dashboard - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Welcome Section -->
    <div class="mb-8">
        <div class="bg-gradient-to-r from-primary to-indigo-700 text-white rounded-lg shadow-lg p-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-4xl font-bold mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h1>
                    <p class="text-indigo-200">Manage your account, orders, and shopping preferences</p>
                </div>
                <div class="text-6xl opacity-20">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-primary">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Total Orders</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $order_count; ?></p>
                </div>
                <div class="text-4xl text-primary opacity-30">
                    <i class="fas fa-shopping-bag"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Pending Orders</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $pending_orders; ?></p>
                </div>
                <div class="text-4xl text-blue-500 opacity-30">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Total Spent</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo formatPrice($total_spent); ?></p>
                </div>
                <div class="text-4xl text-green-500 opacity-30">
                    <i class="fas fa-wallet"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-red-500">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-gray-600 text-sm font-medium mb-1">Wishlist Items</p>
                    <p class="text-3xl font-bold text-gray-900"><?php echo $wishlist_count; ?></p>
                </div>
                <div class="text-4xl text-red-500 opacity-30">
                    <i class="fas fa-heart"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Sidebar -->
        <div class="lg:col-span-1">
            <!-- Profile Card -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-gradient-to-br from-primary to-indigo-700 text-white rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-4">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
                    <div class="mt-3">
                        <span class="inline-block px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-medium uppercase">
                            <?php echo $_SESSION['user_role']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <p class="text-xs text-gray-500 uppercase font-semibold mb-3">Account Joined</p>
                    <p class="text-sm text-gray-700 font-medium">
                        <?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                    </p>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h3 class="font-bold text-gray-900">Quick Links</h3>
                </div>
                <ul class="divide-y">
                    <li>
                        <a href="/pages/order-history.php" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-history text-primary"></i>
                                Order History
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </li>
                    <li>
                        <a href="/pages/wishlist.php" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-heart text-red-500"></i>
                                Wishlist
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </li>
                    <li>
                        <a href="/pages/address-book.php" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-map-marker-alt text-blue-500"></i>
                                Addresses
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </li>
                    <li>
                        <a href="/pages/settings.php" class="flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-cog text-gray-600"></i>
                                Settings
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Right Content -->
        <div class="lg:col-span-2">
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Recent Orders</h2>
                    <a href="/pages/order-history.php" class="text-primary hover:text-indigo-700 font-medium text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <?php if (empty($recent_orders)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Orders Yet</h3>
                        <p class="text-gray-600 mb-6">Start shopping and your orders will appear here</p>
                        <a href="/pages/products.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                            <i class="fas fa-shopping-bag"></i> Browse Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <p class="font-bold text-gray-900">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-primary"><?php echo formatPrice($order['total_amount']); ?></p>
                                        <span class="inline-block mt-1 px-3 py-1 text-xs rounded-full font-medium <?php 
                                            echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' : 
                                                 ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 
                                                  ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700')); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-gray-600">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(substr($order['shipping_address'], 0, 40)) . '...'; ?>
                                    </div>
                                    <a href="/pages/order-details.php?id=<?php echo $order['id']; ?>" class="text-primary hover:text-indigo-700 text-sm font-medium">
                                        View <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
