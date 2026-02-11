<?php
require_once 'config.php';
$page_title = 'Checkout - ' . SITE_NAME;

// Redirect if not logged in
if (!is_logged_in()) {
    flash_message('warning', 'Please login to proceed with checkout');
    redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
}

$user_id = get_user_id();

// Get cart items
$cart_items = [];
$result = mysqli_query($conn, "
    SELECT c.*, p.title, p.price, p.file_name, p.file_path 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = $user_id AND p.status = 'active'
");

while ($row = mysqli_fetch_assoc($result)) {
    $cart_items[] = $row;
}

// Redirect if cart is empty
if (empty($cart_items)) {
    flash_message('warning', 'Your cart is empty');
    redirect('cart.php');
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
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

// Handle coupon
$discount_amount = 0;
$coupon_code = '';
$coupon_data = null;

if (isset($_SESSION['applied_coupon'])) {
    $coupon_data = $_SESSION['applied_coupon'];
    $discount_amount = apply_coupon($total, $coupon_data);
    $coupon_code = $coupon_data['code'];
}

$final_total = $total - $discount_amount;

// Get user information
$user_info = [];
$result = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
if ($row = mysqli_fetch_assoc($result)) {
    $user_info = $row;
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing_name = clean_input($_POST['billing_name']);
    $billing_email = clean_input($_POST['billing_email']);
    $billing_phone = clean_input($_POST['billing_phone']);
    $billing_address = clean_input($_POST['billing_address']);
    $payment_method = clean_input($_POST['payment_method']);
    $notes = clean_input($_POST['notes'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($billing_name)) $errors[] = "Billing name is required";
    if (empty($billing_email)) $errors[] = "Billing email is required";
    if (empty($billing_phone)) $errors[] = "Billing phone is required";
    if (empty($billing_address)) $errors[] = "Billing address is required";
    if (empty($payment_method)) $errors[] = "Payment method is required";
    
    if (empty($errors)) {
        // Generate order ID
        $order_id = generate_order_id();
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Insert order
            $stmt = mysqli_prepare($conn, "
                INSERT INTO orders (
                    order_id, user_id, total_amount, discount_amount, coupon_code, 
                    payment_method, billing_name, billing_email, billing_phone, 
                    billing_address, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            mysqli_stmt_bind_param($stmt, "sidddssssss", 
                $order_id, $user_id, $final_total, $discount_amount, $coupon_code,
                $payment_method, $billing_name, $billing_email, $billing_phone,
                $billing_address, $notes
            );
            
            mysqli_stmt_execute($stmt);
            $order_db_id = mysqli_insert_id($conn);
            
            // Insert order items
            foreach ($cart_items as $item) {
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO order_items (
                        order_id, product_id, product_title, product_price, quantity
                    ) VALUES (?, ?, ?, ?, ?)
                ");
                
                mysqli_stmt_bind_param($stmt, "iisdi", 
                    $order_db_id, $item['product_id'], $item['title'], 
                    $item['price'], $item['quantity']
                );
                
                mysqli_stmt_execute($stmt);
                $order_item_id = mysqli_insert_id($conn);
                
                // Create download entry
                $download_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO downloads (
                        order_item_id, user_id, max_downloads, expiry_date
                    ) VALUES (?, ?, 5, ?)
                ");
                
                mysqli_stmt_bind_param($stmt, "iis", $order_item_id, $user_id, $download_expiry);
                mysqli_stmt_execute($stmt);
            }
            
            // Update coupon usage if applied
            if ($coupon_data) {
                $stmt = mysqli_prepare($conn, "
                    UPDATE coupons SET used_count = used_count + 1 WHERE code = ?
                ");
                mysqli_stmt_bind_param($stmt, "s", $coupon_code);
                mysqli_stmt_execute($stmt);
            }
            
            // Clear cart
            $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            
            // Log activity
            log_activity($user_id, 'order_created', "New order: $order_id");
            
            // Commit transaction
            mysqli_commit($conn);
            
            // Clear coupon session
            unset($_SESSION['applied_coupon']);
            
            // Store order info for payment processing
            $_SESSION['pending_order'] = [
                'order_id' => $order_id,
                'order_db_id' => $order_db_id,
                'amount' => $final_total,
                'payment_method' => $payment_method
            ];
            
            // Redirect to payment page
            redirect('payment.php');
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = "Order processing failed. Please try again.";
        }
    }
}
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
                <strong>Checkout</strong>
            </div>
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
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Checkout Steps -->
        <div class="checkout-steps mb-5">
            <div class="step completed">
                <div class="step-circle">1</div>
                <small>Cart</small>
            </div>
            <div class="step active">
                <div class="step-circle">2</div>
                <small>Checkout</small>
            </div>
            <div class="step">
                <div class="step-circle">3</div>
                <small>Payment</small>
            </div>
            <div class="step">
                <div class="step-circle">4</div>
                <small>Complete</small>
            </div>
        </div>

        <div class="row">
            <!-- Checkout Form -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h4 class="card-title mb-4">Billing Information</h4>
                        
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="checkoutForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_name" class="form-label">
                                        <i class="fas fa-user me-1"></i>Full Name *
                                    </label>
                                    <input type="text" class="form-control" id="billing_name" name="billing_name" 
                                           value="<?php echo htmlspecialchars($_POST['billing_name'] ?? $user_info['name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_email" class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Email Address *
                                    </label>
                                    <input type="email" class="form-control" id="billing_email" name="billing_email" 
                                           value="<?php echo htmlspecialchars($_POST['billing_email'] ?? $user_info['email']); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_phone" class="form-label">
                                        <i class="fas fa-phone me-1"></i>Phone Number *
                                    </label>
                                    <input type="tel" class="form-control" id="billing_phone" name="billing_phone" 
                                           value="<?php echo htmlspecialchars($_POST['billing_phone'] ?? $user_info['phone']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_address" class="form-label">
                                        <i class="fas fa-map-marker-alt me-1"></i>Billing Address *
                                    </label>
                                    <input type="text" class="form-control" id="billing_address" name="billing_address" 
                                           placeholder="Street, City, State, ZIP Code"
                                           value="<?php echo htmlspecialchars($_POST['billing_address'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="notes" class="form-label">
                                    <i class="fas fa-sticky-note me-1"></i>Order Notes (Optional)
                                </label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" 
                                          placeholder="Any special instructions or notes for your order..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            </div>

                            <h4 class="card-title mb-4">Payment Method</h4>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="razorpay" value="razorpay" required>
                                        <label class="form-check-label" for="razorpay">
                                            <div class="card text-center">
                                                <div class="card-body">
                                                    <i class="fab fa-cc-stripe fa-2x mb-2"></i>
                                                    <h6>Razorpay</h6>
                                                    <small class="text-muted">Credit/Debit Cards, UPI, Wallets</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="stripe" value="stripe" required>
                                        <label class="form-check-label" for="stripe">
                                            <div class="card text-center">
                                                <div class="card-body">
                                                    <i class="fab fa-stripe fa-2x mb-2"></i>
                                                    <h6>Stripe</h6>
                                                    <small class="text-muted">International Cards</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check payment-option">
                                        <input class="form-check-input" type="radio" name="payment_method" 
                                               id="paypal" value="paypal" required>
                                        <label class="form-check-label" for="paypal">
                                            <div class="card text-center">
                                                <div class="card-body">
                                                    <i class="fab fa-paypal fa-2x mb-2"></i>
                                                    <h6>PayPal</h6>
                                                    <small class="text-muted">PayPal Account</small>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="cart.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Cart
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-lock me-2"></i>Proceed to Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Order Summary</h5>
                        
                        <!-- Cart Items -->
                        <div class="mb-4">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($item['title']); ?></h6>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                    <strong><?php echo format_price($item['price'] * $item['quantity']); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <hr>
                        
                        <!-- Price Breakdown -->
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Subtotal</span>
                                <strong><?php echo format_price($subtotal); ?></strong>
                            </div>
                        </div>
                        
                        <?php if ($enable_tax): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span>Tax (<?php echo $tax_rate; ?>%)</span>
                                    <strong><?php echo format_price($tax_amount); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($discount_amount > 0): ?>
                            <div class="mb-2">
                                <div class="d-flex justify-content-between text-success">
                                    <span>Discount (<?php echo htmlspecialchars($coupon_code); ?>)</span>
                                    <strong>-<?php echo format_price($discount_amount); ?></strong>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Total</h5>
                            <h5 class="text-primary"><?php echo format_price($final_total); ?></h5>
                        </div>

                        <!-- Security Info -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-shield-alt me-2"></i>Secure Checkout
                            </h6>
                            <p class="mb-2 small">
                                Your payment information is encrypted and secure. We never store your card details.
                            </p>
                            <div class="d-flex justify-content-center gap-2 mt-2">
                                <i class="fab fa-cc-visa fa-2x text-muted"></i>
                                <i class="fab fa-cc-mastercard fa-2x text-muted"></i>
                                <i class="fab fa-cc-amex fa-2x text-muted"></i>
                                <i class="fab fa-cc-paypal fa-2x text-muted"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!paymentMethod) {
                e.preventDefault();
                showNotification('Please select a payment method', 'warning');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Highlight selected payment method
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-option .card').forEach(card => {
                    card.classList.remove('border-primary', 'bg-light');
                });
                
                if (this.checked) {
                    this.closest('.payment-option').querySelector('.card').classList.add('border-primary', 'bg-light');
                }
            });
        });

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new mdb.Tooltip(tooltipTriggerEl);
            });
        });
    </script>

    <style>
        .payment-option .card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .payment-option .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .payment-option input[type="radio"] {
            display: none;
        }
        
        .payment-option input[type="radio"]:checked + label .card {
            border-color: #0066cc;
            background-color: #f8f9fa;
        }
        
        .checkout-steps {
            position: relative;
            margin-bottom: 3rem;
        }
        
        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .step.active .step-circle {
            background: #0066cc;
            color: white;
        }
        
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
    </style>
</body>
</html>
