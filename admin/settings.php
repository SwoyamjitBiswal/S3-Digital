<?php
require_once '../config.php';

if (!is_admin()) {
    redirect('login.php');
}

$page_title = 'Settings - Admin Panel';

$errors = [];

function get_setting_value($key, $default = '') {
    global $conn;
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $key);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        return $row['setting_value'];
    }
    return $default;
}

function upsert_setting($key, $value, $type = 'text', $description = null) {
    global $conn;
    $stmt = mysqli_prepare($conn, "
        INSERT INTO settings (setting_key, setting_value, setting_type, description)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type), description = COALESCE(VALUES(description), description)
    ");
    mysqli_stmt_bind_param($stmt, "ssss", $key, $value, $type, $description);
    return mysqli_stmt_execute($stmt);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_name = trim($_POST['site_name'] ?? 'S3 Digital');
    $site_currency = trim($_POST['site_currency'] ?? 'INR');

    $enable_tax = isset($_POST['enable_tax']) ? 'true' : 'false';
    $tax_rate = (float)($_POST['tax_rate'] ?? 18);

    $download_expiry_hours = (int)($_POST['download_expiry_hours'] ?? 24);
    $max_downloads = (int)($_POST['max_downloads'] ?? 5);

    $email_notifications = isset($_POST['email_notifications']) ? 'true' : 'false';

    $razorpay_enabled = isset($_POST['razorpay_enabled']) ? 'true' : 'false';
    $stripe_enabled = isset($_POST['stripe_enabled']) ? 'true' : 'false';
    $paypal_enabled = isset($_POST['paypal_enabled']) ? 'true' : 'false';

    $razorpay_key = trim($_POST['razorpay_key'] ?? '');
    $razorpay_secret = trim($_POST['razorpay_secret'] ?? '');
    $stripe_key = trim($_POST['stripe_key'] ?? '');
    $stripe_secret = trim($_POST['stripe_secret'] ?? '');
    $paypal_client_id = trim($_POST['paypal_client_id'] ?? '');
    $paypal_client_secret = trim($_POST['paypal_client_secret'] ?? '');

    if ($site_name === '') {
        $errors[] = 'Site name is required';
    }

    if ($tax_rate < 0 || $tax_rate > 100) {
        $errors[] = 'Tax rate must be between 0 and 100';
    }

    if ($download_expiry_hours < 1 || $download_expiry_hours > 720) {
        $errors[] = 'Download expiry must be between 1 and 720 hours';
    }

    if ($max_downloads < 1 || $max_downloads > 50) {
        $errors[] = 'Max downloads must be between 1 and 50';
    }

    if (empty($errors)) {
        $ok = true;

        $ok = $ok && upsert_setting('site_name', $site_name, 'text', 'Website name');
        $ok = $ok && upsert_setting('site_currency', $site_currency, 'text', 'Default currency');

        $ok = $ok && upsert_setting('enable_tax', $enable_tax, 'boolean', 'Enable tax calculation');
        $ok = $ok && upsert_setting('tax_rate', (string)$tax_rate, 'number', 'Tax rate in percentage');

        $ok = $ok && upsert_setting('download_expiry_hours', (string)$download_expiry_hours, 'number', 'Download link expiry in hours');
        $ok = $ok && upsert_setting('max_downloads', (string)$max_downloads, 'number', 'Maximum downloads per purchase');

        $ok = $ok && upsert_setting('email_notifications', $email_notifications, 'boolean', 'Enable email notifications');

        $ok = $ok && upsert_setting('razorpay_enabled', $razorpay_enabled, 'boolean', 'Enable Razorpay payment');
        $ok = $ok && upsert_setting('stripe_enabled', $stripe_enabled, 'boolean', 'Enable Stripe payment');
        $ok = $ok && upsert_setting('paypal_enabled', $paypal_enabled, 'boolean', 'Enable PayPal payment');

        $ok = $ok && upsert_setting('razorpay_key', $razorpay_key, 'text', 'Razorpay public key');
        $ok = $ok && upsert_setting('razorpay_secret', $razorpay_secret, 'text', 'Razorpay secret');
        $ok = $ok && upsert_setting('stripe_key', $stripe_key, 'text', 'Stripe public key');
        $ok = $ok && upsert_setting('stripe_secret', $stripe_secret, 'text', 'Stripe secret');
        $ok = $ok && upsert_setting('paypal_client_id', $paypal_client_id, 'text', 'PayPal client id');
        $ok = $ok && upsert_setting('paypal_client_secret', $paypal_client_secret, 'text', 'PayPal client secret');

        if ($ok) {
            log_activity((int)get_admin_id(), 'settings_updated', 'Updated settings');
            flash_message('success', 'Settings updated successfully!');
            redirect('settings.php');
        } else {
            $errors[] = 'Failed to update some settings';
        }
    }
}

