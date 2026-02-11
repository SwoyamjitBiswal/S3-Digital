<?php
require_once 'config.php';

// Log activity before destroying session
if (is_logged_in()) {
    log_activity(get_user_id(), 'user_logout', "User logout: " . $_SESSION['user_email']);
}

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    
    // Clear token from database
    if (is_logged_in()) {
        $stmt = mysqli_prepare($conn, "UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
    }
}

// Destroy session
session_destroy();

// Redirect to login with message
flash_message('success', 'You have been logged out successfully.');
redirect('login.php');
?>
