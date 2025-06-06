
<?php
require_once 'config/config.php';

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['username']) && $_SESSION['username'] === 'admin';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Utility functions
function generateReferralCode() {
    return strtoupper(substr(uniqid(), -8));
}

function generateVerificationCode() {
    return strtoupper(substr(md5(uniqid()), 0, 8));
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function uploadFile($file, $directory) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $directory . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    
    return false;
}

// Video functions
function addVideoWatch($userId, $videoId, $reward) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Add watch record
        $stmt = $pdo->prepare("INSERT INTO video_watches (user_id, video_id, reward_earned) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $videoId, $reward]);
        
        // Update user balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?");
        $stmt->execute([$reward, $reward, $userId]);
        
        // Check for referral commission
        $user = getUserById($userId);
        if ($user['referred_by'] && $user['email_verified']) {
            $referrer = getUserById($user['referred_by']);
            if ($referrer) {
                $commission = $reward * 0.1; // 10% commission
                
                $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$commission, $user['referred_by']]);
                
                $stmt = $pdo->prepare("INSERT INTO referral_commissions (referrer_id, referred_id, commission_amount, commission_type) VALUES (?, ?, ?, 'earning')");
                $stmt->execute([$user['referred_by'], $userId, $commission]);
            }
        }
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

// News functions
function addNewsRead($userId, $newsId, $timeSpent, $requiredTime, $reward) {
    global $pdo;
    
    if ($timeSpent < $requiredTime) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update or insert news read record
        $stmt = $pdo->prepare("INSERT INTO news_reads (user_id, news_id, time_spent, reward_earned, completed, completed_at) VALUES (?, ?, ?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE time_spent = ?, completed = 1, completed_at = NOW(), reward_earned = ?");
        $stmt->execute([$userId, $newsId, $timeSpent, $reward, $timeSpent, $reward]);
        
        // Update user balance
        $stmt = $pdo->prepare("UPDATE users SET balance = balance + ?, total_earned = total_earned + ? WHERE id = ?");
        $stmt->execute([$reward, $reward, $userId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        return false;
    }
}

// User functions
function getUserById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function updateUserBalance($userId, $amount) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
    return $stmt->execute([$amount, $userId]);
}

// Email functions
function sendEmail($to, $subject, $body) {
    // Simple mail function - you can replace with PHPMailer for better functionality
    $headers = "From: " . SMTP_USERNAME . "\r\n";
    $headers .= "Reply-To: " . SMTP_USERNAME . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

function sendVerificationEmail($email, $token) {
    $verificationUrl = SITE_URL . "/verify-email.php?token=" . $token;
    $subject = "Verify your email address";
    $body = "
    <h2>Welcome to " . SITE_NAME . "!</h2>
    <p>Please click the link below to verify your email address:</p>
    <a href='{$verificationUrl}'>Verify Email</a>
    <p>If you didn't create an account, please ignore this email.</p>
    ";
    
    return sendEmail($email, $subject, $body);
}

// System settings
function getSetting($key, $default = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

function setSetting($key, $value) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}
?>