$values = [
    'site_name' => get_setting_value('site_name', 'S3 Digital'),
    'site_currency' => get_setting_value('site_currency', 'INR'),

    'enable_tax' => get_setting_value('enable_tax', 'true'),
    'tax_rate' => get_setting_value('tax_rate', '18'),

    'download_expiry_hours' => get_setting_value('download_expiry_hours', '24'),
    'max_downloads' => get_setting_value('max_downloads', '5'),

    'email_notifications' => get_setting_value('email_notifications', 'true'),

    'razorpay_enabled' => get_setting_value('razorpay_enabled', 'false'),
    'stripe_enabled' => get_setting_value('stripe_enabled', 'false'),
    'paypal_enabled' => get_setting_value('paypal_enabled', 'false'),

    'razorpay_key' => get_setting_value('razorpay_key', ''),
    'razorpay_secret' => get_setting_value('razorpay_secret', ''),
    'stripe_key' => get_setting_value('stripe_key', ''),
    'stripe_secret' => get_setting_value('stripe_secret', ''),
    'paypal_client_id' => get_setting_value('paypal_client_id', ''),
    'paypal_client_secret' => get_setting_value('paypal_client_secret', ''),
];

$success_msg = get_flash_message('success');
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
                        <li class="nav-item"><a class="nav-link" href="support.php"><i class="fas fa-headset me-2"></i>Support</a></li>
                        <li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                        <li class="nav-item"><a class="nav-link active" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a></li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Settings</h1>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white fw-bold"><i class="fas fa-globe me-2 text-primary"></i>General</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Site Name</label>
                                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($values['site_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Currency</label>
                                            <select class="form-select" name="site_currency">
                                                <?php
                                                $currencies = ['INR','USD','EUR','GBP','AED'];
                                                foreach ($currencies as $cur):
                                                ?>
                                                    <option value="<?php echo $cur; ?>" <?php echo $values['site_currency'] === $cur ? 'selected' : ''; ?>><?php echo $cur; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check mt-3">
                                                <input class="form-check-input" type="checkbox" name="email_notifications" id="email_notifications" <?php echo $values['email_notifications'] === 'true' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="email_notifications">Enable Email Notifications</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white fw-bold"><i class="fas fa-receipt me-2 text-primary"></i>Tax</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check mt-1">
                                                <input class="form-check-input" type="checkbox" name="enable_tax" id="enable_tax" <?php echo $values['enable_tax'] === 'true' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="enable_tax">Enable Tax</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Tax Rate (%)</label>
                                            <input type="number" class="form-control" name="tax_rate" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($values['tax_rate']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white fw-bold"><i class="fas fa-download me-2 text-primary"></i>Downloads</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Download Expiry (hours)</label>
                                            <input type="number" class="form-control" name="download_expiry_hours" min="1" max="720" value="<?php echo htmlspecialchars($values['download_expiry_hours']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Max Downloads per Purchase</label>
                                            <input type="number" class="form-control" name="max_downloads" min="1" max="50" value="<?php echo htmlspecialchars($values['max_downloads']); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-white fw-bold"><i class="fas fa-credit-card me-2 text-primary"></i>Payment Gateways</div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="razorpay_enabled" id="razorpay_enabled" <?php echo $values['razorpay_enabled'] === 'true' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="razorpay_enabled">Enable Razorpay</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="stripe_enabled" id="stripe_enabled" <?php echo $values['stripe_enabled'] === 'true' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="stripe_enabled">Enable Stripe</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="paypal_enabled" id="paypal_enabled" <?php echo $values['paypal_enabled'] === 'true' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="paypal_enabled">Enable PayPal</label>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Razorpay Key</label>
                                            <input type="text" class="form-control" name="razorpay_key" value="<?php echo htmlspecialchars($values['razorpay_key']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Razorpay Secret</label>
                                            <input type="text" class="form-control" name="razorpay_secret" value="<?php echo htmlspecialchars($values['razorpay_secret']); ?>">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Stripe Key</label>
                                            <input type="text" class="form-control" name="stripe_key" value="<?php echo htmlspecialchars($values['stripe_key']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Stripe Secret</label>
                                            <input type="text" class="form-control" name="stripe_secret" value="<?php echo htmlspecialchars($values['stripe_secret']); ?>">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">PayPal Client ID</label>
                                            <input type="text" class="form-control" name="paypal_client_id" value="<?php echo htmlspecialchars($values['paypal_client_id']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">PayPal Client Secret</label>
                                            <input type="text" class="form-control" name="paypal_client_secret" value="<?php echo htmlspecialchars($values['paypal_client_secret']); ?>">
                                        </div>

                                        <div class="col-12">
                                            <div class="alert alert-info mb-0">
                                                <div class="small">
                                                    Checkout uses these settings. For full live gateway integration, you may also need to configure server-side SDK keys.
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </div>

                    </div>
                </form>

            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>
