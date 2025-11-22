<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

// Get dashboard statistics
$stats = [
    'total_users' => $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'")->fetch()['count'],
    'total_vendors' => $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'approved'")->fetch()['count'],
    'pending_vendors' => $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'")->fetch()['count'],
    'total_products' => $conn->query("SELECT COUNT(*) as count FROM products")->fetch()['count'],
    'total_orders' => $conn->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'],
    'pending_orders' => $conn->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'confirmed')")->fetch()['count'],
    'total_categories' => $conn->query("SELECT COUNT(*) as count FROM categories WHERE parent_id IS NULL")->fetch()['count'],
    'total_revenue' => $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid'")->fetch()['total'],
];

// Get recent activities
$recent_orders = $conn->query("SELECT o.id, o.order_number, u.full_name, o.total_amount, o.status, o.created_at 
                               FROM orders o 
                               JOIN users u ON o.user_id = u.id 
                               ORDER BY o.created_at DESC LIMIT 5")->fetchAll();

$recent_vendors = $conn->query("SELECT v.id, v.shop_name, u.full_name, v.status, v.created_at 
                               FROM vendors v 
                               JOIN users u ON v.user_id = u.id 
                               WHERE v.status = 'pending'
                               ORDER BY v.created_at DESC LIMIT 5")->fetchAll();

$page_title = 'Admin Dashboard - SASTO Hub';
include '../includes/admin_header.php';
?>

<div class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <!-- Welcome Section -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900">
                <i class="fas fa-tachometer-alt text-primary"></i> Admin Dashboard
            </h1>
            <p class="text-gray-600 mt-2">Welcome back! Here's your platform overview</p>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Users Card -->
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition p-6 border-l-4 border-blue-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">👥 Total Customers</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_users']; ?></p>
                    </div>
                    <a href="/admin/users.php" class="text-blue-500 hover:text-blue-700">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Vendors Card -->
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition p-6 border-l-4 border-green-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">🏪 Active Vendors</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_vendors']; ?></p>
                        <p class="text-xs text-yellow-600 mt-1"><?php echo $stats['pending_vendors']; ?> pending</p>
                    </div>
                    <a href="/admin/vendors.php" class="text-green-500 hover:text-green-700">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Products Card -->
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition p-6 border-l-4 border-purple-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">📦 Total Products</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_products']; ?></p>
                    </div>
                    <a href="/admin/products.php" class="text-purple-500 hover:text-purple-700">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Orders Card -->
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition p-6 border-l-4 border-orange-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">🛒 Total Orders</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_orders']; ?></p>
                        <p class="text-xs text-orange-600 mt-1"><?php echo $stats['pending_orders']; ?> pending</p>
                    </div>
                    <a href="/admin/orders.php" class="text-orange-500 hover:text-orange-700">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Categories Card -->
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition p-6 border-l-4 border-indigo-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">🏷️ Categories</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo $stats['total_categories']; ?></p>
                    </div>
                    <a href="/admin/categories.php" class="text-indigo-500 hover:text-indigo-700">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- Revenue Card -->
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition p-6 border-l-4 border-emerald-500">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-gray-600 text-sm font-medium mb-1">💰 Total Revenue</p>
                        <p class="text-3xl font-bold text-gray-900"><?php echo formatPrice($stats['total_revenue']); ?></p>
                    </div>
                    <span class="text-emerald-500">
                        <i class="fas fa-chart-line"></i>
                    </span>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column - Quick Actions -->
            <div>
                <!-- Navigation Menu -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Management</h2>
                    <div class="space-y-2">
                        <a href="/admin/users.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-users w-5 text-blue-500"></i>
                                <span class="font-medium text-gray-700">Users</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="/admin/vendors.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-store w-5 text-green-500"></i>
                                <span class="font-medium text-gray-700">Vendors</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="/admin/products.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-box w-5 text-purple-500"></i>
                                <span class="font-medium text-gray-700">Products</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="/admin/categories.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-tags w-5 text-indigo-500"></i>
                                <span class="font-medium text-gray-700">Categories</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="/admin/orders.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-shopping-cart w-5 text-orange-500"></i>
                                <span class="font-medium text-gray-700">Orders</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </div>
                </div>

                <!-- Support & Settings -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Other</h2>
                    <div class="space-y-2">
                        <a href="/admin/settings.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-cog w-5 text-gray-500"></i>
                                <span class="font-medium text-gray-700">Settings</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                        <a href="/admin/reports.php" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition">
                            <span class="flex items-center gap-3">
                                <i class="fas fa-chart-bar w-5 text-cyan-500"></i>
                                <span class="font-medium text-gray-700">Reports</span>
                            </span>
                            <i class="fas fa-chevron-right text-gray-400"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Middle & Right Column - Recent Activities -->
            <div class="lg:col-span-2">
                <!-- Recent Orders -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-gray-900">Recent Orders</h2>
                        <a href="/admin/orders.php" class="text-primary hover:text-indigo-700 text-sm font-medium">View All</a>
                    </div>
                    
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-gray-500 text-sm py-4">No orders yet</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-900">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($order['full_name']); ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-bold text-primary"><?php echo formatPrice($order['total_amount']); ?></p>
                                            <span class="inline-block mt-1 px-2 py-1 text-xs rounded-full <?php 
                                                echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' : 
                                                     ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); 
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pending Vendor Applications -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-lg font-bold text-gray-900">Pending Vendor Approvals</h2>
                        <a href="/admin/vendors.php" class="text-primary hover:text-indigo-700 text-sm font-medium">View All</a>
                    </div>
                    
                    <?php if (empty($recent_vendors)): ?>
                        <p class="text-gray-500 text-sm py-4">No pending vendors</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($recent_vendors as $vendor): ?>
                                <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($vendor['shop_name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($vendor['full_name']); ?></p>
                                        </div>
                                        <span class="inline-block px-3 py-1 text-xs rounded-full bg-yellow-100 text-yellow-700">
                                            Pending
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
