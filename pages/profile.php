<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

// Fetch user's orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();

$page_title = 'My Profile - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">My Profile</h1>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Info -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="text-center mb-6">
                    <div class="w-24 h-24 bg-primary text-white rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-4">
                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 2)); ?>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                    <p class="text-gray-600"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
                    <span class="inline-block mt-2 px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium">
                        <?php echo ucfirst($_SESSION['user_role']); ?>
                    </span>
                </div>
                
                <div class="space-y-2">
                    <?php if (isAdmin()): ?>
                        <a href="/admin/" class="block w-full bg-primary hover:bg-indigo-700 text-white text-center py-2 rounded-lg font-medium transition">
                            <i class="fas fa-cog"></i> Admin Dashboard
                        </a>
                    <?php elseif (isVendor()): ?>
                        <a href="/vendor/" class="block w-full bg-primary hover:bg-indigo-700 text-white text-center py-2 rounded-lg font-medium transition">
                            <i class="fas fa-store"></i> Vendor Dashboard
                        </a>
                    <?php else: ?>
                        <a href="/auth/become-vendor.php" class="block w-full bg-primary hover:bg-indigo-700 text-white text-center py-2 rounded-lg font-medium transition">
                            <i class="fas fa-store"></i> Become a Vendor
                        </a>
                    <?php endif; ?>
                    <a href="/auth/logout.php" class="block w-full bg-red-600 hover:bg-red-700 text-white text-center py-2 rounded-lg font-medium transition">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Order History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Order History</h2>
                
                <?php if (empty($orders)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">No Orders Yet</h3>
                        <p class="text-gray-600 mb-6">Start shopping and your orders will appear here</p>
                        <a href="/pages/products.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                            <i class="fas fa-shopping-bag"></i> Browse Products
                        </a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($orders as $order): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <p class="font-bold text-lg text-gray-900">Order #{<?php echo htmlspecialchars($order['order_number']); ?></p>
                                        <p class="text-sm text-gray-600">
                                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-bold text-xl text-primary"><?php echo formatPrice($order['total_amount']); ?></p>
                                        <span class="inline-block mt-1 px-3 py-1 text-xs rounded-full <?php 
                                            echo $order['status'] === 'delivered' ? 'bg-green-100 text-green-700' : 
                                                 ($order['status'] === 'cancelled' ? 'bg-red-100 text-red-700' : 
                                                  ($order['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700')); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="text-sm text-gray-600 space-y-1">
                                    <p><i class="fas fa-credit-card"></i> Payment: <?php echo ucfirst($order['payment_method']); ?></p>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                                    <?php if ($order['shipping_phone']): ?>
                                        <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['shipping_phone']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-3 pt-3 border-t flex justify-between items-center">
                                    <span class="text-xs px-2 py-1 rounded <?php 
                                        echo $order['payment_status'] === 'paid' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; 
                                    ?>">
                                        Payment: <?php echo ucfirst($order['payment_status']); ?>
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

<?php include '../includes/footer.php'; ?>
