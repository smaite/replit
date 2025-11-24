<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

if (isVendor() || isAdmin()) {
    redirect('/');
}

$error = '';
$success = '';

// Check for status parameters (from redirect)
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'rejected') {
        $error = 'Your vendor application was rejected. Reason: ' . htmlspecialchars($_GET['reason'] ?? 'Not specified');
    } elseif ($_GET['status'] === 'pending') {
        $error = 'Your vendor application is pending review. You will receive an email notification once it\'s approved.';
    }
}

// Check if already applied
$stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$existing_vendor = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing_vendor) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $shop_name = sanitize($_POST['shop_name'] ?? '');
        $shop_description = sanitize($_POST['shop_description'] ?? '');
        $business_location = sanitize($_POST['business_location'] ?? '');
        $business_city = sanitize($_POST['business_city'] ?? '');
        $business_state = sanitize($_POST['business_state'] ?? '');
        $business_postal_code = sanitize($_POST['business_postal_code'] ?? '');
        $business_phone = sanitize($_POST['business_phone'] ?? '');
        $business_website = sanitize($_POST['business_website'] ?? '');
        $bank_account_name = sanitize($_POST['bank_account_name'] ?? '');
        $bank_account_number = sanitize($_POST['bank_account_number'] ?? '');
        
        // Validate required fields
        if (empty($shop_name) || empty($shop_description) || empty($business_location) || 
            empty($business_city) || empty($business_phone) || empty($bank_account_name) || 
            empty($bank_account_number)) {
            $error = 'Please fill in all required fields (marked with *)';
        } elseif (empty($_FILES['national_id_front']['name']) || empty($_FILES['national_id_back']['name']) || 
                  empty($_FILES['pan_vat_document']['name']) || empty($_FILES['business_registration']['name'])) {
            $error = 'Please upload all required documents';
        } elseif (empty($_POST['accept_terms']) || empty($_POST['accept_privacy']) || empty($_POST['confirm_information'])) {
            $error = 'You must accept the Terms of Use, Privacy Policy, and confirm the information provided';
        } else {
            // Validate document uploads
            $upload_dir = '../uploads/vendor-documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            $upload_success = true;
            $uploaded_files = [];
            
            // Define document types
            $documents = [
                'national_id_front' => 'National ID (Front)',
                'national_id_back' => 'National ID (Back)',
                'pan_vat_document' => 'PAN/VAT Certificate',
                'business_registration' => 'Business Registration'
            ];
            
            // Validate and upload files
            foreach ($documents as $field => $label) {
                if (empty($_FILES[$field]['name'])) {
                    $error = "$label is required";
                    $upload_success = false;
                    break;
                }
                
                $file = $_FILES[$field];
                
                // Validate file
                if (!in_array($file['type'], $allowed_types)) {
                    $error = "$label: Only JPEG, PNG, and PDF files are allowed";
                    $upload_success = false;
                    break;
                }
                
                if ($file['size'] > $max_file_size) {
                    $error = "$label: File size must not exceed 5MB";
                    $upload_success = false;
                    break;
                }
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error = "$label: Upload error. Please try again.";
                    $upload_success = false;
                    break;
                }
                
                // Generate unique filename
                $filename = time() . '_' . $_SESSION['user_id'] . '_' . $field . '_' . basename($file['name']);
                $filepath = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $uploaded_files[$field] = $filepath;
                } else {
                    $error = "$label: Failed to upload file";
                    $upload_success = false;
                    break;
                }
            }
            
            // If all files uploaded successfully, save vendor application
            if ($upload_success) {
                try {
                    $stmt = $conn->prepare("INSERT INTO vendors (user_id, shop_name, shop_description, 
                                                                  business_location, business_city, business_state, 
                                                                  business_postal_code, business_phone, business_website,
                                                                  bank_account_name, bank_account_number,
                                                                  status) 
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    
                    $result = $stmt->execute([
                        $_SESSION['user_id'], $shop_name, $shop_description,
                        $business_location, $business_city, $business_state,
                        $business_postal_code, $business_phone, $business_website,
                        $bank_account_name, $bank_account_number
                    ]);
                    
                    if ($result) {
                        $vendor_id = $conn->lastInsertId();
                        
                        // Store document file paths in database
                        $doc_stmt = $conn->prepare("INSERT INTO vendor_documents (vendor_id, document_type, document_url) VALUES (?, ?, ?)");
                        
                        foreach ($documents as $field => $label) {
                            $doc_stmt->execute([$vendor_id, $field, $uploaded_files[$field]]);
                        }
                        
                        $success = 'Vendor application submitted successfully! Your documents have been received. Awaiting admin approval.';
                        header("Refresh:3");
                    } else {
                        $error = 'Failed to submit application. Please try again.';
                        // Clean up uploaded files on failure
                        foreach ($uploaded_files as $file) {
                            if (file_exists($file)) unlink($file);
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    // Clean up uploaded files on failure
                    foreach ($uploaded_files as $file) {
                        if (file_exists($file)) unlink($file);
                    }
                }
            } else {
                // Clean up any uploaded files on validation error
                foreach ($uploaded_files as $file) {
                    if (file_exists($file)) unlink($file);
                }
            }
        }
    }
}

