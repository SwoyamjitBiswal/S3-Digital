<?php
require_once 'config.php';
$page_title = 'Contact - ' . SITE_NAME;

$errors = [];
$success = '';

$name = '';
$email = '';
$subject = '';
$message = '';
$priority = 'medium';

if (is_logged_in()) {
    $name = $_SESSION['user_name'] ?? '';
    $email = $_SESSION['user_email'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $priority = trim($_POST['priority'] ?? 'medium');

    if ($name === '') {
        $errors[] = 'Name is required';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }

    if ($subject === '') {
        $errors[] = 'Subject is required';
    }

    if ($message === '') {
        $errors[] = 'Message is required';
    }

    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }

    if (empty($errors)) {
        $ticket_id = 'TKT' . strtoupper(uniqid());
        $user_id = is_logged_in() ? (int)get_user_id() : null;

        $stmt = mysqli_prepare($conn, "INSERT INTO support_tickets (user_id, ticket_id, subject, message, status, priority) VALUES (?, ?, ?, ?, 'open', ?)");
        mysqli_stmt_bind_param($stmt, "issss", $user_id, $ticket_id, $subject, $message, $priority);

        if (mysqli_stmt_execute($stmt)) {
            $ticket_db_id = mysqli_insert_id($conn);

            $sender_type = 'user';
            $sender_id = $user_id ?: 0;
            $stmt2 = mysqli_prepare($conn, "INSERT INTO ticket_messages (ticket_id, sender_type, sender_id, message) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, "isis", $ticket_db_id, $sender_type, $sender_id, $message);
            mysqli_stmt_execute($stmt2);

            if (is_logged_in()) {
                log_activity(get_user_id(), 'support_ticket_created', "Ticket: $ticket_id");
            }

            $success = "Thanks! Your ticket has been created. Ticket ID: $ticket_id";
            $subject = '';
            $message = '';
            $priority = 'medium';
        } else {
            $errors[] = 'Failed to submit your message. Please try again.';
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
    <link rel="stylesheet" href="assets/css/style-merged.css">
</head>
<body class="<?php echo is_logged_in() ? 'user-logged-in' : ''; ?>">
    <div class="d-md-none mobile-app-bar">
        <div class="d-flex justify-content-between align-items-center p-3 bg-primary text-white">
            <div class="d-flex align-items-center">
                <i class="fas fa-envelope me-2"></i>
                <strong>Contact</strong>
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
                    <li class="nav-item"><a class="nav-link active" href="contact.php">Contact</a></li>
                </ul>

                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button class="btn btn-link nav-link" id="darkModeToggle" title="Toggle dark mode">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-mdb-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account'); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="orders.php">My Orders</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                        <li class="nav-item"><a class="btn btn-primary btn-sm ms-2" href="register.php">Sign Up</a></li>
                    <?php endif; ?>

                    <li class="nav-item ms-2">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-count badge bg-danger" style="position:absolute; top:-5px; right:-8px; font-size:0.7rem;">
                                <?php echo get_cart_count(); ?>
                            </span>
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
            <?php if (is_logged_in()): ?>
                <a href="profile.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-user me-2"></i>Profile</a>
                <a href="orders.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-shopping-bag me-2"></i>My Orders</a>
                <a href="logout.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
            <?php else: ?>
                <a href="login.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-sign-in-alt me-2"></i>Login</a>
                <a href="register.php" class="d-block py-2 text-decoration-none text-dark"><i class="fas fa-user-plus me-2"></i>Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="mb-4">
                    <h1 class="h2 mb-1">Contact Support</h1>
                    <p class="text-muted mb-0">Send us a message and we’ll help you as soon as possible.</p>
                </div>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($errors[0]); ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($name); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Subject</label>
                                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars($subject); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" class="form-select">
                                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>Low</option>
                                        <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>High</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea name="message" class="form-control" rows="6" required><?php echo htmlspecialchars($message); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>

                <div class="row mt-4 g-3">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="fas fa-clock me-2 text-primary"></i>Response Time</h6>
                                <p class="text-muted mb-0">We usually respond within 24 hours.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-body">
                                <h6 class="mb-2"><i class="fas fa-shield-alt me-2 text-primary"></i>Secure</h6>
                                <p class="text-muted mb-0">Your messages are stored securely in our support system.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <nav class="bottom-nav d-md-none">
        <div class="d-flex justify-content-around align-items-center bg-white border-top">
            <a href="index.php" class="nav-item"><i class="fas fa-home"></i><span>Home</span></a>
            <a href="products.php" class="nav-item"><i class="fas fa-box"></i><span>Products</span></a>
            <a href="cart.php" class="nav-item"><i class="fas fa-shopping-cart"></i><span>Cart</span></a>
            <a href="profile.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
        </div>
    </nav>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.4.2/mdb.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>
