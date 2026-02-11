<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Manage Users - Admin Panel';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'active');

        if ($user_id <= 0) {
            $errors[] = 'Invalid user';
        }

        if (!in_array($status, ['active', 'blocked'], true)) {
            $errors[] = 'Invalid status';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $status, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                log_activity(get_admin_id(), 'user_status_updated', "User ID: $user_id status: $status");
                flash_message('success', 'User status updated successfully!');
            } else {
                flash_message('error', 'Failed to update user status');
            }

            redirect('users.php');
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $s = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $types .= 'sss';
}

if ($status_filter !== '' && in_array($status_filter, ['active', 'blocked'], true)) {
    $where[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$count_sql = "SELECT COUNT(*) as total FROM users $where_clause";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}
$total_users = (int)(mysqli_fetch_assoc($count_result)['total'] ?? 0);
$total_pages = (int)ceil($total_users / $per_page);

$list_sql = "
    SELECT u.*,
           (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) as orders_count,
           (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.payment_status = 'completed') as total_spent
    FROM users u
    $where_clause
    ORDER BY u.created_at DESC
    LIMIT $offset, $per_page
";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $list_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $list_sql);
}

$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $users[] = $row;
}

$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$user_details = null;
$user_orders = [];

if ($view_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $view_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        $user_details = mysqli_fetch_assoc($res);

        $stmt2 = mysqli_prepare($conn, "
            SELECT o.*, (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as items_count
            FROM orders o
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
            LIMIT 20
        ");
        mysqli_stmt_bind_param($stmt2, "i", $view_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        while ($r = mysqli_fetch_assoc($res2)) {
            $user_orders[] = $r;
        }
    }
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
                        <li class="nav-item"><a class="nav-link" href="categories.php"><i class="fas fa-tags me-2"></i>Categories</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
                        <li class="nav-item"><a class="nav-link active" href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
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
                    <h1 class="h2">Manage Users</h1>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
                <?php endif; ?>

                <?php $success_msg = get_flash_message('success'); ?>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <?php $error_msg = get_flash_message('error'); ?>
                <?php if ($error_msg): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, phone...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="blocked" <?php echo $status_filter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
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
                                        <th>User</th>
                                        <th>Status</th>
                                        <th>Orders</th>
                                        <th>Spent</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($u['name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($u['email']); ?></div>
                                                <?php if (!empty($u['phone'])): ?>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($u['phone']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo htmlspecialchars(ucfirst($u['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo (int)$u['orders_count']; ?></td>
                                            <td class="fw-bold text-primary"><?php echo format_price((float)$u['total_spent']); ?></td>
                                            <td class="text-muted small"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a class="btn btn-sm btn-outline-primary" href="users.php?<?php echo http_build_query(array_merge($_GET, ['view' => (int)$u['id']])); ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($u['status'] === 'active'): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="updateUserStatus(<?php echo (int)$u['id']; ?>, 'blocked', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="updateUserStatus(<?php echo (int)$u['id']; ?>, 'active', '<?php echo htmlspecialchars($u['name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (empty($users)): ?>
                            <div class="text-center text-muted py-5">No users found.</div>
                        <?php endif; ?>

                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4" aria-label="Users pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fas fa-chevron-left"></i></a></li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"><i class="fas fa-chevron-right"></i></a></li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($user_details): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">User Details: <?php echo htmlspecialchars($user_details['name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($user_details['email']); ?></div>
                            </div>
                            <a class="btn btn-sm btn-outline-secondary" href="users.php?<?php echo http_build_query(array_merge($_GET, ['view' => null])); ?>">
                                <i class="fas fa-times me-1"></i>Close
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($user_details['phone'] ?: '-'); ?></div>
                                    <div><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($user_details['status'])); ?></div>
                                    <div><strong>Email Verified:</strong> <?php echo $user_details['email_verified'] ? 'Yes' : 'No'; ?></div>
                                    <div><strong>Joined:</strong> <?php echo date('F j, Y g:i a', strtotime($user_details['created_at'])); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-info mb-0">
                                        <strong>Purchase History</strong>
                                        <div class="small text-muted">Showing latest 20 orders.</div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Total</th>
                                            <th>Payment</th>
                                            <th>Order</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($user_orders as $o): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($o['order_id']); ?></td>
                                                <td class="text-muted small"><?php echo date('M j, Y', strtotime($o['created_at'])); ?></td>
                                                <td class="fw-bold text-primary"><?php echo format_price((float)$o['total_amount']); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($o['payment_status'])); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($o['order_status'])); ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-primary" href="orders.php?view=<?php echo (int)$o['id']; ?>">
                                                        <i class="fas fa-eye me-1"></i>Order
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($user_orders)): ?>
                                            <tr><td colspan="6" class="text-center text-muted">No orders found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <form method="POST" id="statusForm" style="display:none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="user_id" id="status_user_id" value="">
        <input type="hidden" name="status" id="status_value" value="">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        function updateUserStatus(userId, status, name) {
            const msg = status === 'blocked'
                ? 'Block user "' + name + '"? They will not be able to login.'
                : 'Unblock user "' + name + '"?';

            if (!confirm(msg)) return;

            document.getElementById('status_user_id').value = userId;
            document.getElementById('status_value').value = status;
            document.getElementById('statusForm').submit();
        }
    </script>
</body>
</html>
