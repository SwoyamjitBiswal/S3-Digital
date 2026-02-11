<?php
require_once 'config.php';
$page_title = 'Order Success - ' . SITE_NAME;

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? clean_input($_GET['order_id']) : '';

if (empty($order_id)) {
    redirect('index.php');
}

// Get order details
$result = mysqli_query($conn, "
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.order_id = '$order_id' AND o.payment_status = 'completed'
");

if (mysqli_num_rows($result) === 0) {
    flash_message('error', 'Order not found or payment not completed');
    redirect('index.php');
}

$order = mysqli_fetch_assoc($result);

// Get order items
$order_items = [];
$result = mysqli_query($conn, "
    SELECT oi.*, p.file_name, p.file_path 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = {$order['id']}
");

while ($row = mysqli_fetch_assoc($result)) {
    $order_items[] = $row;
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
                <strong>Order Success</strong>
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

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <!-- Success Message -->
                <div class="text-center mb-5">
                    <div class="success-icon mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h1 class="display-4 fw-bold text-success mb-3">Order Successful!</h1>
                    <p class="lead">Thank you for your purchase. Your order has been confirmed.</p>
                </div>

                <!-- Order Details -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-receipt me-2"></i>Order Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Order ID:</strong> <?php echo htmlspecialchars($order_id); ?></p>
                                <p><strong>Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></p>
                                <p><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id'] ?? 'N/A'); ?></p>
                                <p><strong>Payment Status:</strong> 
                                    <span class="badge bg-success">Completed</span>
                                </p>
                                <p><strong>Total Amount:</strong> <strong class="text-primary"><?php echo format_price($order['total_amount']); ?></strong></p>
                            </div>
                        </div>

                        <hr>

                        <!-- Order Items -->
                        <h6 class="mb-3">Purchased Products</h6>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['product_title']); ?></strong>
                                            </td>
                                            <td><?php echo format_price($item['product_price']); ?></td>
                                            <td><?php echo $item['quantity']; ?></td>
                                            <td><strong><?php echo format_price($item['product_price'] * $item['quantity']); ?></strong></td>
                                            <td>
                                                <a href="download.php?item=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Download Instructions -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-info-circle me-2"></i>Download Instructions
                        </h6>
                        <ul class="mb-0">
                            <li>You can download your purchased products immediately using the download buttons above.</li>
                            <li>Download links are valid for 24 hours from the time of purchase.</li>
                            <li>You can download each product up to 5 times.</li>
                            <li>All your purchased products are also available in your <a href="orders.php">Order History</a>.</li>
                            <li>If you face any issues with downloads, please contact our support team.</li>
                        </ul>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-3">
                            <a href="orders.php" class="btn btn-outline-primary btn-lg w-100">
                                <i class="fas fa-list me-2"></i>View All Orders
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="products.php" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Share Success -->
                <div class="text-center mt-5">
                    <p class="text-muted mb-3">Share your purchase experience</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="#" class="btn btn-outline-primary btn-sm" onclick="shareOnSocial('facebook')">
                            <i class="fab fa-facebook me-1"></i>Facebook
                        </a>
                        <a href="#" class="btn btn-outline-primary btn-sm" onclick="shareOnSocial('twitter')">
                            <i class="fab fa-twitter me-1"></i>Twitter
                        </a>
                        <a href="#" class="btn btn-outline-primary btn-sm" onclick="shareOnSocial('linkedin')">
                            <i class="fab fa-linkedin me-1"></i>LinkedIn
                        </a>
                    </div>
                </div>
            </div>
        </div>
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
            <a href="orders.php" class="nav-item">
                <i class="fas fa-shopping-bag"></i>
                <span>Orders</span>
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
        // Share on social media
        function shareOnSocial(platform) {
            const url = encodeURIComponent(window.location.href);
            const text = encodeURIComponent('I just purchased amazing digital products from S3 Digital!');
            
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
                    break;
                case 'linkedin':
                    shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        // Confetti animation on success
        function createConfetti() {
            const colors = ['#0066cc', '#28a745', '#ffc107', '#dc3545', '#17a2b8'];
            const confettiCount = 100;
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.top = '-10px';
                confetti.style.borderRadius = '50%';
                confetti.style.zIndex = '9999';
                confetti.style.pointerEvents = 'none';
                
                document.body.appendChild(confetti);
                
                const animation = confetti.animate([
                    { transform: 'translateY(0) rotate(0deg)', opacity: 1 },
                    { transform: `translateY(${window.innerHeight}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
                ], {
                    duration: Math.random() * 3000 + 2000,
                    easing: 'cubic-bezier(0.25, 0.46, 0.45, 0.94)'
                });
                
                animation.onfinish = () => confetti.remove();
            }
        }

        // Trigger confetti on page load
        window.addEventListener('load', function() {
            setTimeout(createConfetti, 500);
        });

        // Auto-redirect after 10 seconds (optional)
        let redirectTimer = 10;
        const timerElement = document.createElement('div');
        timerElement.className = 'text-center text-muted small mt-3';
        timerElement.innerHTML = `Redirecting to products page in <span id="countdown">${redirectTimer}</span> seconds...`;
        
        // Uncomment below lines if you want auto-redirect
        // document.querySelector('.text-center').appendChild(timerElement);
        // setInterval(() => {
        //     redirectTimer--;
        //     document.getElementById('countdown').textContent = redirectTimer;
        //     if (redirectTimer <= 0) {
        //         window.location.href = 'products.php';
        //     }
        // }, 1000);
    </script>

    <style>
        .success-icon {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        
        .btn {
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
</body>
</html>
