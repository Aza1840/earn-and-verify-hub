
<?php
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $fullName = sanitizeInput($_POST['full_name']);
    $referralCode = sanitizeInput($_POST['referral_code'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        $error = 'Please fill in all required fields';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            $error = 'Username or email already exists';
        } else {
            try {
                $pdo->beginTransaction();
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $verificationToken = bin2hex(random_bytes(32));
                $userReferralCode = generateReferralCode();
                $referredBy = null;
                
                // Check if referral code is valid
                if (!empty($referralCode)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                    $stmt->execute([$referralCode]);
                    $referrer = $stmt->fetch();
                    if ($referrer) {
                        $referredBy = $referrer['id'];
                    }
                }
                
                // Insert new user
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, referral_code, referred_by, email_verification_token) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashedPassword, $fullName, $userReferralCode, $referredBy, $verificationToken]);
                
                $userId = $pdo->lastInsertId();
                
                // Send verification email
                sendVerificationEmail($email, $verificationToken);
                
                $pdo->commit();
                $success = 'Registration successful! Please check your email to verify your account.';
                
            } catch (Exception $e) {
                $pdo->rollback();
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Create Your Account</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label for="referral_code" class="form-label">Referral Code (Optional)</label>
                                <input type="text" class="form-control" id="referral_code" name="referral_code">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Register</button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                            <p><a href="index.php">Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
