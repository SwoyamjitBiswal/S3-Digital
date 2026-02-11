<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Reports - Admin Panel';

$start_date = trim($_GET['start'] ?? '');
$end_date = trim($_GET['end'] ?? '');

if ($start_date === '' || strtotime($start_date) === false) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if ($end_date === '' || strtotime($end_date) === false) {
    $end_date = date('Y-m-d');
}

$start_dt = $start_date . ' 00:00:00';
$end_dt = $end_date . ' 23:59:59';

// Summary stats
$stmt = mysqli_prepare($conn, "
    SELECT
        COUNT(*) as total_orders,
        SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_orders,
        SUM(CASE WHEN payment_status = 'refunded' THEN 1 ELSE 0 END) as refunded_orders,
        COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END), 0) as revenue
    FROM orders
    WHERE created_at BETWEEN ? AND ?
");
mysqli_stmt_bind_param($stmt, "ss", $start_dt, $end_dt);
mysqli_stmt_execute($stmt);
$summary = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

// Daily sales chart
$stmt = mysqli_prepare($conn, "
    SELECT DATE(created_at) as day, COALESCE(SUM(total_amount), 0) as total
    FROM orders
    WHERE payment_status = 'completed'
      AND created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY day ASC
");
mysqli_stmt_bind_param($stmt, "ss", $start_dt, $end_dt);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$days = [];
$totals = [];
while ($row = mysqli_fetch_assoc($res)) {
    $days[] = $row['day'];
    $totals[] = (float)$row['total'];
}

// Order status breakdown (order_status)
$stmt = mysqli_prepare($conn, "
    SELECT order_status, COUNT(*) as cnt
    FROM orders
    WHERE created_at BETWEEN ? AND ?
    GROUP BY order_status
");
mysqli_stmt_bind_param($stmt, "ss", $start_dt, $end_dt);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$status_labels = [];
$status_counts = [];
while ($row = mysqli_fetch_assoc($res)) {
    $status_labels[] = $row['order_status'];
    $status_counts[] = (int)$row['cnt'];
}

// Top products
$stmt = mysqli_prepare($conn, "
    SELECT oi.product_id,
           oi.product_title,
           SUM(oi.quantity) as qty,
           COALESCE(SUM(oi.quantity * oi.product_price), 0) as amount
    FROM order_items oi
    JOIN orders o ON o.id = oi.order_id
    WHERE o.payment_status = 'completed'
      AND o.created_at BETWEEN ? AND ?
    GROUP BY oi.product_id, oi.product_title
    ORDER BY qty DESC
    LIMIT 10
");
mysqli_stmt_bind_param($stmt, "ss", $start_dt, $end_dt);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$top_products = [];
while ($row = mysqli_fetch_assoc($res)) {
    $top_products[] = $row;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
                        <li class="nav-item"><a class="nav-link" href="coupons.php"><i class="fas fa-ticket-alt me-2"></i>Coupons</a></li>
                        <li class="nav-item"><a class="nav-link" href="support.php"><i class="fas fa-headset me-2"></i>Support</a></li>
                        <li class="nav-item"><a class="nav-link active" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Reports</h1>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            <div class="col-md-4">
                                <button class="btn btn-outline-primary w-100" type="submit"><i class="fas fa-filter me-2"></i>Apply</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Revenue (Completed)</div>
                                <div class="h3 mb-0 text-primary fw-bold"><?php echo format_price((float)$summary['revenue']); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Total Orders</div>
                                <div class="h3 mb-0 fw-bold"><?php echo (int)$summary['total_orders']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Completed</div>
                                <div class="h3 mb-0 fw-bold text-success"><?php echo (int)$summary['completed_orders']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="text-muted small">Pending</div>
                                <div class="h3 mb-0 fw-bold text-warning"><?php echo (int)$summary['pending_orders']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 col-lg-8">
                        <div class="card h-100">
                            <div class="card-header bg-white fw-bold">Daily Sales (Completed Payments)</div>
                            <div class="card-body">
                                <canvas id="salesChart" height="120"></canvas>
                                <?php if (empty($days)): ?>
                                    <div class="text-center text-muted mt-3">No completed orders in this date range.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="card h-100">
                            <div class="card-header bg-white fw-bold">Order Status Breakdown</div>
                            <div class="card-body">
                                <canvas id="statusChart" height="200"></canvas>
                                <?php if (empty($status_labels)): ?>
                                    <div class="text-center text-muted mt-3">No orders in this date range.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-white fw-bold">Top Products (Completed Orders)</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_products as $p): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($p['product_title']); ?></td>
                                            <td class="text-end"><?php echo (int)$p['qty']; ?></td>
                                            <td class="text-end fw-bold text-primary"><?php echo format_price((float)$p['amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($top_products)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No product sales found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <script>
                    const days = <?php echo json_encode($days); ?>;
                    const totals = <?php echo json_encode($totals); ?>;
                    const statusLabels = <?php echo json_encode($status_labels); ?>;
                    const statusCounts = <?php echo json_encode($status_counts); ?>;

                    const salesCtx = document.getElementById('salesChart');
                    if (salesCtx) {
                        new Chart(salesCtx, {
                            type: 'line',
                            data: {
                                labels: days,
                                datasets: [{
                                    label: 'Sales',
                                    data: totals,
                                    borderColor: '#3b71ca',
                                    backgroundColor: 'rgba(59, 113, 202, 0.15)',
                                    tension: 0.3,
                                    fill: true
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: { display: false }
                                },
                                scales: {
                                    y: { beginAtZero: true }
                                }
                            }
                        });
                    }

                    const statusCtx = document.getElementById('statusChart');
                    if (statusCtx) {
                        new Chart(statusCtx, {
                            type: 'doughnut',
                            data: {
                                labels: statusLabels,
                                datasets: [{
                                    data: statusCounts,
                                    backgroundColor: ['#f0ad4e', '#3b71ca', '#14a44d', '#dc4c64', '#6c757d']
                                }]
                            },
                            options: {
                                responsive: true,
                                plugins: {
                                    legend: { position: 'bottom' }
                                }
                            }
                        });
                    }
                </script>

            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
