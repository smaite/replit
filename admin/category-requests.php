<?php
/**
 * Admin Category Requests Management
 * Review and approve/reject seller category requests
 */
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Invalid security token</div>';
    } else {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        
        if ($requestId > 0) {
            if ($action === 'approve') {
                // Get the request details
                $stmt = $conn->prepare("SELECT category_name FROM category_requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
                
                if ($request) {
                    // Create the category
                    $stmt = $conn->prepare("INSERT INTO categories (name, status) VALUES (?, 'active')");
                    $stmt->execute([$request['category_name']]);
                    
                    // Update request status
                    $stmt = $conn->prepare("UPDATE category_requests SET status = 'approved', admin_notes = ? WHERE id = ?");
                    $stmt->execute([$adminNotes, $requestId]);
                    
                    $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6">Category approved and created!</div>';
                }
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE category_requests SET status = 'rejected', admin_notes = ? WHERE id = ?");
                $stmt->execute([$adminNotes, $requestId]);
                $message = '<div class="bg-yellow-100 text-yellow-700 p-4 rounded mb-6">Category request rejected</div>';
            }
        }
    }
}

// Fetch pending requests
$pendingStmt = $conn->query("
    SELECT cr.*, v.shop_name 
    FROM category_requests cr 
    JOIN vendors v ON cr.vendor_id = v.id 
    WHERE cr.status = 'pending' 
    ORDER BY cr.created_at ASC
");
$pendingRequests = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent processed requests
$processedStmt = $conn->query("
    SELECT cr.*, v.shop_name 
    FROM category_requests cr 
    JOIN vendors v ON cr.vendor_id = v.id 
    WHERE cr.status != 'pending' 
    ORDER BY cr.updated_at DESC 
    LIMIT 20
");
$processedRequests = $processedStmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Category Requests - Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <a href="/admin/dashboard.php" class="text-primary hover:text-indigo-700 mb-4 inline-block">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <h1 class="text-3xl font-bold text-gray-900">
            <i class="fas fa-folder-plus text-primary"></i> Category Requests
        </h1>
        <p class="text-gray-600 mt-2">Review seller category requests</p>
    </div>

    <?php echo $message; ?>

    <!-- Pending Requests -->
    <div class="bg-white rounded-lg shadow-sm mb-6">
        <div class="p-4 border-b">
            <h3 class="text-lg font-bold text-gray-900">
                <i class="fas fa-clock text-yellow-500"></i> Pending Requests 
                <span class="text-sm font-normal text-gray-500">(<?php echo count($pendingRequests); ?>)</span>
            </h3>
        </div>
        
        <?php if (empty($pendingRequests)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-check-circle text-4xl text-green-400 mb-2"></i>
                <p>No pending requests</p>
            </div>
        <?php else: ?>
            <div class="divide-y">
                <?php foreach ($pendingRequests as $request): ?>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <p class="font-bold text-gray-900"><?php echo htmlspecialchars($request['category_name']); ?></p>
                                <p class="text-sm text-gray-500">
                                    by <?php echo htmlspecialchars($request['shop_name']); ?> • 
                                    <?php echo date('M d, Y', strtotime($request['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if (!empty($request['description'])): ?>
                            <p class="text-sm text-gray-600 mb-3 bg-gray-50 p-2 rounded">
                                <?php echo htmlspecialchars($request['description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <form method="POST" class="flex gap-2 items-end">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                            
                            <div class="flex-1">
                                <input type="text" name="admin_notes" placeholder="Note (optional)" 
                                       class="w-full px-3 py-2 text-sm border rounded focus:outline-none focus:border-primary">
                            </div>
                            
                            <button type="submit" name="action" value="approve" 
                                    class="px-4 py-2 text-sm bg-green-500 text-white rounded hover:bg-green-600">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button type="submit" name="action" value="reject"
                                    class="px-4 py-2 text-sm bg-red-500 text-white rounded hover:bg-red-600">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Processed -->
    <div class="bg-white rounded-lg shadow-sm">
        <div class="p-4 border-b">
            <h3 class="text-lg font-bold text-gray-900">
                <i class="fas fa-history text-gray-400"></i> Recent Decisions
            </h3>
        </div>
        
        <?php if (empty($processedRequests)): ?>
            <div class="p-8 text-center text-gray-500">
                <p>No processed requests yet</p>
            </div>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Category</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Seller</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php foreach ($processedRequests as $request): ?>
                        <tr>
                            <td class="px-4 py-3"><?php echo htmlspecialchars($request['category_name']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($request['shop_name']); ?></td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-1 text-xs font-medium rounded <?php echo $request['status'] === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($request['updated_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
