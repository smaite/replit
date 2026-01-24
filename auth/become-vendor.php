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

<div class="bg-gray-50 min-h-screen py-12">
    <div class="container mx-auto px-4">
        <div class="max-w-3xl mx-auto bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-8 text-center border-b border-gray-100 bg-gray-50/50">
                <div class="w-16 h-16 bg-primary/10 text-primary rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">
                    <i class="fas fa-store"></i>
                </div>
                <h1 class="text-3xl font-bold text-gray-900">Become a Vendor</h1>
                <p class="text-gray-500 mt-2">Join our marketplace and start selling your products to thousands of customers.</p>
            </div>

            <div class="p-8">
                <?php if ($existing_vendor): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 p-6 rounded-xl mb-8">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600">
                                <i class="fas fa-info"></i>
                            </div>
                            <h3 class="font-bold text-lg">Application Status</h3>
                        </div>

                        <p class="mb-4">Your vendor application is currently:
                            <span class="font-bold px-2 py-1 rounded text-sm uppercase
                                <?php echo $existing_vendor['status'] === 'approved' ? 'bg-green-200 text-green-800' :
                                      ($existing_vendor['status'] === 'rejected' ? 'bg-red-200 text-red-800' : 'bg-yellow-200 text-yellow-800'); ?>">
                                <?php echo htmlspecialchars($existing_vendor['status']); ?>
                            </span>
                        </p>

                        <?php if ($existing_vendor['status'] === 'rejected' && !empty($existing_vendor['rejection_reason'])): ?>
                            <div class="bg-white border border-red-200 text-red-700 p-4 rounded-lg mt-4">
                                <h4 class="font-bold mb-1"><i class="fas fa-exclamation-circle mr-2"></i>Rejection Reason:</h4>
                                <p class="text-sm"><?php echo htmlspecialchars($existing_vendor['rejection_reason']); ?></p>
                            </div>
                            <p class="mt-4 text-sm font-medium">You can submit a new application with corrected information below.</p>
                        <?php elseif ($existing_vendor['status'] === 'pending'): ?>
                            <p class="text-sm">Your application is being reviewed. You will receive an email notification once it's approved.</p>
                        <?php elseif ($existing_vendor['status'] === 'approved'): ?>
                            <p class="text-sm font-medium text-green-700">Congratulations! Your vendor account is approved.</p>
                            <a href="/seller/" class="inline-block mt-3 px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold transition">
                                Go to Vendor Dashboard <i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($error && !$existing_vendor): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <div><?php echo $error; ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                        <i class="fas fa-check-circle text-xl"></i>
                        <div><?php echo $success; ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!$existing_vendor): ?>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                        <!-- Shop Information Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <i class="fas fa-store text-primary"></i> Shop Information
                            </h3>

                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Shop Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="shop_name" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Your shop name"
                                           maxlength="100">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Shop Description <span class="text-red-500">*</span></label>
                                    <textarea name="shop_description" rows="4" required
                                              class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                              placeholder="Describe what you sell and what makes your shop unique"
                                              maxlength="500"></textarea>
                                    <p class="text-xs text-gray-500 mt-1 text-right">Max 500 characters</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Business Website</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                            <i class="fas fa-globe"></i>
                                        </span>
                                        <input type="url" name="business_website"
                                               class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                               placeholder="https://yourshop.com (optional)">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Business Location Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-primary"></i> Business Location
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Full Address <span class="text-red-500">*</span></label>
                                    <textarea name="business_location" rows="2" required
                                              class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                              placeholder="Street address, building name, etc."></textarea>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">City <span class="text-red-500">*</span></label>
                                    <input type="text" name="business_city" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="City">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">State/Province <span class="text-red-500">*</span></label>
                                    <input type="text" name="business_state" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="State/Province">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Postal Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="business_postal_code" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Postal code"
                                           maxlength="10">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" name="business_phone" required
                                               class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                               placeholder="+977 XXXXXXXXX"
                                               maxlength="15">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bank Information Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <i class="fas fa-university text-primary"></i> Bank Account Details
                            </h3>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6 flex items-start gap-3">
                                <i class="fas fa-shield-alt text-blue-500 mt-1"></i>
                                <p class="text-sm text-blue-800">Your bank information is encrypted and stored securely. It will be used for transferring your earnings.</p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Account Holder Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="bank_account_name" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Name as shown on bank account"
                                           maxlength="100">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Account Number <span class="text-red-500">*</span></label>
                                    <input type="text" name="bank_account_number" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="Bank account number"
                                           maxlength="50">
                                </div>
                            </div>
                        </div>

                        <!-- Document Upload Section -->
                        <div class="mb-8">
                            <h3 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                                <i class="fas fa-file-upload text-primary"></i> Required Documents
                            </h3>

                            <div class="grid grid-cols-1 gap-6">
                                <!-- National ID Front -->
                                <div class="p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-primary/50 transition-colors bg-gray-50/50">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        National ID Card - Front <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" name="national_id_front" accept=".jpg,.jpeg,.png,.pdf" required
                                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary file:text-white hover:file:bg-indigo-700 file:transition-colors"
                                           onchange="previewFile(this)">
                                    <p class="text-xs text-gray-500 mt-2">Accepted: JPG, PNG, PDF | Max: 5MB</p>
                                </div>

                                <!-- National ID Back -->
                                <div class="p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-primary/50 transition-colors bg-gray-50/50">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        National ID Card - Back <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" name="national_id_back" accept=".jpg,.jpeg,.png,.pdf" required
                                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary file:text-white hover:file:bg-indigo-700 file:transition-colors"
                                           onchange="previewFile(this)">
                                    <p class="text-xs text-gray-500 mt-2">Accepted: JPG, PNG, PDF | Max: 5MB</p>
                                </div>

                                <!-- PAN/VAT Certificate -->
                                <div class="p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-primary/50 transition-colors bg-gray-50/50">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        PAN/VAT Certificate <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" name="pan_vat_document" accept=".jpg,.jpeg,.png,.pdf" required
                                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary file:text-white hover:file:bg-indigo-700 file:transition-colors"
                                           onchange="previewFile(this)">
                                    <p class="text-xs text-gray-500 mt-2">Upload clear image of your PAN or VAT certificate</p>
                                </div>

                                <!-- Business Registration -->
                                <div class="p-6 border-2 border-dashed border-gray-200 rounded-xl hover:border-primary/50 transition-colors bg-gray-50/50">
                                    <label class="block text-sm font-bold text-gray-700 mb-2">
                                        Business Registration Certificate <span class="text-red-500">*</span>
                                    </label>
                                    <input type="file" name="business_registration" accept=".jpg,.jpeg,.png,.pdf" required
                                           class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-bold file:bg-primary file:text-white hover:file:bg-indigo-700 file:transition-colors"
                                           onchange="previewFile(this)">
                                    <p class="text-xs text-gray-500 mt-2">Business license or registration document</p>
                                </div>
                            </div>
                        </div>

                        <!-- Important Information -->
                        <div class="bg-indigo-50 rounded-xl p-6 mb-8">
                            <h4 class="font-bold text-indigo-900 mb-4 flex items-center gap-2">
                                <i class="fas fa-check-circle text-primary"></i> What Happens Next?
                            </h4>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3 text-sm text-indigo-800">
                                    <i class="fas fa-clock mt-1 text-primary"></i>
                                    <span>All applications are manually verified by our team within <strong>3-5 business days</strong>.</span>
                                </li>
                                <li class="flex items-start gap-3 text-sm text-indigo-800">
                                    <i class="fas fa-envelope mt-1 text-primary"></i>
                                    <span>You will receive an email notification once your application is processed.</span>
                                </li>
                                <li class="flex items-start gap-3 text-sm text-indigo-800">
                                    <i class="fas fa-box-open mt-1 text-primary"></i>
                                    <span>Once approved, you can start uploading products immediately.</span>
                                </li>
                                <li class="flex items-start gap-3 text-sm text-indigo-800">
                                    <i class="fas fa-percent mt-1 text-primary"></i>
                                    <span>Standard commission rate is 10% per sale.</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Terms & Conditions -->
                        <div class="bg-gray-50 rounded-xl p-6 mb-8 border border-gray-100">
                            <div class="space-y-4">
                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input type="checkbox" name="accept_terms" required
                                           class="mt-1 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                                    <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">
                                        I agree to the <a href="/pages/terms-of-use.php" target="_blank" class="text-primary font-bold hover:underline">Terms of Use</a>
                                        <span class="text-red-500">*</span>
                                    </span>
                                </label>

                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input type="checkbox" name="accept_privacy" required
                                           class="mt-1 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                                    <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">
                                        I have read and agree to the <a href="/pages/privacy-policy.php" target="_blank" class="text-primary font-bold hover:underline">Privacy Policy</a>
                                        <span class="text-red-500">*</span>
                                    </span>
                                </label>

                                <label class="flex items-start gap-3 cursor-pointer group">
                                    <input type="checkbox" name="confirm_information" required
                                           class="mt-1 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary cursor-pointer">
                                    <span class="text-sm text-gray-600 group-hover:text-gray-900 transition-colors">
                                        I confirm that all information provided is accurate and true to the best of my knowledge
                                        <span class="text-red-500">*</span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full bg-primary hover:bg-indigo-700 text-white py-4 rounded-xl font-bold text-lg shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
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
document.querySelector('form')?.addEventListener('submit', function(e) {
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
