<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// (Assumes $conn is already available from your bootstrap/index.)
// If not, include your DB connection: require_once __DIR__ . '/path/to/db.php';

// Get site settings
$settings = [];
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'site_description')");
$stmt->execute();
$stmt->bind_result($key, $value);
while ($stmt->fetch()) {
    $settings[$key] = $value;
}
$stmt->close();

// Get stats for homepage
$total_users = 0;
$total_tasks = 0;
$total_completed = 0;
$total_promotion_completions = 0;

// Count total users
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'user'");
$stmt->execute();
$stmt->bind_result($total_users);
$stmt->fetch();
$stmt->close();

// Count regular tasks
$stmt = $conn->prepare("SELECT COUNT(*) FROM tasks");
$stmt->execute();
$stmt->bind_result($total_tasks);
$stmt->fetch();
$stmt->close();

// Count active promotion ads as tasks
$stmt = $conn->prepare("SELECT COUNT(*) FROM promotion_ads WHERE status = 'active' AND remaining_budget >= reward_per_user");
$stmt->execute();
$stmt->bind_result($promotion_count);
$stmt->fetch();
$stmt->close();
$total_tasks += $promotion_count;

// Count regular task completions
$stmt = $conn->prepare("SELECT COUNT(*) FROM task_completions");
$stmt->execute();
$stmt->bind_result($total_completed);
$stmt->fetch();
$stmt->close();

// Count promotion ad completions
$stmt = $conn->prepare("SELECT COUNT(*) FROM promotion_ad_completions WHERE status = 'approved'");
$stmt->execute();
$stmt->bind_result($total_promotion_completions);
$stmt->fetch();
$stmt->close();

// Count daily reward completions
$stmt = $conn->prepare("SELECT COUNT(*) FROM reward_log");
$stmt->execute();
$stmt->bind_result($daily_reward_completions);
$stmt->fetch();
$stmt->close();

// Total completed = regular tasks + promotion completions + daily rewards
$total_completed = $total_completed + $total_promotion_completions + $daily_reward_completions;

// ===============================
// Featured items (Tasks + Promos)
// ===============================
$featured_tasks = [];

