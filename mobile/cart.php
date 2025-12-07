<?php
/**
 * Mobile Cart Page - SASTO Hub
 * Shopping cart with quantity controls and checkout
 */
require_once '../config/config.php';
require_once '../config/database.php';

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$cartItems = [];
$subtotal = 0;

// Handle quantity update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_qty'])) {
        $productId = (int)$_POST['product_id'];
        $qty = max(1, (int)$_POST['quantity']);
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId]['quantity'] = $qty;
        }
        header('Location: cart.php');
        exit;
    }
    
    if (isset($_POST['remove_item'])) {
        $productId = (int)$_POST['product_id'];
        unset($_SESSION['cart'][$productId]);
        header('Location: cart.php');
        exit;
    }
    
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
        header('Location: cart.php');
        exit;
    }
}

// Fetch cart product details
if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    try {
        $stmt = $conn->prepare("SELECT * FROM products WHERE id IN ($placeholders) AND status = 'active'");
        $stmt->execute($productIds);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $qty = $cart[$product['id']]['quantity'];
            $itemTotal = $product['price'] * $qty;
            $subtotal += $itemTotal;
            
            $cartItems[] = [
                'product' => $product,
                'quantity' => $qty,
                'total' => $itemTotal
            ];
        }
    } catch (Exception $e) {
        $cartItems = [];
    }
}

$shipping = $subtotal > 0 ? 100 : 0; // Rs. 100 shipping
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title>Cart - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?php echo filemtime('css/style.css'); ?>">
</head>
<body>
    <div class="app-page">
        <!-- Header -->
        <header class="page-header">
            <button class="back-btn" onclick="window.history.back()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1>Shopping Cart</h1>
            <?php if (!empty($cartItems)): ?>
            <form method="POST" style="margin:0;">
                <button type="submit" name="clear_cart" class="clear-btn">Clear</button>
            </form>
            <?php else: ?>
            <div class="header-spacer"></div>
            <?php endif; ?>
        </header>
        
        <?php if (isset($_GET['added'])): ?>
        <div class="toast success">Product added to cart!</div>
        <?php endif; ?>
        
        <!-- Cart Items -->
        <div class="cart-items">
            <?php if (!empty($cartItems)): ?>
                <?php foreach ($cartItems as $item): ?>
                <div class="cart-item">
                    <div class="item-image">
                        <?php if (!empty($item['product']['image'])): ?>
                            <img src="../uploads/products/<?php echo htmlspecialchars($item['product']['image']); ?>" alt="">
                        <?php else: ?>
                            <div class="placeholder-img"></div>
                        <?php endif; ?>
                    </div>
                    <div class="item-details">
                        <h4><?php echo htmlspecialchars($item['product']['name']); ?></h4>
                        <span class="item-price"><?php echo formatPrice($item['product']['price']); ?></span>
                        
                        <div class="item-actions">
                            <form method="POST" class="qty-form">
                                <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                                <div class="qty-control">
                                    <button type="button" onclick="updateQty(this, -1)">-</button>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="99">
                                    <button type="button" onclick="updateQty(this, 1)">+</button>
                                </div>
                                <button type="submit" name="update_qty" class="update-btn">Update</button>
                            </form>
                        </div>
                    </div>
                    <form method="POST" class="remove-form">
                        <input type="hidden" name="product_id" value="<?php echo $item['product']['id']; ?>">
                        <button type="submit" name="remove_item" class="remove-btn">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
                
                <!-- Cart Summary -->
                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span><?php echo formatPrice($subtotal); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span><?php echo formatPrice($shipping); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span><?php echo formatPrice($total); ?></span>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="empty-cart">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#ccc" stroke-width="1.5">
                        <circle cx="9" cy="21" r="1"></circle>
                        <circle cx="20" cy="21" r="1"></circle>
                        <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                    </svg>
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added any items yet</p>
                    <a href="home.php" class="btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($cartItems)): ?>
        <!-- Checkout Button -->
        <div class="checkout-bar">
            <div class="checkout-total">
                <span>Total:</span>
                <span class="total-amount"><?php echo formatPrice($total); ?></span>
            </div>
            <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
        </div>
        <?php endif; ?>
        
        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="home.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Home</span>
            </a>
            <a href="categories.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Categories</span>
            </a>
            <a href="cart.php" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span>Cart</span>
            </a>
            <a href="profile.php" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Profile</span>
            </a>
        </nav>
    </div>
    
    <script>
        function updateQty(btn, delta) {
            const input = btn.parentElement.querySelector('input[name="quantity"]');
            let val = parseInt(input.value) + delta;
            if (val >= 1 && val <= 99) {
                input.value = val;
            }
        }
        
        // Auto-hide toast
        setTimeout(() => {
            const toast = document.querySelector('.toast');
            if (toast) toast.style.display = 'none';
        }, 3000);
    </script>
</body>
</html>
