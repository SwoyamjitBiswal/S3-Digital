<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Manage Coupons - Admin Panel';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_coupon') {
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $description = trim($_POST['description'] ?? '');
        $discount_type = trim($_POST['discount_type'] ?? 'fixed');
        $discount_value = (float)($_POST['discount_value'] ?? 0);
        $minimum_amount = (float)($_POST['minimum_amount'] ?? 0);
        $usage_limit_raw = trim($_POST['usage_limit'] ?? '');
        $usage_limit = ($usage_limit_raw === '') ? null : (int)$usage_limit_raw;
        $expiry_date_raw = trim($_POST['expiry_date'] ?? '');
        $expiry_date = ($expiry_date_raw === '') ? null : date('Y-m-d H:i:s', strtotime($expiry_date_raw));
        $status = trim($_POST['status'] ?? 'active');

        if ($code === '') {
            $errors[] = 'Coupon code is required';
        }

        if (!preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            $errors[] = 'Coupon code must be 3-50 characters and contain only letters, numbers, _ or -';
        }

        if (!in_array($discount_type, ['fixed', 'percentage'], true)) {
            $errors[] = 'Invalid discount type';
        }

        if ($discount_value <= 0) {
            $errors[] = 'Discount value must be greater than 0';
        }

        if ($discount_type === 'percentage' && $discount_value > 100) {
            $errors[] = 'Percentage discount cannot be greater than 100';
        }

        if ($minimum_amount < 0) {
            $errors[] = 'Minimum amount cannot be negative';
        }

        if ($usage_limit !== null && $usage_limit < 1) {
            $errors[] = 'Usage limit must be empty or at least 1';
        }

        if ($expiry_date_raw !== '' && strtotime($expiry_date_raw) === false) {
            $errors[] = 'Invalid expiry date';
        }

        if (!in_array($status, ['active', 'inactive'], true)) {
            $status = 'active';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "SELECT id FROM coupons WHERE code = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "s", $code);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            if ($res && mysqli_num_rows($res) > 0) {
                $errors[] = 'Coupon code already exists';
            } else {
                $stmt = mysqli_prepare($conn, "
                    INSERT INTO coupons (code, description, discount_type, discount_value, minimum_amount, usage_limit, expiry_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssddiss",
                    $code,
                    $description,
                    $discount_type,
                    $discount_value,
                    $minimum_amount,
                    $usage_limit,
                    $expiry_date,
                    $status
                );

                if (mysqli_stmt_execute($stmt)) {
                    log_activity(get_admin_id(), 'coupon_added', "Added coupon: $code");
                    flash_message('success', 'Coupon created successfully!');
                    redirect('coupons.php');
                } else {
                    $errors[] = 'Failed to create coupon';
                }
            }
        }
    }

    if ($action === 'toggle_status') {
        $coupon_id = (int)($_POST['coupon_id'] ?? 0);
        $new_status = trim($_POST['new_status'] ?? 'inactive');

        if ($coupon_id <= 0) {
            $errors[] = 'Invalid coupon';
        }

        if (!in_array($new_status, ['active', 'inactive'], true)) {
            $errors[] = 'Invalid status';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE coupons SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_status, $coupon_id);
            mysqli_stmt_execute($stmt);
            log_activity(get_admin_id(), 'coupon_status_updated', "Coupon ID: $coupon_id status: $new_status");
            flash_message('success', 'Coupon status updated');
            redirect('coupons.php');
        }
    }

    if ($action === 'delete_coupon') {
        $coupon_id = (int)($_POST['coupon_id'] ?? 0);
        if ($coupon_id > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM coupons WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $coupon_id);
            mysqli_stmt_execute($stmt);
            log_activity(get_admin_id(), 'coupon_deleted', "Deleted coupon ID: $coupon_id");
            flash_message('success', 'Coupon deleted');
        }
        redirect('coupons.php');
    }
}

// List coupons
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(code LIKE ? OR description LIKE ?)";
    $s = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
    $types .= 'ss';
}

if ($status_filter !== '' && in_array($status_filter, ['active', 'inactive'], true)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$result_sql = "SELECT * FROM coupons $where_clause ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $result_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $result_sql);
}

$coupons = [];
while ($row = mysqli_fetch_assoc($result)) {
    $coupons[] = $row;
}

