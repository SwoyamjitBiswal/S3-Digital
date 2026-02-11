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
    if (!isUserLoggedIn()) {
        showNotification('Please login to add products to cart', 'warning');
        window.location.href = 'login.php';
        return;
    }
    
    const button = event.target.closest('button');
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
            showNotification('Product added to cart successfully!', 'success');
            updateCartCount(data.cart_count);
            
            // Change button to show success
            button.innerHTML = '<i class="fas fa-check"></i>';
            button.classList.remove('btn-primary');
            button.classList.add('btn-success');
            
            setTimeout(() => {
                button.innerHTML = originalContent;
                button.classList.remove('btn-success');
                button.classList.add('btn-primary');
                button.disabled = false;
            }, 2000);
        } else {
            showNotification(data.message || 'Failed to add product to cart', 'error');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}

// Update Cart Count
function updateCartCount(count) {
    const cartBadges = document.querySelectorAll('.cart-badge');
    cartBadges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    });
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
            showNotification('Item removed from cart', 'success');
            location.reload();
        } else {
            showNotification(data.message || 'Failed to remove item', 'error');
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
            location.reload();
        } else {
            showNotification(data.message || 'Failed to update quantity', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    });
}

// Apply Coupon
function applyCoupon() {
    const couponCode = document.getElementById('couponCode').value.trim();
    
    if (!couponCode) {
        showNotification('Please enter a coupon code', 'warning');
        return;
    }
    
    const button = document.getElementById('applyCouponBtn');
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
            showNotification('Coupon applied successfully!', 'success');
            location.reload();
        } else {
            showNotification(data.message || 'Invalid coupon code', 'error');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
        button.innerHTML = originalContent;
        button.disabled = false;
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
            <i class="fas ${getNotificationIcon(type)}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Get Notification Icon
function getNotificationIcon(type) {
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    return icons[type] || icons.info;
}

// Check if User is Logged In
function isUserLoggedIn() {
    return document.body.classList.contains('user-logged-in');
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
        
        // Email validation
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
        
        // Password validation
        if (input.type === 'password' && input.value) {
            if (input.value.length < 8) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

// Password Strength Checker
function checkPasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 8) strength++;
    if (password.match(/[a-z]+/)) strength++;
    if (password.match(/[A-Z]+/)) strength++;
    if (password.match(/[0-9]+/)) strength++;
    if (password.match(/[$@#&!]+/)) strength++;
    
    return strength;
}

// Show Password Strength
function showPasswordStrength(password, inputId) {
    const strength = checkPasswordStrength(password);
    const strengthBar = document.getElementById(inputId + '_strength');
    
    if (!strengthBar) return;
    
    const strengthLevels = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const strengthColors = ['#dc3545', '#ffc107', '#fd7e14', '#20c997', '#28a745'];
    
    strengthBar.style.width = (strength * 20) + '%';
    strengthBar.style.backgroundColor = strengthColors[strength - 1] || '#e9ecef';
    
    const strengthText = document.getElementById(inputId + '_strength_text');
    if (strengthText) {
        strengthText.textContent = strengthLevels[strength - 1] || '';
        strengthText.style.color = strengthColors[strength - 1] || '#6c757d';
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
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// Search Products
function searchProducts(query) {
    if (query.length < 2) return;
    
    fetch(`api/search_products.php?q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.products);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Display Search Results
function displaySearchResults(products) {
    const searchResults = document.getElementById('searchResults');
    if (!searchResults) return;
    
    if (products.length === 0) {
        searchResults.innerHTML = '<div class="p-3 text-muted">No products found</div>';
        return;
    }
    
    let html = '';
    products.forEach(product => {
        html += `
            <div class="search-result-item p-3 border-bottom">
                <div class="d-flex">
                    <img src="assets/images/product-placeholder.jpg" alt="${product.title}" class="me-3" style="width: 50px; height: 50px; object-fit: cover;">
                    <div class="flex-grow-1">
                        <h6 class="mb-1">${product.title}</h6>
                        <small class="text-muted">${formatPrice(product.price)}</small>
                    </div>
                    <a href="product.php?id=${product.id}" class="btn btn-sm btn-outline-primary">View</a>
                </div>
            </div>
        `;
    });
    
    searchResults.innerHTML = html;
}

// Format Price
function formatPrice(price, currency = '₹') {
    return currency + parseFloat(price).toFixed(2);
}

// Initialize on DOM Load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new mdb.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new mdb.Popover(popoverTriggerEl);
    });
    
    // Auto-hide notifications on click
    document.addEventListener('click', function(e) {
        if (e.target.closest('.notification')) {
            e.target.closest('.notification').remove();
        }
    });
    
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Lazy load images
    const lazyImages = document.querySelectorAll('img[data-src]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
});

// Handle online/offline status
window.addEventListener('online', function() {
    showNotification('You are back online!', 'success');
});

window.addEventListener('offline', function() {
    showNotification('You are offline. Some features may not work.', 'warning');
});

// Debounce function for search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Search with debounce
const debouncedSearch = debounce(searchProducts, 300);

// Payment Processing
function processPayment(paymentMethod) {
    const button = document.getElementById('paymentBtn');
    const originalContent = button.innerHTML;
    
    button.innerHTML = '<span class="spinner"></span> Processing...';
    button.disabled = true;
    
    fetch('api/process_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            payment_method: paymentMethod
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                showNotification('Payment processed successfully!', 'success');
                window.location.href = 'order_success.php';
            }
        } else {
            showNotification(data.message || 'Payment failed', 'error');
            button.innerHTML = originalContent;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred during payment', 'error');
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}
