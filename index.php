
<?php
require_once 'includes/functions.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Earn Money by Watching Videos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        .feature-card {
            transition: transform 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-coins me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="login.php">Login</a>
                <a class="nav-link" href="register.php">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">Earn Money by Watching Videos & Reading News</h1>
            <p class="lead mb-4">Join thousands of users who are earning money daily by completing simple tasks</p>
            <a href="register.php" class="btn btn-light btn-lg">
                <i class="fas fa-user-plus me-2"></i>Get Started Now
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100 text-center">
                        <div class="card-body">
                            <i class="fas fa-video text-primary fa-3x mb-3"></i>
                            <h5 class="card-title">Watch Videos</h5>
                            <p class="card-text">Earn money by watching short videos and verifying your completion</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100 text-center">
                        <div class="card-body">
                            <i class="fas fa-newspaper text-success fa-3x mb-3"></i>
                            <h5 class="card-title">Read News</h5>
                            <p class="card-text">Stay informed and earn rewards by reading news articles</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100 text-center">
                        <div class="card-body">
                            <i class="fas fa-users text-warning fa-3x mb-3"></i>
                            <h5 class="card-title">Refer Friends</h5>
                            <p class="card-text">Earn commission when your referrals complete tasks</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="bg-light py-5">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 mb-3">
                    <h3 class="text-primary">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE email_verified = 1");
                        echo number_format($stmt->fetch()['count']);
                        ?>
                    </h3>
                    <p class="mb-0">Verified Users</p>
                </div>
                <div class="col-md-3 mb-3">
                    <h3 class="text-success">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM videos WHERE is_active = 1");
                        echo number_format($stmt->fetch()['count']);
                        ?>
                    </h3>
                    <p class="mb-0">Active Videos</p>
                </div>
                <div class="col-md-3 mb-3">
                    <h3 class="text-warning">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM news WHERE is_active = 1");
                        echo number_format($stmt->fetch()['count']);
                        ?>
                    </h3>
                    <p class="mb-0">News Articles</p>
                </div>
                <div class="col-md-3 mb-3">
                    <h3 class="text-info">
                        $<?php
                        $stmt = $pdo->query("SELECT SUM(total_earned) as total FROM users");
                        echo number_format($stmt->fetch()['total'] ?? 0, 2);
                        ?>
                    </h3>
                    <p class="mb-0">Total Paid Out</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; 2024 <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
