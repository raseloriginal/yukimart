<?php
session_start();
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once '../db.php';
$pdo = getDB();

header('Content-Type: application/json');

$log = [];
$errors = [];

try {
    // ─── Ensure Coupons Table Exists ────────────────────────────────────────────
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'fixed',
        discount_value DECIMAL(10,2) NOT NULL,
        min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ─── Fetch Existing Reference Data ──────────────────────────────────────────
    $areas   = $pdo->query("SELECT id FROM delivery_areas WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $slots   = $pdo->query("SELECT id FROM delivery_slots WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
    $products= $pdo->query("SELECT id, regular_price, wholesale_price FROM products WHERE is_active = 1")->fetchAll();
    $dsrs    = $pdo->query("SELECT id FROM users WHERE role = 'dsr'")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($areas) || empty($slots) || empty($products)) {
        echo json_encode(['success' => false, 'message' => 'Please run setup.php first to create base data.']);
        exit;
    }

    // ─── Seed Additional DSR Users ───────────────────────────────────────────────
    $dsr_names = ['Rashed Mia', 'Karim Babu', 'Sujon Ahmed', 'Faruk Hossain', 'Bilal Sarker'];
    foreach ($dsr_names as $name) {
        $phone = '018' . rand(10000000, 99999999);
        $pass  = password_hash('dsr123', PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (name, phone, password, role) VALUES (?,?,?,'dsr')");
            $stmt->execute([$name, $phone, $pass]);
        } catch (Exception $e) { /* skip duplicates */ }
    }
    $dsrs = $pdo->query("SELECT id FROM users WHERE role = 'dsr'")->fetchAll(PDO::FETCH_COLUMN);
    $log[] = "DSR users seeded: " . count($dsrs);

    // ─── Seed Coupons ────────────────────────────────────────────────────────────
    $coupons_data = [
        ['WELCOME10', 'percentage', 10, 0],
        ['SAVE50',    'fixed',      50, 200],
        ['HALFOFF',   'percentage', 50, 500],
        ['FREEDEL',   'fixed',      60, 300],
        ['BIGSALE',   'percentage', 20, 100],
    ];
    foreach ($coupons_data as [$code, $type, $val, $min]) {
        try {
            $stmt = $pdo->prepare("INSERT IGNORE INTO coupons (code, discount_type, discount_value, min_order_amount) VALUES (?,?,?,?)");
            $stmt->execute([$code, $type, $val, $min]);
        } catch (Exception $e) { /* skip duplicates */ }
    }
    $couponCodes = $pdo->query("SELECT code, discount_type, discount_value, min_order_amount FROM coupons WHERE is_active = 1")->fetchAll();
    $log[] = "Coupons seeded.";

    // ─── Seed 30 Days of Orders ──────────────────────────────────────────────────
    $statuses    = ['pending','processing','collected','out_for_delivery','completed','completed','completed','cancelled','due'];
    $firstNames  = ['Sadia','Rifat','Nadia','Tanvir','Mitu','Rana','Shila','Farhan','Poly','Mamun','Joynal','Ruksana'];
    $lastNames   = ['Akter','Ahmed','Islam','Hossain','Khatun','Mia','Begum','Rahman','Sarkar','Bhuyan'];

    $seeded = 0;
    for ($dayOffset = 29; $dayOffset >= 0; $dayOffset--) {
        $date      = date('Y-m-d', strtotime("-{$dayOffset} days"));
        $ordersDay = rand(3, 12);

        for ($o = 0; $o < $ordersDay; $o++) {
            $name        = $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
            $phone       = '017' . rand(10000000, 99999999);
            $area_id     = $areas[array_rand($areas)];
            $slot_id     = $slots[array_rand($slots)];
            $dsr_id      = !empty($dsrs) ? $dsrs[array_rand($dsrs)] : null;
            $status      = $statuses[array_rand($statuses)];

            // Determine hour
            $hour        = rand(8, 21);
            $minute      = rand(0, 59);
            $created_at  = "$date $hour:$minute:00";

            // Pick 1-4 random products
            shuffle($products);
            $cartProducts = array_slice($products, 0, rand(1, min(4, count($products))));

            $subtotal   = 0;
            $total_items= 0;
            $itemsToInsert = [];

            foreach ($cartProducts as $p) {
                $qty       = rand(1, 5);
                $price     = (float)$p['regular_price'];
                $wprice    = (float)$p['wholesale_price'];
                $sub       = $price * $qty;
                $subtotal += $sub;
                $total_items += $qty;
                $itemsToInsert[] = ['product_id' => $p['id'], 'quantity' => $qty, 'unit_price' => $price, 'wholesale_price' => $wprice, 'subtotal' => $sub];
            }

            // Coupon application
            $discount    = 0;
            $coupon_code = null;
            if (!empty($couponCodes) && rand(0, 3) === 0) {
                $c = $couponCodes[array_rand($couponCodes)];
                if ($subtotal >= $c['min_order_amount']) {
                    $coupon_code = $c['code'];
                    if ($c['discount_type'] === 'percentage') {
                        $discount = ($subtotal * $c['discount_value']) / 100;
                    } else {
                        $discount = $c['discount_value'];
                    }
                    $discount = min($discount, $subtotal);
                }
            }

            // Delivery charge
            $chargeStmt = $pdo->prepare("SELECT delivery_charge FROM delivery_areas WHERE id = ?");
            $chargeStmt->execute([$area_id]);
            $delivery_charge = (float)($chargeStmt->fetchColumn() ?: 60);

            $grand_total = ($subtotal - $discount) + $delivery_charge;

            // Insert order
            $stmt = $pdo->prepare("INSERT INTO orders (customer_name, customer_phone, shipping_address, area_id, slot_id, dsr_id, total_items, subtotal, delivery_charge, grand_total, status, coupon_code, discount, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $name, $phone,
                rand(1,999) . ' Sample Street, Dhaka',
                $area_id, $slot_id, $dsr_id,
                $total_items, $subtotal, $delivery_charge, $grand_total,
                $status, $coupon_code, $discount,
                $created_at, $created_at
            ]);
            $order_id = $pdo->lastInsertId();

            // Insert order items
            $istmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price, wholesale_price, subtotal) VALUES (?,?,?,?,?,?)");
            foreach ($itemsToInsert as $item) {
                $istmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['unit_price'], $item['wholesale_price'], $item['subtotal']]);
            }
            $seeded++;
        }
    }

    $log[] = "Seeded $seeded orders over the last 30 days.";

    echo json_encode(['success' => true, 'log' => $log]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
