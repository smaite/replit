<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch default address
$stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? AND is_default = 1 LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$default_address = $stmt->fetch();

// If no default, get the most recent one
if (!$default_address) {
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $default_address = $stmt->fetch();
}

// Fetch recent orders (last 5)
$stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recent_orders = $stmt->fetchAll();

// Fetch wishlist count
$stmt = $conn->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$wishlist_count = $stmt->fetchColumn();

$page_title = 'My Account - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">My Account</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <!-- User Info -->
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-primary/10 text-primary rounded-full flex items-center justify-center text-xl font-bold border border-primary/20">
                                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Hello,</p>
                                <h3 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <nav class="p-4 space-y-1">
                        <a href="/pages/dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-medium rounded-lg transition-colors">
                            <i class="fas fa-user-circle w-5"></i> Manage Account
                        </a>
                        <a href="/pages/order-history.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg transition-colors group">
                            <i class="fas fa-box w-5 group-hover:text-primary transition-colors"></i> My Orders
                        </a>
                        <a href="/pages/wishlist.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg transition-colors group">
                            <i class="fas fa-heart w-5 group-hover:text-primary transition-colors"></i> My Wishlist
                            <?php if ($wishlist_count > 0): ?>
                                <span class="ml-auto bg-gray-100 text-gray-600 text-xs py-0.5 px-2 rounded-full group-hover:bg-primary group-hover:text-white transition-colors"><?php echo $wishlist_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="/pages/address-book.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg transition-colors group">
                            <i class="fas fa-map-marker-alt w-5 group-hover:text-primary transition-colors"></i> Address Book
                        </a>
                        <?php if (isVendor()): ?>
                            <a href="/seller/" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-secondary rounded-lg transition-colors group">
                                <i class="fas fa-store w-5 group-hover:text-secondary transition-colors"></i> Seller Panel
                            </a>
                        <?php endif; ?>
                        <div class="pt-4 mt-4 border-t border-gray-100">
                            <a href="/auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                                <i class="fas fa-sign-out-alt w-5"></i> Logout
                            </a>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-6">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-bold text-gray-900">Account Overview</h1>
                    <span class="text-sm text-gray-500">Last login: <?php echo date('M d, Y'); ?></span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Personal Profile -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-bold text-gray-900">Personal Profile</h3>
                            <a href="/pages/settings.php" class="text-xs font-bold text-primary hover:text-indigo-700 uppercase tracking-wide">Edit</a>
                        </div>
                        <div class="space-y-2">
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                            <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($user['email']); ?></p>
                            <div class="pt-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Address Book -->
                    <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                        <div class="flex justify-between items-start mb-4">
                            <h3 class="font-bold text-gray-900">Shipping Address</h3>
                            <a href="/pages/address-book.php" class="text-xs font-bold text-primary hover:text-indigo-700 uppercase tracking-wide">Edit</a>
                        </div>
                        <div class="space-y-1">
                            <?php if ($default_address): ?>
                                <p class="text-xs text-gray-400 uppercase font-bold mb-2">Default</p>
                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($default_address['full_name']); ?></p>
                                <p class="text-gray-500 text-sm leading-relaxed">
                                    <?php echo htmlspecialchars($default_address['address_line1']); ?><br>
                                    <?php echo htmlspecialchars($default_address['city']); ?>, <?php echo htmlspecialchars($default_address['state']); ?>
                                </p>
                                <p class="text-gray-500 text-sm mt-2"><?php echo htmlspecialchars($default_address['phone']); ?></p>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-2 text-gray-400">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <p class="text-gray-500 text-sm italic mb-3">No default address set.</p>
                                    <a href="/pages/address-book.php?action=add" class="text-sm font-medium text-primary hover:underline">Add Address</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                     <!-- Stats -->
                     <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                         <div class="flex justify-between items-start mb-4">
                            <h3 class="font-bold text-gray-900">Activity</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-4 h-full">
                            <div class="text-center p-3 bg-blue-50 rounded-lg flex flex-col justify-center">
                                <span class="block text-2xl font-bold text-primary mb-1"><?php echo count($recent_orders); ?></span>
                                <span class="text-xs text-gray-600 font-medium">Orders</span>
                            </div>
                             <div class="text-center p-3 bg-pink-50 rounded-lg flex flex-col justify-center">
                                <span class="block text-2xl font-bold text-pink-600 mb-1"><?php echo $wishlist_count; ?></span>
                                <span class="text-xs text-gray-600 font-medium">Wishlist</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-900">Recent Orders</h3>
                        <a href="/pages/order-history.php" class="text-sm font-medium text-primary hover:text-indigo-700 flex items-center gap-1">
                            View All <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                                <tr>
                                    <th class="px-6 py-4 font-semibold">Order #</th>
                                    <th class="px-6 py-4 font-semibold">Placed On</th>
                                    <th class="px-6 py-4 font-semibold">Total</th>
                                    <th class="px-6 py-4 font-semibold">Status</th>
                                    <th class="px-6 py-4 font-semibold text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                            <div class="mb-4">
                                                <i class="fas fa-shopping-bag text-4xl text-gray-200"></i>
                                            </div>
                                            <p>No recent orders found.</p>
                                            <a href="/pages/products.php" class="text-primary hover:underline font-medium text-sm mt-2 inline-block">Start Shopping</a>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            <span class="font-mono">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500 text-sm"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td class="px-6 py-4 font-medium text-gray-900">Rs. <?php echo number_format($order['total_amount']); ?></td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php
                                                echo match($order['status']) {
                                                    'delivered' => 'bg-green-50 text-green-700 border-green-200',
                                                    'cancelled' => 'bg-red-50 text-red-700 border-red-200',
                                                    'shipped' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'processing' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                                    default => 'bg-yellow-50 text-yellow-700 border-yellow-200'
                                                };
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <a href="/pages/order-details.php?id=<?php echo $order['id']; ?>" class="text-primary hover:text-indigo-800 font-medium text-sm bg-primary/5 hover:bg-primary/10 px-3 py-1.5 rounded-lg transition-colors">
                                                Manage
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
