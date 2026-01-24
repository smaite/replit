// SASTO Hub - Main JavaScript

// Add to Cart Function
function addToCart(productId, quantity = 1) {
    if (!productId) return;

    // Find the button that triggered this
    const btn = event.target.closest('button');
    let originalText = '';

    if (btn) {
        originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        btn.disabled = true;
    }

    fetch('/api/cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            product_id: productId,
            quantity: parseInt(quantity)
        })
    })
    .then(response => {
        if (response.status === 401) {
            showNotification('Please login to add items to cart', 'error');
            setTimeout(() => {
                window.location.href = '/auth/login.php';
            }, 1500);
            throw new Error('Unauthorized');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update cart count
            updateCartCount();

            // Show success message
            showNotification('Product added to cart!', 'success');

            // Reset button visual
            if (btn) {
                btn.innerHTML = '<i class="fas fa-check"></i>';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }, 2000);
            }
        } else {
            showNotification(data.message || 'Failed to add to cart', 'error');
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (error.message !== 'Unauthorized') {
            showNotification('Please login to add items to cart', 'error');
            setTimeout(() => {
                window.location.href = '/auth/login.php';
            }, 1500);
        }
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
}

// Toggle Wishlist Function
function toggleWishlist(productId, element) {
    if (!productId) return;

    // Prevent event bubbling if clicked inside a link
    if (event) event.stopPropagation();

    const icon = element.querySelector('i');
    const isLiked = icon.classList.contains('fas'); // Solid heart means liked

    // Optimistic UI update
    if (isLiked) {
        icon.classList.remove('fas', 'text-red-500');
        icon.classList.add('far', 'text-gray-400');
    } else {
        icon.classList.remove('far', 'text-gray-400');
        icon.classList.add('fas', 'text-red-500');
    }

    fetch('/api/wishlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => {
        if (response.status === 401) {
            // Revert UI change
            if (isLiked) {
                icon.classList.add('fas', 'text-red-500');
                icon.classList.remove('far', 'text-gray-400');
            } else {
                icon.classList.add('far', 'text-gray-400');
                icon.classList.remove('fas', 'text-red-500');
            }

            showNotification('Please login to manage wishlist', 'error');
            setTimeout(() => {
                window.location.href = '/auth/login.php';
            }, 1500);
            throw new Error('Unauthorized');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.action === 'added') {
                showNotification('Added to wishlist', 'success');
                // Ensure UI is correct (solid red)
                icon.classList.remove('far', 'text-gray-400');
                icon.classList.add('fas', 'text-red-500');
            } else if (data.action === 'removed') {
                showNotification('Removed from wishlist', 'info');
                // Ensure UI is correct (outline gray)
                icon.classList.remove('fas', 'text-red-500');
                icon.classList.add('far', 'text-gray-400');
            }
        } else {
            showNotification(data.message || 'Failed to update wishlist', 'error');
            // Revert on failure
             if (isLiked) {
                icon.classList.add('fas', 'text-red-500');
                icon.classList.remove('far', 'text-gray-400');
            } else {
                icon.classList.add('far', 'text-gray-400');
                icon.classList.remove('fas', 'text-red-500');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// Update Cart Count
function updateCartCount() {
    fetch('/api/cart.php?action=count')
        .then(response => response.json())
        .then(data => {
            // Update all cart count elements (mobile & desktop)
            const countElements = document.querySelectorAll('.cart-count');
            countElements.forEach(el => {
                 if (data.count > 0) {
                    el.textContent = data.count;
                    el.style.display = 'flex';
                } else {
                    el.style.display = 'none';
                }
            });

            // Update the specific badge in the new header structure
            const headerBadge = document.querySelector('a[href="/pages/cart.php"] .absolute.bg-secondary');
            if (headerBadge) {
                 if (data.count > 0) {
                    headerBadge.innerText = data.count;
                    headerBadge.style.display = 'flex';
                } else {
                    headerBadge.style.display = 'none';
                }
            }
        })
        .catch(error => console.error('Error updating cart count:', error));
}

// Show Notification
function showNotification(message, type = 'info') {
    // Remove existing notifications to prevent stacking too many
    const existing = document.querySelectorAll('.fixed.z-50');
    if (existing.length > 2) {
        existing[0].remove();
    }

    const notification = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-600' : (type === 'error' ? 'bg-red-600' : 'bg-blue-600');

    notification.className = `fixed top-24 right-4 z-50 px-6 py-4 rounded-lg shadow-xl text-white transition-all transform translate-x-full ${bgColor} flex items-center gap-3 min-w-[300px]`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} text-xl"></i>
        <span class="font-medium">${message}</span>
    `;

    document.body.appendChild(notification);

    // Slide in
    requestAnimationFrame(() => {
        notification.style.transform = 'translateX(0)';
    });

    // Slide out and remove
    setTimeout(() => {
        notification.style.transform = 'translateX(120%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Image Lazy Loading
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('load', function() {
            this.classList.add('loaded');
        });
        // If already loaded
        if (img.complete) {
            img.classList.add('loaded');
        }
    });
});

// Search Suggestions Logic
const searchInput = document.querySelector('input[name="q"]');
if (searchInput) {
    // Logic moved to inline script in header.php for better context access
}

// Initialize
console.log('SASTO Hub loaded successfully!');
