<?php
require_once 'config.php';
$page_title = 'Categories - ' . SITE_NAME;

// Get categories
$categories = [];
$result = mysqli_query($conn, "
    SELECT c.*,
           (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') as product_count
    FROM categories c
    WHERE c.status = 'active'
    ORDER BY c.name ASC
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

    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body class="<?php echo is_logged_in() ? 'user-logged-in' : ''; ?>">
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <i class="fas fa-tags me-2"></i>
                <strong>Categories</strong>
            </div>
            <button class="btn btn-link text-white p-0" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

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
                        <a class="nav-link active" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="darkModeToggle" title="Toggle dark mode">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account'); ?>
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
                            <a class="btn btn-primary btn-sm ms-2" href="register.php">Sign Up</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count badge bg-danger" style="position:absolute; top:-5px; right:-8px; font-size:0.7rem;">
                                <?php echo get_cart_count(); ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div id="mobileMenu" class="mobile-menu d-md-none">
        <div class="bg-white p-3 shadow">
            <a href="products.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-box me-2"></i>Products</a>
            <a href="categories.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-tags me-2"></i>Categories</a>
            <a href="faq.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-question-circle me-2"></i>FAQ</a>
            <a href="contact.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-envelope me-2"></i>Contact</a>
            <hr>
            <?php if (is_logged_in()): ?>
                <a href="profile.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-user me-2"></i>Profile</a>
                <a href="orders.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-shopping-bag me-2"></i>My Orders</a>
                <a href="logout.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            <?php else: ?>
                <a href="login.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
                <a href="register.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-user-plus me-2"></i>Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Categories</h1>
                <p class="text-muted mb-0">Browse products by category</p>
            </div>
            <a href="products.php" class="btn btn-outline-primary">
                <i class="fas fa-box me-2"></i>All Products
            </a>
        </div>

        <?php if (!empty($categories)): ?>
            <div class="row g-3">
                <?php foreach ($categories as $category): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <a href="products.php?category=<?php echo (int)$category['id']; ?>" class="text-decoration-none">
                            <div class="card h-100 hover-shadow">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div>
                                            <h5 class="card-title mb-1 text-dark">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </h5>
                                            <div class="text-muted small mb-2">
                                                <?php echo (int)$category['product_count']; ?> products
                                            </div>
                                        </div>
                                        <div class="text-primary">
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </div>
                                    <?php if (!empty($category['description'])): ?>
                                        <p class="card-text text-muted mb-0">
                                            <?php echo htmlspecialchars($category['description']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="card-text text-muted mb-0">Explore products in this category.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-tags fa-4x text-muted mb-3"></i>
                <h3>No categories found</h3>
                <p class="text-muted">Please add categories from admin panel.</p>
                <a href="products.php" class="btn btn-primary">Browse Products</a>
            </div>
        <?php endif; ?>
    </div>

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
            <a href="cart.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
            </a>
            <a href="profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </nav>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
