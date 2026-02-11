<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please login to view order details']);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = get_user_id();

if ($order_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Get order details
$stmt = mysqli_prepare($conn, "
    SELECT o.*, u.name, u.email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$order = mysqli_fetch_assoc($result);

// Get order items
$stmt = mysqli_prepare($conn, "
    SELECT oi.*, p.file_name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Generate HTML
$html = '
    <div class="row mb-3">
        <div class="col-md-6">
            <p><strong>Order ID:</strong> ' . htmlspecialchars($order['order_id']) . '</p>
            <p><strong>Date:</strong> ' . date('F j, Y, g:i a', strtotime($order['created_at'])) . '</p>
            <p><strong>Payment Method:</strong> ' . ucfirst($order['payment_method']) . '</p>
        </div>
        <div class="col-md-6">
            <p><strong>Name:</strong> ' . htmlspecialchars($order['billing_name']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($order['billing_email']) . '</p>
            <p><strong>Phone:</strong> ' . htmlspecialchars($order['billing_phone']) . '</p>
        </div>
    </div>
    
    <h6 class="mb-3">Order Items</h6>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
';

foreach ($items as $item) {
    $html .= '
        <tr>
            <td>' . htmlspecialchars($item['product_title']) . '</td>
            <td>' . format_price($item['product_price']) . '</td>
            <td>' . $item['quantity'] . '</td>
            <td>' . format_price($item['product_price'] * $item['quantity']) . '</td>
        </tr>
    ';
}

$html .= '
            </tbody>
        </table>
    </div>
    
    <div class="row mt-3">
        <div class="col-md-6">
            <p><strong>Billing Address:</strong><br>' . nl2br(htmlspecialchars($order['billing_address'])) . '</p>
            ' . ($order['notes'] ? '<p><strong>Notes:</strong><br>' . nl2br(htmlspecialchars($order['notes'])) . '</p>' : '') . '
        </div>
        <div class="col-md-6 text-end">
            <p><strong>Subtotal:</strong> ' . format_price($order['total_amount'] - $order['discount_amount']) . '</p>
            ' . ($order['discount_amount'] > 0 ? '<p><strong>Discount:</strong> -' . format_price($order['discount_amount']) . '</p>' : '') . '
            <p><strong>Total:</strong> <span class="text-primary">' . format_price($order['total_amount']) . '</span></p>
        </div>
    </div>
';

echo json_encode(['success' => true, 'html' => $html]);
?>
