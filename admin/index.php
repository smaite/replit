<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('/auth/login.php');
}

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$total_vendors = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'approved'")->fetch()['count'];
$pending_vendors = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'")->fetch()['count'];
$total_products = $conn->query("SELECT COUNT(*) as count FROM products")->fetch()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch()['count'];
$total_revenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid'")->fetch()['total'];

// Get recent vendors
$recent_vendors = $conn->query("SELECT v.*, u.full_name, u.email FROM vendors v 
                                JOIN users u ON v.user_id = u.id 
                                ORDER BY v.created_at DESC LIMIT 5")->fetchAll();

// Get recent orders
$recent_orders = $conn->query("SELECT o.*, u.full_name FROM orders o 
                               JOIN users u ON o.user_id = u.id 
                               ORDER BY o.created_at DESC LIMIT 5")->fetchAll();

$page_title = 'Admin Dashboard - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                <i class="fas fa-cog text-primary"></i> Admin Dashboard
            </h1>
            <p class="text-gray-600">Manage your SASTO Hub platform</p>
        </div>
        
        <!-- Statistics -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-600 text-sm">Total Users</p>
                <p class="text-2xl font-bold text-primary mt-1"><?php echo $total_users; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-600 text-sm">Vendors</p>
                <p class="text-2xl font-bold text-green-600 mt-1"><?php echo $total_vendors; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-600 text-sm">Pending</p>
                <p class="text-2xl font-bold text-yellow-600 mt-1"><?php echo $pending_vendors; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-600 text-sm">Products</p>
                <p class="text-2xl font-bold text-blue-600 mt-1"><?php echo $total_products; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-600 text-sm">Orders</p>
                <p class="text-2xl font-bold text-purple-600 mt-1"><?php echo $total_orders; ?></p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-gray-600 text-sm">Revenue</p>
                <p class="text-lg font-bold text-green-600 mt-1"><?php echo formatPrice($total_revenue); ?></p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-900 mb-4">Quick Actions</h2>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <a href="/admin/vendors-verification.php" class="bg-indigo-600 hover:bg-indigo-700 text-white text-center py-3 rounded-lg font-medium transition">
                    <i class="fas fa-check-circle text-xl mb-1 block"></i>
                    Verify Vendors
                </a>
                <a href="/admin/vendors.php" class="bg-primary hover:bg-indigo-700 text-white text-center py-3 rounded-lg font-medium transition">
                    <i class="fas fa-store text-xl mb-1 block"></i>
                    Vendors
                </a>
                <a href="/admin/products.php" class="bg-green-600 hover:bg-green-700 text-white text-center py-3 rounded-lg font-medium transition">
                    <i class="fas fa-box text-xl mb-1 block"></i>
                    Products
                </a>
                <a href="/admin/orders.php" class="bg-yellow-600 hover:bg-yellow-700 text-white text-center py-3 rounded-lg font-medium transition">
                    <i class="fas fa-shopping-cart text-xl mb-1 block"></i>
                    Orders
                </a>
                <a href="/admin/categories.php" class="bg-blue-600 hover:bg-blue-700 text-white text-center py-3 rounded-lg font-medium transition">
                    <i class="fas fa-tags text-xl mb-1 block"></i>
                    Categories
                </a>
                <a href="/admin/users.php" class="bg-gray-600 hover:bg-gray-700 text-white text-center py-3 rounded-lg font-medium transition">
                    <i class="fas fa-users text-xl mb-1 block"></i>
                    Users
                </a>
            </div>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Vendor Applications -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Vendor Applications</h2>
                <div class="space-y-4">
                    <?php if (empty($recent_vendors)): ?>
                        <p class="text-gray-600 text-center py-4">No vendor applications</p>
                    <?php else: ?>
                        <?php foreach ($recent_vendors as $vendor): ?>
                            <div class="flex justify-between items-center border-b pb-3">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($vendor['shop_name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($vendor['full_name']); ?></p>
                                </div>
                                <span class="px-3 py-1 text-sm rounded-full <?php 
                                    echo $vendor['status'] === 'approved' ? 'bg-green-100 text-green-700' : 
                                         ($vendor['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); 
                                ?>">
                                    <?php echo ucfirst($vendor['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Recent Orders</h2>
                <div class="space-y-4">
                    <?php if (empty($recent_orders)): ?>
                        <p class="text-gray-600 text-center py-4">No orders yet</p>
                    <?php else: ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <div class="flex justify-between items-center border-b pb-3">
                                <div>
                                    <p class="font-medium">#<?php echo htmlspecialchars($order['order_number']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($order['full_name']); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-primary"><?php echo formatPrice($order['total_amount']); ?></p>
                                    <span class="text-xs px-2 py-1 rounded-full <?php 
                                        echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; 
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
