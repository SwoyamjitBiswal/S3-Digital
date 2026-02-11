<?php
require_once 'config.php';
$page_title = 'Login - ' . SITE_NAME;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    
    $errors = [];
    
    // Validation
    if (empty($email)) {
        $errors[] = "Email is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Check user credentials
        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ? AND status = 'active'");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            if (verify_password($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                
                // Set remember me cookie
                if ($remember) {
                    $token = generate_token();
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                    
                    setcookie('remember_token', $token, $expiry, '/', '', false, true);
                    
                    // Store token in database
                    $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = ?, token_expiry = ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt, "ssi", $token, date('Y-m-d H:i:s', $expiry), $user['id']);
                    mysqli_stmt_execute($stmt);
                }
                
                // Log activity
                log_activity($user['id'], 'user_login', "User login: $email");
                
                // Redirect to intended page or dashboard
                $redirect = $_GET['redirect'] ?? 'index.php';
                flash_message('success', 'Welcome back! You have been logged in successfully.');
                redirect($redirect);
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
        }
    }
}

// Handle remember me login
if (!is_logged_in() && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE remember_token = ? AND token_expiry > NOW() AND status = 'active'");
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        
        log_activity($user['id'], 'user_login_remember', "Auto login via remember token: $user[email]");
        redirect('index.php');
    }
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
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body class="bg-light">
    <!-- Mobile App Bar -->
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <i class="fas fa-store me-2"></i>
                <strong>S3 Digital</strong>
            </div>
            <a href="index.php" class="btn btn-link text-white p-0">
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>
    </div>

    <!-- Desktop Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm d-none d-md-block">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="index.php">
                <i class="fas fa-store me-2"></i>S3 Digital
            </a>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-sign-in-alt fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Welcome Back</h2>
                            <p class="text-muted">Login to access your account and continue shopping</p>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php
                        $success_msg = get_flash_message('success');
                        if ($success_msg):
                        ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo htmlspecialchars($success_msg); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="loginForm" onsubmit="return validateForm('loginForm')">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       autocomplete="email">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                           autocomplete="current-password">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password_toggle"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please enter your password</div>
                            </div>

                            <div class="mb-3 d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                <a href="forgot-password.php" class="text-primary text-decoration-none">
                                    Forgot Password?
                                </a>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login
                            </button>

                            <div class="text-center">
                                <p class="mb-0">Don't have an account? 
                                    <a href="register.php" class="text-primary text-decoration-none">
                                        <strong>Sign up here</strong>
                                    </a>
                                </p>
                            </div>
                        </form>

                        <hr class="my-4">

                        <!-- Social Login (Optional) -->
                        <div class="text-center">
                            <p class="text-muted mb-3">Or continue with</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-outline-primary">
                                    <i class="fab fa-google me-2"></i>Google
                                </button>
                                <button type="button" class="btn btn-outline-primary">
                                    <i class="fab fa-facebook me-2"></i>Facebook
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title mb-3">Quick Links</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="products.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-box me-1"></i>Browse Products
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="faq.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-question-circle me-1"></i>FAQ
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="contact.php" class="btn btn-outline-primary btn-sm w-100">
                                    <i class="fas fa-envelope me-1"></i>Contact Us
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="admin/login.php" class="btn btn-outline-secondary btn-sm w-100">
                                    <i class="fas fa-cog me-1"></i>Admin
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation (Mobile) -->
    <nav class="bottom-nav d-md-none">
        <div class="d-flex justify-content-around align-items-center bg-white border-top">
            <a href="index.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="products.php" class="nav-item">
                <i class="fas fa-box"></i>
                <span>Products</span>
            </a>
            <a href="cart.php" class="nav-item">
                <i class="fas fa-shopping-cart"></i>
                <span>Cart</span>
            </a>
            <a href="register.php" class="nav-item">
                <i class="fas fa-user-plus"></i>
                <span>Sign Up</span>
            </a>
        </div>
    </nav>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    
    <script>
        // Toggle Password Visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = document.getElementById(inputId + '_toggle');
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.classList.remove('fa-eye');
                toggle.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggle.classList.remove('fa-eye-slash');
                toggle.classList.add('fa-eye');
            }
        }

        // Focus on email field if coming from register
        window.addEventListener('load', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('registered') === 'true') {
                document.getElementById('email').focus();
            }
        });

        // Handle form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span class="spinner"></span> Logging in...';
            submitBtn.disabled = true;
            
            // Re-enable after a short delay in case of validation errors
            setTimeout(() => {
                if (!this.submitted) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }, 3000);
        });
    </script>
</body>
</html>
