<?php
session_start();
require_once '../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (empty($data['phone']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Phone and password are required.']);
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT id, name, phone, password, role FROM users WHERE phone = ?");
    $stmt->execute([$data['phone']]);
    $user = $stmt->fetch();

    if ($user && password_verify($data['password'], $user['password'])) {
        // Login successful
        $role = $user['role'];
        $_SESSION[$role . '_id'] = $user['id'];
        $_SESSION[$role . '_role'] = $user['role'];
        $_SESSION[$role . '_name'] = $user['name'];

        echo json_encode([
            'success' => true, 
            'message' => 'Login successful', 
            'role' => $user['role'],
            'redirect' => '/' . ($user['role'] === 'customer' ? '' : $user['role'] . '/')
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid phone or password.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
