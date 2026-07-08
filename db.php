<?php
// db.php
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$http_host = $_SERVER['HTTP_HOST'] ?? '';

if (strpos($server_name, 'localhost') !== false || strpos($http_host, 'localhost') !== false || $server_name === '127.0.0.1' || $http_host === '127.0.0.1') {
    $host = 'localhost';
    $db   = 'yukimart';
    $user = 'root';
    $pass = '';
} else {
    $host = 'localhost';
    $db   = 'rasedwwq_yukimart';
    $user = 'rasedwwq_yukimart';
    $pass = 'xYKDtznPec!KbDe3';
}
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // First, connect without database to allow creation if it doesn't exist
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Select database (setup.php will handle creation if it fails here)
    $pdo->exec("USE `$db`");
} catch (\PDOException $e) {
    // We catch it silently here so setup.php can handle the initial run smoothly
    // In production, you would handle this more strictly
}

// Helper function to return PDO instance ensuring DB is selected
function getDB() {
    global $host, $db, $user, $pass, $charset, $options;
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}
?>
