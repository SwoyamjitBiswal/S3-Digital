<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Manage Orders - Admin Panel';

$errors = [];

// Update order statuses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_order') {
        $order_db_id = (int)($_POST['order_db_id'] ?? 0);
        $payment_status = trim($_POST['payment_status'] ?? 'pending');
        $order_status = trim($_POST['order_status'] ?? 'pending');
        $transaction_id = trim($_POST['transaction_id'] ?? '');

        $allowed_payment = ['pending', 'completed', 'failed', 'refunded'];
        $allowed_order = ['pending', 'processing', 'completed', 'cancelled'];

        if ($order_db_id <= 0) {
            $errors[] = 'Invalid order';
        }

        if (!in_array($payment_status, $allowed_payment, true)) {
            $errors[] = 'Invalid payment status';
        }

        if (!in_array($order_status, $allowed_order, true)) {
            $errors[] = 'Invalid order status';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE orders SET payment_status = ?, order_status = ?, transaction_id = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssi", $payment_status, $order_status, $transaction_id, $order_db_id);

            if (mysqli_stmt_execute($stmt)) {
                log_activity(get_admin_id(), 'order_updated', "Updated order ID: $order_db_id");
                flash_message('success', 'Order updated successfully!');
                redirect('orders.php?view=' . $order_db_id);
            } else {
                $errors[] = 'Failed to update order';
            }
        }
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$payment_filter = trim($_GET['payment_status'] ?? '');
$order_filter = trim($_GET['order_status'] ?? '');
$method_filter = trim($_GET['payment_method'] ?? '');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(o.order_id LIKE ? OR o.billing_email LIKE ? OR o.billing_name LIKE ? OR o.transaction_id LIKE ?)";
    $s = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $types .= 'ssss';
}

$allowed_payment = ['pending', 'completed', 'failed', 'refunded'];
if ($payment_filter !== '' && in_array($payment_filter, $allowed_payment, true)) {
    $where[] = "o.payment_status = ?";
    $params[] = $payment_filter;
    $types .= 's';
}

$allowed_order = ['pending', 'processing', 'completed', 'cancelled'];
if ($order_filter !== '' && in_array($order_filter, $allowed_order, true)) {
    $where[] = "o.order_status = ?";
    $params[] = $order_filter;
    $types .= 's';
}

$allowed_method = ['razorpay', 'stripe', 'paypal'];
if ($method_filter !== '' && in_array($method_filter, $allowed_method, true)) {
    $where[] = "o.payment_method = ?";
    $params[] = $method_filter;
    $types .= 's';
}

$where_clause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total count
$count_sql = "SELECT COUNT(*) as total FROM orders o $where_clause";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}
$total_orders = (int)(mysqli_fetch_assoc($count_result)['total'] ?? 0);
$total_pages = (int)ceil($total_orders / $per_page);

// Orders list
$list_sql = "
    SELECT o.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) as items_count
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    $where_clause
    ORDER BY o.created_at DESC
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

$orders = [];
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

// View single order
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$order_details = null;
$order_items = [];

