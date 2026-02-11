<?php
require_once '../config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please login to apply coupons']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$coupon_code = clean_input($data['coupon_code'] ?? '');

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a coupon code']);
    exit;
}

// Validate coupon
$coupon = validate_coupon($coupon_code);

if (!$coupon) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
    exit;
}

// Get cart total
$user_id = get_user_id();
$cart_total = get_cart_total();

// Check minimum amount requirement
if ($cart_total < $coupon['minimum_amount']) {
    echo json_encode([
        'success' => false, 
        'message' => 'Minimum order amount of ' . format_price($coupon['minimum_amount']) . ' required to use this coupon'
    ]);
    exit;
}

// Apply coupon
$discount_amount = apply_coupon($cart_total, $coupon);
$new_total = $cart_total - $discount_amount;

// Store coupon in session
$_SESSION['applied_coupon'] = $coupon;

// Log activity
log_activity($user_id, 'coupon_applied', "Applied coupon: $coupon_code");

echo json_encode([
    'success' => true,
    'message' => 'Coupon applied successfully!',
    'coupon' => [
        'code' => $coupon['code'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => $coupon['discount_value'],
        'discount_amount' => $discount_amount
    ],
    'cart_total' => $cart_total,
    'new_total' => $new_total
]);
?>
