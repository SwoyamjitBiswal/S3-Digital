<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Support Tickets - Admin Panel';

$errors = [];

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reply') {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        $new_status = trim($_POST['status'] ?? 'in_progress');

        if ($ticket_id <= 0) {
            $errors[] = 'Invalid ticket';
        }

        if ($message === '') {
            $errors[] = 'Message is required';
        }

        if (!in_array($new_status, ['open', 'in_progress', 'closed'], true)) {
            $new_status = 'in_progress';
        }

        if (empty($errors)) {
            $admin_id = (int)get_admin_id();

            $stmt = mysqli_prepare($conn, "INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, 'admin', ?, ?)");
            mysqli_stmt_bind_param($stmt, "iis", $ticket_id, $admin_id, $message);
            $ok = mysqli_stmt_execute($stmt);

            if ($ok) {
                $stmt2 = mysqli_prepare($conn, "UPDATE support_tickets SET status = ?, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt2, "si", $new_status, $ticket_id);
                mysqli_stmt_execute($stmt2);

                log_activity($admin_id, 'ticket_replied', "Ticket ID: $ticket_id");
                flash_message('success', 'Reply sent successfully!');
                redirect('support.php?view=' . $ticket_id);
            } else {
                $errors[] = 'Failed to send reply';
            }
        }
    }

    if ($action === 'update_ticket') {
        $ticket_id = (int)($_POST['ticket_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'open');
        $priority = trim($_POST['priority'] ?? 'medium');

        if ($ticket_id <= 0) {
            $errors[] = 'Invalid ticket';
        }

        if (!in_array($status, ['open', 'in_progress', 'closed'], true)) {
            $errors[] = 'Invalid status';
        }

        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $errors[] = 'Invalid priority';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE support_tickets SET status = ?, priority = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $status, $priority, $ticket_id);

            if (mysqli_stmt_execute($stmt)) {
                log_activity((int)get_admin_id(), 'ticket_updated', "Ticket ID: $ticket_id");
                flash_message('success', 'Ticket updated successfully!');
            } else {
                flash_message('error', 'Failed to update ticket');
            }

            redirect('support.php?view=' . $ticket_id);
        }
    }
}

// Filters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$priority_filter = trim($_GET['priority'] ?? '');

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(t.ticket_id LIKE ? OR t.subject LIKE ? OR u.email LIKE ? OR u.name LIKE ?)";
    $s = '%' . $search . '%';
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $params[] = $s;
    $types .= 'ssss';
}

if ($status_filter !== '' && in_array($status_filter, ['open', 'in_progress', 'closed'], true)) {
    $where[] = "t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($priority_filter !== '' && in_array($priority_filter, ['low', 'medium', 'high'], true)) {
    $where[] = "t.priority = ?";
    $params[] = $priority_filter;
    $types .= 's';
}

$where_clause = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count
$count_sql = "SELECT COUNT(*) as total FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id $where_clause";
if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $count_result = mysqli_stmt_get_result($stmt);
} else {
    $count_result = mysqli_query($conn, $count_sql);
}
$total_tickets = (int)(mysqli_fetch_assoc($count_result)['total'] ?? 0);
$total_pages = (int)ceil($total_tickets / $per_page);

// List
$list_sql = "
    SELECT t.*, u.name as user_name, u.email as user_email,
           (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) as messages_count
    FROM support_tickets t
    LEFT JOIN users u ON u.id = t.user_id
    $where_clause
    ORDER BY t.updated_at DESC, t.created_at DESC
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

$tickets = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tickets[] = $row;
}

// View ticket
$view_id = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$ticket_details = null;
$messages = [];

