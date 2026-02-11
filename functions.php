<?php
// Utility Functions

function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID, ['cost' => HASH_COST]);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['admin_id']);
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_admin_id() {
    return $_SESSION['admin_id'] ?? null;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function flash_message($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash_message($type) {
    $message = $_SESSION['flash'][$type] ?? '';
    unset($_SESSION['flash'][$type]);
    return $message;
}

function format_price($price, $currency = '₹') {
    $price = is_numeric($price) ? (float)$price : 0;
    return $currency . number_format($price, 2);
}

function generate_order_id() {
    return 'ORD' . strtoupper(uniqid());
}

function send_email($to, $subject, $message, $is_html = false) {
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    
    if ($is_html) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $message, $headers);
}

function create_slug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function upload_file($file, $destination, $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'zip', 'rar']) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'Invalid file upload'];
    }
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    $max_size = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $filename = uniqid() . '.' . $file_ext;
    $filepath = $destination . $filename;
    
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

function paginate($query, $page = 1, $per_page = 10) {
    global $conn;
    
    $offset = ($page - 1) * $per_page;
    $limit_query = $query . " LIMIT $offset, $per_page";
    
    $result = mysqli_query($conn, $limit_query);
    $data = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as count_table";
    $count_result = mysqli_query($conn, $count_query);
    $total = mysqli_fetch_assoc($count_result)['total'];
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

function get_cart_count() {
    if (!is_logged_in()) {
        return 0;
    }
    
    global $conn;
    $user_id = get_user_id();
    
    $result = mysqli_query($conn, "SELECT SUM(quantity) as count FROM cart WHERE user_id = $user_id");
    $row = mysqli_fetch_assoc($result);
    
    return $row['count'] ?? 0;
}

function get_cart_total() {
    if (!is_logged_in()) {
        return 0;
    }
    
    global $conn;
    $user_id = get_user_id();
    
    $result = mysqli_query($conn, "
        SELECT SUM(c.quantity * p.price) as total 
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = $user_id
    ");
    $row = mysqli_fetch_assoc($result);
    
    return $row['total'] ?? 0;
}

function validate_coupon($code) {
    global $conn;
    
    $code = clean_input($code);
    $result = mysqli_query($conn, "
        SELECT * FROM coupons 
        WHERE code = '$code' 
        AND status = 'active' 
        AND (expiry_date IS NULL OR expiry_date > NOW())
        AND (usage_limit IS NULL OR used_count < usage_limit)
    ");
    
    if (mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result);
    }
    
    return false;
}

function apply_coupon($cart_total, $coupon) {
    if ($coupon['discount_type'] === 'percentage') {
        return $cart_total * (1 - $coupon['discount_value'] / 100);
    } else {
        return max(0, $cart_total - $coupon['discount_value']);
    }
}

function log_activity($user_id, $action, $details = '') {
    global $conn;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = mysqli_prepare($conn, "
        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    mysqli_stmt_bind_param($stmt, "issss", $user_id, $action, $details, $ip, $user_agent);
    mysqli_stmt_execute($stmt);
}

// Paginate Query Function
function paginate_query($query, $page = 1, $per_page = 10, $params = []) {
    global $conn;
    
    $page = max(1, (int)$page);
    $per_page = max(1, (int)$per_page);
    $offset = ($page - 1) * $per_page;
    
    // Count total records
    $count_query = "SELECT COUNT(*) as total FROM ($query) as count_query";
    
    if (!empty($params)) {
        $types = str_repeat('i', count($params));
        $stmt = mysqli_prepare($conn, $count_query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $count_query);
    }
    
    $total = mysqli_fetch_assoc($result)['total'];
    $total_pages = ceil($total / $per_page);
    
    // Get paginated results
    $paginated_query = $query . " LIMIT $per_page OFFSET $offset";
    
    if (!empty($params)) {
        $stmt = mysqli_prepare($conn, $paginated_query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, $paginated_query);
    }
    
    return [
        'data' => $result,
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1
    ];
}
?>
