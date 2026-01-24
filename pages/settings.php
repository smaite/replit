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

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/pages/dashboard.php" class="hover:text-primary">My Account</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Settings</span>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900 mb-8">Account Settings</h1>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <nav class="p-2 space-y-1">
                        <a href="#profile" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-bold rounded-lg transition-colors">
                            <i class="fas fa-user-circle w-5"></i> Profile
                        </a>
                        <a href="#password" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors">
                            <i class="fas fa-lock w-5"></i> Password
                        </a>
                        <a href="#notifications" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors">
                            <i class="fas fa-bell w-5"></i> Notifications
                        </a>
                        <a href="#privacy" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors">
                            <i class="fas fa-shield-alt w-5"></i> Privacy
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3 space-y-8">

                <!-- Feedback Messages -->
                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-xl"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl flex items-center gap-3">
                        <i class="fas fa-check-circle text-xl"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Section -->
                <div id="profile" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 scroll-mt-24">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center text-2xl font-bold border border-primary/20">
                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Personal Information</h2>
                            <p class="text-sm text-gray-500">Update your photo and personal details here.</p>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Full Name</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" name="full_name" required
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>"
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" name="email" required
                                           value="<?php echo htmlspecialchars($user['email']); ?>"
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-phone"></i>
                                    </span>
                                    <input type="tel" name="phone"
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="+977">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Account Type</label>
                                <div class="w-full px-4 py-3 bg-gray-100 border border-gray-200 rounded-xl text-gray-600 font-medium">
                                    <?php echo ucfirst($user['role']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="px-8 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Section -->
                <div id="password" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 scroll-mt-24">
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900">Change Password</h2>
                        <p class="text-sm text-gray-500">Ensure your account is secure with a strong password.</p>
                    </div>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="space-y-6 max-w-2xl">
                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Current Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" name="current_password" required
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Enter current password">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">New Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-key"></i>
                                    </span>
                                    <input type="password" name="new_password" required
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Min 6 characters">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Confirm New Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                    <input type="password" name="confirm_password" required
                                           class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Re-enter new password">
                                </div>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" class="px-8 py-3 bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 font-bold rounded-xl transition">
                                    Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Notifications Section -->
                <div id="notifications" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 scroll-mt-24">
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900">Notifications</h2>
                        <p class="text-sm text-gray-500">Manage how you want to be notified.</p>
                    </div>

                    <div class="space-y-4">
                        <label class="flex items-start gap-4 p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition cursor-pointer">
                            <div class="pt-1">
                                <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                            </div>
                            <div>
                                <span class="block font-bold text-gray-900">Order Updates</span>
                                <span class="block text-sm text-gray-500">Get notified about order status changes via email.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-4 p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition cursor-pointer">
                            <div class="pt-1">
                                <input type="checkbox" checked class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                            </div>
                            <div>
                                <span class="block font-bold text-gray-900">Promotions & Offers</span>
                                <span class="block text-sm text-gray-500">Receive exclusive deals and special offers.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-4 p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition cursor-pointer">
                            <div class="pt-1">
                                <input type="checkbox" class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                            </div>
                            <div>
                                <span class="block font-bold text-gray-900">Product Reviews</span>
                                <span class="block text-sm text-gray-500">Receive requests to review products you've purchased.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Privacy Section -->
                <div id="privacy" class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 scroll-mt-24">
                    <div class="mb-8">
                        <h2 class="text-xl font-bold text-gray-900">Privacy & Security</h2>
                        <p class="text-sm text-gray-500">Control your privacy settings and security.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-bold text-gray-900">Two-Factor Authentication</h3>
                                <p class="text-sm text-gray-500">Add an extra layer of security to your account.</p>
                            </div>
                            <button class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition shadow-sm">
                                Enable
                            </button>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                            <div>
                                <h3 class="font-bold text-gray-900">Login History</h3>
                                <p class="text-sm text-gray-500">View devices and locations where you've logged in.</p>
                            </div>
                            <button class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition shadow-sm">
                                View
                            </button>
                        </div>

                        <div class="mt-8 pt-6 border-t border-gray-100">
                            <h3 class="font-bold text-red-600 mb-2">Danger Zone</h3>
                            <p class="text-sm text-gray-500 mb-4">Once you delete your account, there is no going back. Please be certain.</p>
                            <button onclick="alert('Please contact support to delete your account.')" class="px-6 py-2.5 bg-red-50 text-red-600 border border-red-100 font-bold rounded-lg hover:bg-red-100 transition">
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // Simple smooth scroll behavior for sidebar links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();

            // Update active state in sidebar
            document.querySelectorAll('nav a').forEach(link => {
                link.classList.remove('bg-primary/10', 'text-primary', 'font-bold');
                link.classList.add('text-gray-600', 'font-medium');
            });

            this.classList.remove('text-gray-600', 'font-medium');
            this.classList.add('bg-primary/10', 'text-primary', 'font-bold');

            const targetId = this.getAttribute('href');
            document.querySelector(targetId).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });
</script>

<?php include '../includes/footer.php'; ?>
