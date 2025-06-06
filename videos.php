
<?php
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();

// Get all videos with watch status
$stmt = $pdo->prepare("
    SELECT v.*, 
           CASE WHEN vw.id IS NOT NULL THEN 1 ELSE 0 END as watched
    FROM videos v 
    LEFT JOIN video_watches vw ON v.id = vw.video_id AND vw.user_id = ?
    WHERE v.is_active = 1 
    ORDER BY watched ASC, v.created_at DESC
");
$stmt->execute([$user['id']]);
$videos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videos - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link active" href="videos.php">Videos</a>
                <a class="nav-link" href="news.php">News</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-video me-2"></i>Available Videos</h2>
            <div>
                <span class="badge bg-primary">Balance: $<?php echo number_format($user['balance'], 2); ?></span>
            </div>
        </div>

        <?php if (empty($videos)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No videos available at the moment. Check back later!
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($videos as $video): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($video['thumbnail']): ?>
                                <img src="<?php echo UPLOAD_PATH . 'thumbnails/' . $video['thumbnail']; ?>" 
                                     class="card-img-top" alt="Video Thumbnail" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                    <i class="fas fa-video fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($video['title']); ?></h5>
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>Duration: <?php echo $video['duration']; ?>
                                    </small>
                                </div>
                                <div class="mb-3">
                                    <span class="badge bg-success">
                                        <i class="fas fa-dollar-sign me-1"></i>Reward: $<?php echo number_format($video['reward'], 2); ?>
                                    </span>
                                </div>
                                
                                <div class="mt-auto">
                                    <?php if ($video['watched']): ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-check me-2"></i>Already Watched
                                        </button>
                                    <?php else: ?>
                                        <a href="watch-video.php?id=<?php echo $video['id']; ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-play me-2"></i>Watch Video
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
