<?php
require_once 'config.php';
$page_title = 'Products - ' . SITE_NAME;

// Get filter parameters
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$sort = isset($_GET['sort']) ? clean_input($_GET['sort']) : 'latest';
$price_min = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$price_max = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 999999;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;

// Build query
$where_conditions = ["p.status = 'active'"];
$params = [];

if ($category_id > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_id;
}

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_conditions[] = "p.price BETWEEN ? AND ?";
$params[] = $price_min;
$params[] = $price_max;

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Sort options
switch ($sort) {
    case 'price_low':
        $order_by = "p.price ASC";
        break;
    case 'price_high':
        $order_by = "p.price DESC";
        break;
    case 'name_asc':
        $order_by = "p.title ASC";
        break;
    case 'name_desc':
        $order_by = "p.title DESC";
        break;
    case 'popular':
        $order_by = "p.download_count DESC";
        break;
    default:
        $order_by = "p.created_at DESC";
}

// Get products
$query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause 
    ORDER BY $order_by
";

// Prepare and execute query
if (!empty($params)) {
    $types = str_repeat('i', count($params));
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$products = [];
while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

// Pagination
$total_products = count($products);
$total_pages = ceil($total_products / $per_page);
$offset = ($page - 1) * $per_page;
$products = array_slice($products, $offset, $per_page);

// Get categories for filter
$categories = [];
$result = mysqli_query($conn, "SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// Get price range
$result = mysqli_query($conn, "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE status = 'active'");
$price_range = mysqli_fetch_assoc($result);
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="products.php">Products</a>
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
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="darkModeToggle" title="Toggle dark mode">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
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
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h2 mb-1">Products</h1>
                <p class="text-muted mb-0">Discover our premium digital products</p>
            </div>
            <div class="d-none d-md-block">
                <span class="text-muted"><?php echo number_format($total_products); ?> products found</span>
            </div>
        </div>

        <div class="row">
            <!-- Filters Sidebar (Desktop) -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="product-filters">
                    <h5 class="mb-4">Filters</h5>
                    
                    <!-- Search -->
                    <div class="filter-section">
                        <h6>Search</h6>
                        <form method="GET" class="mb-3">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <!-- Preserve other filters -->
                            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                            <input type="hidden" name="price_min" value="<?php echo $price_min; ?>">
                            <input type="hidden" name="price_max" value="<?php echo $price_max; ?>">
                        </form>
                    </div>

                    <!-- Categories -->
                    <div class="filter-section">
                        <h6>Categories</h6>
                        <div class="list-group list-group-flush">
                            <a href="?category=0&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>" 
                               class="list-group-item list-group-item-action <?php echo $category_id == 0 ? 'active' : ''; ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $category): ?>
                                <a href="?category=<?php echo $category['id']; ?>&search=<?php echo urlencode($search); ?>&sort=<?php echo urlencode($sort); ?>" 
                                   class="list-group-item list-group-item-action <?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="filter-section">
                        <h6>Price Range</h6>
                        <form method="GET" id="priceFilter">
                            <div class="mb-3">
                                <label class="form-label">Min Price</label>
                                <input type="number" class="form-control" name="price_min" 
                                       value="<?php echo $price_min; ?>" min="0" step="0.01">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Max Price</label>
                                <input type="number" class="form-control" name="price_max" 
                                       value="<?php echo $price_max; ?>" min="0" step="0.01">
                            </div>
                            <!-- Preserve other filters -->
                            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Apply</button>
                        </form>
                    </div>

                    <!-- Sort -->
                    <div class="filter-section">
                        <h6>Sort By</h6>
                        <form method="GET">
                            <select class="form-select" name="sort" onchange="this.form.submit()">
                                <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest</option>
                                <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Name: A to Z</option>
                                <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Name: Z to A</option>
                                <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            </select>
                            <!-- Preserve other filters -->
                            <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <input type="hidden" name="price_min" value="<?php echo $price_min; ?>">
                            <input type="hidden" name="price_max" value="<?php echo $price_max; ?>">
                        </form>
                    </div>

                    <!-- Clear Filters -->
                    <div class="filter-section">
                        <a href="products.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="fas fa-times me-1"></i>Clear All Filters
                        </a>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <!-- Mobile Search and Sort -->
                <div class="d-lg-none mb-4">
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    
                    <div class="d-flex gap-2 mb-3">
                        <select class="form-select form-select-sm" name="mobile_sort" onchange="updateSort(this.value)">
                            <option value="latest" <?php echo $sort == 'latest' ? 'selected' : ''; ?>>Latest</option>
                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        </select>
                        <button class="btn btn-outline-secondary btn-sm" data-mdb-toggle="collapse" data-mdb-target="#mobileFilters">
                            <i class="fas fa-filter"></i> Filters
                        </button>
                    </div>

                    <!-- Mobile Filters -->
                    <div class="collapse" id="mobileFilters">
                        <div class="card">
                            <div class="card-body">
                                <h6>Categories</h6>
                                <div class="mb-3">
                                    <select class="form-select form-select-sm" name="mobile_category" onchange="updateCategory(this.value)">
                                        <option value="0">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products -->
                <?php if (!empty($products)): ?>
                    <div class="row g-4">
                        <?php foreach ($products as $product): ?>
                            <div class="col-12 col-sm-6 col-lg-4">
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
                                        <p class="card-text text-muted small">
                                            <?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?>
                                        </p>
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

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Product pagination" class="mt-5">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>No products found</h4>
                        <p class="text-muted">Try adjusting your filters or search terms</p>
                        <a href="products.php" class="btn btn-primary">Clear Filters</a>
                    </div>
                <?php endif; ?>
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
            <a href="products.php" class="nav-item active">
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
        // Mobile filter updates
        function updateSort(value) {
            const url = new URL(window.location);
            url.searchParams.set('sort', value);
            window.location.href = url.toString();
        }

        function updateCategory(value) {
            const url = new URL(window.location);
            url.searchParams.set('category', value);
            window.location.href = url.toString();
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-mdb-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new mdb.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
