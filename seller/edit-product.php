<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Check that user is logged in as vendor
if (!isLoggedIn() || !isVendor()) {
    redirect('/auth/login.php');
}

$vendor_id = $_SESSION['vendor_id'] ?? null;
if (!$vendor_id) {
    redirect('/seller/index.php');
}

$error = '';
$success = '';
$product = null;
$images = [];

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    redirect('/seller/products.php');
}

// Fetch product (only if it belongs to this vendor)
try {
    $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();

    if (!$product) {
        $_SESSION['error'] = 'Product not found or you do not have permission to edit it.';
        redirect('/seller/products.php');
    }

    // Fetch product images
    $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
    $stmt->execute([$product_id]);
    $images = $stmt->fetchAll();
} catch (Exception $e) {
    $error = 'Failed to load product: ' . $e->getMessage();
}

// Fetch categories
$categories = [];
try {
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (Exception $e) {
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        try {
            $name = sanitize($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null;
            $category_id = (int)($_POST['category_id'] ?? 0);
            $stock = (int)($_POST['stock'] ?? 0);
            $sku = sanitize($_POST['sku'] ?? '');
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $status = sanitize($_POST['status'] ?? 'active');

            // Validation
            if (empty($name) || $price <= 0 || !$category_id) {
                $error = 'Please fill in all required fields.';
            } else {
                // Update product
                $stmt = $conn->prepare("
                    UPDATE products SET
                        name = ?, description = ?, price = ?, sale_price = ?,
                        category_id = ?, stock = ?, sku = ?, featured = ?, status = ?, updated_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$name, $description, $price, $sale_price, $category_id, $stock, $sku, $is_featured, $status, $product_id, $vendor_id]);

                // Handle new image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/products/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_type = $_FILES['images']['type'][$key];
                            if (in_array($file_type, ['image/jpeg', 'image/png', 'image/webp'])) {
                                $filename = uniqid() . '_' . time() . '.' . pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION);
                                if (move_uploaded_file($tmp_name, $upload_dir . $filename)) {
                                    $stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, display_order) VALUES (?, ?, ?)");
                                    $stmt->execute([$product_id, '/uploads/products/' . $filename, count($images) + $key]);
                                }
                            }
                        }
                    }
                }

                $success = 'Product updated successfully!';

                // Reload images
                $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
                $stmt->execute([$product_id]);
                $images = $stmt->fetchAll();

                // Reload product
                $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $vendor_id]);
                $product = $stmt->fetch();
            }
        } catch (Exception $e) {
            $error = 'Failed to update product: ' . $e->getMessage();
        }
    }
}

