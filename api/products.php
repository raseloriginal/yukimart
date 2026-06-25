<?php
require_once '../db.php';
header('Content-Type: application/json');

try {
    $pdo = getDB();
    
    // Fetch active categories
    $stmt = $pdo->query("SELECT id, name, image_url FROM categories WHERE is_active = 1 ORDER BY sort_order ASC");
    $categories = $stmt->fetchAll();
    
    // Fetch active products
    $stmt = $pdo->query("SELECT id, category_id, name, description, regular_price, wholesale_price, image_url FROM products WHERE is_active = 1");
    $products = $stmt->fetchAll();

    // Fetch delivery areas
    $stmt = $pdo->query("SELECT id, name, delivery_charge FROM delivery_areas WHERE is_active = 1");
    $areas = $stmt->fetchAll();

    // Fetch delivery slots
    $stmt = $pdo->query("SELECT id, slot_time FROM delivery_slots WHERE is_active = 1");
    $slots = $stmt->fetchAll();
    
    // Fetch free delivery amount setting
    $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'free_delivery_amount'");
    $free_delivery_amount = 0;
    if ($row = $stmt->fetch()) {
        $free_delivery_amount = (float)$row['setting_value'];
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'products' => $products,
        'areas' => $areas,
        'slots' => $slots,
        'free_delivery_amount' => $free_delivery_amount
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
