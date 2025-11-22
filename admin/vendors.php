<?php
require_once '../config/config.php';
require_once '../config/database.php';

requireAdmin();

// Handle vendor approval/rejection
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_id'], $_POST['action'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $vendor_id = validateInput($_POST['vendor_id'], 'int');
        $action = $_POST['action'];
        
        if ($vendor_id && in_array($action, ['approve', 'reject'])) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $rejection_reason = ($action === 'reject' && isset($_POST['rejection_reason'])) ? trim($_POST['rejection_reason']) : null;
            
            if ($action === 'reject' && !$rejection_reason) {
                $message = '<div class="bg-red-100 text-red-700 p-4 rounded mb-6">Please provide a reason for rejection</div>';
            } else {
                if ($rejection_reason) {
                    $stmt = $conn->prepare("UPDATE vendors SET status = ?, rejection_reason = ? WHERE id = ?");
                    $stmt->execute([$status, $rejection_reason, $vendor_id]);
                } else {
                    $stmt = $conn->prepare("UPDATE vendors SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $vendor_id]);
                }
                
                // Update user role to vendor if approved
                if ($action === 'approve') {
                    $stmt = $conn->prepare("SELECT user_id FROM vendors WHERE id = ?");
                    $stmt->execute([$vendor_id]);
                    $vendor = $stmt->fetch();
                    
                    if ($vendor) {
                        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                        $stmt->execute([ROLE_VENDOR, $vendor['user_id']]);
                    }
                }
                
                $message = '<div class="bg-green-100 text-green-700 p-4 rounded mb-6"><i class="fas fa-check-circle"></i> Vendor ' . $action . 'ed successfully!</div>';
            }
        }
    }
}

// Fetch all vendors
$stmt = $conn->query("SELECT v.*, u.full_name, u.email, u.created_at as user_created 
                      FROM vendors v 
                      JOIN users u ON v.user_id = u.id 
                      ORDER BY v.created_at DESC");
$vendors = $stmt->fetchAll();

$page_title = 'Manage Vendors - SASTO Hub';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <a href="/admin/dashboard.php" class="text-primary hover:text-indigo-700 mb-2 inline-block">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Manage Vendors</h1>
        </div>
    </div>
    
    <?php echo $message; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle"></i> 
            Vendor <?php echo $_GET['success'] === 'approve' ? 'approved' : 'rejected'; ?> successfully!
        </div>
    <?php endif; ?>
    
    <!-- Filter Tabs -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="flex border-b">
            <button onclick="filterVendors('all')" class="filter-tab active px-6 py-3 font-medium">
                All Vendors
            </button>
            <button onclick="filterVendors('pending')" class="filter-tab px-6 py-3 font-medium text-yellow-600">
                Pending
            </button>
            <button onclick="filterVendors('approved')" class="filter-tab px-6 py-3 font-medium text-green-600">
                Approved
            </button>
            <button onclick="filterVendors('rejected')" class="filter-tab px-6 py-3 font-medium text-red-600">
                Rejected
            </button>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($vendors)): ?>
            <div class="p-12 text-center">
                <i class="fas fa-store text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-bold text-gray-900 mb-2">No Vendors Yet</h3>
                <p class="text-gray-600">Vendor applications will appear here</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Vendor</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Shop Info</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Applied On</th>
                            <th class="px-6 py-4 text-left text-sm font-medium text-gray-600">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-medium text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php foreach ($vendors as $vendor): ?>
                            <tr class="vendor-row" data-status="<?php echo $vendor['status']; ?>">
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($vendor['full_name']); ?></p>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($vendor['email']); ?></p>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($vendor['shop_name']); ?></p>
                                        <p class="text-sm text-gray-600 line-clamp-2"><?php echo htmlspecialchars($vendor['shop_description']); ?></p>
                                        <?php if ($vendor['address']): ?>
                                            <p class="text-xs text-gray-500 mt-1">
                                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($vendor['address']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($vendor['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs rounded-full font-medium <?php 
                                        echo $vendor['status'] === 'approved' ? 'bg-green-100 text-green-700' : 
                                             ($vendor['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); 
                                    ?>">
                                        <?php echo ucfirst($vendor['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <?php if ($vendor['status'] === 'pending'): ?>
                                        <form method="POST" class="inline-flex gap-2">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                            <button type="submit" name="action" value="approve" 
                                                    onclick="return confirm('Approve this vendor?')"
                                                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button onclick="openRejectModal(<?php echo $vendor['id']; ?>, '<?php echo htmlspecialchars($vendor['shop_name']); ?>')"
                                                class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm font-medium">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($vendor['status'] === 'approved'): ?>
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-check-circle text-green-600"></i> Active
                                        </span>
                                    <?php else: ?>
                                        <div class="space-y-1">
                                            <form method="POST" class="inline">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="vendor_id" value="<?php echo $vendor['id']; ?>">
                                                <button type="submit" name="action" value="approve" 
                                                        onclick="return confirm('Re-approve this vendor?')"
                                                        class="text-green-600 hover:text-green-700 text-sm font-medium">
                                                    <i class="fas fa-redo"></i> Re-approve
                                                </button>
                                            </form>
                                            <?php if ($vendor['rejection_reason']): ?>
                                                <p class="text-xs text-red-600">Reason: <?php echo htmlspecialchars(substr($vendor['rejection_reason'], 0, 50)); ?>...</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>



<!-- Rejection Reason Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-sm w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-2">Reject Vendor</h3>
        <p class="text-gray-600 mb-4" id="vendorName"></p>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" id="vendorId" name="vendor_id">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection *</label>
                <textarea name="rejection_reason" rows="4" required placeholder="Explain why this vendor application was rejected..."
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                          minlength="10" maxlength="500"></textarea>
                <p class="text-xs text-gray-500 mt-1">Minimum 10 characters, maximum 500</p>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition font-medium">
                    Reject Vendor
                </button>
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition font-medium">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(vendorId, shopName) {
    document.getElementById('vendorId').value = vendorId;
    document.getElementById('vendorName').textContent = 'Shop: ' + shopName;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.querySelector('textarea[name="rejection_reason"]').focus();
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRejectModal();
});

function filterVendors(status) {
    const rows = document.querySelectorAll('.vendor-row');
    const tabs = document.querySelectorAll('.filter-tab');
    
    tabs.forEach(tab => tab.classList.remove('active', 'border-b-2', 'border-primary', 'text-primary'));
    event.target.classList.add('active', 'border-b-2', 'border-primary', 'text-primary');
    
    rows.forEach(row => {
        if (status === 'all' || row.dataset.status === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<style>
.filter-tab.active {
    border-bottom: 2px solid #4F46E5;
    color: #4F46E5;
}
</style>

<?php include '../includes/footer.php'; ?>