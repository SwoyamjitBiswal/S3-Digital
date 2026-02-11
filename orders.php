<?php
require_once 'config.php';
$page_title = 'My Orders - ' . SITE_NAME;

// Redirect if not logged in
if (!is_logged_in()) {
    flash_message('warning', 'Please login to view your orders');
    redirect('login.php');
}

$user_id = get_user_id();

// Get orders with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$result = mysqli_query($conn, "
    SELECT o.*, 
           COUNT(oi.id) as item_count,
           SUM(oi.quantity * oi.product_price) as total_amount
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = $user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT $offset, $per_page
");

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

// Get total count for pagination
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM orders WHERE user_id = $user_id");
$total_orders = mysqli_fetch_assoc($result)['total'];
$total_pages = ceil($total_orders / $per_page);
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
                <strong>My Orders</strong>
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
                            <li><a class="dropdown-item active" href="orders.php">My Orders</a></li>
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
            <h1 class="h2">My Orders</h1>
            <a href="products.php" class="btn btn-primary">
                <i class="fas fa-shopping-bag me-2"></i>Shop More
            </a>
        </div>

        <?php if (!empty($orders)): ?>
            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0">Order ID: <?php echo htmlspecialchars($order['order_id']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <?php
                                        $status_class = 'secondary';
                                        switch ($order['payment_status']) {
                                            case 'completed': $status_class = 'success'; break;
                                            case 'pending': $status_class = 'warning'; break;
                                            case 'failed': $status_class = 'danger'; break;
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?>">
                                            <?php echo ucfirst($order['payment_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p class="mb-1">
                                            <strong>Items:</strong> <?php echo $order['item_count']; ?> products
                                        </p>
                                        <p class="mb-1">
                                            <strong>Total Amount:</strong> 
                                            <span class="text-primary"><?php echo format_price($order['total_amount']); ?></span>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?>
                                        </p>
                                        <?php if ($order['transaction_id']): ?>
                                            <p class="mb-0">
                                                <strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['transaction_id']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="btn-group-vertical" role="group">
                                            <button class="btn btn-outline-primary btn-sm mb-2" 
                                                    onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                            <?php if ($order['payment_status'] === 'completed'): ?>
                                                <button class="btn btn-primary btn-sm mb-2" 
                                                        onclick="viewDownloads(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-download me-1"></i>Downloads
                                                </button>
                                            <?php endif; ?>
                                            <button class="btn btn-outline-secondary btn-sm" 
                                                    onclick="downloadInvoice(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-file-invoice me-1"></i>Invoice
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Order pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-bag fa-4x text-muted mb-3"></i>
                <h3>No orders yet</h3>
                <p class="text-muted mb-4">You haven't placed any orders yet. Start shopping to see your orders here.</p>
                <a href="products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <!-- Downloads Modal -->
    <div class="modal fade" id="downloadsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Available Downloads</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="downloadsContent">
                    <!-- Content will be loaded via AJAX -->
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
            <a href="orders.php" class="nav-item active">
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
        // View order details
        function viewOrderDetails(orderId) {
            fetch('api/get_order_details.php?order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('orderDetailsContent').innerHTML = data.html;
                        new mdb.Modal(document.getElementById('orderDetailsModal')).show();
                    } else {
                        showNotification('Failed to load order details', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred', 'error');
                });
        }

        // View downloads
        function viewDownloads(orderId) {
            fetch('api/get_downloads.php?order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('downloadsContent').innerHTML = data.html;
                        new mdb.Modal(document.getElementById('downloadsModal')).show();
                    } else {
                        showNotification('Failed to load downloads', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('An error occurred', 'error');
                });
        }

        // Download invoice
        function downloadInvoice(orderId) {
            window.open('api/download_invoice.php?order_id=' + orderId, '_blank');
        }
    </script>
</body>
</html>
