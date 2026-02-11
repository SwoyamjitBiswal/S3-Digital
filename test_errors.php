<?php
// Test for common errors in the project
require_once 'config.php';

echo "<h2>S3 Digital - Error Testing</h2>";

// Test 1: Database Connection
echo "<h3>1. Database Connection</h3>";
if ($conn) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br>";
}

// Test 2: Required Functions
echo "<h3>2. Required Functions</h3>";
$required_functions = [
    'is_logged_in',
    'is_admin', 
    'get_user_id',
    'get_admin_id',
    'clean_input',
    'flash_message',
    'get_flash_message',
    'redirect',
    'password_hash',
    'verify_password',
    'generate_token',
    'format_price',
    'log_activity',
    'get_cart_count',
    'get_cart_total',
    'validate_coupon',
    'apply_coupon',
    'create_slug',
    'upload_file',
    'paginate_query'
];

foreach ($required_functions as $func) {
    if (function_exists($func)) {
        echo "✅ Function $func exists<br>";
    } else {
        echo "❌ Function $func missing<br>";
    }
}

// Test 3: Required Tables
echo "<h3>3. Required Tables</h3>";
$required_tables = [
    'users',
    'admin_users', 
    'categories',
    'products',
    'cart',
    'orders',
    'order_items',
    'coupons',
    'support_tickets',
    'ticket_messages',
    'testimonials',
    'activity_logs',
    'settings'
];

foreach ($required_tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) > 0) {
        echo "✅ Table $table exists<br>";
    } else {
        echo "❌ Table $table missing<br>";
    }
}

// Test 4: Upload Directories
echo "<h3>4. Upload Directories</h3>";
$directories = [
    'uploads',
    'uploads/products',
    'uploads/screenshots'
];

foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "✅ Directory $dir exists<br>";
    } else {
        echo "❌ Directory $dir missing<br>";
    }
}

// Test 5: Admin User
echo "<h3>5. Admin User</h3>";
$result = mysqli_query($conn, "SELECT * FROM admin_users WHERE email = 'admin@s3digital.com' AND status = 'active'");
if (mysqli_num_rows($result) > 0) {
    echo "✅ Admin user exists<br>";
} else {
    echo "❌ Admin user missing or inactive<br>";
}

echo "<h3>Testing Complete!</h3>";
echo "<p>If all tests pass, the project should run without major errors.</p>";
?>
