
<?php
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$newsId = (int)($_GET['id'] ?? 0);

if (!$newsId) {
    header('Location: news.php');
    exit;
}

// Get news article
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ? AND is_active = 1");
$stmt->execute([$newsId]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: news.php');
    exit;
}

// Check if already completed
$stmt = $pdo->prepare("SELECT * FROM news_reads WHERE user_id = ? AND news_id = ?");
$stmt->execute([$user['id'], $newsId]);
$readRecord = $stmt->fetch();

// Handle AJAX requests for time tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'start_reading') {
        // Start or update reading session
        if (!$readRecord) {
            $stmt = $pdo->prepare("INSERT INTO news_reads (user_id, news_id, started_at) VALUES (?, ?, NOW())");
            $stmt->execute([$user['id'], $newsId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($_POST['action'] === 'update_time') {
        $timeSpent = (int)$_POST['time_spent'];
        
        if ($timeSpent >= $article['required_time'] && (!$readRecord || !$readRecord['completed'])) {
            // Reward user
            if (addNewsRead($user['id'], $newsId, $timeSpent, $article['required_time'], $article['reward'])) {
                echo json_encode(['success' => true, 'completed' => true, 'reward' => $article['reward']]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to process reward']);
            }
        } else {
            // Just update time
            $stmt = $pdo->prepare("UPDATE news_reads SET time_spent = ? WHERE user_id = ? AND news_id = ?");
            $stmt->execute([$timeSpent, $user['id'], $newsId]);
            echo json_encode(['success' => true, 'completed' => false]);
        }
        exit;
    }
}

$shareUrl = SITE_URL . '/read-news.php?id=' . $newsId;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="videos.php">Videos</a>
                <a class="nav-link" href="news.php">News</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <!-- Progress Bar -->
                <div class="card mb-4" id="progress-card" <?php echo ($readRecord && $readRecord['completed']) ? 'style="display:none"' : ''; ?>>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Reading Progress</span>
                            <span id="time-display">0 / <?php echo $article['required_time']; ?> seconds</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" id="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted">Stay on this page for <?php echo $article['required_time']; ?> seconds to earn $<?php echo number_format($article['reward'], 2); ?></small>
                    </div>
                </div>

                <!-- Reward Alert -->
                <div class="alert alert-success" id="reward-alert" style="display: none;">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="reward-message"></span>
                </div>

                <!-- Article -->
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <h3><?php echo htmlspecialchars($article['title']); ?></h3>
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-share me-1"></i>Share
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="https://facebook.com/sharer/sharer.php?u=<?php echo urlencode($shareUrl); ?>" target="_blank">
                                        <i class="fab fa-facebook me-2"></i>Facebook
                                    </a></li>
                                    <li><a class="dropdown-item" href="https://twitter.com/intent/tweet?url=<?php echo urlencode($shareUrl); ?>&text=<?php echo urlencode($article['title']); ?>" target="_blank">
                                        <i class="fab fa-twitter me-2"></i>Twitter
                                    </a></li>
                                    <li><a class="dropdown-item" href="https://wa.me/?text=<?php echo urlencode($article['title'] . ' - ' . $shareUrl); ?>" target="_blank">
                                        <i class="fab fa-whatsapp me-2"></i>WhatsApp
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="#" onclick="copyShareLink()">
                                        <i class="fas fa-copy me-2"></i>Copy Link
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-success">Reward: $<?php echo number_format($article['reward'], 2); ?></span>
                            <span class="badge bg-info">Required: <?php echo $article['required_time']; ?>s</span>
                            <?php if ($readRecord && $readRecord['completed']): ?>
                                <span class="badge bg-warning">Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($article['image']): ?>
                        <img src="<?php echo UPLOAD_PATH . 'news/' . $article['image']; ?>" 
                             class="card-img-top" alt="News Image" style="height: 300px; object-fit: cover;">
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <div class="article-content">
                            <?php echo nl2br(htmlspecialchars($article['content'])); ?>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="news.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to News
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let timeSpent = <?php echo $readRecord ? $readRecord['time_spent'] : 0; ?>;
        let requiredTime = <?php echo $article['required_time']; ?>;
        let completed = <?php echo ($readRecord && $readRecord['completed']) ? 'true' : 'false'; ?>;
        let timer;

        function startTimer() {
            // Notify server that reading started
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=start_reading'
            });

            timer = setInterval(function() {
                if (!completed) {
                    timeSpent++;
                    updateProgress();
                    
                    // Update server every 5 seconds
                    if (timeSpent % 5 === 0) {
                        updateServer();
                    }
                }
            }, 1000);
        }

        function updateProgress() {
            let progress = Math.min((timeSpent / requiredTime) * 100, 100);
            document.getElementById('progress-bar').style.width = progress + '%';
            document.getElementById('time-display').textContent = timeSpent + ' / ' + requiredTime + ' seconds';
            
            if (timeSpent >= requiredTime && !completed) {
                document.getElementById('progress-bar').classList.add('bg-success');
            }
        }

        function updateServer() {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=update_time&time_spent=' + timeSpent
            })
            .then(response => response.json())
            .then(data => {
                if (data.completed && !completed) {
                    completed = true;
                    clearInterval(timer);
                    showReward(data.reward);
                }
            });
        }

        function showReward(reward) {
            document.getElementById('progress-card').style.display = 'none';
            document.getElementById('reward-alert').style.display = 'block';
            document.getElementById('reward-message').textContent = 
                'Congratulations! You have earned $' + parseFloat(reward).toFixed(2) + ' for reading this article!';
        }

        function copyShareLink() {
            navigator.clipboard.writeText('<?php echo $shareUrl; ?>');
            alert('Share link copied to clipboard!');
        }

        // Start timer when page loads
        if (!completed) {
            startTimer();
        }

        // Stop timer when page is about to unload
        window.addEventListener('beforeunload', function() {
            if (timer) {
                clearInterval(timer);
                updateServer();
            }
        });

        // Handle page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (timer) clearInterval(timer);
            } else {
                if (!completed) startTimer();
            }
        });
    </script>
</body>
</html>
