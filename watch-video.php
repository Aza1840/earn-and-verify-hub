
<?php
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();
$videoId = (int)($_GET['id'] ?? 0);

if (!$videoId) {
    header('Location: videos.php');
    exit;
}

// Get video details
$stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND is_active = 1");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: videos.php');
    exit;
}

// Check if already watched
$stmt = $pdo->prepare("SELECT id FROM video_watches WHERE user_id = ? AND video_id = ?");
$stmt->execute([$user['id'], $videoId]);
if ($stmt->fetch()) {
    header('Location: videos.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verificationCode = strtoupper(sanitizeInput($_POST['verification_code']));
    
    if ($verificationCode === $video['verification_code']) {
        if (addVideoWatch($user['id'], $videoId, $video['reward'])) {
            $success = "Congratulations! You've earned $" . number_format($video['reward'], 2) . "!";
        } else {
            $error = 'Failed to process reward. Please try again.';
        }
    } else {
        $error = 'Invalid verification code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?> - <?php echo SITE_NAME; ?></title>
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
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo htmlspecialchars($video['title']); ?></h4>
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-success">Reward: $<?php echo number_format($video['reward'], 2); ?></span>
                            <span class="badge bg-info">Duration: <?php echo $video['duration']; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="videos.php" class="btn btn-primary">Watch More Videos</a>
                                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <h5><i class="fas fa-play-circle me-2"></i>Instructions:</h5>
                                <ol>
                                    <li>Click the "Watch Video" button to open the video in a new tab</li>
                                    <li>Watch the entire video</li>
                                    <li>Look for the verification code in the video</li>
                                    <li>Come back and enter the code below to earn your reward</li>
                                </ol>
                            </div>

                            <div class="text-center mb-4">
                                <a href="<?php echo htmlspecialchars($video['redirect_link']); ?>" 
                                   target="_blank" class="btn btn-lg btn-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>Watch Video
                                </a>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="verification_code" class="form-label">
                                        <strong>Enter Verification Code:</strong>
                                    </label>
                                    <input type="text" class="form-control" id="verification_code" 
                                           name="verification_code" placeholder="Enter code from video" required>
                                    <div class="form-text">
                                        The verification code should appear in the video. It's usually displayed as text overlay.
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-check me-2"></i>Verify & Earn Reward
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="videos.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Videos
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
