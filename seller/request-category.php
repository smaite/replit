<?php
/**
 * Seller Request Category Page
 * Allows sellers to request new categories
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Check if vendor is logged in
requireVendor();

$vendorId = $_SESSION['vendor_id'];
$message = '';
$requests = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_category'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Invalid security token</div>';
    } else {
        $categoryName = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        if (strlen($categoryName) < 3) {
            $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Category name must be at least 3 characters</div>';
        } else {
            // Check if already requested
            $stmt = $conn->prepare("SELECT id FROM category_requests WHERE vendor_id = ? AND category_name = ? AND status = 'pending'");
            $stmt->execute([$vendorId, $categoryName]);
            
            if ($stmt->fetch()) {
                $message = '<div class="bg-yellow-100 text-yellow-700 p-4 rounded mb-6">You already have a pending request for this category</div>';
            } else {
                $stmt = $conn->prepare("INSERT INTO category_requests (vendor_id, category_name, description) VALUES (?, ?, ?)");
                if ($stmt->execute([$vendorId, $categoryName, $description])) {
                    $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category request submitted successfully! We will review it soon.</div>';
                } else {
                    $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Error submitting request</div>';
                }
            }
        }
    }
}

// Get vendor's requests
try {
    $stmt = $conn->prepare("SELECT * FROM category_requests WHERE vendor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$vendorId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $requests = [];
}

$page_title = 'Request Category - Seller Dashboard';
include '../includes/seller_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <a href="/seller/" class="text-primary hover:text-indigo-700 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-folder-plus text-primary"></i> Request New Category
        </h1>
        <p class="text-gray-600 mt-2">Don't see the category you need? Request it here.</p>
    </div>

    <?php echo $message; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Request Form -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-plus text-primary"></i> Submit Request
            </h3>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="request_category" value="1">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category Name *</label>
                    <input type="text" name="category_name" placeholder="e.g., Organic Foods" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                           minlength="3" maxlength="100">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Why do you need this category?</label>
                    <textarea name="description" rows="4" placeholder="Explain what products you want to sell in this category..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary resize-none"></textarea>
                </div>
                
                <button type="submit" class="w-full bg-primary text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition font-medium">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </form>
        </div>

        <!-- My Requests -->
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">
                <i class="fas fa-history text-primary"></i> My Requests
            </h3>
            
            <?php if (empty($requests)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2"></i>
                    <p>No requests yet</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($requests as $request): ?>
                        <div class="border rounded-lg p-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-medium text-gray-900"><?php echo htmlspecialchars($request['category_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($request['created_at'])); ?></p>
                                </div>
                                <span class="px-2 py-1 text-xs font-medium rounded 
                                    <?php 
                                    switch($request['status']) {
                                        case 'approved': echo 'bg-green-100 text-green-700'; break;
                                        case 'rejected': echo 'bg-red-100 text-red-700'; break;
                                        default: echo 'bg-yellow-100 text-yellow-700';
                                    }
                                    ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </div>
                            <?php if (!empty($request['admin_notes'])): ?>
                                <p class="text-sm text-gray-600 mt-2 border-t pt-2">
                                    <strong>Admin:</strong> <?php echo htmlspecialchars($request['admin_notes']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
