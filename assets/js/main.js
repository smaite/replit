// SASTO Hub - Main JavaScript

// Add to Cart Function
function addToCart(productId, quantity = 1) {
    if (!productId) return;
    
    // Show loading
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    btn.disabled = true;
    
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
            
            // Reset button
            btn.innerHTML = '<i class="fas fa-check"></i> Added!';
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 2000);
        } else {
            showNotification(data.message || 'Failed to add to cart', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Please login to add items to cart', 'error');
        setTimeout(() => {
            window.location.href = '/auth/login.php';
        }, 1500);
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

// Update Cart Count
function updateCartCount() {
    fetch('/api/cart.php?action=count')
        .then(response => response.json())
        .then(data => {
            const countElement = document.querySelector('.cart-count');
            if (countElement && data.count > 0) {
                countElement.textContent = data.count;
            }
        })
        .catch(error => console.error('Error updating cart count:', error));
}

// Show Notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-20 right-4 z-50 px-6 py-4 rounded-lg shadow-xl text-white transition-all transform translate-x-full ${
        type === 'success' ? 'bg-green-500' : 
        type === 'error' ? 'bg-red-500' : 
        'bg-blue-500'
    }`;
    notification.innerHTML = `
        <div class="flex items-center gap-3">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Slide out and remove
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
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

// Mobile Menu Toggle (if needed)
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.toggle('hidden');
    }
}

// Search Suggestions (optional enhancement)
const searchInput = document.querySelector('input[name="q"]');
if (searchInput) {
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length > 2) {
            searchTimeout = setTimeout(() => {
                // Could implement search suggestions here
                console.log('Searching for:', query);
            }, 300);
        }
    });
}

// Initialize
console.log('SASTO Hub loaded successfully!');
