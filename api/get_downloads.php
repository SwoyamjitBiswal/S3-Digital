<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Please login to view downloads']);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = get_user_id();

if ($order_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Verify order ownership and payment status
$result = mysqli_query($conn, "
    SELECT id FROM orders 
    WHERE id = $order_id AND user_id = $user_id AND payment_status = 'completed'
");

if (mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or payment not completed']);
    exit;
}

// Get downloadable items
$result = mysqli_query($conn, "
    SELECT oi.*, p.file_name, p.file_path, p.title as product_title,
           d.download_count, d.max_downloads, d.expiry_date
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN downloads d ON oi.id = d.order_item_id
    WHERE oi.order_id = $order_id
");

$items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

if (empty($items)) {
    echo json_encode(['success' => false, 'message' => 'No downloadable items found']);
    exit;
}

// Generate HTML
$html = '<div class="row">';

foreach ($items as $item) {
    $download_count = $item['download_count'] ?? 0;
    $remaining_downloads = $item['max_downloads'] - $download_count;
    $is_expired = $item['expiry_date'] && strtotime($item['expiry_date']) < time();
    $can_download = $remaining_downloads > 0 && !$is_expired;
    
    $html .= '
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="mb-1">' . htmlspecialchars($item['product_title']) . '</h6>
                            <p class="mb-1 text-muted">File: ' . htmlspecialchars($item['file_name']) . '</p>
                            <p class="mb-0 small">
                                <strong>Downloads:</strong> ' . $download_count . ' / ' . $item['max_downloads'] . ' remaining
                                ' . ($item['expiry_date'] ? '<br><strong>Expires:</strong> ' . date('F j, Y, g:i a', strtotime($item['expiry_date'])) : '') . '
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
    ';
    
    if ($can_download) {
        $html .= '
            <a href="download.php?item=' . $item['id'] . '" class="btn btn-primary">
                <i class="fas fa-download me-1"></i>Download
            </a>
        ';
    } else {
        if ($is_expired) {
            $html .= '<span class="badge bg-danger">Download Expired</span>';
        } else {
            $html .= '<span class="badge bg-warning">Download Limit Reached</span>';
        }
    }
    
    $html .= '
                        </div>
                    </div>
                </div>
            </div>
        </div>
    ';
}

$html .= '</div>';

$html .= '
    <div class="alert alert-info">
        <h6 class="alert-heading">
            <i class="fas fa-info-circle me-2"></i>Download Information
        </h6>
        <ul class="mb-0">
            <li>Download links are valid for 24 hours from the time of purchase</li>
            <li>You can download each product up to 5 times</li>
            <li>Make sure to save files in a secure location</li>
            <li>If you need additional downloads, please contact support</li>
        </ul>
    </div>
';

echo json_encode(['success' => true, 'html' => $html]);
?>
