
<?php
// Database configuration
// Update these values with your cPanel database credentials

define('DB_HOST', 'localhost');
define('DB_NAME', 'earn_verify_hub');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
