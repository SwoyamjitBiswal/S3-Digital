<?php
require_once '../config.php';
$page_title = 'Admin Login - ' . SITE_NAME;

// Redirect if already logged in
if (is_admin()) {
    redirect('index.php');
}

// Handle admin login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    // Validation
    if (empty($email)) {
        $errors[] = "Email is required";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    if (empty($errors)) {
        // Check admin credentials
        $stmt = mysqli_prepare($conn, "SELECT * FROM admin_users WHERE email = ? AND status = 'active' LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
            $admin = mysqli_fetch_assoc($result);
            
            if (verify_password($password, $admin['password'])) {
                // Set admin session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // Update last login
                $stmt = mysqli_prepare($conn, "UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                mysqli_stmt_bind_param($stmt, "i", $admin['id']);
                mysqli_stmt_execute($stmt);
                
                // Log activity
                log_activity($admin['id'], 'admin_login', "Admin login: $email");
                
                flash_message('success', 'Welcome to admin dashboard!');
                redirect('index.php');
            } else {
                $errors[] = "Invalid email or password";
            }
        } else {
            $errors[] = "Invalid email or password";
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
    
    <!-- MDBootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="admin-logo mb-3">
                                <i class="fas fa-shield-alt fa-4x text-primary"></i>
                            </div>
                            <h2 class="fw-bold">Admin Login</h2>
                            <p class="text-muted">Enter your credentials to access the admin dashboard</p>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?php echo htmlspecialchars($errors[0]); ?>
                            </div>
                        <?php endif; ?>

                        <?php
                        $success_msg = get_flash_message('success');
                        if ($success_msg):
                        ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success_msg); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" id="adminLoginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                       autocomplete="email">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>

                            <div class="mb-4">
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

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                            </button>

                            <div class="text-center">
                                <a href="../index.php" class="text-primary text-decoration-none">
                                    <i class="fas fa-arrow-left me-1"></i>Back to Website
                                </a>
                            </div>
                        </form>

                        <hr class="my-4">

                        <!-- Security Info -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>Security Notice
                            </h6>
                            <p class="mb-2 small">
                                This is a restricted area. Unauthorized access is prohibited and will be logged.
                            </p>
                            <p class="mb-0 small">
                                For security reasons, this session will automatically expire after 2 hours of inactivity.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="text-center mt-4">
                    <p class="text-white-50 mb-0">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> Admin Panel
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
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

        // Form validation
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            
            if (!email || !password) {
                e.preventDefault();
                showNotification('Please fill in all fields', 'warning');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span class="spinner"></span> Authenticating...';
            submitBtn.disabled = true;
            
            return true;
        });

        // Auto-focus on email field
        window.addEventListener('load', function() {
            document.getElementById('email').focus();
        });

        // Add some visual feedback
        document.getElementById('email').addEventListener('focus', function() {
            this.parentElement.classList.add('border-primary');
        });

        document.getElementById('email').addEventListener('blur', function() {
            this.parentElement.classList.remove('border-primary');
        });

        document.getElementById('password').addEventListener('focus', function() {
            this.parentElement.classList.add('border-primary');
        });

        document.getElementById('password').addEventListener('blur', function() {
            this.parentElement.classList.remove('border-primary');
        });
    </script>

    <style>
        .bg-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        .admin-logo {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .card {
            border: none;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</body>
</html>
