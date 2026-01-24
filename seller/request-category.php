<?php
/**
 * Seller Request Category Page
 * Allows sellers to request new categories
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if vendor is logged in
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

$vendor_id = $_SESSION['vendor_id'] ?? null;
if (!$vendor_id) {
    redirect('/seller/index.php');
}

// Get vendor info for sidebar
$stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch();

$message = '';
$requests = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_category'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-xl"></i> Invalid security token</div>';
    } else {
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (strlen($categoryName) < 3) {
            $message = '<div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-xl"></i> Category name must be at least 3 characters</div>';
        } else {
            // Check if already requested
            $stmt = $conn->prepare("SELECT id FROM category_requests WHERE vendor_id = ? AND category_name = ? AND status = 'pending'");
            $stmt->execute([$vendor_id, $categoryName]);

            if ($stmt->fetch()) {
                $message = '<div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-triangle text-xl"></i> You already have a pending request for this category</div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO category_requests (vendor_id, category_name, description) VALUES (?, ?, ?)");
                if ($stmt->execute([$vendor_id, $categoryName, $description])) {
                    $message = '<div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-check-circle text-xl"></i> Category request submitted successfully! We will review it soon.</div>';
                } else {
                    $message = '<div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3"><i class="fas fa-exclamation-circle text-xl"></i> Error submitting request</div>';
                }
            }
        }
    }
}

// Get vendor's requests
try {
    $stmt = $conn->prepare("SELECT * FROM category_requests WHERE vendor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$vendor_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

$page_title = 'Request Category - Seller Dashboard';
include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <a href="/seller/" class="hover:text-primary">Seller Dashboard</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Request Category</span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
             <!-- Sidebar Navigation -->
             <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                    <div class="p-6 border-b border-gray-100 bg-gray-50/50">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-indigo-100 text-indigo-600 rounded-lg flex items-center justify-center text-2xl font-bold border border-indigo-200">
                                <i class="fas fa-store"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-xs text-gray-500 uppercase tracking-wider font-semibold">Shop Dashboard</p>
                                <h3 class="font-bold text-gray-900 truncate"><?php echo htmlspecialchars($vendor['shop_name']); ?></h3>
                            </div>
                        </div>
                    </div>
                    <nav class="p-4 space-y-1">
                        <a href="/seller/" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-chart-line w-5 group-hover:text-primary transition-colors"></i> Dashboard
                        </a>
                        <a href="/seller/products.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-box w-5 group-hover:text-primary transition-colors"></i> My Products
                        </a>
                        <a href="/seller/add-product.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-plus-circle w-5 group-hover:text-primary transition-colors"></i> Add Product
                        </a>
                        <a href="/seller/orders.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-shopping-cart w-5 group-hover:text-primary transition-colors"></i> Orders
                        </a>
                        <a href="/seller/settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-600 hover:bg-gray-50 hover:text-primary font-medium rounded-lg transition-colors group">
                            <i class="fas fa-cog w-5 group-hover:text-primary transition-colors"></i> Shop Settings
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Request New Category</h1>
                    <p class="text-gray-600 mt-1">Don't see the category you need? Request it here.</p>
                </div>

                <?php echo $message; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Request Form -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-fit">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-plus-circle text-primary"></i> Submit Request
                        </h2>

                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="request_category" value="1">

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Category Name <span class="text-red-500">*</span></label>
                                <input type="text" name="category_name" placeholder="e.g., Organic Foods" required
                                       class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                       minlength="3" maxlength="100">
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Why do you need this category?</label>
                                <textarea name="description" rows="5" placeholder="Explain what products you want to sell in this category..."
                                          class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors resize-none"></textarea>
                            </div>

                            <button type="submit" class="w-full px-8 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                                <i class="fas fa-paper-plane mr-2"></i> Submit Request
                            </button>
                        </form>
                    </div>

                    <!-- My Requests -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 h-fit">
                        <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                            <i class="fas fa-history text-primary"></i> My Requests
                        </h2>

                        <?php if (empty($requests)): ?>
                            <div class="text-center py-12 px-6 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                                    <i class="fas fa-inbox text-3xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-900 font-bold">No requests yet</p>
                                <p class="text-sm text-gray-500 mt-1">Your requested categories will appear here.</p>
                            </div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($requests as $request): ?>
                                    <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 hover:border-gray-200 transition-colors">
                                        <div class="flex justify-between items-start mb-2">
                                            <h3 class="font-bold text-gray-900"><?php echo htmlspecialchars($request['category_name']); ?></h3>
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wide
                                                <?php
                                                switch($request['status']) {
                                                    case 'approved': echo 'bg-green-100 text-green-700 border border-green-200'; break;
                                                    case 'rejected': echo 'bg-red-100 text-red-700 border border-red-200'; break;
                                                    default: echo 'bg-yellow-100 text-yellow-700 border border-yellow-200';
                                                }
                                                ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-3">
                                            <i class="far fa-calendar"></i>
                                            <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                        </div>

                                        <?php if (!empty($request['admin_notes'])): ?>
                                            <div class="mt-3 pt-3 border-t border-gray-200">
                                                <p class="text-xs font-bold text-gray-700 mb-1">Admin Response:</p>
                                                <p class="text-sm text-gray-600 bg-white p-2 rounded-lg border border-gray-100">
                                                    <?php echo htmlspecialchars($request['admin_notes']); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
