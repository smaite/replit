<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Require authentication
requireAuth();

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$error = '';
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $full_name = validateInput($_POST['full_name'] ?? '', 'text', 100);
            $email = validateInput($_POST['email'] ?? '', 'email');
            $phone = validateInput($_POST['phone'] ?? '', 'text', 20);

            if (!$full_name || !$email) {
                $error = 'Please fill in all required fields';
            } else {
                // Check if email is already used by another user
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'Email is already in use';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']]);

                    // Update session
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['user_email'] = $email;

                    $success = 'Profile updated successfully!';
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill in all password fields';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = 'Current password is incorrect';
            } elseif (strlen($new_password) < 6) {
                $error = 'New password must be at least 6 characters';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                $success = 'Password changed successfully!';
            }
        }
    }
}

$page_title = 'Settings - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <a href="/pages/dashboard.php" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-4xl font-bold text-gray-900">Account Settings</h1>
        <p class="text-gray-600 mt-2">Manage your account and security preferences</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <ul class="divide-y">
                    <li>
                        <a href="#profile" class="flex items-center gap-3 px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary font-medium">
                            <i class="fas fa-user text-primary w-5"></i> Profile
                        </a>
                    </li>
                    <li>
                        <a href="#password" class="flex items-center gap-3 px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary font-medium">
                            <i class="fas fa-lock text-primary w-5"></i> Password
                        </a>
                    </li>
                    <li>
                        <a href="#notifications" class="flex items-center gap-3 px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary font-medium">
                            <i class="fas fa-bell text-primary w-5"></i> Notifications
                        </a>
                    </li>
                    <li>
                        <a href="#privacy" class="flex items-center gap-3 px-6 py-4 hover:bg-gray-50 transition text-gray-700 hover:text-primary font-medium">
                            <i class="fas fa-shield-alt text-primary w-5"></i> Privacy
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Profile Section -->
            <div class="bg-white rounded-lg shadow p-6" id="profile">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-user text-primary"></i> Profile Information
                </h2>

                <?php if ($error && strpos($_POST['action'] ?? '', 'profile') !== false): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success && strpos($_POST['action'] ?? '', 'profile') !== false): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                            <input type="text" name="full_name" required
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Your full name">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="your@email.com">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="+977-9800000000">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-600 uppercase font-semibold mb-1">Account Type</p>
                                <p class="text-gray-900 font-medium px-4 py-3 bg-gray-50 rounded-lg">
                                    <?php echo ucfirst($_SESSION['user_role']); ?>
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-600 uppercase font-semibold mb-1">Member Since</p>
                                <p class="text-gray-900 font-medium px-4 py-3 bg-gray-50 rounded-lg">
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Password Section -->
            <div class="bg-white rounded-lg shadow p-6" id="password">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-lock text-primary"></i> Change Password
                </h2>

                <?php if ($error && strpos($_POST['action'] ?? '', 'password') !== false): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success && strpos($_POST['action'] ?? '', 'password') !== false): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Enter your current password">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Enter new password (minimum 6 characters)">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Confirm new password">
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle"></i> 
                                Password must be at least 6 characters long and contain a mix of characters.
                            </p>
                        </div>

                        <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">
                            <i class="fas fa-key"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notifications Section -->
            <div class="bg-white rounded-lg shadow p-6" id="notifications">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-bell text-primary"></i> Notification Preferences
                </h2>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Order Updates</h3>
                            <p class="text-sm text-gray-600">Get notified about order status changes</p>
                        </div>
                        <input type="checkbox" checked class="w-5 h-5 text-primary rounded cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Promotions & Offers</h3>
                            <p class="text-sm text-gray-600">Receive exclusive deals and special offers</p>
                        </div>
                        <input type="checkbox" checked class="w-5 h-5 text-primary rounded cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Wishlist Items</h3>
                            <p class="text-sm text-gray-600">Get notified when wishlist items go on sale</p>
                        </div>
                        <input type="checkbox" class="w-5 h-5 text-primary rounded cursor-pointer">
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Product Reviews</h3>
                            <p class="text-sm text-gray-600">Request reviews for products you purchased</p>
                        </div>
                        <input type="checkbox" checked class="w-5 h-5 text-primary rounded cursor-pointer">
                    </div>

                    <div class="mt-6 pt-4 border-t">
                        <button class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </div>
            </div>

            <!-- Privacy Section -->
            <div class="bg-white rounded-lg shadow p-6" id="privacy">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">
                    <i class="fas fa-shield-alt text-primary"></i> Privacy & Security
                </h2>

                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Two-Factor Authentication</h3>
                            <p class="text-sm text-gray-600">Add an extra layer of security to your account</p>
                        </div>
                        <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                            Enable
                        </button>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Login History</h3>
                            <p class="text-sm text-gray-600">View all devices and locations where you've logged in</p>
                        </div>
                        <button class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                            View
                        </button>
                    </div>

                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50">
                        <div>
                            <h3 class="font-medium text-gray-900">Data Privacy</h3>
                            <p class="text-sm text-gray-600">Read our privacy policy and terms of service</p>
                        </div>
                        <a href="#" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                            Read
                        </a>
                    </div>

                    <div class="mt-6 pt-4 border-t">
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                            <h3 class="font-bold text-red-900 mb-2">
                                <i class="fas fa-warning"></i> Danger Zone
                            </h3>
                            <p class="text-sm text-red-700 mb-4">These actions cannot be undone. Please proceed with caution.</p>
                        </div>
                        
                        <button class="w-full bg-red-600 hover:bg-red-700 text-white py-3 rounded-lg font-medium transition" onclick="alert('Feature coming soon')">
                            <i class="fas fa-trash"></i> Delete My Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