if ($view_id > 0) {
    $stmt = mysqli_prepare($conn, "
        SELECT t.*, u.name as user_name, u.email as user_email, u.phone as user_phone
        FROM support_tickets t
        LEFT JOIN users u ON u.id = t.user_id
        WHERE t.id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "i", $view_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && mysqli_num_rows($res) > 0) {
        $ticket_details = mysqli_fetch_assoc($res);

        $stmt2 = mysqli_prepare($conn, "SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY created_at ASC, id ASC");
        mysqli_stmt_bind_param($stmt2, "i", $view_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);

        while ($m = mysqli_fetch_assoc($res2)) {
            $messages[] = $m;
        }
    }
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
                        <li class="nav-item"><a class="nav-link" href="coupons.php"><i class="fas fa-ticket-alt me-2"></i>Coupons</a></li>
                        <li class="nav-item"><a class="nav-link active" href="support.php"><i class="fas fa-headset me-2"></i>Support</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Support Tickets</h1>
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
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Ticket ID, subject, user...">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="">All</option>
                                    <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                                    <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="">All</option>
                                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-12 <?php echo $ticket_details ? 'col-lg-5' : 'col-lg-12'; ?>">
                        <div class="card">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <strong>Tickets</strong>
                                <span class="text-muted small"><?php echo $total_tickets; ?> total</span>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($tickets)): ?>
                                    <?php foreach ($tickets as $t): ?>
                                        <?php
                                        $sClass = 'secondary';
                                        if ($t['status'] === 'open') $sClass = 'warning';
                                        if ($t['status'] === 'in_progress') $sClass = 'primary';
                                        if ($t['status'] === 'closed') $sClass = 'success';

                                        $pClass = 'secondary';
                                        if ($t['priority'] === 'low') $pClass = 'info';
                                        if ($t['priority'] === 'medium') $pClass = 'secondary';
                                        if ($t['priority'] === 'high') $pClass = 'danger';
                                        ?>
                                        <a href="support.php?<?php echo http_build_query(array_merge($_GET, ['view' => (int)$t['id']])); ?>" class="text-decoration-none">
                                            <div class="p-3 border rounded mb-3 <?php echo $view_id === (int)$t['id'] ? 'border-primary' : ''; ?>">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($t['ticket_id']); ?></div>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($t['subject']); ?></div>
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-<?php echo $sClass; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($t['status']))); ?></span>
                                                        <div class="mt-1"><span class="badge bg-<?php echo $pClass; ?>"><?php echo htmlspecialchars(ucfirst($t['priority'])); ?></span></div>
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-2">
                                                    <div class="text-muted small">
                                                        <?php echo htmlspecialchars($t['user_email'] ?: 'Guest'); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php echo (int)$t['messages_count']; ?> msgs • <?php echo date('M j, Y', strtotime($t['updated_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>

                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Tickets pagination">
                                            <ul class="pagination justify-content-center">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>"><i class="fas fa-chevron-left"></i></a></li>
                                                <?php endif; ?>
                                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a></li>
                                                <?php endfor; ?>
                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>"><i class="fas fa-chevron-right"></i></a></li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">No tickets found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($ticket_details): ?>
                        <div class="col-12 col-lg-7">
                            <div class="card">
                                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($ticket_details['ticket_id']); ?> • <?php echo htmlspecialchars($ticket_details['subject']); ?></div>
                                        <div class="text-muted small">Created: <?php echo date('M j, Y g:i a', strtotime($ticket_details['created_at'])); ?></div>
                                    </div>
                                    <a class="btn btn-sm btn-outline-secondary" href="support.php?<?php echo http_build_query(array_merge($_GET, ['view' => null])); ?>"><i class="fas fa-times me-1"></i>Close</a>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div><strong>User:</strong> <?php echo htmlspecialchars($ticket_details['user_name'] ?: 'Guest'); ?></div>
                                            <div><strong>Email:</strong> <?php echo htmlspecialchars($ticket_details['user_email'] ?: '-'); ?></div>
                                            <div><strong>Phone:</strong> <?php echo htmlspecialchars($ticket_details['user_phone'] ?: '-'); ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <form method="POST" class="row g-2">
                                                <input type="hidden" name="action" value="update_ticket">
                                                <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket_details['id']; ?>">

                                                <div class="col-6">
                                                    <label class="form-label small">Status</label>
                                                    <select class="form-select" name="status">
                                                        <option value="open" <?php echo $ticket_details['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                                        <option value="in_progress" <?php echo $ticket_details['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                        <option value="closed" <?php echo $ticket_details['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small">Priority</label>
                                                    <select class="form-select" name="priority">
                                                        <option value="low" <?php echo $ticket_details['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                                        <option value="medium" <?php echo $ticket_details['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                                        <option value="high" <?php echo $ticket_details['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <button class="btn btn-outline-primary btn-sm w-100" type="submit"><i class="fas fa-save me-1"></i>Update</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <hr>

                                    <div style="max-height: 420px; overflow:auto;" class="mb-3">
                                        <?php foreach ($messages as $m): ?>
                                            <?php $isAdmin = $m['sender_type'] === 'admin'; ?>
                                            <div class="mb-3">
                                                <div class="d-flex <?php echo $isAdmin ? 'justify-content-end' : 'justify-content-start'; ?>">
                                                    <div class="p-3 rounded" style="max-width: 90%; background: <?php echo $isAdmin ? '#e8f0fe' : '#f5f5f5'; ?>;">
                                                        <div class="small text-muted mb-1">
                                                            <?php echo $isAdmin ? 'Admin' : 'User'; ?> • <?php echo date('M j, Y g:i a', strtotime($m['created_at'])); ?>
                                                        </div>
                                                        <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                        <?php if (empty($messages)): ?>
                                            <div class="text-center text-muted py-5">No messages yet.</div>
                                        <?php endif; ?>
                                    </div>

                                    <form method="POST">
                                        <input type="hidden" name="action" value="reply">
                                        <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket_details['id']; ?>">

                                        <div class="mb-2">
                                            <label class="form-label">Reply</label>
                                            <textarea class="form-control" name="message" rows="4" placeholder="Type your reply..." required></textarea>
                                        </div>

                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label class="form-label small">Set Status</label>
                                                <select class="form-select" name="status">
                                                    <option value="in_progress">In Progress</option>
                                                    <option value="open">Open</option>
                                                    <option value="closed">Closed</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8 d-flex align-items-end">
                                                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-paper-plane me-2"></i>Send Reply</button>
                                            </div>
                                        </div>
                                    </form>

                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
