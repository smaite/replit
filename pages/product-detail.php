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

// Fetch review stats
$stmt = $conn->prepare("SELECT COUNT(*) as review_count, AVG(rating) as avg_rating FROM reviews WHERE product_id = ? AND status = 'approved'");
$stmt->execute([$product['id']]);
$review_stats = $stmt->fetch();
$review_count = $review_stats['review_count'] ?? 0;
$avg_rating = round($review_stats['avg_rating'] ?? 0, 1);

// Fetch sales stats
$stmt = $conn->prepare("
    SELECT SUM(oi.quantity) as total_sold
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE oi.product_id = ?
    AND o.status != 'cancelled'
    AND o.payment_status = 'paid'
");
$stmt->execute([$product['id']]);
$sales_stats = $stmt->fetch();
$total_sold = $sales_stats['total_sold'] ?? 0;

// Fetch recent reviews
$stmt = $conn->prepare("
    SELECT r.*, u.full_name, u.avatar
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.created_at DESC
    LIMIT 5
");
$stmt->execute([$product['id']]);
$recent_reviews = $stmt->fetchAll();

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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 bg-white rounded-lg shadow-lg p-8">
        <!-- Col 1: Product Images -->
        <div class="lg:col-span-1">
            <div class="mb-4 border border-gray-200 rounded-lg p-2">
                <img id="mainImage"
                     src="<?php echo htmlspecialchars($images[0]['image_path'] ?? 'https://via.placeholder.com/600'); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     class="w-full h-80 object-contain rounded-lg">
            </div>

            <?php if (count($images) > 1): ?>
                <div class="grid grid-cols-5 gap-2">
                    <?php foreach ($images as $img): ?>
                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>"
                             alt="Product image"
                             onclick="document.getElementById('mainImage').src = this.src"
                             class="w-full h-16 object-cover rounded cursor-pointer hover:opacity-75 border border-gray-200 hover:border-primary">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Col 2: Product Info -->
        <div class="lg:col-span-1">
            <h1 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>

            <div class="flex items-center gap-4 mb-4">
                <div class="flex text-yellow-400 text-sm">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <?php if($i <= $avg_rating): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif($i - 0.5 <= $avg_rating): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <span class="text-gray-500 text-sm"><?php echo $review_count; ?> reviews</span>
                <span class="text-gray-300">|</span>
                <span class="text-gray-500 text-sm"><?php echo $total_sold; ?> orders</span>
            </div>

            <div class="mb-4">
                 <?php if ($product['sale_price']): ?>
                    <div class="flex items-end gap-2">
                        <span class="text-3xl font-bold text-red-600"><?php echo formatPrice($product['sale_price']); ?></span>
                        <span class="text-lg text-gray-500 line-through mb-1"><?php echo formatPrice($product['price']); ?></span>
                    </div>
                <?php else: ?>
                    <span class="text-3xl font-bold text-primary"><?php echo formatPrice($product['price']); ?></span>
                <?php endif; ?>
            </div>

            <div class="mb-4">
                 <ul class="text-sm text-gray-600 space-y-2">
                    <?php if ($product['sku']): ?>
                    <li class="flex">
                        <span class="w-24 text-gray-500">SKU:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($product['sku']); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($product['category_name']): ?>
                    <li class="flex">
                        <span class="w-24 text-gray-500">Category:</span>
                        <span class="font-medium"><?php echo htmlspecialchars($product['category_name']); ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="flex">
                        <span class="w-24 text-gray-500">Condition:</span>
                        <span class="font-medium">New</span>
                    </li>
                </ul>
            </div>

            <div class="border-t border-b border-gray-100 py-4 mb-4">
                <h3 class="font-bold text-gray-900 mb-2">Description</h3>
                <p class="text-gray-700 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
        </div>

        <!-- Col 3: Supplier & Actions -->
        <div class="lg:col-span-1">
            <div class="border border-gray-200 rounded-lg p-6 shadow-sm">
                <!-- Supplier Info
                <div class="flex items-center gap-3 mb-4 pb-4 border-b border-gray-100">
                    <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center text-xl font-bold text-gray-500 uppercase">
                        <?php echo substr($product['shop_name'] ?? 'S', 0, 1); ?>
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-900"><?php echo htmlspecialchars($product['shop_name'] ?? 'SASTO Hub Store'); ?></h4>
                        <div class="text-xs text-gray-500">Verified Seller</div>
                    </div>
                </div> -->

                <!-- Stock Status -->
                <div class="mb-4">
                    <div class="<?php echo $product['stock'] > 0 ? 'text-green-600' : 'text-red-600'; ?> font-medium mb-2 flex items-center gap-2">
                        <i class="fas fa-<?php echo $product['stock'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                        <?php echo $product['stock'] > 0 ? "In Stock ({$product['stock']} available)" : "Out of Stock"; ?>
                    </div>
                </div>

                <?php if ($product['stock'] > 0): ?>
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                        <div class="flex items-center border border-gray-300 rounded w-max">
                            <button onclick="changeQty(-1)" class="px-3 py-1 hover:bg-gray-100 border-r border-gray-300">
                                <i class="fas fa-minus text-xs"></i>
                            </button>
                            <input type="number" id="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>"
                                   class="w-12 text-center border-none focus:ring-0 py-1 text-sm">
                            <button onclick="changeQty(1)" class="px-3 py-1 hover:bg-gray-100 border-l border-gray-300">
                                <i class="fas fa-plus text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <button onclick="addToCart(<?php echo $product['id']; ?>, document.getElementById('quantity').value)"
                                class="w-full bg-primary hover:bg-indigo-700 text-white py-3 rounded-lg font-bold transition shadow-sm">
                            Add to cart
                        </button>
                        <button class="w-full border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 py-2 rounded-lg font-medium transition flex items-center justify-center gap-2">
                            <i class="far fa-heart"></i> Save for later
                        </button>
                    </div>
                <?php else: ?>
                    <button disabled class="w-full bg-gray-300 text-gray-600 py-3 rounded-lg font-bold cursor-not-allowed">
                        Out of Stock
                    </button>
                <?php endif; ?>

                <div class="mt-6 pt-4 border-t border-gray-100 text-xs text-gray-500 space-y-2">
                     <div class="flex items-center gap-2">
                        <i class="fas fa-globe text-gray-400"></i> Worldwide shipping
                     </div>
                     <div class="flex items-center gap-2">
                        <i class="fas fa-shield-alt text-gray-400"></i> Secure payments
                     </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Write a Review Section -->
    <div class="mt-8 bg-white rounded-lg shadow-lg p-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Write a Review</h3>
        <?php if (isLoggedIn()): ?>
            <form id="reviewForm" class="space-y-4">
                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                    <div class="flex gap-2 text-2xl text-gray-300 cursor-pointer" id="starRating">
                        <i class="far fa-star hover:text-yellow-400 transition" data-rating="1"></i>
                        <i class="far fa-star hover:text-yellow-400 transition" data-rating="2"></i>
                        <i class="far fa-star hover:text-yellow-400 transition" data-rating="3"></i>
                        <i class="far fa-star hover:text-yellow-400 transition" data-rating="4"></i>
                        <i class="far fa-star hover:text-yellow-400 transition" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Review</label>
                    <textarea name="comment" id="commentInput" rows="4" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-primary focus:border-primary" placeholder="Share your thoughts about this product..." required></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Photos (Optional)</label>
                    <input type="file" name="images[]" multiple accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition" onchange="previewImages(this)">
                    <div id="imagePreview" class="flex gap-2 mt-2 flex-wrap"></div>
                </div>

                <div class="flex gap-2">
                    <button type="submit" id="submitBtn" class="bg-primary hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-bold transition">
                        Submit Review
                    </button>
                    <button type="button" id="cancelEditBtn" onclick="cancelEdit()" class="hidden bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded-lg font-bold transition">
                        Cancel
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                <p class="text-gray-600 mb-4">Please log in to write a review.</p>
                <a href="/auth/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="inline-block bg-primary text-white px-6 py-2 rounded-lg font-bold hover:bg-indigo-700 transition">
                    Log In
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reviews Section -->
    <?php if (!empty($recent_reviews)): ?>
        <div class="mt-8 bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Customer Reviews</h2>
            <div class="space-y-6">
                <?php foreach ($recent_reviews as $review):
                    // Fetch images for this review
                    $stmt = $conn->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                    $stmt->execute([$review['id']]);
                    $r_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $images_json = htmlspecialchars(json_encode($r_images), ENT_QUOTES, 'UTF-8');
                ?>
                    <div class="border-b border-gray-100 pb-6 last:border-0 last:pb-0" id="review-<?php echo $review['id']; ?>">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-4 mb-2">
                                <div class="w-10 h-10 bg-gray-200 rounded-full overflow-hidden">
                                    <?php if ($review['avatar']): ?>
                                        <img src="<?php echo htmlspecialchars($review['avatar']); ?>" alt="User avatar" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-gray-500">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="font-bold text-gray-900"><?php echo htmlspecialchars($review['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></div>
                                </div>
                            </div>
                            <?php if (isLoggedIn() && isset($_SESSION['user_id']) && $_SESSION['user_id'] == $review['user_id']): ?>
                                <div class="flex gap-3">
                                    <button onclick="editReview(<?php echo $review['id']; ?>, <?php echo $review['rating']; ?>, `<?php echo addslashes(htmlspecialchars_decode($review['comment'])); ?>`, <?php echo $images_json; ?>)" class="text-blue-600 hover:text-blue-800 text-xs font-medium transition">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <button onclick="deleteReview(<?php echo $review['id']; ?>)" class="text-red-600 hover:text-red-800 text-xs font-medium transition">
                                        <i class="fas fa-trash-alt mr-1"></i> Delete
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex text-yellow-400 text-xs mb-2">
                            <?php for($i = 1; $i <= 5; $i++): ?>
                                <?php if($i <= $review['rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>

                        <!-- Review Images -->
                        <?php if (!empty($r_images)): ?>
                            <div class="flex gap-2 mt-3">
                                <?php foreach ($r_images as $r_img): ?>
                                    <img src="<?php echo htmlspecialchars($r_img); ?>" class="w-20 h-20 object-cover rounded border border-gray-200 cursor-pointer hover:opacity-90" onclick="window.open(this.src, '_blank')">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="mt-12 bg-white rounded-lg shadow-lg p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <?php foreach ($related_products as $rel): ?>
                    <div class="group">
                        <div class="bg-gray-100 rounded-lg mb-3 overflow-hidden">
                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($rel['slug']); ?>">
                                <img src="<?php echo htmlspecialchars($rel['image_path'] ?? 'https://via.placeholder.com/300'); ?>"
                                     alt="<?php echo htmlspecialchars($rel['name']); ?>"
                                     class="w-full h-48 object-cover group-hover:scale-105 transition duration-300">
                            </a>
                        </div>
                        <div class="p-1">
                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($rel['slug']); ?>">
                                <h3 class="font-medium text-gray-900 hover:text-primary line-clamp-2 mb-1">
                                    <?php echo htmlspecialchars($rel['name']); ?>
                                </h3>
                            </a>
                            <p class="font-bold text-gray-900"><?php echo formatPrice($rel['price']); ?></p>
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

// Star Rating Logic
const stars = document.querySelectorAll('#starRating i');
const ratingInput = document.getElementById('ratingInput');

if (stars.length > 0) {
    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingInput.value = rating;
            updateStars(rating);
        });
    });
}

