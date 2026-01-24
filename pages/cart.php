<?php
require_once '../config/config.php';
require_once '../config/database.php';

$page_title = 'Shopping Cart - SASTO Hub';

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'remove' && isset($_POST['cart_id'])) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE id = ?");
        $stmt->execute([$_POST['cart_id']]);
    } elseif ($action === 'update' && isset($_POST['cart_id'], $_POST['quantity'])) {
        $qty = max(1, intval($_POST['quantity'])); // Ensure at least 1
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$qty, $_POST['cart_id']]);
    }

    // Redirect to prevent form resubmission
    redirect('/pages/cart.php');
}

// Fetch cart items
$cart_items = [];
$total = 0;

if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.sale_price, p.stock, p.slug, pi.image_path, v.shop_name
                            FROM cart c
                            JOIN products p ON c.product_id = p.id
                            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
                            LEFT JOIN vendors v ON p.vendor_id = v.id
                            WHERE c.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();

    foreach ($cart_items as $item) {
        $price = $item['sale_price'] ?? $item['price'];
        $total += $price * $item['quantity'];
    }

    $_SESSION['cart_count'] = count($cart_items);
} else {
    // Redirect to login if not logged in? Or show empty cart?
    // Usually better to show empty or prompt login.
    // For now, consistent with existing logic.
}

include '../includes/header.php';
?>

<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="flex mb-8 text-sm text-gray-500">
            <a href="/" class="hover:text-primary">Home</a>
            <span class="mx-2">/</span>
            <span class="text-gray-900 font-medium">Shopping Cart</span>
        </nav>

        <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart (<?php echo count($cart_items); ?>)</h1>

        <?php if (empty($cart_items)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-16 text-center max-w-2xl mx-auto">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shopping-cart text-4xl text-gray-300"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">Your cart is empty</h3>
                <p class="text-gray-500 mb-8">Looks like you haven't added anything to your cart yet.</p>
                <a href="/pages/products.php" class="inline-flex items-center justify-center px-8 py-3 bg-primary text-white font-bold rounded-xl hover:bg-indigo-700 transition transform hover:-translate-y-0.5">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Cart Items List (8 cols) -->
                <div class="lg:col-span-8">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                        <div class="p-6 border-b border-gray-100 bg-gray-50 hidden md:flex text-sm font-bold text-gray-500">
                            <div class="w-1/2">Product</div>
                            <div class="w-1/6 text-center">Price</div>
                            <div class="w-1/6 text-center">Quantity</div>
                            <div class="w-1/6 text-right">Total</div>
                        </div>

                        <div class="divide-y divide-gray-100">
                            <?php foreach ($cart_items as $item): ?>
                                <?php $item_price = $item['sale_price'] ?? $item['price']; ?>
                                <div class="p-6 flex flex-col md:flex-row items-center gap-6">
                                    <!-- Product Info -->
                                    <div class="w-full md:w-1/2 flex gap-4">
                                        <div class="w-20 h-20 flex-shrink-0 bg-gray-100 rounded-lg overflow-hidden border border-gray-200">
                                            <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/150'); ?>"
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                                 class="w-full h-full object-cover">
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>"
                                               class="font-bold text-gray-900 hover:text-primary line-clamp-2 mb-1">
                                                <?php echo htmlspecialchars($item['name']); ?>
                                            </a>
                                            <p class="text-xs text-gray-500 mb-2">Sold by: <?php echo htmlspecialchars($item['shop_name']); ?></p>

                                            <form method="POST" action="" class="inline-block">
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium flex items-center gap-1 transition">
                                                    <i class="far fa-trash-alt"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <!-- Price -->
                                    <div class="w-full md:w-1/6 md:text-center flex justify-between md:block">
                                        <span class="md:hidden text-gray-500 text-sm">Price:</span>
                                        <span class="font-bold text-gray-900"><?php echo formatPrice($item_price); ?></span>
                                    </div>

                                    <!-- Quantity -->
                                    <div class="w-full md:w-1/6 flex justify-between md:justify-center">
                                        <span class="md:hidden text-gray-500 text-sm">Qty:</span>
                                        <form method="POST" action="" class="flex items-center border border-gray-300 rounded-lg">
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">

                                            <button type="submit" name="quantity" value="<?php echo max(1, $item['quantity'] - 1); ?>"
                                                    class="px-2 py-1 text-gray-500 hover:bg-gray-100 hover:text-primary transition">
                                                <i class="fas fa-minus text-xs"></i>
                                            </button>

                                            <input type="text" value="<?php echo $item['quantity']; ?>" readonly
                                                   class="w-8 text-center text-sm font-medium border-x border-gray-300 py-1 focus:outline-none">

                                            <button type="submit" name="quantity" value="<?php echo min($item['stock'], $item['quantity'] + 1); ?>"
                                                    class="px-2 py-1 text-gray-500 hover:bg-gray-100 hover:text-primary transition"
                                                    <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>>
                                                <i class="fas fa-plus text-xs"></i>
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Subtotal -->
                                    <div class="w-full md:w-1/6 md:text-right flex justify-between md:block">
                                        <span class="md:hidden text-gray-500 text-sm">Subtotal:</span>
                                        <span class="font-bold text-primary"><?php echo formatPrice($item_price * $item['quantity']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-6 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                            <a href="/pages/products.php" class="text-gray-600 hover:text-primary font-medium flex items-center gap-2">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Summary (4 cols) -->
                <div class="lg:col-span-4">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-24">
                        <h3 class="font-bold text-lg text-gray-900 mb-6">Order Summary</h3>

                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span class="font-medium text-gray-900"><?php echo formatPrice($total); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Shipping</span>
                                <span class="font-medium text-green-600">Free</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Tax</span>
                                <span class="font-medium text-gray-900">Rs. 0.00</span>
                            </div>

                            <!-- Coupon Input (Visual only for now) -->
                            <div class="pt-4">
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Promo Code</label>
                                <div class="flex gap-2">
                                    <input type="text" placeholder="Enter code" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-primary">
                                    <button class="px-4 py-2 bg-gray-900 text-white text-sm font-bold rounded-lg hover:bg-gray-800 transition">Apply</button>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-100 flex justify-between items-center">
                                <span class="font-bold text-lg text-gray-900">Total</span>
                                <span class="font-bold text-xl text-primary"><?php echo formatPrice($total); ?></span>
                            </div>
                        </div>

                        <a href="/pages/checkout.php"
                           class="block w-full bg-primary hover:bg-indigo-700 text-white text-center py-4 rounded-xl font-bold text-lg shadow-lg shadow-indigo-200 transition transform hover:-translate-y-0.5">
                            Proceed to Checkout
                        </a>

                        <div class="mt-6 flex items-center justify-center gap-4 text-gray-400">
                            <i class="fab fa-cc-visa text-2xl"></i>
                            <i class="fab fa-cc-mastercard text-2xl"></i>
                            <i class="fas fa-money-bill-wave text-2xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
