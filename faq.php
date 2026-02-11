<?php
require_once 'config.php';
$page_title = 'FAQ - ' . SITE_NAME;

$faq_items = [];
$result = mysqli_query($conn, "SELECT * FROM faq WHERE status = 'active' ORDER BY category ASC, sort_order ASC, created_at DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $faq_items[] = $row;
}

$grouped = [];
foreach ($faq_items as $item) {
    $cat = $item['category'] ?: 'General';
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = [];
    }
    $grouped[$cat][] = $item;
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
                <i class="fas fa-question-circle me-2"></i>
                <strong>FAQ</strong>
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
                    <li class="nav-item"><a class="nav-link active" href="faq.php">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
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
            <div class="col-lg-9">
                <div class="mb-4">
                    <h1 class="h2 mb-1">Frequently Asked Questions</h1>
                    <p class="text-muted mb-0">Find quick answers to common questions.</p>
                </div>

                <?php if (!empty($grouped)): ?>
                    <?php $accId = 0; ?>
                    <?php foreach ($grouped as $category => $items): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-layer-group me-2 text-primary"></i><?php echo htmlspecialchars($category); ?></h5>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="faqAccordion_<?php echo $accId; ?>">
                                    <?php $i = 0; foreach ($items as $item): $collapseId = 'c_' . $accId . '_' . $i; ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="h_<?php echo $collapseId; ?>">
                                                <button class="accordion-button <?php echo $i === 0 ? '' : 'collapsed'; ?>" type="button" data-mdb-toggle="collapse" data-mdb-target="#<?php echo $collapseId; ?>">
                                                    <?php echo htmlspecialchars($item['question']); ?>
                                                </button>
                                            </h2>
                                            <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-mdb-parent="#faqAccordion_<?php echo $accId; ?>">
                                                <div class="accordion-body">
                                                    <?php echo nl2br(htmlspecialchars($item['answer'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php $i++; endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php $accId++; endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-question-circle fa-4x text-muted mb-3"></i>
                        <h3>No FAQs found</h3>
                        <p class="text-muted">Please add FAQ items from admin panel.</p>
                        <a href="contact.php" class="btn btn-primary">Contact Support</a>
                    </div>
                <?php endif; ?>

                <div class="alert alert-info">
                    <div class="d-flex">
                        <div class="me-3"><i class="fas fa-headset fa-lg"></i></div>
                        <div>
                            <strong>Still need help?</strong>
                            <div class="small">Contact our support team and we’ll get back to you.</div>
                            <a href="contact.php" class="btn btn-sm btn-outline-primary mt-2">Contact Support</a>
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
