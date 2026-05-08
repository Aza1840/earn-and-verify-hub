<?php
/**
 * Dashboard Page - Main Entry Point
 * Refactored into modular partial files for easy editing
 * 
 * Partials:
 *   dashboard/data.php              - All database queries and data preparation
 *   dashboard/styles.php            - Base layout & dark mode styles
 *   dashboard/styles_assets.php     - Total assets card & currency modal styles
 *   dashboard/styles_neon_cards.php - Neon glassmorphism card styles
 */

// 1. Load all data
require_once __DIR__ . '/data.php';

// 2. Load styles
include __DIR__ . '/styles.php';
include __DIR__ . '/styles_assets.php';
include __DIR__ . '/styles_neon_cards.php';
?>

<body>

<div class="profile-header">
  <div class="cover-photo" style="background-image: url('<?php echo htmlspecialchars($user['cover_photo'] ?? 'assets/images/default-cover.jpg'); ?>');">
    <form id="coverForm" action="upload_cover.php" method="POST" enctype="multipart/form-data" style="position: absolute; top: 8px; left: 8px;">
      <label for="cover-upload" style="cursor: pointer; background: rgba(0,0,0,0.6); color: white; padding: 5px 8px; border-radius: 4px; font-size: 12px;">
        <i class="fas fa-camera"></i> Cover
      </label>
      <input id="cover-upload" type="file" name="cover_photo" accept="image/*" style="display: none;">
    </form>
    <img id="coverLoader" class="loader" src="assets/images/loader.gif" alt="Loading...">
    <div class="profile-pic-wrapper">
      <img id="profilePic" src="<?php echo htmlspecialchars(!empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/images/default-avatar.png'); ?>" alt="Profile Picture">
      <form id="profileForm" action="upload_profile.php" method="POST" enctype="multipart/form-data">
        <label class="upload-icon" for="profile-upload">
          <i class="fas fa-camera"></i>
          <input id="profile-upload" type="file" name="profile_picture" accept="image/*">
        </label>
      </form>
      <img id="profileLoader" class="loader" src="assets/images/loader.gif" alt="Loading...">
    </div>
  </div>

  <div class="profile-info">
    <div class="profile-name d-flex align-items-center">
      <?php echo htmlspecialchars($user['username']); ?>
      <?php if (!empty($user['is_kyc_verified'])): ?>
        <img src="assets/images/kyc-badge.png" alt="KYC Verified" title="KYC Verified">
      <?php endif; ?>
      <?php if (!empty($user['is_premium'])): ?>
        <img src="assets/images/verified-badge.png" alt="Premium Verified" title="Premium Verified">
      <?php endif; ?>
      <span class="badge bg-primary text-white" id="countdownBadge">00:00:00:00</span>
      <a href="daily_reward" class="btn btn-success" style="font-size: 11px; padding: 4px 8px; margin-left: 8px;">CHECK-IN</a>
      <a href="/promote_dashboard" class="btn create-task-btn" style="margin-left: 8px;">
        <i class="fas fa-plus-circle me-1"></i> CREATE TASK
      </a>
    </div>
    <form method="post" action="/logout">
      <button class="logout-button" type="submit">Logout</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const key = "nextRewardTime";
    const now = new Date().getTime();
    let end = localStorage.getItem(key);
    if (!end || now > parseInt(end)) {
        end = now + 24 * 60 * 60 * 1000;
        localStorage.setItem(key, end);
    } else {
        end = parseInt(end);
    }
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = end - now;
        if (distance <= 0) { document.getElementById("countdownBadge").textContent = "00:00:00:00"; return; }
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        document.getElementById("countdownBadge").textContent =
            String(days).padStart(2, '0') + ":" + String(hours).padStart(2, '0') + ":" +
            String(minutes).padStart(2, '0') + ":" + String(seconds).padStart(2, '0');
    }
    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<div class="container-fluid">
    <div class="stats-container">
        <!-- Total Assets Card -->
        <div class="total-assets-card" id="totalAssetsCard">
            <div class="total-assets-header">
                <span>Total Assets</span>
                <button class="visibility-toggle" id="toggleBalance" title="Toggle visibility">
                    <i class="fas fa-eye" id="visibilityIcon"></i>
                </button>
            </div>
            <div class="balance-display" id="balanceDisplay">
                <div class="balance-left">
                    <div class="main-balance">
                        <span class="amount" id="mainBalance"><?php echo number_format((float)($user['balance'] ?? 0), 2); ?></span>
                        <button class="currency-selector-btn" id="currencySelectBtn" type="button">
                            <span id="selectedCurrencyDisplay">USD</span>
                            <span class="chevron">▼</span>
                        </button>
                        <input type="hidden" id="currencySelect" value="USD">
                    </div>
                    <div class="btc-equivalent">
                        <span>≈</span>
                        <span class="btc-value" id="btcEquivalent"><?php echo number_format((float)($user['balance'] ?? 0) / 97000, 8); ?></span>
                        <span>BTC</span>
                        <i class="fas fa-question-circle" style="font-size: 12px; cursor: help;" title="Approximate BTC equivalent"></i>
                    </div>
                    <div class="todays-pnl">
                        <span class="label">Today's P&L</span>
                        <span class="value <?php echo $todays_pnl > 0 ? 'positive' : ($todays_pnl < 0 ? 'negative' : 'neutral'); ?>">
                            <?php echo $todays_pnl >= 0 ? '+' : ''; ?><?php echo number_format($todays_pnl, 2); ?> USD
                            (<?php echo $todays_pnl >= 0 ? '+' : ''; ?><?php echo number_format($pnl_percentage, 2); ?>%)
                        </span>
                        <i class="fas fa-chevron-right chevron-right" style="font-size: 12px;"></i>
                    </div>
                </div>
                <div class="chart-container">
                    <svg class="trend-chart" viewBox="0 0 120 50" preserveAspectRatio="none">
                        <path id="trendPath" class="<?php echo $todays_pnl > 0 ? 'positive-trend' : ($todays_pnl < 0 ? 'negative-trend' : 'neutral-trend'); ?>" 
                              d="M0,45 Q10,42 20,40 T40,35 T60,30 T80,25 T100,20 T120,<?php echo $todays_pnl >= 0 ? '10' : '40'; ?>"></path>
                    </svg>
                </div>
            </div>
            <div class="assets-actions">
                <a href="/wallet" class="btn btn-primary"><i class="fas fa-wallet me-1"></i> Wallet</a>
                <a href="/withdraw" class="btn btn-outline-light"><i class="fas fa-arrow-up me-1"></i> Withdraw</a>
            </div>
        </div>

        <!-- Currency Selection Modal -->
        <div class="currency-modal-overlay" id="currencyModal">
            <div class="currency-modal">
                <div class="currency-modal-header">
                    <h3>Select a Currency</h3>
                    <button class="currency-modal-close" id="closeCurrencyModal">&times;</button>
                </div>
                <div class="currency-modal-body">
                    <div class="currency-section-title">Most Used</div>
                    <div class="currency-grid" id="mostUsedCurrencies">
                        <div class="currency-option selected" data-currency="USD">USD</div>
                    </div>
                    <div class="currency-section-title">Crypto</div>
                    <div class="currency-grid" id="cryptoCurrencies">
                        <div class="currency-option" data-currency="BTC">BTC</div>
                        <div class="currency-option" data-currency="ETH">ETH</div>
                    </div>
                    <div class="currency-section-title">More</div>
                    <div class="currency-grid" id="fiatCurrencies"></div>
                </div>
            </div>
        </div>

        <!-- Neon Stats Cards Grid -->
        <div class="neon-cards-grid">
            <svg width="0" height="0" style="position: absolute;">
                <defs>
                    <linearGradient id="greenGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" style="stop-color:#00ff88" />
                        <stop offset="100%" style="stop-color:#aaff00" />
                    </linearGradient>
                </defs>
            </svg>

            <!-- Row 1: Completed Tasks & Referrals -->
            <div class="neon-cards-row">
                <div class="neon-card neon-card-green">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <?php 
                        $progress_percent = $total_active_tasks > 0 ? min(100, ($tasks_completed / $total_active_tasks) * 100) : 0;
                        $circumference = 2 * 3.14159 * 24;
                        $offset = $circumference - ($progress_percent / 100) * $circumference;
                        ?>
                        <div class="progress-ring-container">
                            <svg class="progress-ring" viewBox="0 0 60 60">
                                <circle class="progress-ring-bg" cx="30" cy="30" r="24" />
                                <circle class="progress-ring-progress" cx="30" cy="30" r="24" 
                                    stroke-dasharray="<?php echo $circumference; ?>"
                                    stroke-dashoffset="<?php echo $offset; ?>"
                                    style="stroke: url(#greenGradient);" />
                            </svg>
                            <div class="progress-ring-inner"><i class="fas fa-check"></i></div>
                        </div>
                        <div class="neon-card-text">
                            <span class="neon-card-label">COMPLETED</span>
                            <span class="neon-card-label">TASKS</span>
                            <span class="neon-card-value"><?php echo $tasks_completed; ?> / <?php echo $total_active_tasks; ?></span>
                        </div>
                    </div>
                    <a href="/tasks" class="neon-card-cta green-cta">+ Earn more today →</a>
                </div>

                <div class="neon-card neon-card-blue">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <div class="neon-card-icon blue-icon"><i class="fas fa-users"></i></div>
                        <div class="neon-card-text">
                            <span class="neon-card-label">REFERRALS</span>
                            <span class="neon-card-value"><?php echo $referrals_count; ?> <span class="value-suffix">Active</span></span>
                            <span class="neon-card-subtext">Earn up to <strong>5 USDT</strong> / referral</span>
                        </div>
                    </div>
                    <a href="/referrals" class="neon-card-btn blue-btn"><i class="fas fa-share-alt"></i> Invite Friends</a>
                </div>
            </div>

            <!-- Row 2: Deposits & Task Earnings -->
            <div class="neon-cards-row">
                <div class="neon-card neon-card-gold">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <div class="neon-card-icon-ring gold-ring"><i class="fas fa-coins"></i></div>
                        <div class="neon-card-text">
                            <span class="neon-card-label">DEPOSITS</span>
                            <span class="neon-card-value"><?php echo number_format((float)$total_deposits, 2); ?> <span class="value-suffix">USDT</span></span>
                            <span class="neon-card-status gold-status">Status: Active</span>
                        </div>
                    </div>
                    <div class="neon-card-buttons-row">
                        <a href="/deposit" class="neon-card-btn gold-btn"><i class="fas fa-wallet"></i> Add Funds</a>
                        <a href="/deposits" class="neon-card-btn gold-btn-outline"><i class="fas fa-history"></i> History</a>
                    </div>
                </div>

                <div class="neon-card neon-card-purple">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <div class="neon-card-icon-ring purple-ring"><i class="fas fa-chart-line"></i></div>
                        <div class="neon-card-text">
                            <span class="neon-card-label">TASK EARNINGS</span>
                            <span class="neon-card-value"><?php echo number_format((float)$total_earnings, 2); ?> <span class="value-suffix">USDT</span></span>
                            <span class="neon-card-subtext purple-subtext">
                                <i class="fas fa-arrow-up"></i> +<?php echo number_format($todays_pnl, 2); ?> today
                            </span>
                        </div>
                    </div>
                    <a href="/history" class="neon-card-btn purple-btn"><i class="fas fa-history"></i> View History</a>
                </div>
            </div>

            <!-- Row 3: Withdrawable Amount -->
            <div class="neon-card neon-card-withdraw neon-card-withdrawable-compact">
                <div class="neon-card-glow"></div>
                <div class="neon-card-content-withdrawable">
                    <div class="coin-stack-compact"><i class="fas fa-coins"></i></div>
                    <div class="withdrawable-info">
                        <span class="withdrawable-label">WITHDRAWABLE</span>
                        <div class="withdrawable-amount">
                            <span class="withdrawable-value"><?php echo number_format((float)$available_for_withdrawal, 2); ?></span>
                            <span class="withdrawable-currency">USDT</span>
                        </div>
                        <span class="withdrawable-badge">Ready to withdraw</span>
                    </div>
                    <a href="/withdraw" class="withdraw-btn-compact"><i class="fas fa-paper-plane"></i> Withdraw Now</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Banner -->
    <?php if (!$user['is_premium']): ?>
    <div class="premium-banner">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="d-flex align-items-center mb-2">
                    <i class="fas fa-crown text-warning me-2"></i>
                    <h4 class="mb-0">Upgrade to Premium</h4>
                </div>
                <p class="mb-3">Unlock premium benefits and earn 2x rewards on all completed tasks!</p>
                <ul class="mb-0">
                    <li><i class="fas fa-check-circle text-success me-2"></i> 2x earning multiplier on all tasks</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Priority withdrawal processing</li>
                    <li><i class="fas fa-check-circle text-success me-2"></i> Access to exclusive premium tasks</li>
                </ul>
            </div>
            <div class="col-lg-4 mt-3 mt-lg-0 text-center">
                <a href="/upgrade" class="btn btn-warning btn-lg">Upgrade Now</a>
                <div class="mt-2 small text-light">Only 10 USDT needed to upgrade</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Referral Link -->
    <div class="referral-section">
        <div class="card">
            <div class="card-header bg-light"><h5 class="mb-0">Your Referral Link</h5></div>
            <div class="card-body">
                <p>Share this link with friends and earn USDT when they sign up!</p>
                <div class="input-group">
                    <input type="text" id="referralLink" class="form-control" value="<?php echo $referral_link; ?>" readonly>
                    <button class="btn btn-primary" onclick="copyReferralLink()"><i class="fas fa-copy"></i> Copy</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Tasks -->
    <div class="tasks-section">
        <div class="tasks-header">
            <div class="tasks-title-group">
                <h4 class="tasks-title">Available Tasks</h4>
                <span class="tasks-count"><?php echo $total_active_tasks; ?> tasks</span>
            </div>
            <a href="/tasks" class="tasks-view-all">View All <i class="fas fa-chevron-right"></i></a>
        </div>

        <div class="tasks-grid">
            <?php if (!empty($recent_tasks)): ?>
                <?php foreach ($recent_tasks as $task): ?>
                    <?php
                        $is_completed = in_array($task['id'], $completed_task_ids);
                        $is_premium_task = $task['is_premium'] == 1;
                        $can_view = !$is_premium_task || ($is_premium_task && $user['is_premium'] == 1);
                        $type_icon = $task['type'] == 'video' ? 'fa-play-circle' : ($task['type'] == 'promotion' ? 'fa-bullhorn' : 'fa-newspaper');
                        $type_color = $task['type'] == 'video' ? 'type-video' : ($task['type'] == 'promotion' ? 'type-promo' : 'type-news');
                    ?>
                    <div class="premium-task-card <?php echo $is_completed ? 'task-completed' : ''; ?> <?php echo $is_premium_task ? 'task-premium' : ''; ?>">
                        <div class="task-thumbnail-wrapper">
                            <img src="<?php echo !empty($task['thumbnail']) ? $task['thumbnail'] : 'assets/images/placeholder.jpg'; ?>" class="task-thumbnail" alt="<?php echo htmlspecialchars($task['title']); ?>">
                            <div class="task-overlay"></div>
                            <div class="task-badges">
                                <span class="task-type-badge <?php echo $type_color; ?>">
                                    <i class="fas <?php echo $type_icon; ?>"></i> <?php echo ucfirst($task['type']); ?>
                                </span>
                                <?php if ($is_premium_task): ?>
                                    <span class="task-premium-badge"><i class="fas fa-crown"></i> Premium</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_completed): ?>
                                <div class="task-done-overlay">
                                    <div class="task-done-icon"><i class="fas fa-check"></i></div>
                                    <span>Completed</span>
                                </div>
                            <?php endif; ?>
                            <div class="task-reward-chip">
                                <i class="fas fa-coins"></i>
                                <span><?php echo $task['reward']; ?> USDT</span>
                                <?php if ($user['is_premium']): ?>
                                    <span class="reward-multiplier">×2</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="task-content">
                            <h5 class="task-title"><?php echo htmlspecialchars(substr($task['title'], 0, 40)) . (strlen($task['title']) > 40 ? '...' : ''); ?></h5>
                            <p class="task-desc"><?php echo substr(strip_tags($task['description']), 0, 70) . '...'; ?></p>
                        </div>
                        <div class="task-action">
                            <?php if ($is_completed): ?>
                                <button class="task-btn task-btn-completed" disabled><i class="fas fa-check-circle"></i> Done</button>
                            <?php elseif (!$can_view): ?>
                                <button class="task-btn task-btn-locked" disabled><i class="fas fa-lock"></i> Premium Only</button>
                            <?php else: ?>
                                <a href="index.php?page=tasks&action=view&id=<?php echo $task['id']; ?>" class="task-btn task-btn-start">
                                    <span>Start Task</span><i class="fas fa-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="tasks-empty">
                    <i class="fas fa-inbox"></i>
                    <h5>No Tasks Available</h5>
                    <p>Check back later for new earning opportunities</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <style>
    /* Available Tasks Styles */
    .tasks-section { margin: 24px 0; padding: 0; }
    .tasks-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 4px; }
    .tasks-title-group { display: flex; align-items: center; gap: 12px; }
    .tasks-title { font-size: 20px; font-weight: 700; color: #1a1a2e; margin: 0; letter-spacing: -0.3px; }
    .tasks-count { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
    .tasks-view-all { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #667eea; text-decoration: none; transition: all 0.2s ease; }
    .tasks-view-all:hover { color: #764ba2; gap: 10px; }
    .tasks-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .premium-task-card { background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(0, 0, 0, 0.05); }
    .premium-task-card:hover { transform: translateY(-6px); box-shadow: 0 12px 40px rgba(102, 126, 234, 0.2); }
    .premium-task-card.task-premium { border: 1px solid rgba(255, 193, 7, 0.3); }
    .premium-task-card.task-premium:hover { box-shadow: 0 12px 40px rgba(255, 193, 7, 0.25); }
    .task-thumbnail-wrapper { position: relative; height: 140px; overflow: hidden; }
    .task-thumbnail { width: 100%; height: 100%; object-fit: cover; transition: transform 0.4s ease; }
    .premium-task-card:hover .task-thumbnail { transform: scale(1.08); }
    .task-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(0,0,0,0.6) 100%); }
    .task-badges { position: absolute; top: 10px; left: 10px; display: flex; gap: 6px; flex-wrap: wrap; }
    .task-type-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 4px; backdrop-filter: blur(8px); }
    .type-video { background: rgba(102, 126, 234, 0.85); color: white; }
    .type-promo { background: rgba(0, 200, 83, 0.85); color: white; }
    .type-news { background: rgba(108, 117, 125, 0.85); color: white; }
    .task-premium-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px; background: rgba(255, 193, 7, 0.9); color: #1a1a1a; display: flex; align-items: center; gap: 4px; }
    .task-done-overlay { position: absolute; inset: 0; background: rgba(0, 200, 83, 0.85); display: flex; flex-direction: column; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 14px; }
    .task-done-icon { width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; }
    .task-done-icon i { color: #00c853; font-size: 20px; }
    .task-reward-chip { position: absolute; bottom: 10px; right: 10px; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); color: #ffd700; font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 20px; display: flex; align-items: center; gap: 4px; }
    .reward-multiplier { background: #ffd700; color: #1a1a1a; font-size: 10px; padding: 1px 5px; border-radius: 10px; font-weight: 800; }
    .task-content { padding: 16px; }
    .task-title { font-size: 15px; font-weight: 700; color: #1a1a2e; margin: 0 0 6px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .task-desc { font-size: 13px; color: #64748b; margin: 0; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .task-action { padding: 0 16px 16px; }
    .task-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.25s ease; border: none; cursor: pointer; }
    .task-btn-start { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .task-btn-start:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); color: white; }
    .task-btn-completed { background: rgba(0, 200, 83, 0.1); color: #00c853; cursor: default; }
    .task-btn-locked { background: rgba(255, 193, 7, 0.1); color: #f59e0b; cursor: default; }
    .tasks-empty { grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: #f8fafc; border-radius: 16px; }
    .tasks-empty i { font-size: 48px; color: #cbd5e1; margin-bottom: 16px; }
    .tasks-empty h5 { font-size: 18px; font-weight: 600; color: #334155; margin: 0 0 8px; }
    .tasks-empty p { font-size: 14px; color: #64748b; margin: 0; }
    body.dark-mode .tasks-title { color: #e0e0e0; }
    body.dark-mode .premium-task-card { background: #1e1e1e; border-color: #333; }
    body.dark-mode .task-title { color: #e0e0e0; }
    body.dark-mode .task-desc { color: #aaa; }
    body.dark-mode .tasks-empty { background: #252525; }
    body.dark-mode .tasks-empty h5 { color: #e0e0e0; }
    body.dark-mode .tasks-empty p { color: #aaa; }
    @media (max-width: 640px) { .tasks-grid { grid-template-columns: 1fr; } .tasks-title { font-size: 18px; } .task-thumbnail-wrapper { height: 120px; } }
    
.create-task-btn {
    background: linear-gradient(135deg, #1f2937, #111827);
    border: 1px solid rgba(255,255,255,0.08);
    color: #ffffff;
    font-size: 11px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 10px;
    letter-spacing: 0.4px;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0,0,0,0.18);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.create-task-btn:hover {
    background: linear-gradient(135deg, #111827, #000000);
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.28);
}

.create-task-btn:focus {
    color: #ffffff;
    box-shadow: 0 0 0 0.2rem rgba(255,255,255,0.08);
}

</style>

<!-- View All Tasks Button -->
<div class="text-center">
    <a href="/tasks" class="view-all-tasks-btn">
        <i class="fas fa-tasks"></i> View All Tasks <i class="fas fa-arrow-right"></i>
    </a>
</div>

<!-- Full-width Clickable Banner -->
<div class="mt-4 mb-5" style="margin-left: -15px; margin-right: -15px;">
    <a href="/promote_dashboard" target="_blank">
        <img src="assets/images/banner-social-media.jpg" alt="Grow your social media page" style="width: 100%; display: block; border-radius: 0;" />
    </a>
</div>
</div>

<script>
function copyReferralLink() {
    const referralLink = document.getElementById('referralLink');
    referralLink.select();
    referralLink.setSelectionRange(0, 99999);
    document.execCommand('copy');
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.classList.add('btn-success');
    button.classList.remove('btn-primary');
    setTimeout(() => { button.innerHTML = originalText; button.classList.add('btn-primary'); button.classList.remove('btn-success'); }, 2000);
}

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('cover-upload').addEventListener('change', function () {
      document.getElementById('coverLoader').style.display = 'block';
      document.getElementById('coverForm').submit();
    });
    document.getElementById('profile-upload').addEventListener('change', function () {
      document.getElementById('profileLoader').style.display = 'block';
      document.getElementById('profileForm').submit();
    });
});
</script>

<script>
// Balance visibility toggle and currency conversion with REAL-TIME PRICES
document.addEventListener('DOMContentLoaded', async function() {
    const toggleBtn = document.getElementById('toggleBalance');
    const balanceDisplay = document.getElementById('balanceDisplay');
    const visibilityIcon = document.getElementById('visibilityIcon');
    const mainBalance = document.getElementById('mainBalance');
    const btcEquivalent = document.getElementById('btcEquivalent');
    const currencySelectBtn = document.getElementById('currencySelectBtn');
    const selectedCurrencyDisplay = document.getElementById('selectedCurrencyDisplay');
    const currencyModal = document.getElementById('currencyModal');
    const closeCurrencyModal = document.getElementById('closeCurrencyModal');
    const originalBalance = <?php echo (float)($user['balance'] ?? 0); ?>;
    
    const fiatCurrencies = [
        'AED', 'ARS', 'AUD', 'BDT', 'BGN', 'BHD', 'BOB', 'BRL', 'CAD',
        'CHF', 'CLP', 'CNY', 'COP', 'CZK', 'DKK', 'EGP', 'EUR', 'GBP',
        'GEL', 'HKD', 'HUF', 'IDR', 'ILS', 'INR', 'JPY', 'KES', 'KRW',
        'KWD', 'KZT', 'LKR', 'MAD', 'MNT', 'MXN', 'MYR', 'NGN', 'NOK',
        'NZD', 'OMR', 'PEN', 'PHP', 'PKR', 'PLN', 'QAR', 'RON', 'RUB',
        'SAR', 'SEK', 'TRY', 'TWD', 'UAH', 'UGX', 'UYU', 'VES', 'VND', 'ZAR'
    ];
    
    let cryptoPrices = { 'BTC': 97000, 'ETH': 3400, 'USD': 1 };
    let exchangeRates = { 'USD': 1 };
    
    const fiatCurrenciesGrid = document.getElementById('fiatCurrencies');
    fiatCurrencies.forEach(currency => {
        const option = document.createElement('div');
        option.className = 'currency-option';
        option.dataset.currency = currency;
        option.textContent = currency;
        fiatCurrenciesGrid.appendChild(option);
    });
    
    async function fetchCryptoPrices() {
        try {
            const response = await fetch('https://api.coingecko.com/api/v3/simple/price?ids=bitcoin,ethereum&vs_currencies=usd');
            if (response.ok) {
                const data = await response.json();
                cryptoPrices = { 'BTC': data.bitcoin?.usd || 97000, 'ETH': data.ethereum?.usd || 3400, 'USD': 1 };
                updateBtcEquivalent();
            }
        } catch (error) { console.warn('Failed to fetch crypto prices:', error); }
    }
    
    async function fetchExchangeRates() {
        try {
            const response = await fetch('https://api.exchangerate-api.com/v4/latest/USD');
            if (response.ok) { const data = await response.json(); exchangeRates = data.rates; }
        } catch (error) {
            try {
                const fallbackResponse = await fetch('https://api.frankfurter.app/latest?from=USD');
                if (fallbackResponse.ok) { const fallbackData = await fallbackResponse.json(); exchangeRates = { USD: 1, ...fallbackData.rates }; }
            } catch (e) { console.warn('Exchange rate fetch failed:', e); }
        }
    }
    
    function updateBtcEquivalent() { btcEquivalent.textContent = Number(
            (originalBalance / cryptoPrices['BTC']).toFixed(8)
        ).toLocaleString(undefined, {
            minimumFractionDigits: 8,
            maximumFractionDigits: 8
        }); }
    
    function getCurrencyValue(currency) {
        if (currency === 'USD') return originalBalance;
        if (currency === 'BTC') return originalBalance / cryptoPrices['BTC'];
        if (currency === 'ETH') return originalBalance / cryptoPrices['ETH'];
        if (exchangeRates[currency]) return originalBalance * exchangeRates[currency];
        return originalBalance;
    }
    
    function formatCurrencyValue(value, currency) {
        if (currency === 'BTC' || currency === 'ETH') {
            return Number(value).toLocaleString(undefined, {
                minimumFractionDigits: 8,
                maximumFractionDigits: 8
            });
        }

        if (['JPY', 'KRW', 'VND', 'IDR', 'UGX'].includes(currency)) {
            return Math.round(value).toLocaleString();
        }

        return Number(value).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function updateCurrencyDisplay(currency) {
        mainBalance.textContent = formatCurrencyValue(getCurrencyValue(currency), currency);
        selectedCurrencyDisplay.textContent = currency;
        const equivalentContainer = document.querySelector('.btc-equivalent');
        if (equivalentContainer) {
            if (currency === 'USD') {
                equivalentContainer.innerHTML = `
        <span>≈</span>
        <span class="btc-value" id="btcEquivalent">
        ${Number((originalBalance / cryptoPrices['BTC']).toFixed(8)).toLocaleString(undefined, {
            minimumFractionDigits: 8,
            maximumFractionDigits: 8
        })}
        </span>
        <span>BTC</span>
        <i class="fas fa-question-circle" style="font-size: 12px; cursor: help;" title="~$${cryptoPrices['BTC'].toLocaleString()} per BTC"></i>`;
            } else {
                equivalentContainer.innerHTML = `<span>≈</span><span class="btc-value">${originalBalance.toFixed(2)}</span><span>USD</span><i class="fas fa-question-circle" style="font-size: 12px; cursor: help;" title="Original balance in USD"></i>`;
            }
        }
        localStorage.setItem('selectedCurrency', currency);
    }
    
    currencySelectBtn.addEventListener('click', () => currencyModal.classList.add('active'));
    closeCurrencyModal.addEventListener('click', () => currencyModal.classList.remove('active'));
    currencyModal.addEventListener('click', e => { if (e.target === currencyModal) currencyModal.classList.remove('active'); });
    
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('currency-option')) {
            document.querySelectorAll('.currency-option').forEach(opt => opt.classList.remove('selected'));
            e.target.classList.add('selected');
            updateCurrencyDisplay(e.target.dataset.currency);
            currencyModal.classList.remove('active');
        }
    });
    
    await Promise.all([fetchCryptoPrices(), fetchExchangeRates()]);
    const savedCurrency = localStorage.getItem('selectedCurrency') || 'USD';
    const savedOption = document.querySelector(`.currency-option[data-currency="${savedCurrency}"]`);
    if (savedOption) { document.querySelectorAll('.currency-option').forEach(opt => opt.classList.remove('selected')); savedOption.classList.add('selected'); updateCurrencyDisplay(savedCurrency); }
    
    setInterval(async () => { await Promise.all([fetchCryptoPrices(), fetchExchangeRates()]); updateCurrencyDisplay(localStorage.getItem('selectedCurrency') || 'USD'); }, 60000);
    
    const isHidden = localStorage.getItem('balanceHidden') === 'true';
    if (isHidden) { balanceDisplay.classList.add('hidden-balance'); visibilityIcon.classList.remove('fa-eye'); visibilityIcon.classList.add('fa-eye-slash'); }
    
    toggleBtn.addEventListener('click', function() {
        const isCurrentlyHidden = balanceDisplay.classList.contains('hidden-balance');
        if (isCurrentlyHidden) { balanceDisplay.classList.remove('hidden-balance'); visibilityIcon.classList.remove('fa-eye-slash'); visibilityIcon.classList.add('fa-eye'); localStorage.setItem('balanceHidden', 'false'); }
        else { balanceDisplay.classList.add('hidden-balance'); visibilityIcon.classList.remove('fa-eye'); visibilityIcon.classList.add('fa-eye-slash'); localStorage.setItem('balanceHidden', 'true'); }
    });
    
    if (localStorage.getItem('darkMode') === 'true') { document.body.classList.add('dark-mode'); }
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer_navbar.php'; ?>
