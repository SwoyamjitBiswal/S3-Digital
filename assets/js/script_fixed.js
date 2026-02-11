// S3 Digital - Custom JavaScript

// Mobile Menu Toggle
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        menu.classList.toggle('show');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('mobileMenu');
    if (menu) {
        const menuButton = event.target.closest('button[onclick="toggleMobileMenu()"]');
        
        if (!menu.contains(event.target) && !menuButton && menu.classList.contains('show')) {
            menu.classList.remove('show');
        }
    }
});

// Add to Cart Function
function addToCart(productId) {
    // Check if user is logged in
    if (typeof isUserLoggedIn === 'function' && !isUserLoggedIn()) {
        showNotification('Please login to add products to cart', 'warning');
        window.location.href = 'login.php';
        return;
    }
    
    // Get button element safely
    const button = event.target.closest('button');
    if (!button) {
        console.error('Button element not found');
        return;
    }
    
    const originalContent = button.innerHTML;
    
    // Show loading state
    button.innerHTML = '<span class="spinner"></span>';
    button.disabled = true;
    
    fetch('api/add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: 1
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartCount(data.cart_count);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Update Cart Count
function updateCartCount(count) {
    const cartCountElements = document.querySelectorAll('.cart-count');
    cartCountElements.forEach(element => {
        element.textContent = count;
    });
}

// Show Notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span class="notification-message">${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
        </div>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Check if User is Logged In
function isUserLoggedIn() {
    return document.body && document.body.classList.contains('user-logged-in');
}

// Remove from Cart
function removeFromCart(productId) {
    if (!confirm('Are you sure you want to remove this item from cart?')) {
        return;
    }
    
    fetch('api/remove_from_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product_id: productId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartCount(data.cart_count);
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Update Cart Quantity
function updateCartQuantity(productId, quantity) {
    if (quantity < 1) return;
    
    fetch('api/update_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: quantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            updateCartCount(data.cart_count);
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Apply Coupon
function applyCoupon() {
    const couponCode = document.getElementById('coupon_code').value.trim();
    
    if (!couponCode) {
        showNotification('Please enter a coupon code', 'warning');
        return;
    }
    
    const button = document.getElementById('apply_coupon_btn');
    const originalContent = button.innerHTML;
    
    button.innerHTML = '<span class="spinner"></span>';
    button.disabled = true;
    
    fetch('api/apply_coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            coupon_code: couponCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    })
    .finally(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Password Strength Checker
function checkPasswordStrength(password) {
    const strengthIndicator = document.getElementById('password_strength');
    if (!strengthIndicator) return;
    
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    strengthIndicator.className = 'password-strength';
    
    if (strength <= 2) {
        strengthIndicator.textContent = 'Weak';
        strengthIndicator.classList.add('weak');
    } else if (strength <= 4) {
        strengthIndicator.textContent = 'Medium';
        strengthIndicator.classList.add('medium');
    } else {
        strengthIndicator.textContent = 'Strong';
        strengthIndicator.classList.add('strong');
    }
}

// Image Preview
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (!preview) return;
    
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

// Product Search with Debounce
let searchTimeout;
function searchProducts(query) {
    clearTimeout(searchTimeout);
    
    searchTimeout = setTimeout(() => {
        if (query.length >= 2) {
            const searchUrl = `products.php?search=${encodeURIComponent(query)}`;
            window.location.href = searchUrl;
        }
    }, 500);
}

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltips = document.querySelectorAll('[data-mdb-toggle="tooltip"]');
    tooltips.forEach(tooltip => {
        new mdb.Tooltip(tooltip);
    });
    
    // Initialize popovers
    const popovers = document.querySelectorAll('[data-mdb-toggle="popover"]');
    popovers.forEach(popover => {
        new mdb.Popover(popover);
    });
    
    // Auto-hide notifications
    setTimeout(() => {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notification => {
            if (notification.parentElement) {
                notification.remove();
            }
        });
    }, 6000);
});

// Payment Processing
function processPayment(paymentMethod, orderId) {
    const button = document.getElementById(`pay_${paymentMethod}`);
    const originalContent = button.innerHTML;
    
    button.innerHTML = '<span class="spinner"></span> Processing...';
    button.disabled = true;
    
    switch(paymentMethod) {
        case 'razorpay':
            processRazorpayPayment(orderId);
            break;
        case 'stripe':
            processStripePayment(orderId);
            break;
        case 'paypal':
            processPaypalPayment(orderId);
            break;
        default:
            showNotification('Invalid payment method', 'error');
            button.innerHTML = originalContent;
            button.disabled = false;
    }
}

// Razorpay Payment
function processRazorpayPayment(orderId) {
    // This would integrate with Razorpay SDK
    showNotification('Razorpay payment integration would go here', 'info');
}

// Stripe Payment
function processStripePayment(orderId) {
    fetch('api/create_stripe_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            order_id: orderId,
            amount: document.getElementById('total_amount').value
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to Stripe Checkout
            window.location.href = `https://checkout.stripe.com/pay/${data.session_id}`;
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Payment processing failed', 'error');
    });
}

// PayPal Payment
function processPaypalPayment(orderId) {
    // This would integrate with PayPal SDK
    showNotification('PayPal payment integration would go here', 'info');
}
