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

$page_title = 'My Account - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-8">
        <a href="/" class="hover:text-primary"><i class="fas fa-home"></i></a>
        <span>/</span>
        <span class="text-gray-900">My Account</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <!-- User Info -->
                <div class="p-6 border-b border-gray-100 bg-gray-50">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-primary text-white rounded-full flex items-center justify-center text-xl font-bold">
                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Hello,</p>
                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="p-4 space-y-1">
                    <a href="/pages/dashboard.php" class="flex items-center gap-3 px-4 py-3 bg-blue-50 text-primary font-medium rounded-lg">
                        <i class="fas fa-user-circle w-5"></i> Manage My Account
                    </a>
                    <a href="/pages/order-history.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg transition">
                        <i class="fas fa-box w-5"></i> My Orders
                    </a>
                    <a href="/pages/wishlist.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg transition">
                        <i class="fas fa-heart w-5"></i> My Wishlist
                    </a>
                    <a href="/pages/address-book.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary rounded-lg transition">
                        <i class="fas fa-map-marker-alt w-5"></i> Address Book
                    </a>
                     <a href="/auth/logout.php" class="flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 rounded-lg transition mt-4">
                        <i class="fas fa-sign-out-alt w-5"></i> Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3 space-y-6">
            <h1 class="text-2xl font-bold text-gray-900">Manage My Account</h1>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Personal Profile -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="font-medium text-gray-900">Personal Profile</h3>
                        <a href="/pages/settings.php" class="text-sm text-primary hover:underline">Edit</a>
                    </div>
                    <div class="space-y-1">
                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($user['email']); ?></p>
                        <p class="text-gray-500 text-sm mt-2"><?php echo ucfirst($user['role']); ?></p>
                    </div>
                </div>

                <!-- Address Book -->
                <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="font-medium text-gray-900">Address Book</h3>
                        <a href="/pages/address-book.php" class="text-sm text-primary hover:underline">Edit</a>
                    </div>
                    <div class="space-y-1">
                        <p class="text-xs text-gray-400 uppercase font-bold mb-1">Default Shipping Address</p>
                        <?php if ($default_address): ?>
                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($default_address['full_name']); ?></p>
                            <p class="text-gray-500 text-sm">
                                <?php echo htmlspecialchars($default_address['address_line1']); ?><br>
                                <?php echo htmlspecialchars($default_address['city']); ?> - <?php echo htmlspecialchars($default_address['state']); ?><br>
                                <?php echo htmlspecialchars($default_address['phone']); ?>
                            </p>
                        <?php else: ?>
                            <p class="text-gray-500 text-sm italic">No default address set.</p>
                            <a href="/pages/address-book.php?action=add" class="text-sm text-primary hover:underline mt-2 inline-block">Add Address</a>
                        <?php endif; ?>
                    </div>
                </div>

                 <!-- Stats/News (Optional placeholder to fill 3rd col or just stats) -->
                 <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-100">
                     <div class="flex justify-between items-start mb-4">
                        <h3 class="font-medium text-gray-900">My Stats</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <span class="block text-2xl font-bold text-primary"><?php echo count($recent_orders); ?></span>
                            <span class="text-xs text-gray-600">Orders</span>
                        </div>
                         <div class="text-center p-3 bg-green-50 rounded-lg">
                            <span class="block text-2xl font-bold text-green-600">0</span>
                            <span class="text-xs text-gray-600">Reviews</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                    <h3 class="font-bold text-gray-900">Recent Orders</h3>
                    <a href="/pages/order-history.php" class="text-sm text-primary hover:underline">View All</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                            <tr>
                                <th class="px-6 py-4 font-medium">Order #</th>
                                <th class="px-6 py-4 font-medium">Placed On</th>
                                <th class="px-6 py-4 font-medium">Items</th>
                                <th class="px-6 py-4 font-medium">Total</th>
                                <th class="px-6 py-4 font-medium">Status</th>
                                <th class="px-6 py-4 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($recent_orders)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        No orders found. <a href="/" class="text-primary hover:underline">Start Shopping</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td class="px-6 py-4 font-medium text-gray-900">#<?php echo htmlspecialchars($order['order_number']); ?></td>
                                    <td class="px-6 py-4 text-gray-500"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                    <td class="px-6 py-4 text-gray-500">
                                        <div class="w-12 h-12 bg-gray-100 rounded flex items-center justify-center text-gray-400">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-medium text-gray-900">Rs. <?php echo number_format($order['total_amount']); ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php 
                                            echo match($order['status']) {
                                                'delivered' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800',
                                                'shipped' => 'bg-blue-100 text-blue-800',
                                                default => 'bg-yellow-100 text-yellow-800'
                                            };
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="/pages/order-details.php?id=<?php echo $order['id']; ?>" class="text-primary hover:text-indigo-700 font-medium text-sm">Manage</a>
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

<?php include '../includes/footer.php'; ?>