if ($view_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT o.*, u.name as user_name, u.email as user_email FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE o.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $view_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        $order_details = mysqli_fetch_assoc($res);

        $stmt2 = mysqli_prepare($conn, "SELECT oi.*, p.file_name FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id ASC");
        mysqli_stmt_bind_param($stmt2, "i", $view_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        while ($r = mysqli_fetch_assoc($res2)) {
            $order_items[] = $r;
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
                        <li class="nav-item"><a class="nav-link active" href="orders.php"><i class="fas fa-shopping-cart me-2"></i>Orders</a></li>
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
                    <h1 class="h2">Manage Orders</h1>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
                <?php endif; ?>

                <?php $success_msg = get_flash_message('success'); ?>
                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Order ID, email, name, transaction...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Payment</label>
                                <select class="form-select" name="payment_status">
                                    <option value="">All</option>
                                    <option value="pending" <?php echo $payment_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $payment_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="failed" <?php echo $payment_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    <option value="refunded" <?php echo $payment_filter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Order</label>
                                <select class="form-select" name="order_status">
                                    <option value="">All</option>
                                    <option value="pending" <?php echo $order_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="completed" <?php echo $order_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $order_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Method</label>
                                <select class="form-select" name="payment_method">
                                    <option value="">All</option>
                                    <option value="razorpay" <?php echo $method_filter === 'razorpay' ? 'selected' : ''; ?>>Razorpay</option>
                                    <option value="stripe" <?php echo $method_filter === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                    <option value="paypal" <?php echo $method_filter === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-outline-primary w-100">Go</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3">
                    <?php foreach ($orders as $o): ?>
                        <?php
                        $pClass = 'secondary';
                        if ($o['payment_status'] === 'completed') $pClass = 'success';
                        if ($o['payment_status'] === 'pending') $pClass = 'warning';
                        if ($o['payment_status'] === 'failed') $pClass = 'danger';
                        if ($o['payment_status'] === 'refunded') $pClass = 'info';

                        $oClass = 'secondary';
                        if ($o['order_status'] === 'completed') $oClass = 'success';
                        if ($o['order_status'] === 'processing') $oClass = 'primary';
                        if ($o['order_status'] === 'pending') $oClass = 'warning';
                        if ($o['order_status'] === 'cancelled') $oClass = 'danger';
                        ?>
                        <div class="col-12 col-lg-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($o['order_id']); ?></div>
                                            <div class="text-muted small"><?php echo date('M j, Y g:i a', strtotime($o['created_at'])); ?></div>
                                        </div>
                                        <div class="text-end">
                                            <div class="badge bg-<?php echo $pClass; ?> mb-1">Payment: <?php echo htmlspecialchars(ucfirst($o['payment_status'])); ?></div><br>
                                            <div class="badge bg-<?php echo $oClass; ?>">Order: <?php echo htmlspecialchars(ucfirst($o['order_status'])); ?></div>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row">
                                        <div class="col-7">
                                            <div class="small"><strong>Customer:</strong> <?php echo htmlspecialchars($o['billing_name'] ?: ($o['user_name'] ?? '')); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($o['billing_email'] ?: ($o['user_email'] ?? '')); ?></div>
                                            <div class="small"><strong>Items:</strong> <?php echo (int)$o['items_count']; ?></div>
                                            <div class="small"><strong>Method:</strong> <?php echo htmlspecialchars(ucfirst($o['payment_method'])); ?></div>
                                        </div>
                                        <div class="col-5 text-end">
                                            <div class="fw-bold text-primary" style="font-size:1.1rem;">
                                                <?php echo format_price((float)$o['total_amount']); ?>
                                            </div>
                                            <?php if (!empty($o['transaction_id'])): ?>
                                                <div class="text-muted small">Txn: <?php echo htmlspecialchars($o['transaction_id']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-3">
                                        <a class="btn btn-sm btn-outline-primary" href="orders.php?<?php echo http_build_query(array_merge($_GET, ['view' => (int)$o['id']])); ?>">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($orders)): ?>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center text-muted py-5">No orders found.</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4" aria-label="Orders pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fas fa-chevron-left"></i></a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"><i class="fas fa-chevron-right"></i></a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

                <?php if ($order_details): ?>
                    <div class="card mt-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold">Order Details: <?php echo htmlspecialchars($order_details['order_id']); ?></div>
                                <div class="text-muted small"><?php echo date('F j, Y g:i a', strtotime($order_details['created_at'])); ?></div>
                            </div>
                            <a class="btn btn-sm btn-outline-secondary" href="orders.php?<?php echo http_build_query(array_merge($_GET, ['view' => null])); ?>">
                                <i class="fas fa-times me-1"></i>Close
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <h6 class="mb-2">Customer</h6>
                                    <div><strong>Name:</strong> <?php echo htmlspecialchars($order_details['billing_name']); ?></div>
                                    <div><strong>Email:</strong> <?php echo htmlspecialchars($order_details['billing_email']); ?></div>
                                    <div><strong>Phone:</strong> <?php echo htmlspecialchars($order_details['billing_phone']); ?></div>
                                    <div class="mt-2"><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order_details['billing_address'])); ?></div>
                                </div>
                                <div class="col-lg-6">
                                    <h6 class="mb-2">Payment</h6>
                                    <div><strong>Method:</strong> <?php echo htmlspecialchars(ucfirst($order_details['payment_method'])); ?></div>
                                    <div><strong>Transaction:</strong> <?php echo htmlspecialchars($order_details['transaction_id'] ?: '-'); ?></div>
                                    <div><strong>Total:</strong> <span class="text-primary fw-bold"><?php echo format_price((float)$order_details['total_amount']); ?></span></div>
                                    <?php if ((float)$order_details['discount_amount'] > 0): ?>
                                        <div><strong>Discount:</strong> <?php echo format_price((float)$order_details['discount_amount']); ?> (<?php echo htmlspecialchars($order_details['coupon_code'] ?: '-'); ?>)</div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <hr>

                            <h6 class="mb-2">Items</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Qty</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $it): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($it['product_title']); ?></td>
                                                <td><?php echo format_price((float)$it['product_price']); ?></td>
                                                <td><?php echo (int)$it['quantity']; ?></td>
                                                <td><?php echo format_price((float)$it['product_price'] * (int)$it['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <hr>

                            <h6 class="mb-3">Update Order</h6>
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="action" value="update_order">
                                <input type="hidden" name="order_db_id" value="<?php echo (int)$order_details['id']; ?>">

                                <div class="col-md-4">
                                    <label class="form-label">Payment Status</label>
                                    <select class="form-select" name="payment_status">
                                        <option value="pending" <?php echo $order_details['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="completed" <?php echo $order_details['payment_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="failed" <?php echo $order_details['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        <option value="refunded" <?php echo $order_details['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Order Status</label>
                                    <select class="form-select" name="order_status">
                                        <option value="pending" <?php echo $order_details['order_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo $order_details['order_status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo $order_details['order_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $order_details['order_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Transaction ID</label>
                                    <input type="text" class="form-control" name="transaction_id" value="<?php echo htmlspecialchars($order_details['transaction_id'] ?? ''); ?>">
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