function updateStars(rating) {
    stars.forEach(s => {
        const sRating = s.getAttribute('data-rating');
        if (sRating <= rating) {
            s.classList.remove('far', 'text-gray-300');
            s.classList.add('fas', 'text-yellow-400');
        } else {
            s.classList.remove('fas', 'text-yellow-400');
            s.classList.add('far', 'text-gray-300');
        }
    });
}

// Global state for editing
let editingReviewId = null;
let deletedImages = [];

// Form Submission
const reviewForm = document.getElementById('reviewForm');
if (reviewForm) {
    reviewForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!ratingInput.value) {
            alert('Please select a rating');
            return;
        }

        const formData = new FormData(this);

        if (editingReviewId) {
            formData.append('review_id', editingReviewId);
            formData.append('deleted_images', JSON.stringify(deletedImages));
        }

        try {
            // Always use POST for Create and Update (Update handled by review_id)
            const response = await fetch('/api/reviews.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert(editingReviewId ? 'Review updated successfully!' : 'Review submitted successfully!');
                window.location.reload();
            } else {
                alert(result.error || 'Failed to submit review');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while submitting the review');
        }
    });
}

function previewImages(input) {
    // Only clear if we are NOT in edit mode, or append?
    // Let's clear new previews but keep existing ones?
    // For simplicity, we append new previews to the end or clear standard ones?
    // Let's just append new ones to the container

    // Actually, if input changes, it replaces previous file selection (unless custom UI).
    // Standard file input replaces selection. So we should clear "new" previews.

    // We need to separate "Existing Images" vs "New Previews" if we want to support both nicely.
    // For now, let's just dump everything into imagePreview.

    // But if we clear imagePreview, we lose existing images in edit mode.
    // Solution: Keep existing images in DOM, remove only "preview-new" class items.

    // Ideally, we should have separate containers, but let's hack it:
    // Remove only elements that don't have 'existing-image' class?

    // Simplest: Just append. But if user selects files again, we get duplicates visually?
    // Standard <input type=file> clears previous selection when you pick new ones.

    // Let's rely on the fact that existing images are managed by editReview()
    // And new images are managed by input.onchange

    // We should clear *previous previews* but keep *existing DB images*.
    const previewContainer = document.getElementById('imagePreview');

    // Remove old previews
    const oldPreviews = previewContainer.querySelectorAll('.new-preview');
    oldPreviews.forEach(el => el.remove());

    if (input.files) {
        Array.from(input.files).forEach(file => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'w-20 h-20 object-cover rounded border border-gray-200 new-preview';
                previewContainer.appendChild(img);
            }
            reader.readAsDataURL(file);
        });
    }
}

