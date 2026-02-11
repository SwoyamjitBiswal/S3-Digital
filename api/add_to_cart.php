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
    echo json_encode(['success' => false, 'message' => 'Please login to add products to cart']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$product_id = (int)($data['product_id'] ?? 0);
$quantity = (int)($data['quantity'] ?? 1);

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
    exit;
}

// Check if product exists and is active
$stmt = mysqli_prepare($conn, "SELECT id, status FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$product = mysqli_fetch_assoc($result);

if ($product['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Product is not available']);
    exit;
}

$user_id = get_user_id();

// Check if product already in cart
$stmt = mysqli_prepare($conn, "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    // Update quantity
    $cart_item = mysqli_fetch_assoc($result);
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    $stmt = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "iii", $new_quantity, $user_id, $product_id);
    mysqli_stmt_execute($stmt);
} else {
    // Add to cart
    $stmt = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iii", $user_id, $product_id, $quantity);
    mysqli_stmt_execute($stmt);
}

// Get updated cart count
$cart_count = get_cart_count();

// Log activity
log_activity($user_id, 'add_to_cart', "Added product ID: $product_id to cart");

echo json_encode([
    'success' => true,
    'message' => 'Product added to cart successfully',
    'cart_count' => $cart_count
]);
?>
