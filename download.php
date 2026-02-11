<?php
require_once 'config.php';
$page_title = 'Download - ' . SITE_NAME;

// Redirect if not logged in
if (!is_logged_in()) {
    flash_message('warning', 'Please login to download your products');
    redirect('login.php');
}

// Get order item ID
$order_item_id = isset($_GET['item']) ? (int)$_GET['item'] : 0;

if ($order_item_id === 0) {
    flash_message('error', 'Invalid download request');
    redirect('orders.php');
}

$user_id = get_user_id();

// Verify download access
$result = mysqli_query($conn, "
    SELECT oi.*, o.order_id, o.user_id as order_user_id, o.payment_status,
           p.file_name, p.file_path, p.title as product_title,
           d.download_count, d.max_downloads, d.expiry_date
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN downloads d ON oi.id = d.order_item_id
    WHERE oi.id = $order_item_id
");

if (mysqli_num_rows($result) === 0) {
    flash_message('error', 'Download not found');
    redirect('orders.php');
}

$download_info = mysqli_fetch_assoc($result);

// Check if user owns this order
if ($download_info['order_user_id'] !== $user_id) {
    flash_message('error', 'You do not have permission to download this file');
    redirect('orders.php');
}

// Check if payment is completed
if ($download_info['payment_status'] !== 'completed') {
    flash_message('error', 'Payment not completed for this order');
    redirect('orders.php');
}

// Check download limits
if ($download_info['download_count'] >= $download_info['max_downloads']) {
    flash_message('error', 'Download limit exceeded. Please contact support.');
    redirect('orders.php');
}

// Check expiry date
if ($download_info['expiry_date'] && strtotime($download_info['expiry_date']) < time()) {
    flash_message('error', 'Download link has expired. Please contact support.');
    redirect('orders.php');
}

// Check if file exists
$file_path = $download_info['file_path'];
if (!file_exists($file_path)) {
    flash_message('error', 'File not found on server');
    redirect('orders.php');
}

// Handle download
if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    // Update download count
    if ($download_info['download_count'] === null) {
        // Create download record
        $expiry_date = date('Y-m-d H:i:s', strtotime('+24 hours'));
        $stmt = mysqli_prepare($conn, "
            INSERT INTO downloads (order_item_id, user_id, download_count, max_downloads, expiry_date)
            VALUES (?, ?, 1, 5, ?)
        ");
        mysqli_stmt_bind_param($stmt, "iis", $order_item_id, $user_id, $expiry_date);
        mysqli_stmt_execute($stmt);
    } else {
        // Update existing record
        $new_count = $download_info['download_count'] + 1;
        $stmt = mysqli_prepare($conn, "
            UPDATE downloads SET download_count = ?, last_downloaded = NOW() 
            WHERE order_item_id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $new_count, $order_item_id);
        mysqli_stmt_execute($stmt);
    }

    // Log activity
    log_activity($user_id, 'product_downloaded', "Downloaded: {$download_info['product_title']}");

    // Force file download
    $file_name = $download_info['file_name'];
    $file_size = filesize($file_path);
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . $file_size);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    readfile($file_path);
    exit;
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
                <button class="btn btn-link text-white p-0 me-2" onclick="history.back()">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <strong>Download</strong>
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
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-download fa-3x text-primary mb-3"></i>
                            <h2>Download Product</h2>
                            <p class="text-muted">Confirm your download below</p>
                        </div>

                        <!-- Product Information -->
                        <div class="alert alert-info mb-4">
                            <h6 class="alert-heading">Product Details</h6>
                            <p class="mb-1"><strong>Product:</strong> <?php echo htmlspecialchars($download_info['product_title']); ?></p>
                            <p class="mb-1"><strong>Order ID:</strong> <?php echo htmlspecialchars($download_info['order_id']); ?></p>
                            <p class="mb-1"><strong>File Name:</strong> <?php echo htmlspecialchars($download_info['file_name']); ?></p>
                            <p class="mb-0"><strong>File Size:</strong> <?php echo number_format(filesize($file_path) / 1024 / 1024, 2); ?> MB</p>
                        </div>

                        <!-- Download Status -->
                        <div class="alert alert-warning mb-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>Download Information
                            </h6>
                            <p class="mb-1">
                                <strong>Downloads Remaining:</strong> 
                                <?php echo $download_info['max_downloads'] - ($download_info['download_count'] ?? 0); ?> 
                                of <?php echo $download_info['max_downloads']; ?>
                            </p>
                            <?php if ($download_info['expiry_date']): ?>
                                <p class="mb-0">
                                    <strong>Expires:</strong> 
                                    <?php echo date('F j, Y, g:i a', strtotime($download_info['expiry_date'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Important Notes -->
                        <div class="alert alert-secondary mb-4">
                            <h6 class="alert-heading">
                                <i class="fas fa-exclamation-triangle me-2"></i>Important Notes
                            </h6>
                            <ul class="mb-0">
                                <li>Download links are valid for 24 hours from the time of purchase</li>
                                <li>You can download each product up to <?php echo $download_info['max_downloads']; ?> times</li>
                                <li>Make sure to save the file in a secure location</li>
                                <li>If you face any issues, please contact our support team</li>
                            </ul>
                        </div>

                        <!-- Download Button -->
                        <div class="text-center">
                            <a href="download.php?item=<?php echo $order_item_id; ?>&confirm=1" 
                               class="btn btn-primary btn-lg">
                                <i class="fas fa-download me-2"></i>Download Now
                            </a>
                        </div>

                        <!-- Action Buttons -->
                        <div class="text-center mt-4">
                            <a href="orders.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Orders
                            </a>
                            <a href="contact.php" class="btn btn-outline-primary">
                                <i class="fas fa-headset me-2"></i>Contact Support
                            </a>
                        </div>
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
        // Auto-redirect after 30 seconds if no action
        let redirectTimer = 30;
        const timerElement = document.createElement('div');
        timerElement.className = 'text-center text-muted small mt-3';
        timerElement.innerHTML = `This page will redirect to orders in <span id="countdown">${redirectTimer}</span> seconds...`;
        
        document.querySelector('.text-center').appendChild(timerElement);
        
        setInterval(() => {
            redirectTimer--;
            document.getElementById('countdown').textContent = redirectTimer;
            if (redirectTimer <= 0) {
                window.location.href = 'orders.php';
            }
        }, 1000);
    </script>
</body>
</html>
