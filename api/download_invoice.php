<?php
require_once '../config.php';
require_once '../functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get order ID
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Get user ID
$user_id = get_user_id();

// Fetch order details
$stmt = mysqli_prepare($conn, "
    SELECT o.*, u.name, u.email, u.phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$order = mysqli_fetch_assoc($result);

// Fetch order items
$stmt = mysqli_prepare($conn, "
    SELECT oi.*, p.title, p.file_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
mysqli_stmt_bind_param($stmt, "i", $order_id);
mysqli_stmt_execute($stmt);
$items_result = mysqli_stmt_get_result($stmt);

$items = [];
while ($item = mysqli_fetch_assoc($items_result)) {
    $items[] = $item;
}

// Generate invoice HTML
$invoice_html = generate_invoice_html($order, $items);

// Create PDF using DOMPDF or similar
// For now, we'll generate a simple HTML invoice that can be printed/saved

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: inline; filename="invoice_' . $order_id . '.html"');

echo $invoice_html;

function generate_invoice_html($order, $items) {
    $total = $order['total_amount'] ?? 0;
    
    $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #' . $order['id'] . ' - S3 Digital</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
            min-height: 100vh;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(236, 72, 153, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(16, 185, 129, 0.2) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        .invoice-container {
            background: rgba(15, 23, 42, 0.95);
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 2px solid rgba(99, 102, 241, 0.3);
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .invoice-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .invoice-header h1 {
            margin: 0;
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(45deg, #fff, #f0f9ff, #e0e7ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }
        
        .invoice-header p {
            margin: 10px 0 0 0;
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .invoice-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            padding: 30px;
            background: rgba(30, 41, 59, 0.8);
        }
        
        .info-section {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: #f1f5f9;
            padding: 25px;
            border-radius: 15px;
            border: 2px solid rgba(99, 102, 241, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .info-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899, #10b981, #06b6d4);
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .info-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.4);
        }
        
        .info-section h3 {
            margin: 0 0 20px 0;
            background: linear-gradient(45deg, #6366f1, #8b5cf6, #ec4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.4rem;
            font-weight: 700;
        }
        
        .info-section p {
            margin: 8px 0;
            font-size: 1rem;
            transition: color 0.2s ease;
        }
        
        .info-section p:hover {
            color: #a78bfa;
        }
        
        .items-section {
            padding: 0 30px 30px 30px;
            background: rgba(30, 41, 59, 0.8);
        }
        
        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .items-table th {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
            color: white;
            padding: 20px;
            text-align: left;
            font-weight: 700;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        
        .items-table th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
        }
        
        .items-table td {
            padding: 18px 20px;
            border-bottom: 1px solid rgba(99, 102, 241, 0.1);
            color: #f1f5f9;
            font-size: 1rem;
            background: rgba(30, 41, 59, 0.6);
            transition: all 0.2s ease;
        }
        
        .items-table tr:hover td {
            background: rgba(99, 102, 241, 0.1);
            color: #e0e7ff;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        .total-section {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 30px;
            text-align: center;
            border-radius: 15px;
            margin: 0 30px 30px 30px;
            border: 2px solid rgba(16, 185, 129, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .total-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(circle at 30% 30%, rgba(16, 185, 129, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 70% 70%, rgba(6, 182, 212, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .total-section h3 {
            margin: 0;
            background: linear-gradient(45deg, #10b981, #06b6d4, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 1.8rem;
            font-weight: 800;
            position: relative;
            z-index: 1;
        }
        
        .total-amount {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(45deg, #10b981, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: 15px;
            position: relative;
            z-index: 1;
            text-shadow: 0 0 30px rgba(16, 185, 129, 0.3);
        }
        
        .footer {
            text-align: center;
            padding: 30px;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 0 0 20px 20px;
            color: #94a3b8;
            border-top: 2px solid rgba(99, 102, 241, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899, #10b981, #06b6d4, #f59e0b, #ef4444);
            animation: rainbow 5s linear infinite;
        }
        
        @keyframes rainbow {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .footer p {
            margin: 8px 0;
            font-size: 1rem;
            transition: color 0.2s ease;
            position: relative;
            z-index: 1;
        }
        
        .footer p:hover {
            color: #a78bfa;
        }
        
        .highlight-badge {
            display: inline-block;
            background: linear-gradient(45deg, #10b981, #06b6d4);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: 10px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .discount-info {
            background: linear-gradient(135deg, #f59e0b, #ef4444);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-weight: 600;
            text-align: center;
        }
        
        .coupon-info {
            background: linear-gradient(135deg, #8b5cf6, #ec4899);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-weight: 600;
            text-align: center;
        }
        
        @media print {
            body { 
                background: white !important; 
                padding: 20px !important;
            }
            .invoice-container {
                background: white !important;
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            .invoice-header { 
                background: #6366f1 !important;
                color: white !important;
            }
            .invoice-header h1 {
                color: white !important;
                -webkit-text-fill-color: white !important;
            }
            .info-section {
                background: #f8fafc !important;
                color: #333 !important;
                border: 1px solid #ddd !important;
            }
            .info-section h3 {
                color: #6366f1 !important;
                -webkit-text-fill-color: #6366f1 !important;
            }
            .items-table th {
                background: #6366f1 !important;
                color: white !important;
            }
            .items-table td {
                background: white !important;
                color: #333 !important;
                border-bottom: 1px solid #ddd !important;
            }
            .total-section {
                background: #f8fafc !important;
                border: 1px solid #ddd !important;
            }
            .total-section h3 {
                color: #10b981 !important;
                -webkit-text-fill-color: #10b981 !important;
            }
            .total-amount {
                color: #10b981 !important;
                -webkit-text-fill-color: #10b981 !important;
            }
            .footer {
                background: #f8fafc !important;
                color: #666 !important;
                border-top: 1px solid #ddd !important;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="invoice-header">
            <h1>🧾 INVOICE</h1>
            <p>Order #' . $order['id'] . '</p>
            <p>Date: ' . date('F j, Y', strtotime($order['created_at'])) . '</p>
        </div>
        
        <div class="invoice-info">
            <div class="info-section">
                <h3>📦 Billing Information</h3>
                <p><strong>Name:</strong> ' . htmlspecialchars($order['billing_name'] ?? $order['name'] ?? 'N/A') . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($order['billing_email'] ?? $order['email'] ?? 'N/A') . '</p>
                <p><strong>Phone:</strong> ' . htmlspecialchars($order['billing_phone'] ?? $order['phone'] ?? 'N/A') . '</p>
            </div>
            
            <div class="info-section">
                <h3>📍 Shipping Address</h3>
                <p>' . nl2br(htmlspecialchars($order['billing_address'] ?? 'Digital Download - No shipping required')) . '</p>
                <p><strong>Payment Method:</strong> ' . ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'N/A')) . '</p>
                <p><strong>Status:</strong> ' . ucfirst($order['payment_status'] ?? 'pending') . '<span class="highlight-badge">' . ($order['payment_status'] === 'completed' ? '✓ Paid' : '⏳ Pending') . '</span></p>
            </div>
        </div>
        
        <div class="items-section">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($items as $item) {
        $item_price = $item['product_price'] ?? 0;
        $item_quantity = $item['quantity'] ?? 1;
        $item_total = $item_price * $item_quantity;
        $item_title = $item['product_title'] ?? $item['title'] ?? 'Unknown Product';
        
        $html .= '
                    <tr>
                        <td>🎯 ' . htmlspecialchars($item_title) . '</td>
                        <td>' . format_price($item_price) . '</td>
                        <td>' . $item_quantity . '</td>
                        <td>' . format_price($item_total) . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="total-section">
            <h3>💰 Total Amount</h3>
            <div class="total-amount">' . format_price($total) . '</div>';
    
    if (!empty($order['discount_amount']) && $order['discount_amount'] > 0) {
        $html .= '<div class="discount-info">🎉 Discount Applied: ' . format_price($order['discount_amount']) . '</div>';
    }
    
    if (!empty($order['coupon_code'])) {
        $html .= '<div class="coupon-info">🎫 Coupon Code: ' . htmlspecialchars($order['coupon_code']) . '</div>';
    }
    
    $html .= '
        </div>
        
        <div class="footer">
            <p>🎉 Thank you for your purchase from S3 Digital!</p>
            <p>This is a computer-generated invoice. No signature required.</p>
            <p>For any queries, please contact us at support@s3digital.com</p>';
    
    if (!empty($order['transaction_id'])) {
        $html .= '<p style="margin-top: 15px; font-size: 0.9rem;">🔗 Transaction ID: ' . htmlspecialchars($order['transaction_id']) . '</p>';
    }
    
    $html .= '
        </div>
    </div>
</body>
</html>';
    
    return $html;
}
?>
