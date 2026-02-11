<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Manage Categories - Admin Panel';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $slug = create_slug($name);

            $stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE slug = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $slug);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $slug .= '-' . time();
            }

            $stmt = mysqli_prepare($conn, "INSERT INTO categories (name, slug, description, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "ssss", $name, $slug, $description, $status);

            if (mysqli_stmt_execute($stmt)) {
                log_activity(get_admin_id(), 'category_added', "Added category: $name");
                flash_message('success', 'Category added successfully!');
                redirect('categories.php');
            } else {
                $errors[] = 'Failed to add category';
            }
        }
    }

    if ($action === 'update_category') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = trim($_POST['status'] ?? 'active');

        if ($category_id <= 0) {
            $errors[] = 'Invalid category';
        }

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $slug = create_slug($name);

            $stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE slug = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "si", $slug, $category_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result && mysqli_num_rows($result) > 0) {
                $slug .= '-' . time();
            }

            $stmt = mysqli_prepare($conn, "UPDATE categories SET name = ?, slug = ?, description = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssi", $name, $slug, $description, $status, $category_id);

            if (mysqli_stmt_execute($stmt)) {
                log_activity(get_admin_id(), 'category_updated', "Updated category ID: $category_id");
                flash_message('success', 'Category updated successfully!');
                redirect('categories.php');
            } else {
                $errors[] = 'Failed to update category';
            }
        }
    }

    if ($action === 'delete_category') {
        $category_id = (int)($_POST['category_id'] ?? 0);

        if ($category_id > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE products SET category_id = NULL WHERE category_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $category_id);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $category_id);

            if (mysqli_stmt_execute($stmt)) {
                log_activity(get_admin_id(), 'category_deleted', "Deleted category ID: $category_id");
                flash_message('success', 'Category deleted successfully!');
            } else {
                flash_message('error', 'Failed to delete category');
            }
        }

        redirect('categories.php');
    }
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(name LIKE ? OR description LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}

if ($status_filter !== '' && in_array($status_filter, ['active', 'inactive'], true)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) as product_count FROM categories c $where_clause ORDER BY c.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$categories = [];
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
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shield-alt me-2"></i>S3 Digital Admin
            </a>

            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>
                        <span class="badge bg-warning ms-1"><?php echo htmlspecialchars(ucfirst($_SESSION['admin_role'] ?? 'admin')); ?></span>
                    </a>
                    <ul class="dropdown-menu">
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
            <nav class="col-md-3 col-lg-2 d-md-block admin-sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li class="nav-item"><a class="nav-link" href="products.php"><i class="fas fa-box me-2"></i>Products</a></li>
                        <li class="nav-item"><a class="nav-link active" href="categories.php"><i class="fas fa-tags me-2"></i>Categories</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                        <li class="nav-item"><a class="nav-link" href="coupons.php"><i class="fas fa-ticket-alt me-2"></i>Coupons</a></li>
                        <li class="nav-item"><a class="nav-link" href="support.php"><i class="fas fa-headset me-2"></i>Support</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Categories</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-mdb-toggle="modal" data-mdb-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>Add Category
                        </button>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($errors[0]); ?>
                    </div>
                <?php endif; ?>

                <?php $success_msg = get_flash_message('success'); ?>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success_msg); ?>
                    </div>
                <?php endif; ?>

                <?php $error_msg = get_flash_message('error'); ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search categories...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Slug</th>
                                        <th>Products</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                                <?php if (!empty($category['description'])): ?>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($category['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                                            <td><?php echo (int)$category['product_count']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($category['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-mdb-toggle="modal" data-mdb-target="#editCategoryModal"
                                                        data-id="<?php echo (int)$category['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>"
                                                        data-description="<?php echo htmlspecialchars($category['description'] ?? '', ENT_QUOTES); ?>"
                                                        data-status="<?php echo htmlspecialchars($category['status'], ENT_QUOTES); ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?php echo (int)$category['id']; ?>, '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (empty($categories)): ?>
                            <div class="text-center text-muted py-5">No categories found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Category</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Category</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_category">
                    <input type="hidden" name="category_id" id="edit_category_id" value="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="deleteCategoryForm" style="display:none;">
        <input type="hidden" name="action" value="delete_category">
        <input type="hidden" name="category_id" id="delete_category_id" value="">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        const editModal = document.getElementById('editCategoryModal');
        if (editModal) {
            editModal.addEventListener('show.mdb.modal', function (event) {
                const button = event.relatedTarget;
                if (!button) return;

                document.getElementById('edit_category_id').value = button.getAttribute('data-id') || '';
                document.getElementById('edit_name').value = button.getAttribute('data-name') || '';
                document.getElementById('edit_description').value = button.getAttribute('data-description') || '';
                document.getElementById('edit_status').value = button.getAttribute('data-status') || 'active';
            });
        }

        function deleteCategory(id, name) {
            if (!confirm('Delete category "' + name + '"? Products in this category will be moved to no category.')) {
                return;
            }
            document.getElementById('delete_category_id').value = id;
            document.getElementById('deleteCategoryForm').submit();
        }
    </script>
</body>
</html>
