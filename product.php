<?php
require_once 'config.php';
$page_title = 'Product Details - ' . SITE_NAME;

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id === 0) {
    redirect('products.php');
}

// Get product details
$result = mysqli_query($conn, "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id = $product_id AND p.status = 'active'
");

if (mysqli_num_rows($result) === 0) {
    flash_message('error', 'Product not found');
    redirect('products.php');
}

$product = mysqli_fetch_assoc($result);

// Get product screenshots
$screenshots = [];
$result = mysqli_query($conn, "
    SELECT * FROM product_screenshots 
    WHERE product_id = $product_id 
    ORDER BY sort_order ASC
");

while ($row = mysqli_fetch_assoc($result)) {
    $screenshots[] = $row;
}

// Get related products
$related_products = [];
$result = mysqli_query($conn, "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.id != $product_id AND p.status = 'active' 
    AND (p.category_id = {$product['category_id']} OR p.featured = 1)
    ORDER BY p.created_at DESC 
    LIMIT 4
");

while ($row = mysqli_fetch_assoc($result)) {
    $related_products[] = $row;
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!is_logged_in()) {
        flash_message('warning', 'Please login to add products to cart');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    
    $quantity = (int)$_POST['quantity'];
    if ($quantity < 1) $quantity = 1;
    
    // Check if product already in cart
    $user_id = get_user_id();
    $result = mysqli_query($conn, "SELECT id FROM cart WHERE user_id = $user_id AND product_id = $product_id");
    
    if (mysqli_num_rows($result) > 0) {
        // Update quantity
        $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = quantity + ? WHERE user_id = ? AND product_id = ?");
        mysqli_stmt_bind_param($stmt, "iii", $quantity, $user_id, $product_id);
        mysqli_stmt_execute($stmt);
    } else {
        // Add to cart
        $stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iii", $user_id, $product_id, $quantity);
        mysqli_stmt_execute($stmt);
    }
    
    log_activity($user_id, 'add_to_cart', "Added product to cart: {$product['title']}");
    flash_message('success', 'Product added to cart successfully!');
    redirect('product.php?id=' . $product_id);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> - <?php echo SITE_NAME; ?></title>
    
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
                <strong>Product Details</strong>
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
                    <?php if (is_logged_in()): ?>
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
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php if ($cart_count = get_cart_count() > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $cart_count; ?>
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
            <?php if (is_logged_in()): ?>
                <a href="profile.php" class="d-block py-2 text-decoration-none text-dark">
                    <i class="fas fa-user me-2"></i>Profile
                </a>
                <a href="orders.php" class="d-block py-2 text-decoration-none text-dark">
                    <i class="fas fa-shopping-bag me-2"></i>My Orders
                </a>
                <a href="logout.php" class="d-block py-2 text-decoration-none text-dark">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            <?php else: ?>
                <a href="login.php" class="d-block py-2 text-decoration-none text-dark">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
                <a href="register.php" class="d-block py-2 text-decoration-none text-dark">
                    <i class="fas fa-user-plus me-2"></i>Sign Up
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container py-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Products</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['title']); ?></li>
            </ol>
        </nav>

        <!-- Product Details -->
        <div class="row">
            <div class="col-lg-6 mb-4">
                <!-- Product Images -->
                <div class="card">
                    <div class="card-body p-0">
                        <!-- Main Image -->
                        <div id="productCarousel" class="carousel slide" data-mdb-ride="carousel">
                            <div class="carousel-inner">
                                <?php if (!empty($screenshots)): ?>
                                    <?php foreach ($screenshots as $index => $screenshot): ?>
                                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                            <img src="<?php echo htmlspecialchars($screenshot['image_path']); ?>" 
                                                 class="d-block w-100" alt="Product Screenshot <?php echo $index + 1; ?>"
                                                 style="height: 400px; object-fit: cover;">
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="carousel-item active">
                                        <img src="<?php echo !empty($product['file_name']) ? 'uploads/products/' . htmlspecialchars($product['file_name']) : 'assets/images/product-placeholder.svg'; ?>" 
                                             class="d-block w-100" alt="Product Image"
                                             style="height: 400px; object-fit: cover;"
                                             onerror="this.src='assets/images/product-placeholder.svg';">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (count($screenshots) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-mdb-target="#productCarousel" data-mdb-slide="prev">
                                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-mdb-target="#productCarousel" data-mdb-slide="next">
                                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Thumbnail Gallery -->
                <?php if (count($screenshots) > 1): ?>
                    <div class="row mt-3 g-2">
                        <?php foreach ($screenshots as $index => $screenshot): ?>
                            <div class="col-3">
                                <img src="<?php echo htmlspecialchars($screenshot['image_path']); ?>" 
                                     class="img-thumbnail cursor-pointer" 
                                     alt="Thumbnail <?php echo $index + 1; ?>"
                                     onclick="showImage(<?php echo $index; ?>)"
                                     style="height: 80px; object-fit: cover; cursor: pointer;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-lg-6">
                <!-- Product Info -->
                <div class="card mb-4">
                    <div class="card-body">
                        <?php if ($product['featured']): ?>
                            <span class="badge bg-warning mb-2">
                                <i class="fas fa-star me-1"></i>Featured Product
                            </span>
                        <?php endif; ?>
                        
                        <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($product['category_name']); ?></span>
                        
                        <h1 class="h2 mb-3"><?php echo htmlspecialchars($product['title']); ?></h1>
                        
                        <div class="d-flex align-items-center mb-3">
                            <span class="h3 text-primary me-3"><?php echo format_price($product['price']); ?></span>
                            <?php if ($product['download_count'] > 0): ?>
                                <small class="text-muted">
                                    <i class="fas fa-download me-1"></i>
                                    <?php echo number_format($product['download_count']); ?> downloads
                                </small>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($product['short_description'])): ?>
                            <p class="lead text-muted"><?php echo htmlspecialchars($product['short_description']); ?></p>
                        <?php endif; ?>

                        <div class="mb-4">
                            <h5>Description</h5>
                            <div class="product-description">
                                <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                            </div>
                        </div>

                        <!-- Product Features -->
                        <div class="mb-4">
                            <h5>Product Features</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Instant Download</li>
                                <li><i class="fas fa-check text-success me-2"></i>Secure Payment</li>
                                <li><i class="fas fa-check text-success me-2"></i>Lifetime Updates</li>
                                <li><i class="fas fa-check text-success me-2"></i>24/7 Support</li>
                            </ul>
                        </div>

                        <!-- Add to Cart Form -->
                        <form method="POST" class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <label for="quantity" class="form-label">Quantity:</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="1" min="1" max="10" style="width: 80px;">
                                </div>
                                <div class="col">
                                    <button type="submit" name="add_to_cart" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Quick Actions -->
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary" onclick="addToWishlist(<?php echo $product['id']; ?>)">
                                <i class="fas fa-heart me-1"></i>Add to Wishlist
                            </button>
                            <button class="btn btn-outline-secondary" onclick="shareProduct()">
                                <i class="fas fa-share-alt me-1"></i>Share
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Product Info Cards -->
                <div class="row g-3">
                    <div class="col-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                <h6 class="card-title">Secure Payment</h6>
                                <p class="card-text small text-muted">100% secure payment processing</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="fas fa-download fa-2x text-info mb-2"></i>
                                <h6 class="card-title">Instant Download</h6>
                                <p class="card-text small text-muted">Download immediately after purchase</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="mt-5">
                <h3 class="mb-4">Related Products</h3>
                <div class="row g-4">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="card h-100 product-card">
                                <?php if ($related['featured']): ?>
                                    <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                                        <i class="fas fa-star me-1"></i>Featured
                                    </span>
                                <?php endif; ?>
                                
                                <div class="card-img-container">
                                    <img src="<?php echo !empty($related['file_name']) ? 'uploads/products/' . htmlspecialchars($related['file_name']) : 'assets/images/product-placeholder.svg'; ?>" 
                                         class="card-img-top" 
                                         alt="<?php echo htmlspecialchars($related['title']); ?>"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="product-img-placeholder">
                                        <i class="fas fa-box"></i>
                                    </div>
                                </div>
                                
                                <div class="card-body">
                                    <span class="badge bg-secondary small"><?php echo htmlspecialchars($related['category_name']); ?></span>
                                    <h6 class="card-title mt-2"><?php echo htmlspecialchars($related['title']); ?></h6>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6 text-primary mb-0"><?php echo format_price($related['price']); ?></span>
                                        <div>
                                            <a href="product.php?id=<?php echo $related['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <button class="btn btn-sm btn-primary" onclick="addToCart(<?php echo $related['id']; ?>)">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
            <a href="cart.php" class="nav-item position-relative">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
                <?php if ($cart_count = get_cart_count() > 0): ?>
                    <span class="cart-badge"><?php echo $cart_count; ?></span>
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
        // Show specific image in carousel
        function showImage(index) {
            const carousel = new mdb.Carousel(document.getElementById('productCarousel'));
            carousel.to(index);
        }

        // Add to wishlist (placeholder function)
        function addToWishlist(productId) {
            if (!isUserLoggedIn()) {
                showNotification('Please login to add products to wishlist', 'warning');
                window.location.href = 'login.php?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }
            
            // Implement wishlist functionality
            showNotification('Added to wishlist!', 'success');
        }

        // Share product
        function shareProduct() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo htmlspecialchars($product["title"]); ?>',
                    text: '<?php echo htmlspecialchars($product["short_description"] ?? "Check out this amazing product!"); ?>',
                    url: window.location.href
                });
            } else {
                // Fallback - copy to clipboard
                navigator.clipboard.writeText(window.location.href);
                showNotification('Product link copied to clipboard!', 'success');
            }
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
