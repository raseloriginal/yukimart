<?php
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || empty($data['cart']) || empty($data['name']) || empty($data['phone']) || empty($data['address']) || empty($data['area_id']) || empty($data['slot_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data provided.']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();

    // Calculate totals securely from DB (never trust frontend prices)
    $subtotal = 0;
    $total_items = 0;
    $product_ids = array_keys($data['cart']); // cart is an object: { product_id: quantity }
    
    // Fetch products
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, regular_price, wholesale_price FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    
    // Instead of FETCH_KEY_PAIR, fetch everything and index by id
    $products = [];
    while ($row = $stmt->fetch()) {
        $products[$row['id']] = $row;
    }

    $orderItemsData = [];

    foreach ($data['cart'] as $productId => $quantity) {
        if (isset($products[$productId])) {
            $price = $products[$productId]['regular_price'];
            $w_price = $products[$productId]['wholesale_price'];
            $item_subtotal = $price * $quantity;
            $subtotal += $item_subtotal;
            $total_items += $quantity;
            
            $orderItemsData[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $price,
                'wholesale_price' => $w_price,
                'subtotal' => $item_subtotal
            ];
        }
    }

    // Handle Coupon
    $discount = 0;
    $coupon_code = null;
    if (!empty($data['coupon_code'])) {
        $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
        $stmt->execute([strtoupper(trim($data['coupon_code']))]);
        $coupon = $stmt->fetch();
        
        if ($coupon && $subtotal >= $coupon['min_order_amount']) {
            $coupon_code = $coupon['code'];
            if ($coupon['discount_type'] === 'percentage') {
                $discount = ($subtotal * $coupon['discount_value']) / 100;
            } else {
                $discount = $coupon['discount_value'];
            }
            // Ensure discount doesn't exceed subtotal
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
        }
    }

    $subtotal_after_discount = $subtotal - $discount;

    // Get delivery charge
    $stmt = $pdo->prepare("SELECT delivery_charge FROM delivery_areas WHERE id = ?");
    $stmt->execute([$data['area_id']]);
    $delivery_charge = $stmt->fetchColumn();

    if ($delivery_charge === false) {
        throw new Exception("Invalid delivery area.");
    }

    // Check Free Delivery Setting
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'free_delivery_amount'");
    if ($row = $stmt->fetch()) {
        $free_delivery_threshold = (float)$row['setting_value'];
        if ($free_delivery_threshold > 0 && $subtotal_after_discount >= $free_delivery_threshold) {
            $delivery_charge = 0;
        }
    }

    $grand_total = $subtotal_after_discount + $delivery_charge;

    // Insert order
    $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_phone, shipping_address, area_id, slot_id, total_items, subtotal, delivery_charge, grand_total, status, coupon_code, discount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
    $stmt->execute([
        $data['name'],
        $data['phone'],
        $data['address'],
        $data['area_id'],
        $data['slot_id'],
        $total_items,
        $subtotal,
        $delivery_charge,
        $grand_total,
        $coupon_code,
        $discount
    ]);

    $order_id = $pdo->lastInsertId();

    // Insert order items
    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, wholesale_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($orderItemsData as $item) {
        $stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['wholesale_price'],
            $item['subtotal']
        ]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'Order placed successfully!', 
        'order_id' => $order_id,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'delivery_charge' => $delivery_charge,
        'grand_total' => $grand_total
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
