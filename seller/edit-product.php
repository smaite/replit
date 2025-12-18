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
} catch (Exception $e) {}

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
            
            // Validation
            if (empty($name) || $price <= 0 || !$category_id) {
                $error = 'Please fill in all required fields.';
            } else {
                // Update product
                $stmt = $conn->prepare("
                    UPDATE products SET 
                        name = ?, description = ?, price = ?, sale_price = ?,
                        category_id = ?, stock = ?, sku = ?, updated_at = NOW()
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$name, $description, $price, $sale_price, $category_id, $stock, $sku, $product_id, $vendor_id]);
                
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
        
        // Update products main image
        $stmt = $conn->prepare("SELECT image_path FROM product_images WHERE id = ?");
        $stmt->execute([$image_id]);
        $img = $stmt->fetch();
        if ($img) {
            $stmt = $conn->prepare("UPDATE products SET image = ? WHERE id = ?");
            $stmt->execute([$img['image_path'], $product_id]);
        }
        
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
include '../includes/seller_header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Edit Product</h1>
                <p class="text-gray-600 mt-1">Update your product details</p>
            </div>
            <a href="/seller/products.php" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-6 py-2 rounded-lg font-medium">
                <i class="fas fa-arrow-left mr-2"></i> Back to Products
            </a>
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
        
        <form method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-lg p-8">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="update_product" value="1">
            
            <!-- Basic Info -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-info-circle text-primary"></i> Basic Information
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 font-medium mb-2">Product Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 font-medium mb-2">Description</label>
                        <textarea name="description" rows="4"
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Category *</label>
                        <select name="category_id" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">SKU</label>
                        <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    </div>
                </div>
            </div>
            
            <!-- Pricing -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-tag text-primary"></i> Pricing & Stock
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Regular Price (Rs.) *</label>
                        <input type="number" name="price" step="0.01" min="0" value="<?php echo $product['price'] ?? ''; ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Sale Price (Rs.)</label>
                        <input type="number" name="sale_price" step="0.01" min="0" value="<?php echo $product['sale_price'] ?? ''; ?>"
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    </div>
                    
                    <div>
                        <label class="block text-gray-700 font-medium mb-2">Stock Quantity *</label>
                        <input type="number" name="stock" min="0" value="<?php echo $product['stock'] ?? 0; ?>" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    </div>
                </div>
            </div>
            
            <!-- Current Images -->
            <div class="mb-8">
                <h2 class="text-xl font-bold text-gray-900 mb-4">
                    <i class="fas fa-images text-primary"></i> Product Images
                </h2>
                
                <?php if (!empty($images)): ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                        <?php foreach ($images as $img): ?>
                            <div class="relative group">
                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" 
                                     class="w-full h-32 object-cover rounded-lg border <?php echo $img['is_primary'] ? 'border-primary border-2' : 'border-gray-200'; ?>">
                                
                                <?php if ($img['is_primary']): ?>
                                    <span class="absolute top-2 left-2 bg-primary text-white text-xs px-2 py-1 rounded">Primary</span>
                                <?php endif; ?>
                                
                                <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 rounded-lg">
                                    <?php if (!$img['is_primary']): ?>
                                        <a href="?id=<?php echo $product_id; ?>&set_primary=<?php echo $img['id']; ?>" 
                                           class="bg-white text-primary p-2 rounded-full hover:bg-gray-100" title="Set as Primary">
                                            <i class="fas fa-star"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?id=<?php echo $product_id; ?>&delete_image=<?php echo $img['id']; ?>" 
                                       class="bg-white text-red-500 p-2 rounded-full hover:bg-gray-100" title="Delete"
                                       onclick="return confirm('Delete this image?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 mb-4">No images uploaded yet.</p>
                <?php endif; ?>
                
                <div>
                    <label class="block text-gray-700 font-medium mb-2">Add More Images</label>
                    <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-primary">
                    <p class="text-xs text-gray-500 mt-1">JPG, PNG, or WebP. You can select multiple files.</p>
                </div>
            </div>
            
            <!-- Status Info -->
            <div class="mb-8 p-4 bg-gray-50 rounded-lg">
                <div class="flex items-center gap-4">
                    <span class="text-gray-700">Status:</span>
                    <span class="px-3 py-1 rounded-full text-sm font-medium 
                        <?php 
                        switch($product['status']) {
                            case 'active': echo 'bg-green-100 text-green-700'; break;
                            case 'draft': echo 'bg-yellow-100 text-yellow-700'; break;
                            case 'inactive': echo 'bg-red-100 text-red-700'; break;
                            default: echo 'bg-gray-100 text-gray-700';
                        }
                        ?>">
                        <?php echo ucfirst($product['status'] ?? 'unknown'); ?>
                    </span>
                    
                    <span class="text-gray-700 ml-4">Verification:</span>
                    <span class="px-3 py-1 rounded-full text-sm font-medium 
                        <?php 
                        switch($product['verification_status'] ?? 'pending') {
                            case 'approved': echo 'bg-green-100 text-green-700'; break;
                            case 'pending': echo 'bg-yellow-100 text-yellow-700'; break;
                            case 'rejected': echo 'bg-red-100 text-red-700'; break;
                            default: echo 'bg-gray-100 text-gray-700';
                        }
                        ?>">
                        <?php echo ucfirst($product['verification_status'] ?? 'pending'); ?>
                    </span>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button type="submit" class="bg-primary hover:bg-indigo-700 text-white px-8 py-3 rounded-lg font-medium">
                    <i class="fas fa-save mr-2"></i> Save Changes
                </button>
                <a href="/seller/products.php" class="bg-gray-300 hover:bg-gray-400 text-gray-900 px-8 py-3 rounded-lg font-medium">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
