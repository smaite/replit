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
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$_POST['quantity'], $_POST['cart_id']]);
    }
    
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
}

include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-12">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>
    
    <?php if (empty($cart_items)): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center">
            <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Your cart is empty</h3>
            <p class="text-gray-600 mb-6">Add some products to your cart and they will appear here</p>
            <a href="/pages/products.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg hover:bg-indigo-700 font-medium">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-4">
                <?php foreach ($cart_items as $item): ?>
                    <?php $item_price = $item['sale_price'] ?? $item['price']; ?>
                    <div class="bg-white rounded-lg shadow p-4 flex gap-4">
                        <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>">
                            <img src="<?php echo htmlspecialchars($item['image_path'] ?? 'https://via.placeholder.com/150'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['name']); ?>"
                                 class="w-24 h-24 object-cover rounded">
                        </a>
                        
                        <div class="flex-1">
                            <a href="/pages/product-detail.php?slug=<?php echo htmlspecialchars($item['slug']); ?>" 
                               class="font-bold text-gray-900 hover:text-primary">
                                <?php echo htmlspecialchars($item['name']); ?>
                            </a>

                            <p class="text-lg font-bold text-primary mt-2">
                                <?php echo formatPrice($item_price); ?>
                            </p>
                        </div>
                        
                        <div class="flex flex-col items-end justify-between">
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" class="text-red-600 hover:text-red-700">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            
                            <div class="flex items-center gap-2">
                                <form method="POST" action="" class="inline">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                           min="1" max="<?php echo $item['stock']; ?>"
                                           class="w-16 text-center border border-gray-300 rounded py-1"
                                           onchange="this.form.submit()">
                                </form>
                            </div>
                            
                            <p class="font-bold text-gray-900">
                                <?php echo formatPrice($item_price * $item['quantity']); ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-24">
                    <h3 class="font-bold text-xl text-gray-900 mb-4">Order Summary</h3>
                    
                    <div class="space-y-3 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium"><?php echo formatPrice($total); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium text-green-600">FREE</span>
                        </div>
                        <div class="border-t pt-3 flex justify-between text-lg font-bold">
                            <span>Total</span>
                            <span class="text-primary"><?php echo formatPrice($total); ?></span>
                        </div>
                    </div>
                    
                    <a href="/pages/checkout.php" 
                       class="block w-full bg-primary hover:bg-indigo-700 text-white text-center py-3 rounded-lg font-medium transition mb-3">
                        <i class="fas fa-credit-card"></i> Proceed to Checkout
                    </a>
                    
                    <a href="/pages/products.php" 
                       class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-800 text-center py-3 rounded-lg font-medium transition">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
