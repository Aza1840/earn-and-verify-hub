
<?php
require_once 'includes/functions.php';
requireLogin();

$user = getCurrentUser();

// Get all news with read status
$stmt = $pdo->prepare("
    SELECT n.*, 
           CASE WHEN nr.completed = 1 THEN 1 ELSE 0 END as completed,
           nr.time_spent
    FROM news n 
    LEFT JOIN news_reads nr ON n.id = nr.news_id AND nr.user_id = ?
    WHERE n.is_active = 1 
    ORDER BY completed ASC, n.created_at DESC
");
$stmt->execute([$user['id']]);
$news = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News - <?php echo SITE_NAME; ?></title>
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
                <a class="nav-link active" href="news.php">News</a>
                <a class="nav-link" href="profile.php">Profile</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-newspaper me-2"></i>Latest News</h2>
            <div>
                <span class="badge bg-primary">Balance: $<?php echo number_format($user['balance'], 2); ?></span>
            </div>
        </div>

        <?php if (empty($news)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>No news articles available at the moment. Check back later!
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($news as $article): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <?php if ($article['image']): ?>
                                <img src="<?php echo UPLOAD_PATH . 'news/' . $article['image']; ?>" 
                                     class="card-img-top" alt="News Image" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 200px;">
                                    <i class="fas fa-newspaper fa-3x text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($article['title']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars(substr($article['content'], 0, 150)) . '...'; ?></p>
                                
                                <div class="mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>Required time: <?php echo $article['required_time']; ?> seconds
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge bg-success">
                                        <i class="fas fa-dollar-sign me-1"></i>Reward: $<?php echo number_format($article['reward'], 2); ?>
                                    </span>
                                    <?php if ($article['completed']): ?>
                                        <span class="badge bg-info ms-2">
                                            <i class="fas fa-check me-1"></i>Completed
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-auto">
                                    <?php if ($article['completed']): ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-check me-2"></i>Already Read
                                        </button>
                                    <?php else: ?>
                                        <a href="read-news.php?id=<?php echo $article['id']; ?>" class="btn btn-primary w-100">
                                            <i class="fas fa-book-open me-2"></i>Read Article
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
