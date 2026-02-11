<?php
require_once 'config.php';
$page_title = 'Shopping Cart - ' . SITE_NAME;

// Redirect if not logged in
if (!is_logged_in()) {
    flash_message('warning', 'Please login to view your cart');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = get_user_id();

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_cart'])) {
        foreach ($_POST['quantities'] as $product_id => $quantity) {
            $quantity = (int)$quantity;
            if ($quantity > 0) {
                $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                mysqli_stmt_bind_param($stmt, "iii", $quantity, $user_id, $product_id);
                mysqli_stmt_execute($stmt);
            } else {
                // Remove item if quantity is 0
                $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
                mysqli_stmt_execute($stmt);
            }
        }
        flash_message('success', 'Cart updated successfully!');
        redirect('cart.php');
    }
    
    if (isset($_POST['remove_item'])) {
        $product_id = (int)$_POST['product_id'];
        $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
        mysqli_stmt_execute($stmt);
        flash_message('success', 'Item removed from cart');
        redirect('cart.php');
    }
}

// Get cart items
$cart_items = [];
$result = mysqli_query($conn, "
    SELECT c.*, p.title, p.price, p.status as product_status 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = $user_id
    ORDER BY c.created_at DESC
");

while ($row = mysqli_fetch_assoc($result)) {
    $cart_items[] = $row;
}

// Calculate totals
$subtotal = 0;
$total_items = 0;

foreach ($cart_items as $item) {
    if ($item['product_status'] === 'active') {
        $subtotal += $item['price'] * $item['quantity'];
        $total_items += $item['quantity'];
    }
}

// Get tax settings
$tax_rate = 18; // Default tax rate
$enable_tax = true;

$result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'tax_rate'");
if ($row = mysqli_fetch_assoc($result)) {
    $tax_rate = (float)$row['setting_value'];
}

$result = mysqli_query($conn, "SELECT setting_value FROM settings WHERE setting_key = 'enable_tax'");
if ($row = mysqli_fetch_assoc($result)) {
    $enable_tax = $row['setting_value'] === 'true';
}

$tax_amount = $enable_tax ? ($subtotal * $tax_rate / 100) : 0;
$total = $subtotal + $tax_amount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body class="user-logged-in">
    <!-- Mobile App Bar -->
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <button class="btn btn-link text-white p-0 me-2" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <strong>Shopping Cart</strong>
            </div>
            <button class="btn btn-link text-white p-0" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

    <!-- Desktop Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm d-none d-md-block">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fas fa-store me-2"></i>S3 Digital
            </a>
            
            <button class="navbar-toggler" type="button" data-mdb-toggle="collapse" data-mdb-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="products.php">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($total_items > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $total_items; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div id="mobileMenu" class="mobile-menu d-md-none">
        <div class="bg-white p-3 shadow">
            <a href="products.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-box me-2"></i>Products
            </a>
            <a href="categories.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-tags me-2"></i>Categories
            </a>
            <a href="faq.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-question-circle me-2"></i>FAQ
            </a>
            <a href="contact.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-envelope me-2"></i>Contact
            </a>
            <hr>
            <a href="profile.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-user me-2"></i>Profile
            </a>
            <a href="orders.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-shopping-bag me-2"></i>My Orders
            </a>
            <a href="logout.php" class="d-block py-2 text-decoration-none text-dark">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </div>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">Shopping Cart</h1>
            <a href="products.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
            </a>
        </div>

        <?php if (!empty($cart_items)): ?>
            <div class="row">
                <!-- Cart Items -->
                <div class="col-lg-8">
                    <form method="POST" id="cartForm">
                        <div class="card">
                            <div class="card-body">
                                <?php foreach ($cart_items as $item): ?>
                                    <div class="cart-item border-bottom pb-3 mb-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <img src="<?php echo !empty($item['file_name']) ? 'uploads/products/' . htmlspecialchars($item['file_name']) : 'assets/images/product-placeholder.svg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                     class="img-fluid rounded"
                                                     onerror="this.src='assets/images/product-placeholder.svg';">
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                <?php if ($item['product_status'] !== 'active'): ?>
                                                    <span class="badge bg-danger">Out of Stock</span>
                                                <?php endif; ?>
                                                <p class="text-muted mb-0 small">
                                                    <?php echo format_price($item['price']); ?> each
                                                </p>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            onclick="updateQuantity(<?php echo $item['product_id']; ?>, <?php echo $item['quantity'] - 1; ?>)">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number" class="form-control text-center" 
                                                           name="quantities[<?php echo $item['product_id']; ?>]" 
                                                           value="<?php echo $item['quantity']; ?>" 
                                                           min="1" max="10" readonly>
                                                    <button type="button" class="btn btn-outline-secondary" 
                                                            onclick="updateQuantity(<?php echo $item['product_id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <strong><?php echo format_price($item['price'] * $item['quantity']); ?></strong>
                                            </div>
                                            <div class="col-md-1 text-end">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                    <button type="submit" name="remove_item" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Remove this item from cart?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-3">
                            <button type="submit" name="update_cart" class="btn btn-outline-primary">
                                <i class="fas fa-sync-alt me-2"></i>Update Cart
                            </button>
                            <a href="products.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Order Summary</h5>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal (<?php echo $total_items; ?> items)</span>
                                <strong><?php echo format_price($subtotal); ?></strong>
                            </div>
                            
                            <?php if ($enable_tax): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Tax (<?php echo $tax_rate; ?>%)</span>
                                    <strong><?php echo format_price($tax_amount); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Total</h5>
                                <h5 class="text-primary"><?php echo format_price($total); ?></h5>
                            </div>

                            <!-- Coupon Code -->
                            <div class="mb-3">
                                <label for="couponCode" class="form-label">Coupon Code</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="couponCode" placeholder="Enter coupon code">
                                    <button class="btn btn-outline-secondary" type="button" onclick="applyCoupon()">
                                        Apply
                                    </button>
                                </div>
                            </div>

                            <a href="checkout.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </a>

                            <!-- Security Badges -->
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>Secure Checkout
                                </small>
                                <div class="mt-2">
                                    <i class="fab fa-cc-visa fa-2x text-muted me-2"></i>
                                    <i class="fab fa-cc-mastercard fa-2x text-muted me-2"></i>
                                    <i class="fab fa-cc-amex fa-2x text-muted me-2"></i>
                                    <i class="fab fa-cc-paypal fa-2x text-muted"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                <h3>Your cart is empty</h3>
                <p class="text-muted mb-4">Looks like you haven't added any products to your cart yet.</p>
                <a href="products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav d-md-none">
        <div class="d-flex justify-content-around align-items-center bg-white border-top">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="products.php" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="cart.php" class="nav-item active position-relative">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
                <?php if ($total_items > 0): ?>
                    <span class="cart-badge"><?php echo $total_items; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </nav>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        // Update quantity and submit form
        function updateQuantity(productId, newQuantity) {
            if (newQuantity < 1) newQuantity = 1;
            if (newQuantity > 10) newQuantity = 10;
            
            const input = document.querySelector(`input[name="quantities[${productId}]"]`);
            if (input) {
                input.value = newQuantity;
                document.getElementById('cartForm').submit();
            }
        }

        // Handle coupon application
        function applyCoupon() {
            const couponCode = document.getElementById('couponCode').value.trim();
            
            if (!couponCode) {
                showNotification('Please enter a coupon code', 'warning');
                return;
            }
            
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
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new mdb.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
