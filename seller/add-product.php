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
    include '../includes/header.php';
?>
    <div class="bg-gray-50 min-h-screen py-12">
        <div class="container mx-auto px-4">
            <div class="max-w-md mx-auto bg-white rounded-xl shadow-sm border border-red-100 p-8 text-center">
                <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-ban text-2xl text-red-500"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900 mb-2">Access Restricted</h2>
                <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($error_msg); ?></p>
                <a href="/seller/" class="inline-flex items-center justify-center px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition transform hover:-translate-y-0.5">
                    Go to Dashboard
                </a>
            </div>
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

// Get brands
$stmt = $conn->prepare("SELECT * FROM brands WHERE status = 'active' ORDER BY name");
$stmt->execute();
$brands = $stmt->fetchAll();

// Get shipping profiles
$stmt = $conn->prepare("SELECT * FROM shipping_profiles WHERE vendor_id = ? OR vendor_id IS NULL ORDER BY name");
$stmt->execute([$vendor['id']]);
$shipping_profiles = $stmt->fetchAll();

// Get return policies
$stmt = $conn->prepare("SELECT * FROM return_policies WHERE vendor_id = ? OR vendor_id IS NULL ORDER BY days");
$stmt->execute([$vendor['id']]);
$return_policies = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token invalid. Please try again.';
    } else {
        // ... (Same validation logic as before) ...
        $name = sanitize($_POST['product_name'] ?? '');
        $description = sanitize($_POST['product_description'] ?? '');
        $category_ids = isset($_POST['category_ids']) && is_array($_POST['category_ids']) ? array_map('intval', $_POST['category_ids']) : [];
        $price = (float)($_POST['price'] ?? 0);
        $buy_price = (float)($_POST['buy_price'] ?? 0);
        $sale_price = (float)($_POST['sale_price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $sku = sanitize($_POST['sku'] ?? '');
        $tags = sanitize($_POST['tags'] ?? '');

        // New fields
        $condition = sanitize($_POST['condition'] ?? 'new');
        $brand_id = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $shipping_weight = (float)($_POST['shipping_weight'] ?? 0);
        $shipping_profile_id = !empty($_POST['shipping_profile_id']) ? (int)$_POST['shipping_profile_id'] : null;
        $return_policy_id = !empty($_POST['return_policy_id']) ? (int)$_POST['return_policy_id'] : null;
        $video_url = sanitize($_POST['video_url'] ?? '');
        $handling_days = (int)($_POST['handling_days'] ?? 1);
        $free_shipping = isset($_POST['free_shipping']) ? 1 : 0;
        $flash_sale_eligible = isset($_POST['flash_sale_eligible']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;

        $dimensions_length = (float)($_POST['dimensions_length'] ?? 0);
        $dimensions_width = (float)($_POST['dimensions_width'] ?? 0);
        $dimensions_height = (float)($_POST['dimensions_height'] ?? 0);

        // Bullet points
        $bullet_points = [];
        if (isset($_POST['bullet_points']) && is_array($_POST['bullet_points'])) {
            foreach ($_POST['bullet_points'] as $bp) {
                if (!empty(trim($bp))) $bullet_points[] = sanitize($bp);
            }
        }
        $bullet_points_json = !empty($bullet_points) ? json_encode($bullet_points) : null;

        // Validate inputs
        if (empty($name) || empty($description) || $price <= 0 || empty($category_ids) || $stock < 0) {
            $error = 'Please fill in all required fields correctly.';
        } elseif (empty($_FILES['product_images']['name'][0])) {
            $error = 'Please upload at least one product image.';
        } else {
            // Create upload directory
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            try {
                $conn->beginTransaction();

                // Generate slug
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
                $slug = $slug . '-' . time(); // Ensure uniqueness

                // Use first category as primary
                $primary_category_id = $category_ids[0];

                // Insert product
                $stmt = $conn->prepare("
                    INSERT INTO products (
                        vendor_id, category_id, name, slug, description, buy_price, price, sale_price, stock, sku, tags,
                        `condition`, brand_id, shipping_weight, shipping_profile_id, return_policy_id, video_url, bullet_points,
                        dimensions_length, dimensions_width, dimensions_height, handling_days, free_shipping, flash_sale_eligible, featured,
                        status, verification_status
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?,
                        'active', 'pending'
                    )
                ");

                $stmt->execute([
                    $vendor['id'],
                    $primary_category_id,
                    $name,
                    $slug,
                    $description,
                    $buy_price,
                    $price,
                    $sale_price ?: null,
                    $stock,
                    $sku ?: null,
                    $tags ?: null,
                    $condition,
                    $brand_id,
                    $shipping_weight,
                    $shipping_profile_id,
                    $return_policy_id,
                    $video_url ?: null,
                    $bullet_points_json,
                    $dimensions_length,
                    $dimensions_width,
                    $dimensions_height,
                    $handling_days,
                    $free_shipping,
                    $flash_sale_eligible,
                    $is_featured
                ]);

                $product_id = $conn->lastInsertId();

                // Insert categories
                $cat_stmt = $conn->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
                foreach ($category_ids as $cat_id) {
                    try {
                        $cat_stmt->execute([$product_id, $cat_id]);
                    } catch (PDOException $e) {
                        // Ignore duplicate category errors
                    }
                }

                // Handle Image Uploads
                $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
                $max_size = 5 * 1024 * 1024; // 5MB

                foreach ($_FILES['product_images']['name'] as $key => $img_name) {
                    if ($_FILES['product_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['product_images']['tmp_name'][$key];
                        $type = $_FILES['product_images']['type'][$key];
                        $size = $_FILES['product_images']['size'][$key];

                        if (in_array($type, $allowed_types) && $size <= $max_size) {
                            $filename = time() . '_' . $vendor['id'] . '_' . $key . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($img_name));
                            $filepath = $upload_dir . $filename;

                            if (move_uploaded_file($tmp_name, $filepath)) {
                                $image_url = '/uploads/products/' . $filename;
                                $is_primary = ($key === 0) ? 1 : 0;

                                $img_stmt = $conn->prepare("INSERT INTO product_images (product_id, image_path, is_primary, display_order) VALUES (?, ?, ?, ?)");
                                $img_stmt->execute([$product_id, $image_url, $is_primary, $key]);
                            }
                        }
                    }
                }

                // Handle Variants
                if (isset($_POST['variants_sku']) && is_array($_POST['variants_sku'])) {
                    $v_stmt = $conn->prepare("INSERT INTO product_variants (product_id, sku, price, stock, attributes) VALUES (?, ?, ?, ?, ?)");

                    foreach ($_POST['variants_sku'] as $k => $v_sku) {
                        if (!empty($v_sku)) {
                            $v_price = (float)($_POST['variants_price'][$k] ?? $price);
                            $v_stock = (int)($_POST['variants_stock'][$k] ?? 0);

                            // Construct attributes JSON
                            $v_attrs = [];
                            if (!empty($_POST['variants_color'][$k])) $v_attrs['Color'] = sanitize($_POST['variants_color'][$k]);
                            if (!empty($_POST['variants_size'][$k])) $v_attrs['Size'] = sanitize($_POST['variants_size'][$k]);

                            $v_attributes_json = !empty($v_attrs) ? json_encode($v_attrs) : null;

                            $v_stmt->execute([$product_id, $v_sku, $v_price, $v_stock, $v_attributes_json]);
                        }
                    }
                }

                $conn->commit();
                $success = 'Product uploaded successfully! It is now pending admin approval.';
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Error uploading product: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Add Product - SASTO Hub';
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
            <span class="text-gray-900 font-medium">Add Product</span>
        </nav>

        <div class="max-w-5xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Add New Product</h1>
                    <p class="text-gray-500 mt-1">Create a new listing for your store</p>
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
                    <div>
                        <p class="font-bold"><?php echo htmlspecialchars($success); ?></p>
                        <p class="text-sm mt-1"><a href="/seller/products.php" class="underline">View all products</a> or add another one below.</p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

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
                                    <input type="text" name="product_name" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           placeholder="e.g. Premium Cotton T-Shirt"
                                           value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Description <span class="text-red-500">*</span></label>
                                    <textarea name="product_description" rows="6" required
                                              class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                              placeholder="Detailed product description..."><?php echo htmlspecialchars($_POST['product_description'] ?? ''); ?></textarea>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Brand</label>
                                        <div class="relative">
                                            <select name="brand_id" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none cursor-pointer">
                                                <option value="">No Brand / Generic</option>
                                                <?php foreach ($brands as $brand): ?>
                                                    <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                                <i class="fas fa-chevron-down text-xs"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-bold text-gray-700 mb-2">Condition <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <select name="condition" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none cursor-pointer">
                                                <option value="new">New</option>
                                                <option value="used">Used</option>
                                            </select>
                                            <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                                <i class="fas fa-chevron-down text-xs"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Media -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-images text-primary"></i> Media
                            </h2>

                            <div class="space-y-6">
                                <div class="p-8 border-2 border-dashed border-gray-300 rounded-xl hover:border-primary/50 transition-colors bg-gray-50/50 text-center">
                                    <div class="mb-4">
                                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-300"></i>
                                    </div>
                                    <label class="block">
                                        <span class="bg-primary text-white px-4 py-2 rounded-lg cursor-pointer hover:bg-indigo-700 transition font-bold text-sm">Choose Files</span>
                                        <input type="file" name="product_images[]" multiple accept="image/*" required class="hidden" onchange="previewImages(this)">
                                    </label>
                                    <p class="text-xs text-gray-500 mt-2">Upload up to 5 images. JPG, PNG, WebP allowed.</p>
                                </div>
                                <div id="image-preview" class="grid grid-cols-5 gap-4"></div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Video URL (Optional)</label>
                                    <input type="url" name="video_url" placeholder="https://youtube.com/..."
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Inventory & Variants -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                                <i class="fas fa-boxes text-primary"></i> Inventory
                            </h2>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">SKU</label>
                                    <input type="text" name="sku"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Total Stock <span class="text-red-500">*</span></label>
                                    <input type="number" name="stock" min="0" required
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($_POST['stock'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="border-t border-gray-100 pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h3 class="font-bold text-gray-900">Variants (Optional)</h3>
                                    <button type="button" onclick="addVariantRow()" class="text-primary hover:text-indigo-700 font-bold text-sm">
                                        <i class="fas fa-plus mr-1"></i> Add Variant
                                    </button>
                                </div>
                                <div id="variants-container" class="space-y-3">
                                    <!-- Variant rows added via JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Sidebar Settings -->
                    <div class="space-y-8">
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
                                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Sale Price</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-gray-500">Rs.</span>
                                        <input type="number" name="sale_price" step="0.01" min="0"
                                               class="w-full pl-12 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                               value="<?php echo htmlspecialchars($_POST['sale_price'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="flash_sale_eligible" value="1" class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                                        <span class="text-sm font-medium text-gray-700">Eligible for Flash Sales</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Organization -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6">Organization</h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Category <span class="text-red-500">*</span></label>
                                    <select name="category_ids[]" required multiple class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none h-40">
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-2">Hold Ctrl/Cmd to select multiple</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Tags</label>
                                    <input type="text" name="tags" placeholder="comma, separated, tags"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Shipping -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-6">Shipping</h2>

                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Weight (kg)</label>
                                    <input type="number" name="shipping_weight" step="0.01" min="0"
                                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                                           value="<?php echo htmlspecialchars($_POST['shipping_weight'] ?? ''); ?>">
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-gray-700 mb-2">Shipping Profile</label>
                                    <div class="relative">
                                        <select name="shipping_profile_id" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-primary appearance-none cursor-pointer">
                                            <option value="">Default Profile</option>
                                            <?php foreach ($shipping_profiles as $profile): ?>
                                                <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center px-4 pointer-events-none text-gray-500">
                                            <i class="fas fa-chevron-down text-xs"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="free_shipping" value="1" class="w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                                        <span class="text-sm font-medium text-gray-700">Free Shipping</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Bar -->
                <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 shadow-lg z-50">
                    <div class="container mx-auto max-w-5xl flex justify-between items-center">
                        <p class="text-sm text-gray-500 hidden sm:block">Unsaved changes</p>
                        <div class="flex gap-4">
                            <a href="/seller/products.php" class="px-6 py-3 bg-white border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition">
                                Discard
                            </a>
                            <button type="submit" class="px-8 py-3 bg-primary hover:bg-indigo-700 text-white font-bold rounded-xl shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                                Publish Product
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
    function addVariantRow() {
        const container = document.getElementById('variants-container');
        const html = `
        <div class="flex gap-3 items-start p-3 bg-gray-50 rounded-lg border border-gray-200 relative group">
            <div class="flex-1">
                <input type="text" name="variants_sku[]" placeholder="SKU" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="flex-1">
                <input type="text" name="variants_color[]" placeholder="Color" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="flex-1">
                <input type="text" name="variants_size[]" placeholder="Size" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="w-24">
                <input type="number" name="variants_price[]" placeholder="Price" step="0.01" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="w-24">
                <input type="number" name="variants_stock[]" placeholder="Stock" class="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm">
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-gray-400 hover:text-red-500 p-2">
                <i class="fas fa-trash-alt"></i>
            </button>
        </div>
    `;
        container.insertAdjacentHTML('beforeend', html);
    }

    function previewImages(input) {
        const preview = document.getElementById('image-preview');
        preview.innerHTML = '';

        if (input.files) {
            Array.from(input.files).forEach(file => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square rounded-lg overflow-hidden border border-gray-200';
                    div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                    preview.appendChild(div);
                }
                reader.readAsDataURL(file);
            });
        }
    }
</script>

<?php include '../includes/footer.php'; ?>
