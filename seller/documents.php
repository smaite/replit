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

$page_title = 'My Documents - Seller Dashboard';
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
            <span class="text-gray-900 font-medium">Documents</span>
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
                        <a href="/seller/documents.php" class="flex items-center gap-3 px-4 py-3 bg-primary/10 text-primary font-bold rounded-lg transition-colors">
                            <i class="fas fa-file-alt w-5"></i> Documents
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:col-span-3">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900">Verification Documents</h1>
                    <p class="text-gray-600 mt-1">Manage your business verification documents</p>
                </div>

                <!-- Application Status Alert -->
                <div class="bg-white border border-gray-200 rounded-xl p-6 mb-8 shadow-sm">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl flex-shrink-0">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-gray-900 mb-1">Application Status</h3>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-gray-600">Current Status:</span>
                                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide <?php
                                    echo $vendor['status'] === 'approved' ? 'bg-green-100 text-green-700' :
                                         ($vendor['status'] === 'rejected' ? 'bg-red-100 text-red-700' :
                                          'bg-yellow-100 text-yellow-700');
                                ?>">
                                    <?php echo $vendor['status']; ?>
                                </span>
                            </div>

                            <?php if ($vendor['status'] === 'pending'): ?>
                                <p class="text-sm text-gray-600">We're reviewing your documents. This usually takes 3-5 business days.</p>
                            <?php elseif ($vendor['status'] === 'approved'): ?>
                                <p class="text-sm text-green-600 font-medium">Your application has been approved! You can now start selling.</p>
                            <?php elseif ($vendor['status'] === 'rejected'): ?>
                                <div class="mt-2 bg-red-50 border border-red-100 rounded-lg p-3">
                                    <p class="text-sm font-bold text-red-800 mb-1">Reason for Rejection:</p>
                                    <p class="text-sm text-red-600"><?php echo htmlspecialchars($vendor['rejection_reason']); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Document List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
                            <i class="fas fa-file-upload text-primary"></i> Uploaded Documents
                        </h2>
                        <span class="text-sm font-medium px-3 py-1 bg-gray-100 text-gray-600 rounded-full">
                            <?php echo count($documents); ?> / 4 Uploaded
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- National ID Front -->
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center transition-colors <?php echo isset($docsByType['national_id_front']) ? 'bg-green-50/50 border-green-200' : 'bg-gray-50 hover:bg-gray-100'; ?>">
                            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-id-card text-3xl <?php echo isset($docsByType['national_id_front']) ? 'text-green-500' : 'text-gray-400'; ?>"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-2">National ID - Front</h4>

                            <?php if (isset($docsByType['national_id_front'])): ?>
                                <div class="flex items-center justify-center gap-2 mb-4">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <span class="text-sm text-green-700 font-bold">Uploaded</span>
                                </div>
                                <div class="flex justify-center gap-2">
                                    <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['national_id_front']['document_url']); ?>', 'National ID - Front')"
                                            class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                    <a href="<?php echo htmlspecialchars($docsByType['national_id_front']['document_url']); ?>" download
                                       class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-download mr-1"></i>
                                    </a>
                                </div>
                                <p class="text-xs text-gray-400 mt-4">
                                    Uploaded: <?php echo date('M d, Y', strtotime($docsByType['national_id_front']['created_at'])); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 mb-4">Not uploaded yet</p>
                                <a href="/auth/become-vendor.php" class="inline-block px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                                    <i class="fas fa-upload mr-1"></i> Upload Now
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- National ID Back -->
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center transition-colors <?php echo isset($docsByType['national_id_back']) ? 'bg-green-50/50 border-green-200' : 'bg-gray-50 hover:bg-gray-100'; ?>">
                            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-id-card text-3xl <?php echo isset($docsByType['national_id_back']) ? 'text-green-500' : 'text-gray-400'; ?>"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-2">National ID - Back</h4>

                            <?php if (isset($docsByType['national_id_back'])): ?>
                                <div class="flex items-center justify-center gap-2 mb-4">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <span class="text-sm text-green-700 font-bold">Uploaded</span>
                                </div>
                                <div class="flex justify-center gap-2">
                                    <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['national_id_back']['document_url']); ?>', 'National ID - Back')"
                                            class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                    <a href="<?php echo htmlspecialchars($docsByType['national_id_back']['document_url']); ?>" download
                                       class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-download mr-1"></i>
                                    </a>
                                </div>
                                <p class="text-xs text-gray-400 mt-4">
                                    Uploaded: <?php echo date('M d, Y', strtotime($docsByType['national_id_back']['created_at'])); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 mb-4">Not uploaded yet</p>
                                <a href="/auth/become-vendor.php" class="inline-block px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                                    <i class="fas fa-upload mr-1"></i> Upload Now
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- PAN/VAT Certificate -->
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center transition-colors <?php echo isset($docsByType['pan_vat_document']) ? 'bg-green-50/50 border-green-200' : 'bg-gray-50 hover:bg-gray-100'; ?>">
                            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-file-invoice text-3xl <?php echo isset($docsByType['pan_vat_document']) ? 'text-green-500' : 'text-gray-400'; ?>"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-2">PAN/VAT Certificate</h4>

                            <?php if (isset($docsByType['pan_vat_document'])): ?>
                                <div class="flex items-center justify-center gap-2 mb-4">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <span class="text-sm text-green-700 font-bold">Uploaded</span>
                                </div>
                                <div class="flex justify-center gap-2">
                                    <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['pan_vat_document']['document_url']); ?>', 'PAN/VAT Certificate')"
                                            class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                    <a href="<?php echo htmlspecialchars($docsByType['pan_vat_document']['document_url']); ?>" download
                                       class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-download mr-1"></i>
                                    </a>
                                </div>
                                <p class="text-xs text-gray-400 mt-4">
                                    Uploaded: <?php echo date('M d, Y', strtotime($docsByType['pan_vat_document']['created_at'])); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 mb-4">Not uploaded yet</p>
                                <a href="/auth/become-vendor.php" class="inline-block px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                                    <i class="fas fa-upload mr-1"></i> Upload Now
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- Business Registration -->
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center transition-colors <?php echo isset($docsByType['business_registration']) ? 'bg-green-50/50 border-green-200' : 'bg-gray-50 hover:bg-gray-100'; ?>">
                            <div class="w-16 h-16 bg-white rounded-full shadow-sm flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-building text-3xl <?php echo isset($docsByType['business_registration']) ? 'text-green-500' : 'text-gray-400'; ?>"></i>
                            </div>
                            <h4 class="text-lg font-bold text-gray-900 mb-2">Business Registration</h4>

                            <?php if (isset($docsByType['business_registration'])): ?>
                                <div class="flex items-center justify-center gap-2 mb-4">
                                    <i class="fas fa-check-circle text-green-500"></i>
                                    <span class="text-sm text-green-700 font-bold">Uploaded</span>
                                </div>
                                <div class="flex justify-center gap-2">
                                    <button type="button" onclick="previewDocument('<?php echo htmlspecialchars($docsByType['business_registration']['document_url']); ?>', 'Business Registration')"
                                            class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                    <a href="<?php echo htmlspecialchars($docsByType['business_registration']['document_url']); ?>" download
                                       class="px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-lg text-sm font-bold hover:bg-gray-50 transition shadow-sm">
                                        <i class="fas fa-download mr-1"></i>
                                    </a>
                                </div>
                                <p class="text-xs text-gray-400 mt-4">
                                    Uploaded: <?php echo date('M d, Y', strtotime($docsByType['business_registration']['created_at'])); ?>
                                </p>
                            <?php else: ?>
                                <p class="text-sm text-gray-500 mb-4">Not uploaded yet</p>
                                <a href="/auth/become-vendor.php" class="inline-block px-4 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                                    <i class="fas fa-upload mr-1"></i> Upload Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div id="previewModal" class="hidden fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col shadow-2xl animate-fade-in-up">
        <div class="bg-white border-b border-gray-100 px-6 py-4 flex justify-between items-center z-10">
            <h3 id="previewTitle" class="text-lg font-bold text-gray-900">Document Preview</h3>
            <button type="button" onclick="closePreview()" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-900 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="flex-1 overflow-auto p-8 bg-gray-50 flex items-center justify-center">
            <div id="previewContainer" class="text-center w-full h-full flex items-center justify-center"></div>
        </div>
        <div class="border-t border-gray-100 bg-white px-6 py-4 flex justify-end items-center gap-3">
            <button type="button" onclick="closePreview()" class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-bold hover:bg-gray-50 transition">
                Close
            </button>
            <a id="downloadBtn" href="#" download class="px-5 py-2.5 rounded-xl bg-primary text-white font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center gap-2">
                <i class="fas fa-download"></i> Download
            </a>
        </div>
    </div>
</div>

<script>
function previewDocument(url, title) {
    const container = document.getElementById('previewContainer');
    const previewTitle = document.getElementById('previewTitle');
    const downloadBtn = document.getElementById('downloadBtn');
    const modal = document.getElementById('previewModal');

    previewTitle.textContent = title;
    downloadBtn.href = url;
    downloadBtn.download = title.replace(/\s+/g, '_') + (url.includes('.pdf') ? '.pdf' : '.jpg');

    // Determine file type and display accordingly
    if (url.toLowerCase().endsWith('.pdf')) {
        container.innerHTML = '<embed src="' + url + '" type="application/pdf" width="100%" height="100%" style="min-height: 500px; border-radius: 8px;" />';
    } else {
        // Image display with proper sizing
        container.innerHTML = '<img src="' + url + '" alt="' + title + '" style="max-width: 100%; max-height: 600px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);" />';
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closePreview() {
    document.getElementById('previewModal').classList.add('hidden');
    document.body.style.overflow = ''; // Restore scrolling
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePreview();
    }
});

// Close modal when clicking outside
document.getElementById('previewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePreview();
    }
});
</script>

<?php include '../includes/footer.php'; ?>
