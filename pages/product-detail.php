<?php
require_once '../config/config.php';
require_once '../config/database.php';

$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    redirect('/pages/products.php');
}

// Fetch product details
$stmt = $conn->prepare("SELECT p.*, v.shop_name, v.shop_description, c.name as category_name 
                        FROM products p 
                        LEFT JOIN vendors v ON p.vendor_id = v.id
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.slug = ? AND p.status = 'active'");
$stmt->execute([$slug]);
$product = $stmt->fetch();

if (!$product) {
    redirect('/pages/products.php');
}

// Fetch product images
$stmt = $conn->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order");
$stmt->execute([$product['id']]);
$images = $stmt->fetchAll();

// Fetch related products from same category
$stmt = $conn->prepare("SELECT p.*, pi.image_path 
                        FROM products p 
                        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active'
                        LIMIT 4");
$stmt->execute([$product['category_id'], $product['id']]);
$related_products = $stmt->fetchAll();

$page_title = htmlspecialchars($product['name']) . ' - SASTO Hub';
include '../includes/header.php';
?>

<div class="bg-white py-4 border-b">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-gray-600">
            <a href="/" class="hover:text-primary">Home</a> / 
            <a href="/pages/products.php" class="hover:text-primary">Products</a> / 
            <?php if ($product['category_name']): ?>
                <a href="/pages/products.php?slug=<?php echo htmlspecialchars($product['category_id']); ?>" class="hover:text-primary">
                    <?php echo htmlspecialchars($product['category_name']); ?>
                </a> / 
            <?php endif; ?>
            <span class="text-gray-900"><?php echo htmlspecialchars($product['name']); ?></span>
        </nav>
    </div>
</div>

<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 bg-white rounded-lg shadow-lg p-8">
        <!-- Product Images -->
        <div>
            <div class="mb-4">
                <img id="mainImage" 
                     src="<?php echo htmlspecialchars($images[0]['image_path'] ?? 'https://via.placeholder.com/600'); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     class="w-full h-96 object-cover rounded-lg shadow">
            </div>
            
            <?php if (count($images) > 1): ?>
                <div class="grid grid-cols-4 gap-2">
                    <?php foreach ($images as $img): ?>
                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" 
                             alt="Product image"
                             onclick="document.getElementById('mainImage').src = this.src"
                             class="w-full h-20 object-cover rounded cursor-pointer hover:opacity-75 border-2 hover:border-primary">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Product Info -->
        <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>
            
            <div class="flex items-center gap-4 mb-4">
                <?php if ($product['sku']): ?>
                    <div class="text-sm text-gray-600">
                        SKU: <?php echo htmlspecialchars($product['sku']); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-6">
                <?php if ($product['sale_price']): ?>
                    <span class="text-4xl font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></span>
                    <span class="text-xl text-gray-500 line-through ml-3"><?php echo formatPrice($product['price']); ?></span>
                    <span class="ml-3 bg-red-100 text-red-600 px-3 py-1 rounded-full text-sm font-medium">
                        Save <?php echo round((($product['price'] - $product['sale_price']) / $product['price']) * 100); ?>%
                    </span>
                <?php else: ?>
                    <span class="text-4xl font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                <?php endif; ?>
            </div>
            
            <div class="mb-6">
                <h3 class="font-bold text-gray-900 mb-2">Description</h3>
                <p class="text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
            
            <div class="mb-6">
                <div class="flex items-center gap-4 text-sm">
                    <div class="<?php echo $product['stock'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        <i class="fas fa-box"></i>
                        <?php echo $product['stock'] > 0 ? "In Stock ({$product['stock']} available)" : "Out of Stock"; ?>
                    </div>
                    <?php if ($product['category_name']): ?>
                        <div class="text-gray-600">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($product['stock'] > 0): ?>
                <div class="mb-6">
                    <label class="block font-medium text-gray-900 mb-2">Quantity</label>
                    <div class="flex items-center gap-3">
                        <button onclick="changeQty(-1)" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" 
                               class="w-20 text-center border border-gray-300 rounded-lg py-2">
                        <button onclick="changeQty(1)" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded-lg">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <div class="flex gap-4">
                    <button onclick="addToCart(<?php echo $product['id']; ?>, document.getElementById('quantity').value)" 
                            class="flex-1 bg-primary hover:bg-indigo-700 text-white py-4 rounded-lg font-bold text-lg transition">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                    <button class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-4 rounded-lg">
                        <i class="fas fa-heart"></i>
                    </button>
                </div>
            <?php else: ?>
                <button disabled class="w-full bg-gray-300 text-gray-600 py-4 rounded-lg font-bold text-lg cursor-not-allowed">
                    Out of Stock
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="mt-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($related_products as $rel): ?>
                    <div class="bg-white rounded-lg shadow hover:shadow-xl transition">
                        <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($rel['slug']); ?>">
                            <img src="<?php echo htmlspecialchars($rel['image_path'] ?? 'https://via.placeholder.com/300'); ?>" 
                                 alt="<?php echo htmlspecialchars($rel['name']); ?>"
                                 class="w-full h-48 object-cover rounded-t-lg">
                        </a>
                        <div class="p-4">
                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($rel['slug']); ?>">
                                <h3 class="font-bold text-gray-900 hover:text-primary line-clamp-2">
                                    <?php echo htmlspecialchars($rel['name']); ?>
                                </h3>
                            </a>
                            <p class="text-lg font-bold text-primary mt-2"><?php echo formatPrice($rel['price']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function changeQty(delta) {
    const input = document.getElementById('quantity');
    const newValue = parseInt(input.value) + delta;
    if (newValue >= 1 && newValue <= <?php echo $product['stock']; ?>) {
        input.value = newValue;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
