
<?php
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$stats = [
    'total_videos' => 0,
    'watched_videos' => 0,
    'total_news' => 0,
    'read_news' => 0,
    'referrals' => 0
];

// Get user stats
$stmt = $pdo->query("SELECT COUNT(*) as count FROM videos WHERE is_active = 1");
$stats['total_videos'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM video_watches WHERE user_id = ?");
$stmt->execute([$user['id']]);
$stats['watched_videos'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM news WHERE is_active = 1");
$stats['total_news'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM news_reads WHERE user_id = ? AND completed = 1");
$stmt->execute([$user['id']]);
$stats['read_news'] = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE referred_by = ?");
$stmt->execute([$user['id']]);
$stats['referrals'] = $stmt->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-coins me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="videos.php">Videos</a>
                <a class="nav-link" href="news.php">News</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- User Info -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="<?php echo UPLOAD_PATH . 'profiles/' . $user['profile_picture']; ?>" 
                                         class="rounded-circle" width="80" height="80" alt="Profile">
                                <?php else: ?>
                                    <i class="fas fa-user-circle fa-5x"></i>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h4>Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h4>
                                <p class="mb-1">Username: <?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="mb-0">
                                    Status: 
                                    <?php if ($user['is_premium']): ?>
                                        <span class="badge bg-warning">Premium</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Free</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <h3>$<?php echo number_format($user['balance'], 2); ?></h3>
                                <p class="mb-0">Available Balance</p>
                                <small>Total Earned: $<?php echo number_format($user['total_earned'], 2); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-video text-primary fa-2x mb-2"></i>
                        <h5><?php echo $stats['watched_videos']; ?> / <?php echo $stats['total_videos']; ?></h5>
                        <p class="mb-0">Videos Watched</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-newspaper text-success fa-2x mb-2"></i>
                        <h5><?php echo $stats['read_news']; ?> / <?php echo $stats['total_news']; ?></h5>
                        <p class="mb-0">News Read</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users text-warning fa-2x mb-2"></i>
                        <h5><?php echo $stats['referrals']; ?></h5>
                        <p class="mb-0">Referrals</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-crown text-info fa-2x mb-2"></i>
                        <h5>
                            <?php if ($user['is_premium']): ?>
                                Active
                            <?php else: ?>
                                <a href="premium.php" class="btn btn-sm btn-warning">Upgrade</a>
                            <?php endif; ?>
                        </h5>
                        <p class="mb-0">Premium Status</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-video me-2"></i>Latest Videos</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT v.* FROM videos v 
                            LEFT JOIN video_watches vw ON v.id = vw.video_id AND vw.user_id = ?
                            WHERE v.is_active = 1 AND vw.id IS NULL 
                            ORDER BY v.created_at DESC LIMIT 3
                        ");
                        $stmt->execute([$user['id']]);
                        $videos = $stmt->fetchAll();
                        
                        if ($videos): ?>
                            <?php foreach ($videos as $video): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($video['title']); ?></h6>
                                        <small class="text-muted">Reward: $<?php echo number_format($video['reward'], 2); ?></small>
                                    </div>
                                    <a href="watch-video.php?id=<?php echo $video['id']; ?>" class="btn btn-sm btn-primary">Watch</a>
                                </div>
                            <?php endforeach; ?>
                            <a href="videos.php" class="btn btn-outline-primary btn-sm">View All Videos</a>
                        <?php else: ?>
                            <p class="text-muted">No new videos available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-newspaper me-2"></i>Latest News</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT n.* FROM news n 
                            LEFT JOIN news_reads nr ON n.id = nr.news_id AND nr.user_id = ?
                            WHERE n.is_active = 1 AND nr.id IS NULL 
                            ORDER BY n.created_at DESC LIMIT 3
                        ");
                        $stmt->execute([$user['id']]);
                        $news = $stmt->fetchAll();
                        
                        if ($news): ?>
                            <?php foreach ($news as $article): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($article['title']); ?></h6>
                                        <small class="text-muted">Reward: $<?php echo number_format($article['reward'], 2); ?></small>
                                    </div>
                                    <a href="read-news.php?id=<?php echo $article['id']; ?>" class="btn btn-sm btn-success">Read</a>
                                </div>
                            <?php endforeach; ?>
                            <a href="news.php" class="btn btn-outline-success btn-sm">View All News</a>
                        <?php else: ?>
                            <p class="text-muted">No new articles available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Referral Section -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-share-alt me-2"></i>Referral Program</h5>
                    </div>
                    <div class="card-body">
                        <p>Share your referral code and earn commission on your referrals' activities!</p>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="<?php echo $user['referral_code']; ?>" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyReferralCode()">Copy Code</button>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" value="<?php echo SITE_URL; ?>/register.php?ref=<?php echo $user['referral_code']; ?>" readonly>
                            <button class="btn btn-outline-secondary" onclick="copyReferralLink()">Copy Link</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyReferralCode() {
            navigator.clipboard.writeText('<?php echo $user['referral_code']; ?>');
            alert('Referral code copied!');
        }
        
        function copyReferralLink() {
            navigator.clipboard.writeText('<?php echo SITE_URL; ?>/register.php?ref=<?php echo $user['referral_code']; ?>');
            alert('Referral link copied!');
        }
    </script>
</body>
</html>
