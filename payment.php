<?php
require_once 'config.php';
$page_title = 'Payment - ' . SITE_NAME;

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php');
}

// Check if there's a pending order
if (!isset($_SESSION['pending_order'])) {
    flash_message('error', 'No pending order found');
    redirect('cart.php');
}

$pending_order = $_SESSION['pending_order'];
$order_id = $pending_order['order_id'];
$amount = $pending_order['amount'];
$payment_method = $pending_order['payment_method'];

// Get order details
$result = mysqli_query($conn, "
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.order_id = '$order_id'
");

if (mysqli_num_rows($result) === 0) {
    flash_message('error', 'Order not found');
    redirect('cart.php');
}

$order = mysqli_fetch_assoc($result);

// Handle payment success
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $transaction_id = $_GET['transaction_id'] ?? '';
    
    // Update order status
    $stmt = mysqli_prepare($conn, "
        UPDATE orders SET 
        payment_status = 'completed', 
        order_status = 'processing',
        transaction_id = ? 
        WHERE order_id = ?
    ");
    
    mysqli_stmt_bind_param($stmt, "ss", $transaction_id, $order_id);
    mysqli_stmt_execute($stmt);
    
    // Log activity
    log_activity($order['user_id'], 'payment_completed', "Payment completed for order: $order_id");
    
    // Send order confirmation email
    $subject = "Order Confirmation - " . SITE_NAME;
    $message = "
        <h2>Order Confirmation</h2>
        <p>Thank you for your order! Your payment has been successfully processed.</p>
        <p><strong>Order ID:</strong> $order_id</p>
        <p><strong>Amount:</strong> " . format_price($amount) . "</p>
        <p><strong>Payment Method:</strong> " . ucfirst($payment_method) . "</p>
        <p>You can download your products from your account dashboard.</p>
        <p>Best regards,<br>" . SITE_NAME . " Team</p>
    ";
    
    send_email($order['email'], $subject, $message, true);
    
    // Clear pending order
    unset($_SESSION['pending_order']);
    
    // Redirect to success page
    redirect('order_success.php?order_id=' . urlencode($order_id));
}

// Handle payment cancellation
if (isset($_GET['status']) && $_GET['status'] === 'cancelled') {
    // Update order status
    $stmt = mysqli_prepare($conn, "
        UPDATE orders SET 
        payment_status = 'failed' 
        WHERE order_id = ?
    ");
    
    mysqli_stmt_bind_param($stmt, "s", $order_id);
    mysqli_stmt_execute($stmt);
    
    // Clear pending order
    unset($_SESSION['pending_order']);
    
    flash_message('error', 'Payment was cancelled. Please try again.');
    redirect('cart.php');
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="user-logged-in">
    <!-- Mobile App Bar -->
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <strong>Payment</strong>
            </div>
        </div>
    </div>

    <!-- Desktop Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm d-none d-md-block">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fas fa-store me-2"></i>S3 Digital
            </a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                            <h2>Secure Payment</h2>
                            <p class="text-muted">Complete your payment securely</p>
                        </div>

                        <!-- Order Summary -->
                        <div class="alert alert-info mb-4">
                            <h6 class="alert-heading">Order Summary</h6>
                            <div class="row">
                                <div class="col-6">
                                    <strong>Order ID:</strong><br>
                                    <strong>Amount:</strong><br>
                                    <strong>Payment Method:</strong>
                                </div>
                                <div class="col-6 text-end">
                                    <?php echo htmlspecialchars($order_id); ?><br>
                                    <?php echo format_price($amount); ?><br>
                                    <?php echo ucfirst($payment_method); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form -->
                        <div id="paymentForm">
                            <?php if ($payment_method === 'razorpay'): ?>
                                <!-- Razorpay Payment -->
                                <div class="text-center">
                                    <p>Click the button below to pay with Razorpay</p>
                                    <button id="razorpayBtn" class="btn btn-primary btn-lg">
                                        <i class="fab fa-cc-stripe me-2"></i>Pay with Razorpay
                                    </button>
                                </div>
                                
                                <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
                                <script>
                                    document.getElementById('razorpayBtn').addEventListener('click', function(e) {
                                        e.preventDefault();
                                        
                                        const options = {
                                            key: '<?php echo RAZORPAY_KEY; ?>',
                                            amount: <?php echo $amount * 100; ?>, // Amount in paise
                                            currency: 'INR',
                                            name: '<?php echo SITE_NAME; ?>',
                                            description: 'Order Payment',
                                            order_id: '<?php echo $order_id; ?>',
                                            handler: function(response) {
                                                window.location.href = 'payment.php?status=success&transaction_id=' + response.razorpay_payment_id;
                                            },
                                            modal: {
                                                ondismiss: function() {
                                                    window.location.href = 'payment.php?status=cancelled';
                                                }
                                            }
                                        };
                                        
                                        const rzp = new Razorpay(options);
                                        rzp.open();
                                    });
                                </script>
                                
                            <?php elseif ($payment_method === 'stripe'): ?>
                                <!-- Stripe Payment -->
                                <div class="text-center">
                                    <p>Click the button below to pay with Stripe</p>
                                    <button id="stripeBtn" class="btn btn-primary btn-lg">
                                        <i class="fab fa-stripe me-2"></i>Pay with Stripe
                                    </button>
                                </div>
                                
                                <script src="https://js.stripe.com/v3/"></script>
                                <script>
                                    const stripe = Stripe('<?php echo STRIPE_KEY; ?>');
                                    
                                    document.getElementById('stripeBtn').addEventListener('click', async function() {
                                        try {
                                            const response = await fetch('api/create_stripe_session.php', {
                                                method: 'POST',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                },
                                                body: JSON.stringify({
                                                    order_id: '<?php echo $order_id; ?>',
                                                    amount: <?php echo $amount; ?>
                                                })
                                            });
                                            
                                            const session = await response.json();
                                            
                                            if (session.success) {
                                                const result = await stripe.redirectToCheckout({
                                                    sessionId: session.session_id
                                                });
                                                
                                                if (result.error) {
                                                    alert(result.error.message);
                                                }
                                            } else {
                                                alert('Payment initialization failed');
                                            }
                                        } catch (error) {
                                            console.error('Error:', error);
                                            alert('Payment processing error');
                                        }
                                    });
                                </script>
                                
                            <?php elseif ($payment_method === 'paypal'): ?>
                                <!-- PayPal Payment -->
                                <div class="text-center">
                                    <p>Click the button below to pay with PayPal</p>
                                    <div id="paypal-button-container"></div>
                                </div>
                                
                                <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=USD"></script>
                                <script>
                                    paypal.Buttons({
                                        createOrder: function(data, actions) {
                                            return actions.order.create({
                                                purchase_units: [{
                                                    amount: {
                                                        value: '<?php echo number_format($amount, 2, '.', ''); ?>'
                                                    }
                                                }]
                                            });
                                        },
                                        onApprove: function(data, actions) {
                                            return actions.order.capture().then(function(details) {
                                                window.location.href = 'payment.php?status=success&transaction_id=' + details.id;
                                            });
                                        },
                                        onCancel: function(data) {
                                            window.location.href = 'payment.php?status=cancelled';
                                        }
                                    }).render('#paypal-button-container');
                                </script>
                            <?php endif; ?>
                        </div>

                        <!-- Cancel Button -->
                        <div class="text-center mt-4">
                            <a href="payment.php?status=cancelled" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancel Payment
                            </a>
                        </div>

                        <!-- Security Information -->
                        <div class="alert alert-success mt-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-shield-alt me-2"></i>Payment Security
                            </h6>
                            <p class="mb-0">
                                Your payment information is encrypted and secure. We use industry-standard security measures 
                                to protect your personal and financial data.
                            </p>
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
        // Show loading state when payment button is clicked
        document.addEventListener('DOMContentLoaded', function() {
            const paymentBtns = document.querySelectorAll('#razorpayBtn, #stripeBtn');
            
            paymentBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.innerHTML = '<span class="spinner"></span> Processing...';
                    this.disabled = true;
                });
            });
        });

        // Auto-redirect if no payment method is available
        setTimeout(function() {
            const paymentForm = document.getElementById('paymentForm');
            if (paymentForm && paymentForm.innerHTML.trim() === '') {
                window.location.href = 'cart.php';
            }
        }, 5000);
    </script>
</body>
</html>
