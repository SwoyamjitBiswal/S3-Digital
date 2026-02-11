<?php
require_once 'config.php';

echo "<h1>S3 Digital - Complete System Test</h1>";

// Test 1: Database Connection
echo "<h2>1. Database Connection Test</h2>";
if ($conn) {
    echo "✅ Database connection successful<br>";
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "<br>";
}

// Test 2: Admin User Check
echo "<h2>2. Admin User Test</h2>";
$result = mysqli_query($conn, "SELECT * FROM admin_users WHERE email = 'admin@s3digital.com'");
if (mysqli_num_rows($result) > 0) {
    echo "✅ Admin user found<br>";
    $admin = mysqli_fetch_assoc($result);
    echo "📧 Email: " . $admin['email'] . "<br>";
    echo "👤 Name: " . $admin['name'] . "<br>";
    echo "🔐 Role: " . $admin['role'] . "<br>";
    
    // Test password verification
    if (password_verify('admin123', $admin['password'])) {
        echo "✅ Password verification successful for 'admin123'<br>";
    } else {
        echo "❌ Password verification failed<br>";
    }
} else {
    echo "❌ Admin user not found<br>";
}

// Test 3: Required Files
echo "<h2>3. Required Files Test</h2>";
$required_files = [
    'config.php',
    'functions.php',
    'index.php',
    'admin/login.php',
    'admin/index.php',
    'assets/js/script.js',
    'assets/css/style.css'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

// Test 4: Directories
echo "<h2>4. Directories Test</h2>";
$required_dirs = [
    'uploads',
    'uploads/products',
    'uploads/screenshots',
    'api'
];

foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ $dir exists<br>";
    } else {
        echo "❌ $dir missing<br>";
    }
}

// Test 5: Session Test
echo "<h2>5. Session Test</h2>";
session_start();
$_SESSION['test'] = 'working';
if ($_SESSION['test'] === 'working') {
    echo "✅ Sessions are working<br>";
} else {
    echo "❌ Sessions not working<br>";
}

// Test 6: Functions Test
echo "<h2>6. Functions Test</h2>";
if (function_exists('clean_input')) {
    echo "✅ clean_input function exists<br>";
} else {
    echo "❌ clean_input function missing<br>";
}

if (function_exists('is_admin')) {
    echo "✅ is_admin function exists<br>";
} else {
    echo "❌ is_admin function missing<br>";
}

if (function_exists('password_verify')) {
    echo "✅ password_verify function exists<br>";
} else {
    echo "❌ password_verify function missing<br>";
}

echo "<h2>🚀 Next Steps</h2>";
echo "<p>If all tests pass above, you can:</p>";
echo "<ul>";
echo "<li><a href='index.php'>Visit Frontend</a></li>";
echo "<li><a href='admin/login.php'>Visit Admin Panel</a></li>";
echo "</ul>";

echo "<h2>🔧 Admin Login Credentials</h2>";
echo "<p><strong>Email:</strong> admin@s3digital.com</p>";
echo "<p><strong>Password:</strong> admin123</p>";

echo "<h2>📊 Database Tables</h2>";
$result = mysqli_query($conn, "SHOW TABLES");
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th>Table Name</th></tr>";
while ($row = mysqli_fetch_row($result)) {
    echo "<tr><td>" . $row[0] . "</td></tr>";
}
echo "</table>";
?>
