<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/mail.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('/auth/login.php');
}

$error = '';
$success = '';
$filter = $_GET['filter'] ?? 'pending'; // pending, approved, rejected, all

// Handle vendor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $vendor_id = (int)$_POST['vendor_id'];
        $action = $_POST['action'];
        
        // Get vendor data with user info
        $stmt = $conn->prepare("SELECT v.*, u.email, u.full_name FROM vendors v JOIN users u ON v.user_id = u.id WHERE v.id = ?");
        $stmt->execute([$vendor_id]);
        $vendor = $stmt->fetch();
        
        if (!$vendor) {
            $error = 'Vendor not found.';
        } else {
            try {
                if ($action === 'approve') {
                    $stmt = $conn->prepare("UPDATE vendors SET status = 'approved', rejection_reason = NULL WHERE id = ?");
                    $stmt->execute([$vendor_id]);
                    
                    // Send approval email
                    $mailSent = sendVendorApprovalEmail($vendor, $vendor);
                    
                    $success = 'Vendor application approved successfully!';
                    if ($mailSent) {
                        $success .= ' Email notification sent.';
                    }
                    
                } elseif ($action === 'reject') {
                    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
                    if (empty($rejection_reason)) {
                        $error = 'Please provide a reason for rejection.';
                    } else {
                        $stmt = $conn->prepare("UPDATE vendors SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $vendor_id]);
                        
                        // Send rejection email
                        $mailSent = sendVendorRejectionEmail($vendor, $vendor, $rejection_reason);
                        
                        $success = 'Vendor application rejected. Reason has been saved.';
                        if ($mailSent) {
                            $success .= ' Notification email sent.';
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get vendors based on filter
$query = "SELECT v.*, u.email, u.full_name, u.phone FROM vendors v 
          JOIN users u ON v.user_id = u.id 
          WHERE 1=1";

if ($filter === 'pending') {
    $query .= " AND v.status = 'pending'";
} elseif ($filter === 'approved') {
    $query .= " AND v.status = 'approved'";
} elseif ($filter === 'rejected') {
    $query .= " AND v.status = 'rejected'";
}

$query .= " ORDER BY v.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute();
$vendors = $stmt->fetchAll();

// Get document count for each vendor
$vendorDocuments = [];
if (!empty($vendors)) {
    $vendor_ids = array_column($vendors, 'id');
    $placeholders = implode(',', $vendor_ids);
    $stmt = $conn->prepare("SELECT vendor_id, COUNT(*) as doc_count FROM vendor_documents WHERE vendor_id IN ($placeholders) GROUP BY vendor_id");
    $stmt->execute();
    $docs = $stmt->fetchAll();
    foreach ($docs as $doc) {
        $vendorDocuments[$doc['vendor_id']] = $doc['doc_count'];
    }
}

$page_title = 'Vendor Verification - Admin';
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Vendor Verification</h1>
                <p class="text-gray-600 mt-2">Review and approve vendor applications</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-lg mb-6">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-6 border-b">
        <a href="?filter=pending" class="px-6 py-3 font-medium <?php echo $filter === 'pending' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-900'; ?>">
            <i class="fas fa-hourglass-half"></i> Pending
            <?php
            $stmt = $conn->prepare("SELECT COUNT(*) FROM vendors WHERE status = 'pending'");
            $stmt->execute();
            $pending_count = $stmt->fetchColumn();
            echo "($pending_count)";
            ?>
        </a>
        <a href="?filter=approved" class="px-6 py-3 font-medium <?php echo $filter === 'approved' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-900'; ?>">
            <i class="fas fa-check-circle"></i> Approved
            <?php
            $stmt = $conn->prepare("SELECT COUNT(*) FROM vendors WHERE status = 'approved'");
            $stmt->execute();
            $approved_count = $stmt->fetchColumn();
            echo "($approved_count)";
            ?>
        </a>
        <a href="?filter=rejected" class="px-6 py-3 font-medium <?php echo $filter === 'rejected' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-900'; ?>">
            <i class="fas fa-times-circle"></i> Rejected
            <?php
            $stmt = $conn->prepare("SELECT COUNT(*) FROM vendors WHERE status = 'rejected'");
            $stmt->execute();
            $rejected_count = $stmt->fetchColumn();
            echo "($rejected_count)";
            ?>
        </a>
        <a href="?filter=all" class="px-6 py-3 font-medium <?php echo $filter === 'all' ? 'border-b-2 border-primary text-primary' : 'text-gray-600 hover:text-gray-900'; ?>">
            <i class="fas fa-th-list"></i> All
        </a>
    </div>

    <!-- Vendors List -->
    <?php if (empty($vendors)): ?>
        <div class="bg-gray-50 rounded-lg p-12 text-center">
            <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-500 mb-2">No vendors to display</h3>
            <p class="text-gray-500">No <?php echo $filter; ?> vendor applications found.</p>
        </div>
    <?php else: ?>
        <div class="grid gap-6">
            <?php foreach ($vendors as $vendor): ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition border-l-4 <?php 
                    echo $vendor['status'] === 'approved' ? 'border-green-500' : ($vendor['status'] === 'rejected' ? 'border-red-500' : 'border-yellow-500');
                ?>">
                    <div class="p-6">
                        <!-- Header Row -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                            <!-- Shop Info -->
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 mb-1"><?php echo htmlspecialchars($vendor['shop_name']); ?></h3>
                                <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($vendor['full_name']); ?></p>
                                <p class="text-gray-500 text-sm"><?php echo htmlspecialchars($vendor['email']); ?></p>
                            </div>

                            <!-- Location & Phone -->
                            <div>
                                <p class="text-sm text-gray-600 mb-1">
                                    <strong>Location:</strong><br>
                                    <?php echo htmlspecialchars($vendor['business_city'] ?? 'N/A'); ?>, 
                                    <?php echo htmlspecialchars($vendor['business_state'] ?? 'N/A'); ?>
                                </p>
                                <p class="text-sm text-gray-600">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($vendor['business_phone'] ?? 'N/A'); ?>
                                </p>
                            </div>

                            <!-- Documents -->
                            <div>
                                <p class="text-sm font-medium text-gray-900 mb-2">Documents Uploaded:</p>
                                <div class="flex items-center gap-2">
                                    <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm font-medium">
                                        <i class="fas fa-file"></i> <?php echo $vendorDocuments[$vendor['id']] ?? 0; ?>/4
                                    </span>
                                </div>
                                <?php if (($vendorDocuments[$vendor['id']] ?? 0) < 4): ?>
                                    <p class="text-xs text-orange-600 mt-1">
                                        <i class="fas fa-exclamation-triangle"></i> Missing documents
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Status & Date -->
                            <div class="text-right">
                                <p class="text-sm text-gray-600 mb-2">
                                    <strong>Status:</strong><br>
                                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium <?php 
                                        echo $vendor['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                                             ($vendor['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 
                                              'bg-yellow-100 text-yellow-800');
                                    ?>">
                                        <?php echo strtoupper($vendor['status']); ?>
                                    </span>
                                </p>
                                <p class="text-xs text-gray-500">
                                    Applied: <?php echo date('M d, Y', strtotime($vendor['created_at'])); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="border-t my-4"></div>

                        <!-- Description & Actions -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Description -->
                            <div class="md:col-span-2">
                                <p class="text-sm text-gray-600 mb-3">
                                    <strong>Shop Description:</strong><br>
                                    <?php echo htmlspecialchars(substr($vendor['shop_description'], 0, 150)); ?>
                                    <?php if (strlen($vendor['shop_description']) > 150): ?>...<?php endif; ?>
                                </p>

                                <?php if (!empty($vendor['rejection_reason'])): ?>
                                    <div class="bg-red-50 border border-red-200 rounded p-3 mt-2">
                                        <p class="text-sm font-medium text-red-900 mb-1">Rejection Reason:</p>
                                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($vendor['rejection_reason']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-col gap-2">
                                <a href="/admin/vendor-detail.php?id=<?php echo $vendor['id']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition text-center">
                                    <i class="fas fa-eye"></i> View Details
                                </a>

                                <?php if ($vendor['status'] === 'pending'): ?>
                                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button type="button" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition" onclick="rejectVendorModal(<?php echo $vendor['id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php elseif ($vendor['status'] === 'approved'): ?>
                                    <button type="button" class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition" onclick="rejectVendorModal(<?php echo $vendor['id']; ?>)">
                                        <i class="fas fa-ban"></i> Revoke
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition" onclick="approveVendor(<?php echo $vendor['id']; ?>)">
                                        <i class="fas fa-redo"></i> Re-approve
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Form (Hidden) -->
<form id="approveForm" method="POST" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
    <input type="hidden" name="action" value="approve">
    <input type="hidden" name="vendor_id" id="approveVendorId">
</form>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Reject Vendor Application</h3>
        <p class="text-gray-600 mb-4">Please provide a reason for rejection. This will be shown to the vendor.</p>

        <form id="rejectForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="vendor_id" id="rejectVendorId">

            <textarea name="rejection_reason" required placeholder="Enter rejection reason..." 
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary mb-4" 
                      rows="4"></textarea>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function approveVendor(vendorId) {
    if (confirm('Are you sure you want to approve this vendor?')) {
        document.getElementById('approveVendorId').value = vendorId;
        document.getElementById('approveForm').submit();
    }
}

function rejectVendorModal(vendorId) {
    document.getElementById('rejectVendorId').value = vendorId;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.querySelector('textarea[name="rejection_reason"]').focus();
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeRejectModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
