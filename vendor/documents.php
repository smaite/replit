<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check vendor access
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

// Get vendor info
$stmt = $conn->prepare("SELECT v.*, u.email, u.full_name FROM vendors v 
                       JOIN users u ON v.user_id = u.id 
                       WHERE v.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('/auth/become-vendor.php');
}

// Get vendor documents
$stmt = $conn->prepare("SELECT * FROM vendor_documents WHERE vendor_id = ? ORDER BY document_type");
$stmt->execute([$vendor['id']]);
$documents = $stmt->fetchAll();

// Organize documents by type
$docsByType = [];
foreach ($documents as $doc) {
    $docsByType[$doc['document_type']] = $doc;
}

$page_title = 'My Uploaded Documents';
include '../includes/vendor_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">My Documents</h1>
            <p class="text-gray-600 mt-2">View and manage your uploaded verification documents</p>
        </div>

        <!-- Application Status Alert -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-8">
            <div class="flex items-start gap-4">
                <i class="fas fa-info-circle text-2xl text-blue-600 mt-1"></i>
                <div>
                    <h3 class="font-bold text-blue-900 mb-2">Application Status</h3>
                    <p class="text-blue-700 mb-3">
                        Your vendor application is currently: 
                        <span class="font-bold <?php 
                            echo $vendor['status'] === 'approved' ? 'text-green-600' : 
                                 ($vendor['status'] === 'rejected' ? 'text-red-600' : 
                                  'text-yellow-600');
                        ?>">
                            <?php echo strtoupper($vendor['status']); ?>
                        </span>
                    </p>
                    
                    <?php if ($vendor['status'] === 'pending'): ?>
                        <p class="text-sm text-blue-700">We're reviewing your documents. This usually takes 3-5 business days.</p>
                    <?php elseif ($vendor['status'] === 'approved'): ?>
                        <p class="text-sm text-green-700">✅ Your application has been approved! You can now start selling.</p>
                    <?php elseif ($vendor['status'] === 'rejected'): ?>
                        <div class="mt-3 bg-red-100 border border-red-300 rounded p-3">
                            <p class="text-sm font-medium text-red-900 mb-1">Reason for Rejection:</p>
                            <p class="text-sm text-red-700"><?php echo htmlspecialchars($vendor['rejection_reason']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Document List -->
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">
                <i class="fas fa-file-upload"></i> Verification Documents
            </h2>

            <p class="text-gray-600 mb-6">
                You have uploaded <strong><?php echo count($documents); ?> of 4</strong> required documents.
                <?php if (count($documents) < 4): ?>
                    <span class="text-orange-600 font-medium">
                        (Missing: <?php echo 4 - count($documents); ?> document(s))
                    </span>
                <?php else: ?>
                    <span class="text-green-600 font-medium">✅ Complete</span>
                <?php endif; ?>
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <!-- National ID Front -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center <?php echo isset($docsByType['national_id_front']) ? 'bg-green-50 border-green-300' : 'bg-gray-50'; ?>">
                    <div class="mb-3">
                        <i class="fas fa-id-card text-4xl <?php echo isset($docsByType['national_id_front']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">National ID - Front</h4>
                    
                    <?php if (isset($docsByType['national_id_front'])): ?>
                        <p class="text-sm text-green-600 mb-3 font-medium">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['national_id_front']['document_url']); ?>', 'National ID - Front')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition mb-2 inline-block">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <a href="<?php echo htmlspecialchars($docsByType['national_id_front']['document_url']); ?>" download 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition inline-block ml-2">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <p class="text-xs text-gray-500 mt-3">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['national_id_front']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600 font-medium mb-2">❌ Not Uploaded</p>
                        <a href="/auth/become-vendor.php" class="text-primary hover:text-indigo-700 text-sm font-medium">
                            <i class="fas fa-upload"></i> Upload Now
                        </a>
                    <?php endif; ?>
                </div>

                <!-- National ID Back -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center <?php echo isset($docsByType['national_id_back']) ? 'bg-green-50 border-green-300' : 'bg-gray-50'; ?>">
                    <div class="mb-3">
                        <i class="fas fa-id-card text-4xl <?php echo isset($docsByType['national_id_back']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">National ID - Back</h4>
                    
                    <?php if (isset($docsByType['national_id_back'])): ?>
                        <p class="text-sm text-green-600 mb-3 font-medium">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['national_id_back']['document_url']); ?>', 'National ID - Back')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition mb-2 inline-block">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <a href="<?php echo htmlspecialchars($docsByType['national_id_back']['document_url']); ?>" download 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition inline-block ml-2">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <p class="text-xs text-gray-500 mt-3">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['national_id_back']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600 font-medium mb-2">❌ Not Uploaded</p>
                        <a href="/auth/become-vendor.php" class="text-primary hover:text-indigo-700 text-sm font-medium">
                            <i class="fas fa-upload"></i> Upload Now
                        </a>
                    <?php endif; ?>
                </div>

                <!-- PAN/VAT Certificate -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center <?php echo isset($docsByType['pan_vat_document']) ? 'bg-green-50 border-green-300' : 'bg-gray-50'; ?>">
                    <div class="mb-3">
                        <i class="fas fa-file-certificate text-4xl <?php echo isset($docsByType['pan_vat_document']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">PAN/VAT Certificate</h4>
                    
                    <?php if (isset($docsByType['pan_vat_document'])): ?>
                        <p class="text-sm text-green-600 mb-3 font-medium">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['pan_vat_document']['document_url']); ?>', 'PAN/VAT Certificate')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition mb-2 inline-block">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <a href="<?php echo htmlspecialchars($docsByType['pan_vat_document']['document_url']); ?>" download 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition inline-block ml-2">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <p class="text-xs text-gray-500 mt-3">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['pan_vat_document']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600 font-medium mb-2">❌ Not Uploaded</p>
                        <a href="/auth/become-vendor.php" class="text-primary hover:text-indigo-700 text-sm font-medium">
                            <i class="fas fa-upload"></i> Upload Now
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Business Registration -->
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center <?php echo isset($docsByType['business_registration']) ? 'bg-green-50 border-green-300' : 'bg-gray-50'; ?>">
                    <div class="mb-3">
                        <i class="fas fa-file-contract text-4xl <?php echo isset($docsByType['business_registration']) ? 'text-green-600' : 'text-gray-400'; ?>"></i>
                    </div>
                    <h4 class="text-lg font-bold text-gray-900 mb-2">Business Registration</h4>
                    
                    <?php if (isset($docsByType['business_registration'])): ?>
                        <p class="text-sm text-green-600 mb-3 font-medium">✅ Uploaded</p>
                        <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['business_registration']['document_url']); ?>', 'Business Registration')" 
                                class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition mb-2 inline-block">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <a href="<?php echo htmlspecialchars($docsByType['business_registration']['document_url']); ?>" download 
                           class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition inline-block ml-2">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <p class="text-xs text-gray-500 mt-3">
                            Uploaded: <?php echo date('M d, Y', strtotime($docsByType['business_registration']['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="text-sm text-red-600 font-medium mb-2">❌ Not Uploaded</p>
                        <a href="/auth/become-vendor.php" class="text-primary hover:text-indigo-700 text-sm font-medium">
                            <i class="fas fa-upload"></i> Upload Now
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Document Summary -->
            <?php if (count($documents) > 0): ?>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-900">
                        <i class="fas fa-info-circle"></i> 
                        You can view and download your documents anytime. Once all documents are uploaded, your application will be reviewed by our team.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Action Button -->
            <div class="border-t pt-6">
                <a href="/vendor/" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-6 py-3 rounded-lg font-medium transition inline-block">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
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

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreview();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
