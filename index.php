<?php
require_once 'config.php';
$page_title = 'Home - ' . SITE_NAME;

// Get featured products
$featured_products = [];
$result = mysqli_query($conn, "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active' AND p.featured = 1 
    ORDER BY p.created_at DESC 
    LIMIT 8
");

while ($row = mysqli_fetch_assoc($result)) {
    $featured_products[] = $row;
}

// Get testimonials
$testimonials = [];
$result = mysqli_query($conn, "
    SELECT * FROM testimonials 
    WHERE status = 'active' 
    ORDER BY created_at DESC 
    LIMIT 6
");

while ($row = mysqli_fetch_assoc($result)) {
    $testimonials[] = $row;
}

// Get categories
$categories = [];
$result = mysqli_query($conn, "
    SELECT * FROM categories 
    WHERE status = 'active' 
    ORDER BY name ASC
");

while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
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
<body>
    <!-- Mobile App Bar -->
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <i class="fas fa-store me-2"></i>
                <strong>S3 Digital</strong>
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="faq.php">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
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

    <!-- Mobile Menu (Hidden by default) -->
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

    <!-- Hero Section -->
    <section class="hero-section bg-gradient text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Premium Digital Products</h1>
                    <p class="lead mb-4">Discover high-quality digital products including software, templates, e-books, graphics, and courses. Instant download after purchase.</p>
                    <div class="d-flex gap-3">
                        <a href="products.php" class="btn btn-light btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>Explore Products
                        </a>
                        <a href="#featured" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-star me-2"></i>Featured Items
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-laptop-code display-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Shop by Category</h2>
            <div class="row g-4">
                <?php foreach ($categories as $category): ?>
                    <div class="col-6 col-md-3">
                        <a href="products.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                            <div class="card h-100 category-card">
                                <div class="card-body text-center">
                                    <div class="category-icon mb-3">
                                        <?php
                                        $icon = 'fa-folder';
                                        switch ($category['slug']) {
                                            case 'software': $icon = 'fa-desktop'; break;
                                            case 'templates': $icon = 'fa-file-code'; break;
                                            case 'ebooks': $icon = 'fa-book'; break;
                                            case 'graphics': $icon = 'fa-image'; break;
                                            case 'courses': $icon = 'fa-graduation-cap'; break;
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?> fa-3x text-primary"></i>
                                    </div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($category['description']); ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section id="featured" class="py-5 bg-light">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center mb-5">
                <h2>Featured Products</h2>
                <a href="products.php" class="btn btn-outline-primary">
                    View All <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
            
            <div class="row g-4">
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="card h-100 product-card">
                            <?php if ($product['featured']): ?>
                                <span class="badge bg-warning position-absolute top-0 start-0 m-2">
                                    <i class="fas fa-star me-1"></i>Featured
                                </span>
                            <?php endif; ?>
                            
                            <div class="card-img-container">
                                <img src="<?php echo !empty($product['file_name']) ? 'uploads/products/' . htmlspecialchars($product['file_name']) : 'assets/images/product-placeholder.svg'; ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($product['title']); ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="product-img-placeholder">
                                    <i class="fas fa-box"></i>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <span class="badge bg-secondary small"><?php echo htmlspecialchars($product['category_name']); ?></span>
                                <h5 class="card-title mt-2"><?php echo htmlspecialchars($product['title']); ?></h5>
                                <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="h5 text-primary mb-0"><?php echo format_price($product['price']); ?></span>
                                    <div>
                                        <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-primary" onclick="addToCart(<?php echo $product['id']; ?>)">
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
    </section>

    <!-- Testimonials -->
    <section class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">What Our Customers Say</h2>
            <div class="row g-4">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= $testimonial['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <p class="card-text">"<?php echo htmlspecialchars($testimonial['testimonial']); ?>"</p>
                                <footer class="blockquote-footer">
                                    <strong><?php echo htmlspecialchars($testimonial['user_name']); ?></strong>
                                    <?php if ($testimonial['product_name']): ?>
                                        <br><small class="text-muted">Purchased: <?php echo htmlspecialchars($testimonial['product_name']); ?></small>
                                    <?php endif; ?>
                                </footer>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Get Started?</h2>
            <p class="lead mb-4">Join thousands of satisfied customers who have purchased our premium digital products.</p>
            <a href="products.php" class="btn btn-light btn-lg">
                <i class="fas fa-rocket me-2"></i>Start Shopping Now
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-store me-2"></i>S3 Digital</h5>
                    <p>Your trusted marketplace for premium digital products.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="products.php" class="text-white-50">Products</a></li>
                        <li><a href="faq.php" class="text-white-50">FAQ</a></li>
                        <li><a href="contact.php" class="text-white-50">Contact</a></li>
                        <li><a href="terms.php" class="text-white-50">Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Connect With Us</h5>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white-50"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white-50"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> S3 Digital. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav d-md-none">
        <div class="d-flex justify-content-around align-items-center bg-white border-top">
            <a href="index.php" class="nav-item active">
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
</body>
</html>
