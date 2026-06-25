<?php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['coupon_code']) || !isset($data['subtotal'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request payload']);
    exit;
}

$coupon_code = strtoupper(trim($data['coupon_code']));
$subtotal = (float)$data['subtotal'];

if (empty($coupon_code)) {
    echo json_encode(['success' => false, 'message' => 'Coupon code cannot be empty']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$coupon_code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired coupon code']);
        exit;
    }

    $min_order = (float)$coupon['min_order_amount'];
    if ($subtotal < $min_order) {
        echo json_encode([
            'success' => false, 
            'message' => 'Minimum order amount for this coupon is ৳' . number_format($min_order, 2)
        ]);
        exit;
    }

    $discount = 0;
    $discount_value = (float)$coupon['discount_value'];
    if ($coupon['discount_type'] === 'percentage') {
        $discount = ($subtotal * $discount_value) / 100;
    } else {
        $discount = $discount_value;
    }

    // Discount shouldn't exceed subtotal
    if ($discount > $subtotal) {
        $discount = $subtotal;
    }

    echo json_encode([
        'success' => true,
        'code' => $coupon['code'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => $discount_value,
        'discount' => $discount,
        'min_order_amount' => $min_order,
        'message' => 'Coupon applied successfully!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
