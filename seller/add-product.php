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
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-md mx-auto bg-red-50 border border-red-200 rounded-lg p-6 text-center">
            <i class="fas fa-ban text-4xl text-red-600 mb-4"></i>
            <h2 class="text-xl font-bold text-red-900 mb-2">Cannot Upload Products</h2>
            <p class="text-red-700"><?php echo htmlspecialchars($error_msg); ?></p>
            <a href="/seller/" class="inline-block mt-6 bg-primary text-white px-6 py-2 rounded-lg hover:bg-indigo-700">
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
            $error = 'Please fill in all required fields correctly (including at least one category).';
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

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Add New Product</h1>
        <a href="/seller/" class="text-gray-600 hover:text-primary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <span class="block sm:inline"><?php echo htmlspecialchars($success); ?></span>
            <p class="mt-2"><a href="/seller/" class="font-bold underline">Return to Dashboard</a> or add another product below.</p>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow-md p-6">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

        <!-- Basic Information -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="product_name">Product Title *</label>
                    <input type="text" id="product_name" name="product_name" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['product_name'] ?? ''); ?>">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="product_description">Description *</label>
                    <textarea id="product_description" name="product_description" rows="5" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($_POST['product_description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="category_ids">Category *</label>
                    <select id="category_ids" name="category_ids[]" required multiple
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline h-32">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple categories.</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="brand_id">Brand</label>
                    <select id="brand_id" name="brand_id"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">No Brand / Generic</option>
                        <?php foreach ($brands as $brand): ?>
                            <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="condition">Condition *</label>
                    <select id="condition" name="condition" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="new">New</option>
                        <option value="used">Used</option>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="sku">SKU (Stock Keeping Unit)</label>
                    <input type="text" id="sku" name="sku"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="tags">Tags (comma separated)</label>
                    <input type="text" id="tags" name="tags" placeholder="e.g. smartphone, electronics, sale"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Pricing & Inventory -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Pricing & Inventory</h2>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="price">Sell Price (Rs.) *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="sale_price">Discount Price (Rs.)</label>
                    <input type="number" id="sale_price" name="sale_price" step="0.01" min="0"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['sale_price'] ?? ''); ?>">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="stock">Stock Quantity *</label>
                    <input type="number" id="stock" name="stock" min="0" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['stock'] ?? ''); ?>">
                </div>

                <div class="md:col-span-4">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="flash_sale_eligible" class="form-checkbox h-5 w-5 text-primary" value="1">
                        <span class="ml-2 text-gray-700">Eligible for Flash Sales</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Shipping & Delivery -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Shipping & Delivery</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="shipping_weight">Weight (kg)</label>
                    <input type="number" id="shipping_weight" name="shipping_weight" step="0.01" min="0"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['shipping_weight'] ?? ''); ?>">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="shipping_profile_id">Shipping Profile</label>
                    <select id="shipping_profile_id" name="shipping_profile_id"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Default Shipping</option>
                        <?php foreach ($shipping_profiles as $profile): ?>
                            <option value="<?php echo $profile['id']; ?>"><?php echo htmlspecialchars($profile['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="handling_days">Handling Time (Days)</label>
                    <input type="number" id="handling_days" name="handling_days" min="1" value="1"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>

                <div class="md:col-span-3 grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Length (cm)</label>
                        <input type="number" name="dimensions_length" step="0.1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Width (cm)</label>
                        <input type="number" name="dimensions_width" step="0.1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-bold mb-2">Height (cm)</label>
                        <input type="number" name="dimensions_height" step="0.1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700">
                    </div>
                </div>

                <div class="md:col-span-3">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="free_shipping" class="form-checkbox h-5 w-5 text-primary" value="1">
                        <span class="ml-2 text-gray-700">Free Shipping</span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Media -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Product Media</h2>
            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="product_images">Product Images (Max 5) *</label>
                    <input type="file" id="product_images" name="product_images[]" multiple accept="image/*" required
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    <p class="text-xs text-gray-500 mt-1">First image will be the main image. Supported: JPG, PNG, WebP. Max 5MB each.</p>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="video_url">Product Video URL (YouTube/Vimeo)</label>
                    <input type="url" id="video_url" name="video_url" placeholder="https://..."
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                        value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Variants -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Product Variants (Optional)</h2>
            <div id="variants-container">
                <!-- Variant rows will be added here -->
            </div>
            <button type="button" onclick="addVariantRow()" class="mt-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded inline-flex items-center">
                <i class="fas fa-plus mr-2"></i> Add Variant
            </button>
        </div>

        <!-- Other -->
        <div class="mb-8">
            <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Additional Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="return_policy_id">Return Policy</label>
                    <select id="return_policy_id" name="return_policy_id"
                        class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        <option value="">Default Policy</option>
                        <?php foreach ($return_policies as $policy): ?>
                            <option value="<?php echo $policy['id']; ?>"><?php echo htmlspecialchars($policy['name']); ?> (<?php echo $policy['days']; ?> days)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Bullet Points (Highlights)</label>
                    <div id="bullet-points-container">
                        <input type="text" name="bullet_points[]" placeholder="Feature highlight 1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-2">
                        <input type="text" name="bullet_points[]" placeholder="Feature highlight 2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-2">
                        <input type="text" name="bullet_points[]" placeholder="Feature highlight 3" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-2">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="is_featured" class="form-checkbox h-5 w-5 text-primary" value="1">
                        <span class="ml-2 text-gray-700">Request to be Featured Product</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end">
            <button type="submit" class="bg-primary hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded focus:outline-none focus:shadow-outline">
                Upload Product
            </button>
        </div>
    </form>
</div>

<script>
    function addVariantRow() {
        const container = document.getElementById('variants-container');
        const index = container.children.length;
        const html = `
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4 p-4 border rounded bg-gray-50 relative">
            <button type="button" onclick="this.parentElement.remove()" class="absolute top-2 right-2 text-red-500 hover:text-red-700">
                <i class="fas fa-times"></i>
            </button>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">SKU</label>
                <input type="text" name="variants_sku[]" required class="shadow appearance-none border rounded w-full py-1 px-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Color</label>
                <input type="text" name="variants_color[]" class="shadow appearance-none border rounded w-full py-1 px-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Size</label>
                <input type="text" name="variants_size[]" class="shadow appearance-none border rounded w-full py-1 px-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Price</label>
                <input type="number" name="variants_price[]" step="0.01" class="shadow appearance-none border rounded w-full py-1 px-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Stock</label>
                <input type="number" name="variants_stock[]" class="shadow appearance-none border rounded w-full py-1 px-2 text-sm">
            </div>
        </div>
    `;
        container.insertAdjacentHTML('beforeend', html);
    }
</script>

<?php include '../includes/footer.php'; ?>