$page_title = 'Become a Vendor - SASTO Hub';
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-xl p-8">
        <div class="text-center mb-8">
            <i class="fas fa-store text-6xl text-primary mb-4"></i>
            <h1 class="text-3xl font-bold text-gray-900">Become a Vendor</h1>
            <p class="text-gray-600 mt-2">Start selling your products on SASTO Hub</p>
        </div>
        
        <?php if ($existing_vendor): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-6 py-4 rounded-lg mb-8">
                <i class="fas fa-info-circle text-2xl mb-2"></i>
                <h3 class="font-bold text-lg mb-2">Application Status</h3>
                <p class="mb-4">Your vendor application is currently: 
                    <span class="font-bold <?php echo $existing_vendor['status'] === 'approved' ? 'text-green-600' : ($existing_vendor['status'] === 'rejected' ? 'text-red-600' : 'text-yellow-600'); ?>">
                        <?php echo strtoupper($existing_vendor['status']); ?>
                    </span>
                </p>
                <?php if ($existing_vendor['status'] === 'rejected' && !empty($existing_vendor['rejection_reason'])): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mt-4">
                        <h4 class="font-bold mb-2">Rejection Reason:</h4>
                        <p><?php echo htmlspecialchars($existing_vendor['rejection_reason']); ?></p>
                    </div>
                    <p class="mt-4 text-sm">You can submit a new application with corrected information below.</p>
                <?php elseif ($existing_vendor['status'] === 'pending'): ?>
                    <p class="text-sm">Your application is being reviewed. You will receive an email notification once it's approved or if we need more information.</p>
                <?php elseif ($existing_vendor['status'] === 'approved'): ?>
                    <p class="text-sm text-green-700">Congratulations! Your vendor account is approved. <a href="/vendor/" class="underline font-bold">Go to Vendor Dashboard</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error && !$existing_vendor): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$existing_vendor): ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                
                <!-- Shop Information Section -->
                <div class="bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-store text-primary"></i> Shop Information
                    </h3>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Shop Name *</label>
                        <input type="text" name="shop_name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Your shop name"
                               maxlength="100">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Shop Description *</label>
                        <textarea name="shop_description" rows="4" required
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                  placeholder="Describe what you sell and what makes your shop unique"
                                  maxlength="500"></textarea>
                        <p class="text-sm text-gray-500 mt-1">Max 500 characters</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Business Website</label>
                        <input type="url" name="business_website"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="https://yourshop.com (optional)">
                    </div>
                </div>
                
                <!-- Business Location Section -->
                <div class="bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-map-marker-alt text-primary"></i> Business Location
                    </h3>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Full Address *</label>
                        <textarea name="business_location" rows="3" required
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                  placeholder="Street address, building name, etc."></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">City *</label>
                            <input type="text" name="business_city" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="City">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">State/Province *</label>
                            <input type="text" name="business_state" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="State/Province">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Postal Code *</label>
                            <input type="text" name="business_postal_code" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="Postal code"
                                   maxlength="10">
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-2">Phone Number *</label>
                            <input type="tel" name="business_phone" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="+977 XXXXXXXXX"
                                   maxlength="15">
                        </div>
                    </div>
                </div>
                
                <!-- Bank Information Section -->
                <div class="bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-university text-primary"></i> Bank Account Details
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">For payment transfers. Information is kept secure.</p>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Account Holder Name *</label>
                        <input type="text" name="bank_account_name" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Name as shown on bank account"
                               maxlength="100">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 font-medium mb-2">Account Number *</label>
                        <input type="text" name="bank_account_number" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               placeholder="Bank account number"
                               maxlength="50">
                    </div>
                </div>
                
                <!-- Document Upload Section -->
                <div class="bg-gray-50 p-6 rounded-lg mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">
                        <i class="fas fa-file-upload text-primary"></i> Required Documents
                    </h3>
                    <p class="text-sm text-gray-600 mb-6">Upload clear images or PDFs of your documents for verification.</p>
                    
                    <!-- National ID Front -->
                    <div class="mb-6 p-4 border-2 border-dashed border-gray-300 rounded-lg">
                        <label class="block text-gray-700 font-medium mb-3">
                            <i class="fas fa-id-card text-primary"></i> National ID Card - Front *
                        </label>
                        <input type="file" name="national_id_front" accept=".jpg,.jpeg,.png,.pdf" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               onchange="previewFile(this)">
                        <p class="text-xs text-gray-500 mt-2">Accepted: JPG, PNG, PDF | Max: 5MB | Must be clear and readable</p>
                    </div>
                    
                    <!-- National ID Back -->
                    <div class="mb-6 p-4 border-2 border-dashed border-gray-300 rounded-lg">
                        <label class="block text-gray-700 font-medium mb-3">
                            <i class="fas fa-id-card text-primary"></i> National ID Card - Back *
                        </label>
                        <input type="file" name="national_id_back" accept=".jpg,.jpeg,.png,.pdf" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               onchange="previewFile(this)">
                        <p class="text-xs text-gray-500 mt-2">Accepted: JPG, PNG, PDF | Max: 5MB | Must be clear and readable</p>
                    </div>
                    
                    <!-- PAN/VAT Certificate -->
                    <div class="mb-6 p-4 border-2 border-dashed border-gray-300 rounded-lg">
                        <label class="block text-gray-700 font-medium mb-3">
                            <i class="fas fa-file-pdf text-primary"></i> PAN/VAT Certificate *
                        </label>
                        <input type="file" name="pan_vat_document" accept=".jpg,.jpeg,.png,.pdf" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               onchange="previewFile(this)">
                        <p class="text-xs text-gray-500 mt-2">Upload clear image of your PAN or VAT certificate. Accepted: JPG, PNG, PDF | Max: 5MB</p>
                    </div>
                    
                    <!-- Business Registration -->
                    <div class="mb-6 p-4 border-2 border-dashed border-gray-300 rounded-lg">
                        <label class="block text-gray-700 font-medium mb-3">
                            <i class="fas fa-file-contract text-primary"></i> Business Registration Certificate *
                        </label>
                        <input type="file" name="business_registration" accept=".jpg,.jpeg,.png,.pdf" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                               onchange="previewFile(this)">
                        <p class="text-xs text-gray-500 mt-2">Upload GST certificate, business license, or registration document. Accepted: JPG, PNG, PDF | Max: 5MB</p>
                    </div>
                </div>
                
                <!-- Important Information -->
                <div class="bg-blue-50 border-l-4 border-primary p-4 rounded-lg mb-6">
                    <h4 class="font-bold text-gray-900 mb-2">
                        <i class="fas fa-info-circle text-primary"></i> Important Information
                    </h4>
                    <ul class="text-sm text-gray-700 space-y-1">
                        <li>✓ All information will be verified by our admin team</li>
                        <li>✓ Documents must be clear and readable</li>
                        <li>✓ Processing time: 3-5 business days</li>
                        <li>✓ Commission: 10% per sale</li>
                        <li>✓ You'll receive email notification once your application is approved</li>
                        <li>✓ You can start uploading products after approval</li>
                    </ul>
                </div>
                
                <!-- Terms & Conditions -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <div class="space-y-3">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="accept_terms" required 
                                   class="mt-1 h-4 w-4 text-primary rounded border-gray-300 focus:outline-none">
                            <span class="text-sm text-gray-700">
                                I agree to the <a href="/pages/terms-of-use.php" target="_blank" class="text-primary font-semibold hover:underline">Terms of Use</a>
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                        
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="accept_privacy" required 
                                   class="mt-1 h-4 w-4 text-primary rounded border-gray-300 focus:outline-none">
                            <span class="text-sm text-gray-700">
                                I have read and agree to the <a href="/pages/privacy-policy.php" target="_blank" class="text-primary font-semibold hover:underline">Privacy Policy</a>
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                        
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" name="confirm_information" required 
                                   class="mt-1 h-4 w-4 text-primary rounded border-gray-300 focus:outline-none">
                            <span class="text-sm text-gray-700">
                                I confirm that all information provided is accurate and true to the best of my knowledge
                                <span class="text-red-500">*</span>
                            </span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" 
                        class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
function previewFile(input) {
    const file = input.files[0];
    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
    
    if (file) {
        // Check file size
        if (file.size > maxSize) {
            alert('File is too large. Maximum size is 5MB.');
            input.value = '';
            return;
        }
        
        // Check file type
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Only JPEG, PNG, and PDF are allowed.');
            input.value = '';
            return;
        }
        
        // Show file name
        const label = input.parentElement.querySelector('label');
        const fileName = file.name;
        input.title = fileName;
    }
}

// Validate form before submission
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFiles = ['national_id_front', 'national_id_back', 'pan_vat_document', 'business_registration'];
    let hasErrors = false;
    
    for (const fieldName of requiredFiles) {
        const field = document.querySelector(`input[name="${fieldName}"]`);
        if (!field || !field.files || field.files.length === 0) {
            alert('Please upload all required documents');
            hasErrors = true;
            break;
        }
    }
    
    if (hasErrors) {
        e.preventDefault();
    }
});
</script>

<?php include '../includes/footer.php'; ?>

