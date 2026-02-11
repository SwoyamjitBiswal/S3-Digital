<?php
require_once '../config.php';

// Check if admin is logged in
if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Manage Products - Admin Panel';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_product') {
        $title = clean_input($_POST['title']);
        $description = clean_input($_POST['description']);
        $short_description = clean_input($_POST['short_description']);
        $price = (float)$_POST['price'];
        $category_id = (int)$_POST['category_id'];
        $featured = isset($_POST['featured']) ? 1 : 0;
        $status = clean_input($_POST['status']);
        
        $errors = [];
        
        if (empty($title)) $errors[] = "Title is required";
        if (empty($description)) $errors[] = "Description is required";
        if ($price <= 0) $errors[] = "Price must be greater than 0";
        
        if (empty($errors)) {
            // Handle file upload
            $file_name = '';
            $file_path = '';
            $file_size = 0;
            
            if (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($_FILES['product_file'], PRODUCT_FILES_PATH, ['zip', 'rar', 'pdf', 'doc', 'docx']);
                if ($upload['success']) {
                    $file_name = $upload['filename'];
                    $file_path = $upload['filepath'];
                    $file_size = $_FILES['product_file']['size'];
                } else {
                    $errors[] = $upload['message'];
                }
            }
            
            if (empty($errors)) {
                $slug = create_slug($title);
                
                // Check if slug exists
                $result = mysqli_query($conn, "SELECT id FROM products WHERE slug = '$slug'");
                if (mysqli_num_rows($result) > 0) {
                    $slug .= '-' . time();
                }
                
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO products (
                        category_id, title, slug, description, short_description, 
                        price, file_name, file_path, file_size, featured, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                mysqli_stmt_bind_param($stmt, "issssdssiis", 
                    $category_id, $title, $slug, $description, $short_description,
                    $price, $file_name, $file_path, $file_size, $featured, $status
                );
                
                if (mysqli_stmt_execute($stmt)) {
                    $product_id = mysqli_insert_id($conn);
                    
                    // Handle screenshots
                    if (isset($_FILES['screenshots'])) {
                        foreach ($_FILES['screenshots']['tmp_name'] as $key => $tmp_name) {
                            if ($_FILES['screenshots']['error'][$key] === UPLOAD_ERR_OK) {
                                $screenshot_file = [
                                    'name' => $_FILES['screenshots']['name'][$key],
                                    'type' => $_FILES['screenshots']['type'][$key],
                                    'tmp_name' => $tmp_name,
                                    'error' => $_FILES['screenshots']['error'][$key],
                                    'size' => $_FILES['screenshots']['size'][$key]
                                ];
                                
                                $upload = upload_file($screenshot_file, SCREENSHOTS_PATH, ['jpg', 'jpeg', 'png', 'gif']);
                                if ($upload['success']) {
                                    $stmt = mysqli_prepare($conn, "
                                        INSERT INTO product_screenshots (product_id, image_name, image_path, sort_order)
                                        VALUES (?, ?, ?, ?)
                                    ");
                                    $sort_order = $key;
                                    mysqli_stmt_bind_param($stmt, "issi", $product_id, $upload['filename'], $upload['filepath'], $sort_order);
                                    mysqli_stmt_execute($stmt);
                                }
                            }
                        }
                    }
                    
                    log_activity(get_admin_id(), 'product_added', "Added product: $title");
                    flash_message('success', 'Product added successfully!');
                    redirect('products.php');
                } else {
                    $errors[] = "Failed to add product";
                }
            }
        }
        
        if (!empty($errors)) {
            $error_msg = implode('<br>', $errors);
            echo "<script>alert('$error_msg');</script>";
        }
    }
    
    if ($action === 'delete_product') {
        $product_id = (int)$_POST['product_id'];
        
        // Get product info for file deletion
        $result = mysqli_query($conn, "SELECT file_path FROM products WHERE id = $product_id");
        $product = mysqli_fetch_assoc($result);
        
        // Delete product
        $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $product_id);
        
        if (mysqli_stmt_execute($stmt)) {
            // Delete file if exists
            if ($product && file_exists($product['file_path'])) {
                unlink($product['file_path']);
            }
            
            log_activity(get_admin_id(), 'product_deleted', "Deleted product ID: $product_id");
            flash_message('success', 'Product deleted successfully!');
        } else {
            flash_message('error', 'Failed to delete product');
        }
        
        redirect('products.php');
    }
}

// Get products with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$query = "
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    $where_clause 
    ORDER BY p.created_at DESC 
    LIMIT $offset, $per_page
";

if (!empty($params)) {
    $types = '';
    foreach ($params as $param) {
        $types .= is_int($param) ? 'i' : 's';
    }
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

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total 
    FROM products p 
    $where_clause
";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_query);
}

$total_products = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_products / $per_page);

// Get categories
$categories = [];
$result = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");
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
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Admin Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shield-alt me-2"></i>S3 Digital Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_name']); ?>
                        <span class="badge bg-warning ms-1"><?php echo ucfirst($_SESSION['admin_role']); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block admin-sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fas fa-tags me-2"></i>Categories
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="coupons.php">
                                <i class="fas fa-ticket-alt me-2"></i>Coupons
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="support.php">
                                <i class="fas fa-headset me-2"></i>Support
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>View Website
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Products</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-mdb-toggle="modal" data-mdb-target="#addProductModal">
                            <i class="fas fa-plus me-2"></i>Add Product
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" placeholder="Search products...">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Featured</th>
                                        <th>Downloads</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($product['title']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</small>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                            <td><strong><?php echo format_price($product['price']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($product['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($product['featured']): ?>
                                                    <span class="badge bg-warning"><i class="fas fa-star"></i></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo number_format($product['download_count']); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="editProduct(<?php echo $product['id']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['title']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Products pagination" class="mt-4">
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category *</label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="price" class="form-label">Price *</label>
                                <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="short_description" class="form-label">Short Description</label>
                            <textarea class="form-control" id="short_description" name="short_description" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Full Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="product_file" class="form-label">Product File</label>
                                <input type="file" class="form-control" id="product_file" name="product_file">
                                <small class="text-muted">Allowed: ZIP, RAR, PDF, DOC, DOCX (Max: 50MB)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="screenshots" class="form-label">Screenshots</label>
                                <input type="file" class="form-control" id="screenshots" name="screenshots[]" multiple>
                                <small class="text-muted">Allowed: JPG, PNG, GIF (Max: 5MB each)</small>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="featured" name="featured">
                            <label class="form-check-label" for="featured">
                                Featured Product
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Delete product
        function deleteProduct(productId, productTitle) {
            if (confirm(`Are you sure you want to delete "${productTitle}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="product_id" value="${productId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Edit product (placeholder)
        function editProduct(productId) {
            // This would open an edit modal or redirect to edit page
            alert('Edit functionality would be implemented here');
        }
    </script>
</body>
</html>
