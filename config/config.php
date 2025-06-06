
<?php
session_start();

// Site configuration
define('SITE_URL', 'https://yourdomain.com'); // Update with your domain
define('SITE_NAME', 'Earn & Verify Hub');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Email configuration (update with your SMTP settings)
define('SMTP_HOST', 'your_smtp_host');
define('SMTP_USERNAME', 'your_email@yourdomain.com');
define('SMTP_PASSWORD', 'your_email_password');
define('SMTP_PORT', 587);

// Include database connection
require_once 'database.php';

// Auto-create upload directories
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(UPLOAD_PATH . 'thumbnails/')) {
    mkdir(UPLOAD_PATH . 'thumbnails/', 0755, true);
}
if (!file_exists(UPLOAD_PATH . 'profiles/')) {
    mkdir(UPLOAD_PATH . 'profiles/', 0755, true);
}
if (!file_exists(UPLOAD_PATH . 'news/')) {
    mkdir(UPLOAD_PATH . 'news/', 0755, true);
}
?>