function editReview(id, rating, comment, existingImages = []) {
    editingReviewId = id;
    deletedImages = [];

    // Scroll to form
    document.getElementById('reviewForm').scrollIntoView({ behavior: 'smooth' });

    // Populate form
    document.getElementById('ratingInput').value = rating;
    updateStars(rating);
    document.getElementById('commentInput').value = comment;

    // Change UI
    document.getElementById('submitBtn').textContent = 'Update Review';
    document.getElementById('cancelEditBtn').classList.remove('hidden');

    // Display existing images
    const preview = document.getElementById('imagePreview');
    preview.innerHTML = ''; // Clear everything first

    existingImages.forEach(src => {
        const container = document.createElement('div');
        container.className = 'relative inline-block existing-image';

        const img = document.createElement('img');
        img.src = src;
        img.className = 'w-20 h-20 object-cover rounded border border-gray-200';

        const removeBtn = document.createElement('button');
        removeBtn.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs shadow-md hover:bg-red-600';
        removeBtn.innerHTML = '<i class="fas fa-times"></i>';
        removeBtn.onclick = function(e) {
            e.preventDefault();
            container.remove();
            deletedImages.push(src);
        };

        container.appendChild(img);
        container.appendChild(removeBtn);
        preview.appendChild(container);
    });
}

function cancelEdit() {
    editingReviewId = null;
    deletedImages = [];
    document.getElementById('reviewForm').reset();
    document.getElementById('ratingInput').value = '';
    updateStars(0);
    document.getElementById('submitBtn').textContent = 'Submit Review';
    document.getElementById('cancelEditBtn').classList.add('hidden');
    document.getElementById('imagePreview').innerHTML = '';
}

async function deleteReview(id) {
    if (!confirm('Are you sure you want to delete this review?')) return;

    try {
        const response = await fetch(`/api/reviews.php?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (result.success) {
            alert('Review deleted successfully');
            window.location.reload();
        } else {
            alert(result.error || 'Failed to delete review');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while deleting the review');
    }
}
</script>

<?php include '../includes/footer.php'; ?>
