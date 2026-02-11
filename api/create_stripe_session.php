<?php
require_once '../config.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

$order_id = $data['order_id'] ?? '';
$amount = $data['amount'] ?? 0;

if (empty($order_id) || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order data']);
    exit;
}

try {
    // Initialize Stripe (you'll need to install stripe-php via composer)
    // For now, we'll create a mock session
    // In production, you would use: \Stripe\Stripe::setApiKey(STRIPE_SECRET);
    
    // Create a mock session ID for demonstration
    $session_id = 'cs_test_' . uniqid();
    
    // In production with Stripe:
    /*
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Order #' . $order_id,
                ],
                'unit_amount' => $amount * 100, // Amount in cents
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => SITE_URL . 'payment.php?status=success&session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => SITE_URL . 'payment.php?status=cancelled',
    ]);
    
    $session_id = $session->id;
    */
    
    echo json_encode([
        'success' => true,
        'session_id' => $session_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create payment session: ' . $e->getMessage()
    ]);
}
?>
