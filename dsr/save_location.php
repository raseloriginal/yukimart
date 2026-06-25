<?php
// save_location.php
// Receives JSON payload with order_id, latitude, longitude and updates the orders table.

header('Content-Type: application/json');

// Read raw input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['order_id'], $data['latitude'], $data['longitude'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

require_once '../db.php';
$pdo = getDB();

try {
    $stmt = $pdo->prepare("UPDATE orders SET latitude = ?, longitude = ? WHERE id = ?");
    $stmt->execute([$data['latitude'], $data['longitude'], $data['order_id']]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
