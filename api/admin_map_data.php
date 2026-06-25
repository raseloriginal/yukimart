<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../db.php';
$pdo = getDB();

try {
    // 1. Get dispatch point
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('dispatch_lat', 'dispatch_lng')");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $dispatch_point = [
        'lat' => isset($settings['dispatch_lat']) ? (float)$settings['dispatch_lat'] : null,
        'lng' => isset($settings['dispatch_lng']) ? (float)$settings['dispatch_lng'] : null,
    ];

    // 2. Get delivery areas
    $areas = $pdo->query("SELECT id, name, delivery_charge, is_active, latitude, longitude, radius_meters, color, polygon_data FROM delivery_areas")->fetchAll(PDO::FETCH_ASSOC);
    
    // Type casting for JS
    foreach ($areas as &$area) {
        $area['id'] = (int)$area['id'];
        $area['delivery_charge'] = (float)$area['delivery_charge'];
        $area['is_active'] = (bool)$area['is_active'];
        $area['latitude'] = $area['latitude'] !== null ? (float)$area['latitude'] : null;
        $area['longitude'] = $area['longitude'] !== null ? (float)$area['longitude'] : null;
        $area['radius_meters'] = $area['radius_meters'] !== null ? (int)$area['radius_meters'] : null;
    }

    // 3. Get customer pins (unique locations from orders)
    // We group by customer_name and lat/lng to avoid showing 100 pins for the same customer at the same place
    $stmt = $pdo->query("
        SELECT 
            customer_name, 
            MAX(customer_phone) as phone, 
            latitude as lat, 
            longitude as lng,
            SUM(CASE WHEN status IN ('pending', 'processing', 'collected', 'out_for_delivery', 'due') THEN 1 ELSE 0 END) as active_orders
        FROM orders 
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL 
        GROUP BY customer_name, latitude, longitude
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($customers as &$customer) {
        $customer['lat'] = (float)$customer['lat'];
        $customer['lng'] = (float)$customer['lng'];
        $customer['has_active_order'] = (int)$customer['active_orders'] > 0;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'dispatch_point' => $dispatch_point,
            'areas' => $areas,
            'customers' => $customers
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
