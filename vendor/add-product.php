<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check vendor access
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

// Get vendor info
$stmt = $conn->prepare("SELECT * FROM vendors WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$vendor = $stmt->fetch();

if (!$vendor) {
    redirect('/auth/become-vendor.php');
}

// Check vendor status
if ($vendor['status'] !== 'approved') {
    $error_msg = $vendor['status'] === 'pending' 
        ? 'Your vendor application is still pending. You cannot upload products yet.'
        : 'Your vendor application was rejected. Please fix the issues and reapply.';
    
    $page_title = 'Add Product - SASTO Hub';
    include '../includes/vendor_header.php';
    ?>
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <i class="fas fa-ban text-4xl text-red-600 mb-4"></i>
            <h2 class="text-xl font-bold text-red-900 mb-2">Cannot Upload Products</h2>
            <p class="text-red-700"><?php echo htmlspecialchars($error_msg); ?></p>
            <a href="/vendor/" class="inline-block mt-6 bg-primary text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
                Go to Dashboard
            </a>
        </div>
    </div>
    <?php
    include '../includes/footer.php';
    exit;
}

$error = '';
$success = '';

// Get categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        $name = sanitize($_POST['product_name'] ?? '');
        $description = sanitize($_POST['product_description'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $sale_price = (float)($_POST['sale_price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $sku = sanitize($_POST['sku'] ?? '');
        
        // Validate inputs
        if (empty($name) || empty($description) || $price <= 0 || $category_id <= 0 || $stock < 0) {
            $error = 'Please fill in all required fields correctly.';
        } elseif (empty($_FILES['product_image']['name'])) {
            $error = 'Please upload a product image.';
        } else {
            // Validate image
            $file = $_FILES['product_image'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Only JPEG, PNG, and WebP images are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error = 'Image size must not exceed 5MB.';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $error = 'File upload error. Please try again.';
            } else {
                // Create upload directory
                $upload_dir = '../uploads/products/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename
                $filename = time() . '_' . $vendor['id'] . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                $filepath = $upload_dir . $filename;
                $image_url = '/uploads/products/' . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    try {
                        // Generate slug
                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                        $slug = $slug . '-' . time(); // Ensure uniqueness
                        
                        // Insert product with pending verification status
                        $stmt = $conn->prepare("
                            INSERT INTO products 
                            (vendor_id, category_id, name, slug, description, price, sale_price, stock, sku, status, verification_status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 'pending', NOW())
                        ");
                        
                        $result = $stmt->execute([
                            $vendor['id'], 
                            $category_id, 
                            $name, 
                            $slug, 
                            $description, 
                            $price,
                            $sale_price > 0 ? $sale_price : NULL,
                            $stock,
                            !empty($sku) ? $sku : NULL
                        ]);
                        
                        if ($result) {
                            $product_id = $conn->lastInsertId();
                            
                            // Save product image
                            $img_stmt = $conn->prepare("
                                INSERT INTO product_images (product_id, image_path, is_primary, display_order)
                                VALUES (?, ?, 1, 0)
                            ");
                            $img_stmt->execute([$product_id, $image_url]);
                            
                            $success = 'Product uploaded successfully! It will be visible after admin approval (usually within 24 hours).';
                            
                            // Log the action
                            error_log("Product uploaded by vendor {$vendor['id']}: {$name}");
                        } else {
                            $error = 'Failed to save product. Please try again.';
                            unlink($filepath);
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                        unlink($filepath);
                    }
                } else {
                    $error = 'Failed to upload image. Please try again.';
                }
            }
        }
    }
}

$page_title = 'Add New Product - SASTO Hub';
include '../includes/vendor_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-8">
            <a href="/vendor/" class="text-primary hover:text-indigo-700 font-medium mb-4 inline-block">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1 class="text-3xl font-bold text-gray-900">Add New Product</h1>
            <p class="text-gray-600 mt-2">Upload and manage your products</p>
        </div>

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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Upload Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-lg p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Product Information</h2>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                        <!-- Product Image -->
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">
                                <i class="fas fa-image text-primary"></i> Product Image *
                            </label>
                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer hover:border-primary transition" id="imageDropZone">
                                <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
                                <p class="text-gray-600 mb-2">Click to upload or drag and drop</p>
                                <p class="text-sm text-gray-500">JPEG, PNG or WebP (Max 5MB)</p>
                                <input type="file" name="product_image" id="productImage" accept="image/jpeg,image/png,image/webp" class="hidden" required onchange="previewImage(this)">
                            </div>
                            <p class="text-xs text-gray-500 mt-2">High quality images help sell products better</p>
                        </div>

                        <!-- Product Name -->
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">Product Name *</label>
                            <input type="text" name="product_name" required maxlength="255"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                   placeholder="e.g., Premium Wireless Headphones">
                        </div>

                        <!-- Description -->
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">Product Description *</label>
                            <textarea name="product_description" rows="5" required maxlength="2000"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                      placeholder="Describe your product in detail..."></textarea>
                            <p class="text-sm text-gray-500 mt-1">Max 2000 characters</p>
                        </div>

                        <!-- Category -->
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">Category *</label>
                            <select name="category_id" required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                                <option value="">Select a category...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Pricing -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Price (Rs) *</label>
                                <input type="number" name="price" step="0.01" min="0" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                       placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Sale Price (Rs)</label>
                                <input type="number" name="sale_price" step="0.01" min="0"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                       placeholder="0.00 (optional)">
                            </div>
                        </div>

                        <!-- Stock & SKU -->
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Stock Quantity *</label>
                                <input type="number" name="stock" min="0" required
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                       placeholder="0">
                            </div>
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">SKU (Stock Keeping Unit)</label>
                                <input type="text" name="sku" maxlength="100"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"
                                       placeholder="Optional product code">
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <p class="text-sm text-blue-900">
                                <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                                <strong>Verification Required:</strong> Your product will be reviewed by our admin team to ensure quality and compliance. This usually takes 12-24 hours.
                            </p>
                        </div>

                        <button type="submit" class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Product
                        </button>
                    </form>
                </div>
            </div>

            <!-- Image Preview -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-lg p-8 sticky top-4">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Image Preview</h3>
                    <div id="previewBox" class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center min-h-[300px] flex items-center justify-center bg-gray-50">
                        <div class="text-gray-400">
                            <i class="fas fa-image text-4xl block mb-2"></i>
                            <p class="text-sm">No image selected</p>
                        </div>
                    </div>
                    <div id="imageInfo" class="mt-4 text-sm text-gray-600" style="display:none;">
                        <p><strong>File:</strong> <span id="fileName"></span></p>
                        <p><strong>Size:</strong> <span id="fileSize"></span></p>
                        <p><strong>Type:</strong> <span id="fileType"></span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Drag and Drop Script -->
<script>
const dropZone = document.getElementById('imageDropZone');
const imageInput = document.getElementById('productImage');

// Click to select
dropZone.addEventListener('click', () => imageInput.click());

// Drag and drop
dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('border-primary', 'bg-indigo-50');
});

dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('border-primary', 'bg-indigo-50');
});

dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('border-primary', 'bg-indigo-50');
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        imageInput.files = files;
        previewImage(imageInput);
    }
});

function previewImage(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewBox = document.getElementById('previewBox');
            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width: 100%; max-height: 400px; border-radius: 8px;">';
            
            // Show file info
            document.getElementById('imageInfo').style.display = 'block';
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
            document.getElementById('fileType').textContent = file.type;
        };
        reader.readAsDataURL(file);
    }
}
</script>

<?php include '../includes/footer.php'; ?>

