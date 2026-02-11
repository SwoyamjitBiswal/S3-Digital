<?php
require_once 'config.php';
$page_title = 'Register - ' . SITE_NAME;

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone = clean_input($_POST['phone']);
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    $result = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($result) > 0) {
        $errors[] = "Email already registered";
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = hash_password($password);
        
        // Insert user
        $stmt = mysqli_prepare($conn, "INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed_password, $phone);
        
        if (mysqli_stmt_execute($stmt)) {
            $user_id = mysqli_insert_id($conn);
            
            // Log activity
            log_activity($user_id, 'user_registered', "New user registration: $email");
            
            // Send welcome email
            $subject = "Welcome to " . SITE_NAME;
            $message = "
                <h2>Welcome to " . SITE_NAME . "!</h2>
                <p>Thank you for registering with us. Your account has been created successfully.</p>
                <p>You can now:</p>
                <ul>
                    <li>Browse our premium digital products</li>
                    <li>Make purchases securely</li>
                    <li>Download your purchased products anytime</li>
                    <li>Track your order history</li>
                </ul>
                <p>If you have any questions, feel free to contact our support team.</p>
                <p>Best regards,<br>" . SITE_NAME . " Team</p>
            ";
            
            send_email($email, $subject, $message, true);
            
            flash_message('success', 'Registration successful! Please login to continue.');
            redirect('login.php');
        } else {
            $errors[] = "Registration failed. Please try again.";
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

    <!-- Registration Form -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow-lg">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                            <h2 class="fw-bold">Create Account</h2>
                            <p class="text-muted">Join us and start exploring premium digital products</p>
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

                        <form method="POST" id="registerForm" onsubmit="return validateForm('registerForm')">
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-user me-1"></i>Full Name
                                </label>
                                <input type="text" class="form-control" id="name" name="name" required
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                                <div class="invalid-feedback">Please enter your full name</div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope me-1"></i>Email Address
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <div class="invalid-feedback">Please enter a valid email address</div>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">
                                    <i class="fas fa-phone me-1"></i>Phone Number (Optional)
                                </label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required
                                           onkeyup="showPasswordStrength(this.value, 'password')">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                        <i class="fas fa-eye" id="password_toggle"></i>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <div class="progress" style="height: 5px;">
                                        <div class="progress-bar" id="password_strength" role="progressbar" style="width: 0%;"></div>
                                    </div>
                                    <small class="text-muted" id="password_strength_text"></small>
                                </div>
                                <div class="invalid-feedback">Password must be at least 8 characters long</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>Confirm Password
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye" id="confirm_password_toggle"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Passwords do not match</div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label" for="terms">
                                        I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> 
                                        and <a href="privacy.php" target="_blank">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>

                            <div class="text-center">
                                <p class="mb-0">Already have an account? 
                                    <a href="login.php" class="text-primary text-decoration-none">
                                        <strong>Login here</strong>
                                    </a>
                                </p>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Features -->
                <div class="row mt-4">
                    <div class="col-4 text-center">
                        <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                        <small class="d-block">Secure Payment</small>
                    </div>
                    <div class="col-4 text-center">
                        <i class="fas fa-download fa-2x text-info mb-2"></i>
                        <small class="d-block">Instant Download</small>
                    </div>
                    <div class="col-4 text-center">
                        <i class="fas fa-headset fa-2x text-warning mb-2"></i>
                        <small class="d-block">24/7 Support</small>
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
            <a href="login.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span>Login</span>
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

        // Custom Form Validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                showNotification('Passwords do not match', 'error');
                document.getElementById('confirm_password').classList.add('is-invalid');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                showNotification('Password must be at least 8 characters long', 'error');
                document.getElementById('password').classList.add('is-invalid');
                return false;
            }
            
            return true;
        });

        // Real-time password confirmation check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>
