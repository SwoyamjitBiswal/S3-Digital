<?php
require_once '../config.php';

// Check if admin is logged in
if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Dashboard - Admin Panel';

// Get dashboard statistics
$stats = [];

// Total users
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users");
$stats['total_users'] = mysqli_fetch_assoc($result)['count'];

// Active users
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE status = 'active'");
$stats['active_users'] = mysqli_fetch_assoc($result)['count'];

// Total products
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products");
$stats['total_products'] = mysqli_fetch_assoc($result)['count'];

// Active products
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM products WHERE status = 'active'");
$stats['active_products'] = mysqli_fetch_assoc($result)['count'];

// Total orders
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders");
$stats['total_orders'] = mysqli_fetch_assoc($result)['count'];

// Completed orders
$result = mysqli_query($conn, "SELECT COUNT(*) as count FROM orders WHERE payment_status = 'completed'");
$stats['completed_orders'] = mysqli_fetch_assoc($result)['count'];

// Total revenue
$result = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'completed'");
$stats['total_revenue'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Today's revenue
$result = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'completed' AND DATE(created_at) = CURDATE()");
$stats['today_revenue'] = mysqli_fetch_assoc($result)['total'] ?? 0;

// Recent orders
$recent_orders = [];
$result = mysqli_query($conn, "
    SELECT o.*, u.name as customer_name, u.email as customer_email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($result)) {
    $recent_orders[] = $row;
}

// Top selling products
$top_products = [];
$result = mysqli_query($conn, "
    SELECT p.title, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.product_price) as revenue
    FROM products p
    JOIN order_items oi ON p.id = oi.product_id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'completed'
    GROUP BY p.id, p.title
    ORDER BY total_sold DESC
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($result)) {
    $top_products[] = $row;
}

// Recent registrations
$recent_users = [];
$result = mysqli_query($conn, "
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

while ($row = mysqli_fetch_assoc($result)) {
    $recent_users[] = $row;
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
    <link rel="stylesheet" href="../assets/css/style-merged.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <!-- Admin Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shield-alt me-2"></i>S3 Digital Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item">
                    <button class="btn btn-link nav-link text-white" id="darkModeToggle" title="Toggle dark mode">
                        <i class="fas fa-moon" id="darkModeIcon"></i>
                    </button>
                </div>
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
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card primary">
                            <div class="stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="fw-bold"><?php echo number_format($stats['total_users']); ?></h3>
                            <p class="text-muted mb-0">Total Users</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>
                                <?php echo number_format($stats['active_users']); ?> active
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card success">
                            <div class="stats-icon">
                                <i class="fas fa-box"></i>
                            </div>
                            <h3 class="fw-bold"><?php echo number_format($stats['total_products']); ?></h3>
                            <p class="text-muted mb-0">Total Products</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>
                                <?php echo number_format($stats['active_products']); ?> active
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card warning">
                            <div class="stats-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h3 class="fw-bold"><?php echo number_format($stats['total_orders']); ?></h3>
                            <p class="text-muted mb-0">Total Orders</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>
                                <?php echo number_format($stats['completed_orders']); ?> completed
                            </small>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="stats-card danger">
                            <div class="stats-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <h3 class="fw-bold"><?php echo format_price($stats['total_revenue']); ?></h3>
                            <p class="text-muted mb-0">Total Revenue</p>
                            <small class="text-success">
                                <i class="fas fa-arrow-up me-1"></i>
                                <?php echo format_price($stats['today_revenue']); ?> today
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Sales Overview</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="salesChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Order Status</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="orderStatusChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Orders</h5>
                                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                                <tr>
                                                    <td><small><?php echo htmlspecialchars($order['order_id']); ?></small></td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars($order['customer_name']); ?></small>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                                    </td>
                                                    <td><strong><?php echo format_price($order['total_amount']); ?></strong></td>
                                                    <td>
                                                        <?php
                                                        $status_class = 'secondary';
                                                        switch ($order['payment_status']) {
                                                            case 'completed': $status_class = 'success'; break;
                                                            case 'pending': $status_class = 'warning'; break;
                                                            case 'failed': $status_class = 'danger'; break;
                                                        }
                                                        ?>
                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                            <?php echo ucfirst($order['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Top Selling Products</h5>
                                <a href="products.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <small><?php echo htmlspecialchars(substr($product['title'], 0, 30)); ?>...</small>
                                                    </td>
                                                    <td><strong><?php echo number_format($product['total_sold']); ?></strong></td>
                                                    <td><strong><?php echo format_price($product['revenue']); ?></strong></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Revenue',
                    data: [12000, 19000, 15000, 25000, 22000, 30000, 28000],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Orders',
                    data: [120, 190, 150, 250, 220, 300, 280],
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                }
            }
        });

        // Order Status Chart
        const orderCtx = document.getElementById('orderStatusChart').getContext('2d');
        const orderChart = new Chart(orderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Failed'],
                datasets: [{
                    data: [<?php echo $stats['completed_orders']; ?>, 25, 10],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });

        // Auto-refresh dashboard every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
    <script src="../assets/js/dark-mode.js"></script>
</body>
</html>
