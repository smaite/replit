<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/mail.php';

// Check admin access
if (!isLoggedIn() || !isAdmin()) {
    redirect('/auth/login.php');
}

$vendor_id = (int)($_GET['id'] ?? $_POST['vendor_id'] ?? 0);

if ($vendor_id <= 0) {
    redirect('/admin/vendors-verification.php');
}

$error = '';
$success = '';

// Handle vendor approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
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
                    $old_status = $vendor['status'];
                    $stmt = $conn->prepare("UPDATE vendors SET status = 'approved', rejection_reason = NULL WHERE id = ?");
                    $stmt->execute([$vendor_id]);
                    
                    // Send appropriate email based on previous status
                    if ($old_status === 'rejected') {
                        $mailSent = sendVendorReapprovalEmail($vendor, $vendor);
                    } else {
                        $mailSent = sendVendorApprovalEmail($vendor, $vendor);
                    }
                    
                    $success = 'Vendor application approved successfully!';
                    if ($mailSent) {
                        $success .= ' Approval email sent to ' . htmlspecialchars($vendor['email']);
                    }
                    
                } elseif ($action === 'reject' || $action === 'revoke') {
                    $rejection_reason = sanitize($_POST['rejection_reason'] ?? '');
                    if (empty($rejection_reason)) {
                        $error = 'Please provide a reason.';
                    } else {
                        $stmt = $conn->prepare("UPDATE vendors SET status = 'rejected', rejection_reason = ? WHERE id = ?");
                        $stmt->execute([$rejection_reason, $vendor_id]);
                        
                        // Send rejection email
                        $mailSent = sendVendorRejectionEmail($vendor, $vendor, $rejection_reason);
                        
                        $successMsg = ($action === 'revoke') ? 'Vendor approval revoked.' : 'Vendor application rejected.';
                        $success = $successMsg . ' Reason has been saved.';
                        if ($mailSent) {
                            $success .= ' Notification email sent to ' . htmlspecialchars($vendor['email']);
                        }
                    }
                }
            } catch (Exception $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get vendor details
$stmt = $conn->prepare("SELECT v.*, u.email, u.full_name, u.phone, u.created_at as user_created 
                       FROM vendors v 
                       JOIN users u ON v.user_id = u.id 
                       WHERE v.id = ?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('/admin/vendors-verification.php');
}

// Get vendor documents
$stmt = $conn->prepare("SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY document_type");
$stmt->execute([$vendor_id]);
$documents = $stmt->fetchAll();

// Organize documents by type
$docsByType = [];
foreach ($documents as $doc) {
    $docsByType[$doc['document_type']] = $doc;
}

$page_title = 'Vendor Details - ' . htmlspecialchars($vendor['shop_name']);
include '../includes/admin_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <!-- Back Button -->
    <div class="mb-6">
        <a href="/admin/vendors-verification.php" class="text-primary hover:text-indigo-700 font-medium">
            <i class="fas fa-arrow-left"></i> Back to Verification
        </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-6">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Vendor Header -->
    <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            <!-- Shop Info -->
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($vendor['shop_name']); ?></h1>
                <p class="text-gray-600 mb-1">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($vendor['full_name']); ?>
                </p>
                <p class="text-gray-600 mb-1">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($vendor['email']); ?>
                </p>
                <p class="text-gray-600">
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($vendor['phone'] ?? 'N/A'); ?>
                </p>
            </div>

            <!-- Status & Dates -->
            <div>
                <p class="text-sm text-gray-600 mb-3"><strong>Status:</strong></p>
                <div class="mb-4">
                    <span class="inline-block px-4 py-2 rounded-full text-sm font-medium <?php 
                        echo $vendor['status'] === 'approved' ? 'bg-green-100 text-green-800' : 
                             ($vendor['status'] === 'rejected' ? 'bg-red-100 text-red-800' : 
                              'bg-yellow-100 text-yellow-800');
                    ?>">
                        <?php echo strtoupper($vendor['status']); ?>
                    </span>
                </div>

                <p class="text-sm text-gray-600 mb-1">
                    <strong>Applied:</strong><br>
                    <?php echo date('M d, Y \a\t h:i A', strtotime($vendor['created_at'])); ?>
                </p>
                <p class="text-sm text-gray-600">
                    <strong>Member Since:</strong><br>
                    <?php echo date('M d, Y', strtotime($vendor['user_created'])); ?>
                </p>
            </div>

            <!-- Location & Bank -->
            <div>
                <p class="text-sm text-gray-600 mb-1">
                    <strong><i class="fas fa-map-marker-alt"></i> Location:</strong><br>
                    <?php echo htmlspecialchars($vendor['business_city'] ?? 'N/A'); ?>, 
                    <?php echo htmlspecialchars($vendor['business_state'] ?? 'N/A'); ?> 
                    <?php echo htmlspecialchars($vendor['business_postal_code'] ?? 'N/A'); ?>
                </p>
                <p class="text-sm text-gray-600 mb-3">
                    <strong><i class="fas fa-university"></i> Bank Account:</strong><br>
                    <span class="text-xs">****<?php echo substr($vendor['bank_account_number'] ?? 'N/A', -4); ?></span>
                </p>
                <p class="text-sm text-gray-600">
                    <strong><i class="fas fa-file"></i> Documents:</strong><br>
                    <span class="font-medium"><?php echo count($documents); ?>/4 Uploaded</span>
                </p>
            </div>
        </div>

        <div class="border-t pt-6">
            <h3 class="text-lg font-bold text-gray-900 mb-3">Shop Description</h3>
            <p class="text-gray-700 leading-relaxed"><?php echo htmlspecialchars($vendor['shop_description']); ?></p>
            
            <?php if (!empty($vendor['business_website'])): ?>
                <p class="text-gray-600 mt-3">
                    <strong>Website:</strong> 
                    <a href="<?php echo htmlspecialchars($vendor['business_website']); ?>" target="_blank" class="text-primary hover:underline">
                        <?php echo htmlspecialchars($vendor['business_website']); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>

        <?php if (!empty($vendor['rejection_reason'])): ?>
            <div class="border-t pt-6 mt-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h4 class="text-lg font-bold text-red-900 mb-2">Rejection Reason</h4>
                    <p class="text-red-700"><?php echo htmlspecialchars($vendor['rejection_reason']); ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Documents Section -->
    <div class="bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">
            <i class="fas fa-file-upload"></i> Uploaded Documents
        </h2>

        <?php if (empty($documents)): ?>
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                <p class="text-gray-600">No documents uploaded yet.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- National ID Front -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <div class="mb-3">
                        <i class="fas fa-id-card text-4xl text-primary"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">National ID - Front</h4>
                    <?php if (isset($docsByType['national_id_front'])): ?>
                        <p class="text-sm text-gray-600 mb-3">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['national_id_front']['document_url']); ?>', 'National ID - Front')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-eye"></i> View Document
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['national_id_front']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600">❌ Missing</p>
                    <?php endif; ?>
                </div>

                <!-- National ID Back -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <div class="mb-3">
                        <i class="fas fa-id-card text-4xl text-primary"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">National ID - Back</h4>
                    <?php if (isset($docsByType['national_id_back'])): ?>
                        <p class="text-sm text-gray-600 mb-3">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['national_id_back']['document_url']); ?>', 'National ID - Back')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-eye"></i> View Document
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['national_id_back']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600">❌ Missing</p>
                    <?php endif; ?>
                </div>

                <!-- PAN/VAT Certificate -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <div class="mb-3">
                        <i class="fas fa-file-certificate text-4xl text-primary"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">PAN/VAT Certificate</h4>
                    <?php if (isset($docsByType['pan_vat_document'])): ?>
                        <p class="text-sm text-gray-600 mb-3">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['pan_vat_document']['document_url']); ?>', 'PAN/VAT Certificate')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-eye"></i> View Document
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['pan_vat_document']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600">❌ Missing</p>
                    <?php endif; ?>
                </div>

                <!-- Business Registration -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                    <div class="mb-3">
                        <i class="fas fa-file-contract text-4xl text-primary"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">Business Registration</h4>
                    <?php if (isset($docsByType['business_registration'])): ?>
                        <p class="text-sm text-gray-600 mb-3">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['business_registration']['document_url']); ?>', 'Business Registration')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                            <i class="fas fa-eye"></i> View Document
                        </button>
                        <p class="text-xs text-gray-500 mt-2">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['business_registration']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600">❌ Missing</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Document Summary -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-900">
                    <i class="fas fa-info-circle"></i> 
                    <strong><?php echo count($documents); ?> of 4 documents</strong> have been uploaded. 
                    <?php if (count($documents) < 4): ?>
                        <span class="text-orange-600">Missing: <?php echo 4 - count($documents); ?> document(s)</span>
                    <?php else: ?>
                        <span class="text-green-600">✅ All documents complete</span>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- Action Buttons -->
        <div class="border-t pt-6">
            <div class="flex flex-wrap gap-3">
                <?php if ($vendor['status'] === 'pending'): ?>
                    <form method="POST" action="/admin/vendor-detail.php?id=<?php echo $vendor_id; ?>" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition" onclick="return confirm('Approve this vendor?')">
                            <i class="fas fa-check-circle"></i> Approve Vendor
                        </button>
                    </form>
                    <button type="button" onclick="showRejectModal()" class="bg-red-500 hover:bg-red-600 text-white px-6 py-3 rounded-lg font-medium transition">
                        <i class="fas fa-times-circle"></i> Reject Vendor
                    </button>
                <?php elseif ($vendor['status'] === 'approved'): ?>
                    <button type="button" onclick="showRejectModal()" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                        <i class="fas fa-ban"></i> Revoke Approval
                    </button>
                <?php else: ?>
                    <form method="POST" action="/admin/vendor-detail.php?id=<?php echo $vendor_id; ?>" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-medium transition" onclick="return confirm('Re-approve this vendor?')">
                            <i class="fas fa-redo"></i> Re-approve Vendor
                        </button>
                    </form>
                    <button type="button" onclick="showRejectModal()" class="bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-lg font-medium transition">
                        <i class="fas fa-pen"></i> Update Rejection Reason
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg w-full max-w-4xl max-h-[90vh] overflow-auto flex flex-col">
        <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center z-10">
            <h3 id="previewTitle" class="text-xl font-bold text-gray-900"></h3>
            <button type="button" onclick="closePreview()" class="text-gray-600 hover:text-gray-900 text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="flex-1 overflow-auto p-6 bg-white flex items-center justify-center">
            <div id="previewContainer" class="text-center max-w-full"></div>
        </div>
        <div class="border-t bg-gray-50 px-6 py-4 flex justify-between items-center gap-3">
            <a id="downloadBtn" href="#" download class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium transition">
                <i class="fas fa-download"></i> Download
            </a>
            <button type="button" onclick="closePreview()" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <h3 class="text-xl font-bold text-gray-900 mb-4" id="rejectModalTitle">Reject Vendor Application</h3>
        <p class="text-gray-600 mb-4" id="rejectModalDesc">Please provide a reason for rejection.</p>

        <form method="POST" action="/admin/vendor-detail.php?id=<?php echo $vendor_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" id="rejectAction" value="reject">
            <input type="hidden" name="vendor_id" value="<?php echo $vendor_id; ?>">

            <textarea name="rejection_reason" required placeholder="Enter reason..." 
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary mb-4" 
                      rows="4"></textarea>

            <div class="flex gap-3">
                <button type="submit" id="rejectSubmitBtn" class="flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition">
                    <i class="fas fa-times"></i> Reject
                </button>
                <button type="button" onclick="closeRejectModal()" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-2 rounded-lg font-medium transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewDocument(url, title) {
    const container = document.getElementById('previewContainer');
    const previewTitle = document.getElementById('previewTitle');
    const downloadBtn = document.getElementById('downloadBtn');
    
    previewTitle.textContent = title;
    downloadBtn.href = url;
    downloadBtn.download = title.replace(/\s+/g, '_') + (url.includes('.pdf') ? '.pdf' : '.jpg');
    
    // Determine file type and display accordingly
    if (url.toLowerCase().endsWith('.pdf')) {
        container.innerHTML = '<embed src="' + url + '" type="application/pdf" width="100%" height="600px" style="background:white;" />';
    } else {
        // Image display with proper sizing
        container.innerHTML = '<img src="' + url + '" alt="' + title + '" style="max-width: 100%; max-height: 600px; object-fit: contain; border-radius: 8px; background: white;" />';
    }
    
    document.getElementById('previewModal').classList.remove('hidden');
}

function closePreview() {
    document.getElementById('previewModal').classList.add('hidden');
}

function showRejectModal() {
    const status = '<?php echo $vendor["status"]; ?>';
    const modal = document.getElementById('rejectModal');
    const title = document.getElementById('rejectModalTitle');
    const desc = document.getElementById('rejectModalDesc');
    const btn = document.getElementById('rejectSubmitBtn');
    const action = document.getElementById('rejectAction');
    
    if (status === 'approved') {
        title.textContent = 'Revoke Vendor Approval';
        desc.textContent = 'Are you sure? Provide a reason for revoking this vendor\'s approval.';
        btn.innerHTML = '<i class="fas fa-ban"></i> Revoke';
        btn.className = 'flex-1 bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg font-medium transition';
        action.value = 'revoke';
    } else {
        title.textContent = 'Reject Vendor Application';
        desc.textContent = 'Please provide a reason for rejection.';
        btn.innerHTML = '<i class="fas fa-times"></i> Reject';
        btn.className = 'flex-1 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg font-medium transition';
        action.value = 'reject';
    }
    
    modal.classList.remove('hidden');
    document.querySelector('textarea[name="rejection_reason"]').focus();
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}

// Close modals on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreview();
        closeRejectModal();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
