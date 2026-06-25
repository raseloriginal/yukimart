<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../db.php';
$pdo = getDB();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'save_dispatch') {
        $lat = $_POST['lat'] ?? '';
        $lng = $_POST['lng'] ?? '';
        
        if ($lat !== '' && $lng !== '') {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('dispatch_lat', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$lat, $lat]);
            
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('dispatch_lng', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$lng, $lng]);
            
            // Update all areas to remain concentric
            $pdo->prepare("UPDATE delivery_areas SET latitude = ?, longitude = ?")->execute([$lat, $lng]);
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
        }
    } elseif ($action === 'save_area') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $charge = (float)($_POST['charge'] ?? 0);
        $lat = $_POST['lat'] ?? null;
        $lng = $_POST['lng'] ?? null;
        $radius = (int)($_POST['radius'] ?? 5000);
        $color = $_POST['color'] ?? '#3388ff';
        $polygon_data = $_POST['polygon_data'] ?? null;
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Name is required']);
            exit;
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE delivery_areas SET name=?, delivery_charge=?, color=?, latitude=?, longitude=?, radius_meters=?, polygon_data=? WHERE id=?");
            $stmt->execute([$name, $charge, $color, $lat, $lng, $radius, $polygon_data, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO delivery_areas (name, delivery_charge, is_active, color, latitude, longitude, radius_meters, polygon_data) VALUES (?, ?, 1, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $charge, $color, $lat, $lng, $radius, $polygon_data]);
        }
        echo json_encode(['success' => true]);
        exit;
    } elseif ($action === 'update_area_coords') {
        $id = (int)($_POST['id'] ?? 0);
        $lat = $_POST['lat'] ?? null;
        $lng = $_POST['lng'] ?? null;
        $radius = isset($_POST['radius']) ? (int)$_POST['radius'] : null;

        if ($id > 0 && $lat !== null && $lng !== null) {
            if ($radius !== null) {
                 $stmt = $pdo->prepare("UPDATE delivery_areas SET latitude = ?, longitude = ?, radius_meters = ? WHERE id = ?");
                 $stmt->execute([$lat, $lng, $radius, $id]);
            } else {
                 $stmt = $pdo->prepare("UPDATE delivery_areas SET latitude = ?, longitude = ? WHERE id = ?");
                 $stmt->execute([$lat, $lng, $id]);
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    } elseif ($action === 'delete_area') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM delivery_areas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid area ID']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
