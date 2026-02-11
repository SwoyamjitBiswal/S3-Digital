<?php
// Test database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 's3_digital';

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

echo "✅ Database connection successful!<br>";

// Check if admin_users table exists
$result = mysqli_query($conn, "SELECT * FROM admin_users");
if ($result) {
    echo "✅ admin_users table exists<br>";
    $count = mysqli_num_rows($result);
    echo "📊 Found $count admin users<br>";
    
    if ($count > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "👤 Admin: " . $row['email'] . " (Role: " . $row['role'] . ")<br>";
        }
    } else {
        echo "❌ No admin users found!<br>";
    }
} else {
    echo "❌ Error querying admin_users: " . mysqli_error($conn);
}

// Test login with default credentials
$email = 'admin@s3digital.com';
$password = 'admin123';

$result = mysqli_query($conn, "SELECT * FROM admin_users WHERE email = '$email' AND status = 'active'");

if (mysqli_num_rows($result) > 0) {
    $admin = mysqli_fetch_assoc($result);
    if (password_verify($password, $admin['password'])) {
        echo "✅ Login test successful for $email<br>";
    } else {
        echo "❌ Password verification failed<br>";
    }
} else {
    echo "❌ Admin user not found or inactive<br>";
}

mysqli_close($conn);
?>
