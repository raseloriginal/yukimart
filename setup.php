<?php
// setup.php
$host = 'localhost';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `yukimart` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database 'yukimart' created or already exists.\n";
    
    $pdo->exec("USE `yukimart`");
    
    // Create users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'dsr', 'customer', 'wholesaler') NOT NULL DEFAULT 'customer',
        area_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (phone),
        INDEX (role)
    )");
    echo "Table 'users' created.\n";

    // Create categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        image_url VARCHAR(255) NULL,
        sort_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (is_active)
    )");
    echo "Table 'categories' created.\n";

    // Create products table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        regular_price DECIMAL(10,2) NOT NULL,
        wholesale_price DECIMAL(10,2) NOT NULL,
        image_url VARCHAR(255) NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        INDEX (category_id),
        INDEX (name),
        INDEX (is_active)
    )");
    echo "Table 'products' created.\n";

    // Create delivery_areas table
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_areas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        delivery_charge DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        is_active BOOLEAN DEFAULT TRUE,
        INDEX (is_active)
    )");
    echo "Table 'delivery_areas' created.\n";

    // Create delivery_slots table
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slot_name VARCHAR(255) NULL,
        start_time TIME NULL,
        end_time TIME NULL,
        slot_time VARCHAR(255) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE
    )");
    echo "Table 'delivery_slots' created.\n";

    // Create orders table
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        dsr_id INT NULL,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        shipping_address TEXT NOT NULL,
        area_id INT NOT NULL,
        slot_id INT NULL,
        total_items INT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        delivery_charge DECIMAL(10,2) NOT NULL,
        grand_total DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'processing', 'collected', 'out_for_delivery', 'completed', 'cancelled', 'due') DEFAULT 'pending',
        latitude DECIMAL(10,8) NULL,
        longitude DECIMAL(11,8) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (dsr_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (area_id) REFERENCES delivery_areas(id),
        FOREIGN KEY (slot_id) REFERENCES delivery_slots(id) ON DELETE SET NULL,
        INDEX (user_id),
        INDEX (dsr_id),
        INDEX (customer_phone),
        INDEX (area_id),
        INDEX (slot_id),
        INDEX (status),
        INDEX (created_at)
    )");
    echo "Table 'orders' created.\n";

    // Create order_items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        is_collected BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX (order_id),
        INDEX (product_id),
        INDEX (is_collected)
    )");
    echo "Table 'order_items' created.\n";

    // Seed Admin User
    $adminPhone = '01700000000';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$adminPhone]);
    if ($stmt->rowCount() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (name, phone, password, role) VALUES ('Admin', '$adminPhone', '$adminPass', 'admin')");
        echo "Default admin user created (Phone: $adminPhone, Pass: admin123).\n";
    }

    // Seed Sample Delivery Areas
    $stmt = $pdo->query("SELECT COUNT(*) FROM delivery_areas");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO delivery_areas (name, delivery_charge) VALUES 
            ('Dhanmondi', 60.00),
            ('Gulshan', 80.00),
            ('Banani', 80.00),
            ('Mirpur', 50.00),
            ('Uttara', 100.00)
        ");
        echo "Sample delivery areas seeded.\n";
    }

    // Seed Sample Delivery Slots
    $stmt = $pdo->query("SELECT COUNT(*) FROM delivery_slots");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO delivery_slots (slot_name, start_time, end_time, slot_time) VALUES 
            ('Morning Slot', '08:00:00', '10:00:00', 'Morning Slot (08:00 AM - 10:00 AM)'),
            ('Late Morning Slot', '10:00:00', '12:00:00', 'Late Morning Slot (10:00 AM - 12:00 PM)'),
            ('Afternoon Slot', '12:00:00', '14:00:00', 'Afternoon Slot (12:00 PM - 02:00 PM)'),
            ('Late Afternoon Slot', '14:00:00', '16:00:00', 'Late Afternoon Slot (02:00 PM - 04:00 PM)'),
            ('Evening Slot', '16:00:00', '18:00:00', 'Evening Slot (04:00 PM - 06:00 PM)')
        ");
        echo "Sample delivery slots seeded.\n";
    }
    
    // Seed Sample Categories
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO categories (name, image_url) VALUES 
            ('Vegetables', 'https://images.unsplash.com/photo-1566385101042-1a0aa0c1268c?w=200'),
            ('Fruits', 'https://images.unsplash.com/photo-1610832958506-aa56368176cf?w=200'),
            ('Meat & Fish', 'https://images.unsplash.com/photo-1603048297172-c92544798d5e?w=200'),
            ('Dairy', 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?w=200')
        ");
        echo "Sample categories seeded.\n";
    }

    // Seed Sample Products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO products (category_id, name, description, regular_price, wholesale_price, image_url) VALUES 
            (1, 'Fresh Tomato', 'Organic local tomatoes', 50.00, 40.00, 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=200'),
            (1, 'Potato', 'Regular size potatoes', 30.00, 25.00, 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=200'),
            (2, 'Apple (Gala)', 'Sweet imported apples', 250.00, 220.00, 'https://images.unsplash.com/photo-1560806887-1e4cd0b6faa6?w=200'),
            (3, 'Beef (Bone in)', 'Fresh cow meat', 750.00, 700.00, 'https://images.unsplash.com/photo-1603048297172-c92544798d5e?w=200')
        ");
        echo "Sample products seeded.\n";
    }

    echo "Setup completed successfully!\n";

} catch (\PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>