// 1) Pull regular tasks (limit 20 newest)
$stmt = $conn->prepare("
    SELECT id, title, description, reward, type, is_premium, thumbnail, created_at 
    FROM tasks 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute();
$stmt->bind_result($id, $title, $description, $reward, $type, $is_premium, $thumbnail, $created_at);
while ($stmt->fetch()) {
    $featured_tasks[] = [
        'id'            => (int)$id,
        'title'         => $title,
        'description'   => $description,
        'reward'        => (float)$reward,
        'type'          => $type,             // 'video', 'news', etc.
        'is_premium'    => (int)$is_premium,
        'thumbnail'     => $thumbnail,
        'created_at'    => $created_at,
        'source'        => 'regular'
    ];
}
$stmt->close();

// 2) Pull active promotion ads (same criteria as tasks.php)
$promotion_ads = [];
$stmt = $conn->prepare("
    SELECT id, title, description, social_platform, social_url, task_type, reward_per_user, thumbnail_url, created_at
    FROM promotion_ads
    WHERE status = 'active' 
      AND remaining_budget >= reward_per_user
    ORDER BY created_at DESC
    LIMIT 20
");
$stmt->execute();
$stmt->bind_result($pid, $ptitle, $pdescription, $psocial_platform, $psocial_url, $ptask_type, $preward_per_user, $pthumbnail_url, $pcreated_at);
while ($stmt->fetch()) {
    $promotion_ads[] = [
        'id'              => (int)$pid,
        'title'           => $ptitle,
        'description'     => $pdescription,
        'social_platform' => $psocial_platform,
        'url'             => $psocial_url,
        'task_type'       => $ptask_type,
        'reward'          => (float)$preward_per_user,
        'thumbnail'       => $pthumbnail_url,
        'created_at'      => $pcreated_at,
        'source'          => 'promotion',
        'type'            => 'promotion', // important for button logic below
        'is_premium'      => 0
    ];
}
$stmt->close();

// 3) Merge & sort by created_at (newest first). Limit to 20 total for the homepage grid.
$featured_tasks = array_merge($featured_tasks, $promotion_ads);
usort($featured_tasks, function ($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
$featured_tasks = array_slice($featured_tasks, 0, 20);
?>
<style>
  body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #fdfdfd;
    background-image: url("data:image/svg+xml,%3Csvg width='6' height='6' viewBox='0 0 6 6' xmlns='http://www.w3.org/2000/svg'%3E%3Ccircle cx='1' cy='1' r='1' fill='%23000055' fill-opacity='0.05' /%3E%3C/svg%3E");
  }
  .border-premium { border: 2px solid #facc15; }
</style>

<!-- Hero Section -->
<div class="hero-section position-relative text-white py-5" style="background: url('uploads/banner.jpg') center center / cover no-repeat;">
    <div class="overlay position-absolute top-0 start-0 w-100 h-100" style="background-color: rgba(0,0,0,0.5); z-index: 1;"></div>

    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">
                    <?php echo isset($settings['site_name']) ? htmlspecialchars($settings['site_name']) : 'VIDEXCEL'; ?>
                </h1>
                <p class="lead mb-4">
                    <?php echo isset($settings['site_description']) ? htmlspecialchars($settings['site_description']) : 'Earn rewards by completing tasks and referring friends'; ?>
                </p>
                <div class="d-grid gap-2 d-md-flex">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register" class="btn btn-primary btn-lg px-4">Get Started</a>
                        <a href="login" class="btn btn-outline-primary btn-lg px-4">Sign In</a>
                    <?php else: ?>
                        <a href="dashboard" class="btn btn-primary btn-lg px-4">Go to Dashboard</a>
                        <a href="logout" class="btn btn-outline-primary btn-lg px-4">Log Out</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <img src="assets/images/hero-image.png" alt="VIDEXCEL" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<!-- Stats Section -->
<div class="py-5 bg-light" style="background-image: url('data:image/svg+xml,%3Csvg width=\'6\' height=\'6\' viewBox=\'0 0 6 6\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Ccircle cx=\'1\' cy=\'1\' r=\'1\' fill=\'%23000055\' fill-opacity=\'0.05\' /%3E%3C/svg%3E'); background-color: #fdfdfd;">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <i class="fas fa-users fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold"><?php echo number_format($total_users); ?>+</h2>
                        <p class="text-muted">Active Users</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <i class="fas fa-tasks fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold"><?php echo number_format($total_tasks); ?>+</h2>
                        <p class="text-muted">Available Tasks</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-3x text-primary mb-3"></i>
                        <h2 class="fw-bold"><?php echo number_format($total_completed); ?>+</h2>
                        <p class="text-muted">Tasks Completed</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- How It Works Section -->
<div class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">How It Works</h2>
            <p class="lead text-muted">Start earning rewards in just three simple steps</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-user-plus fa-2x"></i>
                        </div>
                        <h5 class="card-title">1. Create Account</h5>
                        <p class="card-text">Sign up for free and create your account in just a few seconds.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-play-circle fa-2x"></i>
                        </div>
                        <h5 class="card-title">2. Complete Tasks</h5>
                        <p class="card-text">Watch videos, read articles, and complete simple tasks to earn USDT.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-inline-flex justify-content-center align-items-center mb-3" style="width: 60px; height: 60px;">
                            <i class="fas fa-wallet fa-2x"></i>
                        </div>
                        <h5 class="card-title">3. Get Rewards</h5>
                        <p class="card-text">Convert your USDT and withdraw to your preferred cryptocurrency.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Featured Tasks Section -->
<?php if (!empty($featured_tasks)): ?>
<div class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Featured Tasks</h2>
            <p class="lead text-muted">Start earning with these popular tasks</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($featured_tasks as $task): ?>
            <div class="col-md-4">
                <div class="card h-100 task-card <?php echo !empty($task['is_premium']) ? 'border-premium' : ''; ?>">
                    <img src="<?php echo !empty($task['thumbnail']) ? htmlspecialchars($task['thumbnail']) : 'assets/images/placeholder.jpg'; ?>" 
                         class="card-img-top" alt="<?php echo htmlspecialchars($task['title']); ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="badge <?php echo ($task['type'] ?? '') === 'video' ? 'bg-primary' : (($task['type'] ?? '') === 'promotion' ? 'bg-success' : 'bg-info'); ?>">
                                <?php echo ucfirst($task['type'] ?? 'task'); ?>
                            </span>
                            <?php if (!empty($task['is_premium'])): ?>
                            <span class="badge bg-warning">Premium</span>
                            <?php endif; ?>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($task['title']); ?></h5>
                        <p class="card-text text-muted">
                            <?php 
                              $desc = $task['description'] ?? '';
                              echo htmlspecialchars(mb_strimwidth($desc, 0, 80, strlen($desc) > 80 ? '...' : ''));
                            ?>
                        </p>
                        <p class="card-text">
                            <strong>Reward:</strong> <?php echo number_format((float)$task['reward'], 3); ?> USDT
                        </p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="register" class="btn btn-primary w-100">Sign Up to Complete</a>
                        <?php else: ?>
                            <?php if (($task['type'] ?? '') === 'promotion'): ?>
                                <!-- For promotion ads, send users to the Tasks page list (tasks.php handles promotions) -->
                                <a href="tasks" class="btn btn-success w-100">Start Task</a>
                            <?php else: ?>
                                <!-- For regular tasks, deep link by id (your tasks.php supports this) -->
                                <a href="index.php?page=tasks&id=<?php echo (int)$task['id']; ?>" class="btn btn-success w-100">Start Task</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-5">
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="register" class="btn btn-lg btn-outline-primary">View All Tasks</a>
            <?php else: ?>
                <a href="tasks" class="btn btn-lg btn-outline-success">Go to Tasks</a>
            <?php endif; ?>
        </div>

        <!-- Full-width Clickable Banner with Sharp Edges -->
        <div class="mt-4 mb-5" style="margin-left: -15px; margin-right: -15px;">
            <a href="promote_dashboard" target="_blank">
                <img src="assets/images/banner-social-media.jpg" alt="Grow your social media page" style="width: 100%; display: block; border-radius: 0;" />
            </a>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- Testimonials Section -->
<div class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">What Users Say</h2>
            <p class="lead text-muted">Join thousands of satisfied users earning rewards</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="card-text mb-4">"I've been using EarnRewards for 3 months and have already earned enough for several crypto withdrawals. The tasks are easy and the platform is reliable."</p>
                        <div class="d-flex align-items-center">
                            <img src="https://i.pravatar.cc/40?img=1" alt="User" class="rounded-circle me-3">
                            <div>
                                <h6 class="mb-0">Sarah Johnson</h6>
                                <small class="text-muted">Premium Member</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="card-text mb-4">"The best rewards platform I've found. Fast payments, great support team, and lots of easy tasks. I recommend it to all my friends!"</p>
                        <div class="d-flex align-items-center">
                            <img src="https://i.pravatar.cc/40?img=2" alt="User" class="rounded-circle me-3">
                            <div>
                                <h6 class="mb-0">Michael Rodriguez</h6>
                                <small class="text-muted">User since 2022</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star-half-alt text-warning"></i>
                        </div>
                        <p class="card-text mb-4">"I love how easy it is to earn here. The referral program is especially great - I've earned a lot just by inviting my friends. Payments are always on time."</p>
                        <div class="d-flex align-items-center">
                            <img src="https://i.pravatar.cc/40?img=3" alt="User" class="rounded-circle me-3">
                            <div>
                                <h6 class="mb-0">Emma Wilson</h6>
                                <small class="text-muted">Top Referrer</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CTA Section -->
<div class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-4 mb-lg-0">
                <h2 class="fw-bold mb-3">Ready to Start Earning?</h2>
                <p class="lead mb-0">Join thousands of users who are already earning rewards by completing simple tasks.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register" class="btn btn-light btn-lg px-4">Create Free Account</a>
                <?php else: ?>
                    <a href="tasks" class="btn btn-light btn-lg px-4">Start Earning Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
