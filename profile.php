<?php
require_once 'config.php';
$page_title = 'Profile - ' . SITE_NAME;

if (!is_logged_in()) {
    flash_message('warning', 'Please login to view your profile');
    redirect('login.php?redirect=profile.php');
}

$user_id = (int)get_user_id();
$errors = [];

// Fetch current user
$stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, status, created_at FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = $res ? mysqli_fetch_assoc($res) : null;

if (!$user) {
    flash_message('error', 'User not found');
    redirect('logout.php');
}

// Handle updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $name, $phone, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['user_name'] = $name;
                flash_message('success', 'Profile updated successfully!');
                redirect('profile.php');
            } else {
                $errors[] = 'Failed to update profile';
            }
        }
    }

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $errors[] = 'All password fields are required';
        }

        if ($new_password !== '' && strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters';
        }

        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirm password do not match';
        }

        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$row || !verify_password($current_password, $row['password'])) {
                $errors[] = 'Current password is incorrect';
            } else {
                $new_hash = hash_password($new_password);
                $stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, remember_token = NULL, token_expiry = NULL, updated_at = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "si", $new_hash, $user_id);

                if (mysqli_stmt_execute($stmt)) {
                    flash_message('success', 'Password updated successfully! Please login again.');
                    redirect('logout.php');
                } else {
                    $errors[] = 'Failed to update password';
                }
            }
        }
    }
}

$success_msg = get_flash_message('success');
$warning_msg = get_flash_message('warning');
$error_msg = get_flash_message('error');

// Re-fetch after updates
$stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, status, created_at FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body class="user-logged-in">
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <strong>Profile</strong>
            </div>
            <button class="btn btn-link text-white p-0" onclick="toggleMobileMenu()">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </div>

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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
                    <li class="nav-item"><a class="nav-link" href="faq.php">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="darkModeToggle" title="Toggle dark mode">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                            <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div id="mobileMenu" class="mobile-menu d-md-none">
        <div class="bg-white p-3 shadow">
            <a href="products.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-box me-2"></i>Products</a>
            <a href="categories.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-tags me-2"></i>Categories</a>
            <a href="faq.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-question-circle me-2"></i>FAQ</a>
            <a href="contact.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-envelope me-2"></i>Contact</a>
            <hr>
            <a href="profile.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-user me-2"></i>Profile</a>
            <a href="orders.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-shopping-bag me-2"></i>My Orders</a>
            <a href="logout.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
        </div>
    </div>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2">My Profile</h1>
            <a href="orders.php" class="btn btn-outline-primary">
                <i class="fas fa-receipt me-2"></i>My Orders
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errors[0]); ?></div>
        <?php endif; ?>
        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_msg); ?></div>
        <?php endif; ?>
        <?php if ($warning_msg): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($warning_msg); ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold"><i class="fas fa-user me-2 text-primary"></i>Profile Details</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">

                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <div class="form-text">Email cannot be changed.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="Optional">
                            </div>

                            <div class="d-flex justify-content-between">
                                <div class="text-muted small">Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Save
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold"><i class="fas fa-lock me-2 text-primary"></i>Change Password</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">

                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" class="form-control" name="new_password" required>
                                <div class="form-text">Minimum 8 characters.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" name="confirm_password" required>
                            </div>

                            <button type="submit" class="btn btn-outline-primary w-100">
                                <i class="fas fa-key me-2"></i>Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    <div class="small">
                        For security, changing your password will log you out from this device.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
