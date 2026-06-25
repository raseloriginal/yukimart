<?php
require_once 'db.php';
$pdo = getDB();

try {
    $pdo->exec("ALTER TABLE products ADD COLUMN wholesaler_id INT NULL");
    $pdo->exec("ALTER TABLE products ADD CONSTRAINT fk_products_wholesaler FOREIGN KEY (wholesaler_id) REFERENCES users(id) ON DELETE SET NULL");
    echo "Added wholesaler_id to products.\n";
} catch (Exception $e) {
    echo "Notice on products: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE order_items ADD COLUMN wholesale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    echo "Added wholesale_price to order_items.\n";
} catch (Exception $e) {
    echo "Notice on order_items: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NULL
    )");
    
    // Seed default free delivery amount if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'free_delivery_amount'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('free_delivery_amount', '0')");
    }
    echo "Added settings table.\n";
} catch (Exception $e) {
    echo "Notice on settings: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'fixed',
        discount_value DECIMAL(10,2) NOT NULL,
        min_order_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (code),
        INDEX (is_active)
    )");
    echo "Added coupons table.\n";
} catch (Exception $e) {
    echo "Notice on coupons: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    echo "Added coupon_code and discount to orders.\n";
} catch (Exception $e) {
    echo "Notice on orders: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN latitude DECIMAL(10,8) NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN longitude DECIMAL(11,8) NULL");
    echo "Added latitude and longitude to orders.\n";
} catch (Exception $e) {
    echo "Notice on orders (lat/lng): " . $e->getMessage() . "\n";
}

try {
    // Find constraint name for slot_id foreign key
    $stmt = $pdo->prepare("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'slot_id'
          AND REFERENCED_TABLE_NAME = 'delivery_slots'
        LIMIT 1
    ");
    $stmt->execute();
    $fk = $stmt->fetchColumn();
    
    if ($fk) {
        $pdo->exec("ALTER TABLE orders DROP FOREIGN KEY $fk");
        echo "Dropped old foreign key constraint '$fk' on orders.\n";
    }
    
    // Modify slot_id to be nullable
    $pdo->exec("ALTER TABLE orders MODIFY COLUMN slot_id INT NULL");
    echo "Modified slot_id column on orders to be nullable.\n";
    
    // Add new constraint with ON DELETE SET NULL
    $pdo->exec("ALTER TABLE orders ADD CONSTRAINT fk_orders_slot_id FOREIGN KEY (slot_id) REFERENCES delivery_slots(id) ON DELETE SET NULL");
    echo "Added new foreign key constraint fk_orders_slot_id with ON DELETE SET NULL to orders.\n";
} catch (Exception $e) {
    echo "Notice on orders slot_id migration: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE delivery_slots ADD COLUMN slot_name VARCHAR(255) NULL");
    $pdo->exec("ALTER TABLE delivery_slots ADD COLUMN start_time TIME NULL");
    $pdo->exec("ALTER TABLE delivery_slots ADD COLUMN end_time TIME NULL");
    echo "Added slot_name, start_time, and end_time columns to delivery_slots.\n";
} catch (Exception $e) {
    echo "Notice on delivery_slots structured columns migration: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE delivery_areas ADD COLUMN latitude DECIMAL(10,8) NULL");
    $pdo->exec("ALTER TABLE delivery_areas ADD COLUMN longitude DECIMAL(11,8) NULL");
    $pdo->exec("ALTER TABLE delivery_areas ADD COLUMN radius_meters INT NULL DEFAULT 5000");
    $pdo->exec("ALTER TABLE delivery_areas ADD COLUMN color VARCHAR(7) NULL DEFAULT '#3388ff'");
    echo "Added latitude, longitude, radius_meters, and color to delivery_areas.\n";
} catch (Exception $e) {
    echo "Notice on delivery_areas (lat/lng/radius/color): " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE delivery_areas ADD COLUMN polygon_data TEXT NULL");
    echo "Added polygon_data to delivery_areas.\n";
} catch (Exception $e) {
    echo "Notice on delivery_areas (polygon): " . $e->getMessage() . "\n";
}

try {
    // Seed default dispatch coordinates (Dhaka)
    $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'dispatch_lat'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('dispatch_lat', '23.8103')");
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('dispatch_lng', '90.4125')");
        echo "Added dispatch coordinates to settings.\n";
    }
} catch (Exception $e) {
    echo "Notice on settings dispatch point: " . $e->getMessage() . "\n";
}
?>
