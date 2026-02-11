<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 's3_digital');

// Site Configuration
define('SITE_NAME', 'S3 Digital');
define('SITE_URL', 'http://localhost/S3%20Digital/');
define('ADMIN_EMAIL', 'admin@s3digital.com');
define('UPLOAD_PATH', 'uploads/');
define('PRODUCT_FILES_PATH', 'uploads/products/');
define('SCREENSHOTS_PATH', 'uploads/screenshots/');

// Security
define('JWT_SECRET', 'your-super-secret-jwt-key-change-this-in-production');
define('HASH_COST', 12);

// Payment Gateway Configuration (can be updated from admin panel)
define('RAZORPAY_KEY', '');
define('RAZORPAY_SECRET', '');
define('STRIPE_KEY', '');
define('STRIPE_SECRET', '');
define('PAYPAL_CLIENT_ID', '');
define('PAYPAL_CLIENT_SECRET', '');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@s3digital.com');
define('SMTP_FROM_NAME', 'S3 Digital');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.use_only_cookies', 1);
session_start();

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Include Functions
require_once __DIR__ . '/functions.php';

// Connect to Database
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