$success_msg = get_flash_message('success');
$error_msg = get_flash_message('error');
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
                        <li class="nav-item"><a class="nav-link" href="categories.php"><i class="fas fa-tags me-2"></i>Categories</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li class="nav-item"><a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                        <li class="nav-item"><a class="nav-link active" href="coupons.php"><i class="fas fa-ticket-alt me-2"></i>Coupons</a></li>
                        <li class="nav-item"><a class="nav-link" href="support.php"><i class="fas fa-headset me-2"></i>Support</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Coupons</h1>
                    <button class="btn btn-primary" data-mdb-toggle="modal" data-mdb-target="#addCouponModal"><i class="fas fa-plus me-2"></i>Add Coupon</button>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
                <?php endif; ?>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Code or description...">
                            </div>
                            <div class="col-md-3">
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
                                        <th>Code</th>
                                        <th>Discount</th>
                                        <th>Min Amount</th>
                                        <th>Usage</th>
                                        <th>Expiry</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coupons as $c): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><code><?php echo htmlspecialchars($c['code']); ?></code></div>
                                                <?php if (!empty($c['description'])): ?>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($c['description']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($c['discount_type'] === 'percentage'): ?>
                                                    <span class="badge bg-info"><?php echo (float)$c['discount_value']; ?>%</span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary"><?php echo format_price((float)$c['discount_value']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo format_price((float)$c['minimum_amount']); ?></td>
                                            <td>
                                                <div class="small text-muted">Used: <?php echo (int)$c['used_count']; ?></div>
                                                <div class="small text-muted">Limit: <?php echo $c['usage_limit'] === null ? '∞' : (int)$c['usage_limit']; ?></div>
                                            </td>
                                            <td>
                                                <?php if (!empty($c['expiry_date'])): ?>
                                                    <div class="small"><?php echo date('M j, Y', strtotime($c['expiry_date'])); ?></div>
                                                <?php else: ?>
                                                    <span class="text-muted">No expiry</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $c['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($c['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <?php if ($c['status'] === 'active'): ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="toggleStatus(<?php echo (int)$c['id']; ?>, 'inactive')"><i class="fas fa-pause"></i></button>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-outline-success" onclick="toggleStatus(<?php echo (int)$c['id']; ?>, 'active')"><i class="fas fa-play"></i></button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteCoupon(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars($c['code'], ENT_QUOTES); ?>')"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (empty($coupons)): ?>
                            <div class="text-center text-muted py-5">No coupons found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="addCouponModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Coupon</h5>
                    <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_coupon">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Code *</label>
                                <input type="text" name="code" class="form-control" placeholder="SAVE10" required>
                                <div class="form-text">Letters/numbers only. Example: SAVE10</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Type *</label>
                                <select name="discount_type" class="form-select">
                                    <option value="fixed">Fixed</option>
                                    <option value="percentage">Percentage</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Value *</label>
                                <input type="number" name="discount_value" class="form-control" step="0.01" min="0" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Minimum Amount</label>
                                <input type="number" name="minimum_amount" class="form-control" step="0.01" min="0" value="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Usage Limit (optional)</label>
                                <input type="number" name="usage_limit" class="form-control" min="1" placeholder="Leave empty for unlimited">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Expiry Date (optional)</label>
                                <input type="datetime-local" name="expiry_date" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Optional description..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-mdb-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Coupon</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form method="POST" id="toggleForm" style="display:none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="coupon_id" id="toggle_coupon_id" value="">
        <input type="hidden" name="new_status" id="toggle_new_status" value="">
    </form>

    <form method="POST" id="deleteForm" style="display:none;">
        <input type="hidden" name="action" value="delete_coupon">
        <input type="hidden" name="coupon_id" id="delete_coupon_id" value="">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        function toggleStatus(id, status) {
            document.getElementById('toggle_coupon_id').value = id;
            document.getElementById('toggle_new_status').value = status;
            document.getElementById('toggleForm').submit();
        }

        function deleteCoupon(id, code) {
            if (!confirm('Delete coupon "' + code + '"?')) return;
            document.getElementById('delete_coupon_id').value = id;
            document.getElementById('deleteForm').submit();
        }
    </script>
</body>
</html>