// Handle image deletion
if (isset($_GET['delete_image'])) {
    $image_id = (int)$_GET['delete_image'];
    try {
        // Verify image belongs to this product
        $stmt = $conn->prepare("SELECT * FROM product_images WHERE id = ? AND product_id = ?");
        $stmt->execute([$image_id, $product_id]);
        $image = $stmt->fetch();

        if ($image) {
            // Delete file
            $file_path = '..' . $image['image_path'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Delete record
            $stmt = $conn->prepare("DELETE FROM product_images WHERE id = ?");
            $stmt->execute([$image_id]);

            $success = 'Image deleted successfully.';

            // Reload images
            $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
            $stmt->execute([$product_id]);
            $images = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $error = 'Failed to delete image: ' . $e->getMessage();
    }
}

// Handle set primary image
if (isset($_GET['set_primary'])) {
    $image_id = (int)$_GET['set_primary'];
    try {
        // Reset all to non-primary
        $stmt = $conn->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?");
        $stmt->execute([$product_id]);

        // Set selected as primary
        $stmt = $conn->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
        $stmt->execute([$image_id, $product_id]);

        $success = 'Primary image updated.';

        // Reload images
        $stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
        $stmt->execute([$product_id]);
        $images = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = 'Failed to set primary image: ' . $e->getMessage();
    }
}

$page_title = 'Edit Product - ' . htmlspecialchars($product['name'] ?? 'Product');
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
            <a href="/seller/products.php" class="hover:text-primary">My Products</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Edit Product</span>
        </nav>

        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Edit Product</h1>
                    <p class="text-gray-500 mt-1">Update product information and inventory</p>
                </div>
                <a href="/seller/products.php" class="bg-white border border-gray-200 text-gray-700 px-6 py-2.5 rounded-xl font-bold hover:bg-gray-50 transition">
                    Cancel
                </a>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-xl"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-6 py-4 rounded-xl mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle text-xl"></i>
                    <div><?php echo htmlspecialchars($success); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="update_product" value="1">

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Left Column: Main Info -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Basic Information -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-info-circle text-primary"></i> Basic Information
                            </h2>

                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Product Title <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                                    <textarea name="description" rows="6" required
                                              class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Media -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-images text-primary"></i> Media
                            </h2>

                            <div class="space-y-6">
                                <?php if (!empty($images)): ?>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                        <?php foreach ($images as $img): ?>
                                            <div class="relative group aspect-square rounded-xl overflow-hidden border <?php echo $img['is_primary'] ? 'border-primary ring-2 ring-primary ring-opacity-50' : 'border-gray-200'; ?>">
                                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                                                     class="w-full h-full object-cover">

                                                <?php if ($img['is_primary']): ?>
                                                    <span class="absolute top-2 left-2 bg-primary text-white text-xs font-bold px-2 py-1 rounded shadow-sm">Primary</span>
                                                <?php endif; ?>

                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <?php if (!$img['is_primary']): ?>
                                                        <a href="?id=<?php echo $product_id; ?>&set_primary=<?php echo $img['id']; ?>"
                                                            class="bg-white text-primary p-2 rounded-full hover:bg-gray-100 transition-colors shadow-sm" title="Set as Primary">
                                                            <i class="fas fa-star"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?id=<?php echo $product_id; ?>&delete_image=<?php echo $img['id']; ?>"
                                                        class="bg-white text-red-500 p-2 rounded-full hover:bg-gray-100 transition-colors shadow-sm" title="Delete"
                                                        onclick="return confirm('Delete this image?')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-8 text-center text-gray-500">
                                        <i class="fas fa-image text-4xl mb-2 text-gray-300"></i>
                                        <p>No images uploaded yet.</p>
                                    </div>
                                <?php endif; ?>

                                <div class="p-8 border-2 border-dashed border-gray-300 rounded-xl hover:border-primary/50 transition-colors bg-gray-50/50 text-center">
                                    <div class="mb-4">
                                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-300"></i>
                                    </div>
                                    <label class="block">
                                        <span class="bg-primary text-white px-4 py-2 rounded-lg cursor-pointer hover:bg-indigo-700 transition font-bold text-sm">Add More Images</span>
                                        <input type="file" name="images[]" multiple accept="image/*" class="hidden" onchange="previewImages(this)">
                                    </label>
                                    <p class="text-xs text-gray-500 mt-2">JPG, PNG, WebP allowed.</p>
                                </div>
                                <div id="image-preview" class="grid grid-cols-5 gap-4"></div>
                            </div>
                        </div>

                        <!-- Inventory -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-boxes text-primary"></i> Inventory
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">SKU</label>
                                    <input type="text" name="sku"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Stock Quantity <span class="text-red-500">*</span></label>
                                    <input type="number" name="stock" min="0" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($product['stock'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Sidebar Settings -->
                    <div class="space-y-8">
                        <!-- Status -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6">Status</h2>

                            <div class="mb-4">
                                <label class="block text-sm font-bold text-gray-700 mb-2">Product Status</label>
                                <select name="status" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none cursor-pointer">
                                    <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active (Visible)</option>
                                    <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                                    <option value="draft" <?php echo $product['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                </select>
                            </div>

                            <div class="p-4 bg-indigo-50 rounded-xl border border-indigo-100">
                                <div class="flex justify-between items-center text-sm mb-1">
                                    <span class="font-bold text-indigo-900">Verification</span>
                                    <span class="uppercase font-bold text-xs px-2 py-0.5 rounded bg-white text-indigo-600 border border-indigo-200">
                                        <?php echo htmlspecialchars($product['verification_status'] ?? 'Pending'); ?>
                                    </span>
                                </div>
                                <p class="text-xs text-indigo-700">Admin approval status</p>
                            </div>
                        </div>

                        <!-- Pricing -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6">Pricing</h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Regular Price <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500">Rs.</span>
                                        <input type="number" name="price" step="0.01" min="0" required
                                               class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors font-bold text-gray-900"
                                               value="<?php echo htmlspecialchars($product['price'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Sale Price</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500">Rs.</span>
                                        <input type="number" name="sale_price" step="0.01" min="0"
                                               class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                               value="<?php echo htmlspecialchars($product['sale_price'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="is_featured" value="1" <?php echo $product['featured'] ? 'checked' : ''; ?> class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                                        <span class="text-sm font-medium text-gray-700">Featured Product</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Organization -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6">Organization</h2>

                            <div>
                                <label class="block text-sm font-bold text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                                <select name="category_id" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none cursor-pointer">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 shadow-lg z-50">
                    <div class="container mx-auto max-w-5xl flex justify-between items-center">
                        <p class="text-sm text-gray-500 hidden sm:block">Unsaved changes will be lost.</p>
                        <div class="flex gap-4">
                            <a href="/seller/products.php" class="px-6 py-3 bg-white border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition">
                                Discard
                            </a>
                            <button type="submit" class="px-8 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
                <div class="h-16"></div> <!-- Spacer for fixed footer -->
            </form>
        </div>
    </div>
</div>

<script>
    function previewImages(input) {
        const preview = document.getElementById('image-preview');
        preview.innerHTML = '';

        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square rounded-xl overflow-hidden border border-gray-200';
                    div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                    preview.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
