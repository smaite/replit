<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$user_id = $_GET['id'] ?? null;
if (!$user_id) {
    header('Location: /admin/users.php');
    exit;
}

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: /admin/users.php');
    exit;
}

// Fetch related data
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$orders_count = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart_count = $stmt->fetch()['total'];

// Handle role change
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    if (verifyCsrfToken($_POST['csrf_token'])) {
        $new_role = $_POST['new_role'];
        if (in_array($new_role, ['customer', 'vendor', 'admin'])) {
            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $user_id])) {
                $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Role updated successfully</div>';
                $user['role'] = $new_role;
            }
        }
    }
}

$page_title = 'User Details - SASTO Hub Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <a href="/admin/users.php" class="text-primary hover:text-indigo-700 mb-6 inline-block">
        <i class="fas fa-arrow-left"></i> Back to Users
    </a>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- User Profile Card -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm p-8">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-24 h-24 bg-primary text-white rounded-full flex items-center justify-center text-4xl font-bold">
                            <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                            <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                            <span class="inline-block mt-2 px-3 py-1 text-xs rounded-full <?php 
                                echo $user['role'] === 'admin' ? 'bg-red-100 text-red-700' :
                                     ($user['role'] === 'vendor' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700');
                            ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Account Information</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="text-sm text-gray-600">Full Name</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600">Email</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600">Phone</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        <div>
                            <label class="text-sm text-gray-600">Joined Date</label>
                            <p class="text-gray-900 font-medium"><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Change Role -->
                <div class="border-t border-gray-200 pt-6 mt-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Change User Role</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="change_role" value="1">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Role</label>
                            <select name="new_role" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                                <option value="customer" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                <option value="vendor" <?php echo $user['role'] === 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                            Update Role
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Stats -->
        <div class="space-y-6">
            <!-- Stats Card -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Activity Stats</h3>
                
                <div class="space-y-4">
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                        <span class="text-gray-700">Total Orders</span>
                        <span class="text-3xl font-bold text-primary"><?php echo $orders_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center pb-4 border-b border-gray-200">
                        <span class="text-gray-700">Cart Items</span>
                        <span class="text-3xl font-bold text-primary"><?php echo $cart_count; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-700">Account Age</span>
                        <span class="font-medium"><?php echo floor((time() - strtotime($user['created_at'])) / (60*60*24)); ?> days</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="/admin/orders.php?user=<?php echo $user_id; ?>" class="block px-4 py-2 bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition font-medium text-center">
                        <i class="fas fa-box"></i> View Orders
                    </a>
                    <?php if ($user['role'] === 'vendor'): ?>
                        <a href="/admin/products.php?vendor=<?php echo $user_id; ?>" class="block px-4 py-2 bg-green-100 text-green-700 rounded hover:bg-green-200 transition font-medium text-center">
                            <i class="fas fa-shop"></i> View Products
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
