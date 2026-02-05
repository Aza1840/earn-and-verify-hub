<?php
require_once __DIR__ . '/../includes/auth.php';

$user_id = $_SESSION['user_id'];
$completed_task_ids = [];
$recent_tasks = [];
$tasks_completed = 0;
$referrals_count = 0;

/* =====================================================
   USER INFO
===================================================== */
$stmt = $conn->prepare("
    SELECT id, name, email, balance, username, is_premium, profile_picture, cover_photo 
    FROM users 
    WHERE id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($id, $name, $email, $balance, $username, $is_premium, $profile_picture, $cover_photo);
$stmt->fetch();
$stmt->close();

$user = [
    'id' => $id,
    'name' => $name,
    'email' => $email,
    'balance' => $balance ?? 0,
    'username' => $username,
    'is_premium' => $is_premium,
    'profile_picture' => $profile_picture,
    'cover_photo' => $cover_photo
];

/* =====================================================
   TOTAL TASKS COMPLETED (REGULAR + PROMOTION)
===================================================== */
$stmt = $conn->prepare("
    SELECT 
        (
            SELECT COUNT(*) 
            FROM completed_tasks 
            WHERE user_id = ?
        ) +
        (
            SELECT COUNT(*) 
            FROM promotion_ad_completions 
            WHERE user_id = ? 
              AND status = 'approved'
        ) AS total_completed
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stmt->bind_result($tasks_completed);
$stmt->fetch();
$stmt->close();

/* =====================================================
   FINANCIAL STATS
===================================================== */
// Total deposits
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount), 0) 
    FROM deposits 
    WHERE user_id = ? AND status = 'completed'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($total_deposits);
$stmt->fetch();
$stmt->close();

// Pending withdrawals
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount), 0) 
    FROM withdrawals 
    WHERE user_id = ? AND status = 'pending'
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($pending_withdrawals);
$stmt->fetch();
$stmt->close();

$available_for_withdrawal = $user['balance'];
$total_earnings = $user['balance'];

/* =====================================================
   REFERRALS
===================================================== */
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE referred_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($referrals_count);
$stmt->fetch();
$stmt->close();

/* =====================================================
   RECENT REGULAR TASKS
===================================================== */
$stmt = $conn->prepare("
    SELECT id, title, description, reward, type, is_premium, thumbnail, created_at
    FROM tasks
    WHERE status = 'active'
    ORDER BY created_at DESC
    LIMIT 9
");
$stmt->execute();
$stmt->bind_result($task_id, $task_title, $task_desc, $reward, $type, $is_premium_task, $thumbnail, $created_at);
while ($stmt->fetch()) {
    $recent_tasks[] = [
        'id' => $task_id,
        'title' => $task_title,
        'description' => $task_desc,
        'reward' => $reward,
        'type' => $type,
        'is_premium' => $is_premium_task,
        'thumbnail' => $thumbnail,
        'created_at' => $created_at,
        'source' => 'regular'
    ];
}
$stmt->close();

/* =====================================================
   RECENT PROMOTION TASKS
===================================================== */
$stmt = $conn->prepare("
    SELECT id, title, description, social_platform, social_url, task_type,
           reward_per_user, COALESCE(thumbnail_url,'') AS thumbnail_url, created_at
    FROM promotion_ads
    WHERE status IN ('active','approved')
      AND remaining_budget >= reward_per_user
    ORDER BY created_at DESC
    LIMIT 9
");
$stmt->execute();
$stmt->bind_result(
    $ad_id, $ad_title, $ad_desc, $platform, $url,
    $ad_task_type, $ad_reward, $thumb, $created_at
);
while ($stmt->fetch()) {
    $recent_tasks[] = [
        'id' => $ad_id,
        'title' => $ad_title,
        'description' => $ad_desc,
        'reward' => $ad_reward,
        'type' => 'promotion',
        'is_premium' => 0,
        'thumbnail' => $thumb ?: 'uploads/default_thumb.png',
        'created_at' => $created_at,
        'source' => 'promotion_ad',
        'social_platform' => $platform,
        'url' => $url,
        'task_type' => $ad_task_type
    ];
}
$stmt->close();

/* =====================================================
   SORT & LIMIT RECENT TASKS
===================================================== */
usort($recent_tasks, fn($a, $b) =>
    strtotime($b['created_at']) <=> strtotime($a['created_at'])
);
$recent_tasks = array_slice($recent_tasks, 0, 9);

/* =====================================================
   COMPLETED REGULAR TASK IDS
===================================================== */
$regular_ids = array_column(
    array_filter($recent_tasks, fn($t) => $t['source'] === 'regular'),
    'id'
);

if (!empty($regular_ids)) {
    $placeholders = implode(',', array_fill(0, count($regular_ids), '?'));
    $sql = "SELECT task_id FROM completed_tasks WHERE user_id = ? AND task_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i' . str_repeat('i', count($regular_ids)), $user_id, ...$regular_ids);
    $stmt->execute();
    $stmt->bind_result($cid);
    while ($stmt->fetch()) {
        $completed_task_ids[] = 'regular:' . $cid;
    }
    $stmt->close();
}

/* =====================================================
   COMPLETED PROMOTION TASK IDS
===================================================== */
$promo_ids = array_column(
    array_filter($recent_tasks, fn($t) => $t['source'] === 'promotion_ad'),
    'id'
);

if (!empty($promo_ids)) {
    $placeholders = implode(',', array_fill(0, count($promo_ids), '?'));
    $sql = "
        SELECT ad_id 
        FROM promotion_ad_completions 
        WHERE user_id = ? 
          AND status = 'approved'
          AND ad_id IN ($placeholders)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i' . str_repeat('i', count($promo_ids)), $user_id, ...$promo_ids);
    $stmt->execute();
    $stmt->bind_result($pid);
    while ($stmt->fetch()) {
        $completed_task_ids[] = 'promotion:' . $pid;
    }
    $stmt->close();
}

/* =====================================================
   DAILY REWARD LOGIC
===================================================== */
$can_claim = true;
$stmt = $conn->prepare("
    SELECT rewarded_at 
    FROM reward_log 
    WHERE user_id = ? 
    ORDER BY rewarded_at DESC 
    LIMIT 1
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($last_claim_time);
if ($stmt->fetch()) {
    $can_claim = time() >= (strtotime($last_claim_time) + 86400);
}
$stmt->close();

/* =====================================================
   REFERRAL LINK
===================================================== */
$referral_code = $user_id . bin2hex(random_bytes(4));
$referral_link = "https://" . $_SERVER['HTTP_HOST'] . "/register?ref=" . $referral_code;

// Daily reward claim time logic
$can_claim = true;
$last_claim_time = null;

$stmt = $conn->prepare("SELECT rewarded_at FROM reward_log WHERE user_id = ? ORDER BY rewarded_at DESC LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($last_claim_time);
    if ($stmt->fetch()) {
        $next_claim_time = strtotime($last_claim_time) + 86400;
        $can_claim = time() >= $next_claim_time;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare reward_log query: " . $conn->error);
    $can_claim = true; // Allow claim if check fails (optional)
}
?>

<?php
/* =====================================================
   TODAY'S P&L CALCULATION (resets at midnight)
===================================================== */
$today_start = date('Y-m-d 00:00:00');
$todays_pnl = 0;

// Get earnings from completed_tasks today
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(t.reward), 0) as task_earnings
    FROM completed_tasks ct
    JOIN tasks t ON ct.task_id = t.id
    WHERE ct.user_id = ? AND ct.completed_at >= ?
");
if ($stmt) {
    $stmt->bind_param("is", $user_id, $today_start);
    $stmt->execute();
    $stmt->bind_result($task_earnings);
    $stmt->fetch();
    $todays_pnl += floatval($task_earnings);
    $stmt->close();
}

// Get earnings from promotion_ad_completions today
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(earned_credits), 0) as promo_earnings
    FROM promotion_ad_completions
    WHERE user_id = ? AND status = 'approved' AND completed_at >= ?
");
if ($stmt) {
    $stmt->bind_param("is", $user_id, $today_start);
    $stmt->execute();
    $stmt->bind_result($promo_earnings);
    $stmt->fetch();
    $todays_pnl += floatval($promo_earnings);
    $stmt->close();
}

// Get earnings from daily rewards today
$stmt = $conn->prepare("
    SELECT IFNULL(SUM(amount), 0) as reward_earnings
    FROM reward_log
    WHERE user_id = ? AND rewarded_at >= ?
");
if ($stmt) {
    $stmt->bind_param("is", $user_id, $today_start);
    $stmt->execute();
    $stmt->bind_result($reward_earnings);
    $stmt->fetch();
    $todays_pnl += floatval($reward_earnings);
    $stmt->close();
}

// Calculate P&L percentage
$user_balance = floatval($user['balance'] ?? 0);
$previous_balance = max(0.01, $user_balance - $todays_pnl);
$pnl_percentage = $previous_balance > 0 ? ($todays_pnl / $previous_balance) * 100 : 0;
?>

<style>
*,
*::before,
*::after {
  box-sizing: inherit;
}

body {
    margin: 0;
    padding: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f8f9fa;
    line-height: 1.5;
}

html, body {
  max-width: 100vw;
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  overflow-x: hidden;
  width: 100%;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.container-fluid {
    padding: 0 10px;
    max-width: 100%;
}

.profile-header {
    background-color: #1c1c1c;
    position: relative;
    margin-bottom: 15px;
}

.cover-photo {
    width: 100%;
    height: 180px;
    background-color: #333;
    background-size: cover;
    background-position: center;
    position: relative;
}

.cover-photo .camera-icon {
    position: absolute;
    top: 8px;
    right: 8px;
    color: white;
    font-size: 16px;
    background: rgba(0, 0, 0, 0.6);
    padding: 5px;
    border-radius: 50%;
    cursor: pointer;
}

.profile-pic-wrapper {
    position: absolute;
    bottom: -50px;
    left: 15px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: #fff;
    border: 3px solid #1c1c1c;
    overflow: hidden;
    z-index: 2;
}

.profile-pic-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-pic-wrapper .upload-icon {
    position: absolute;
    bottom: 3px;
    right: 3px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 4px;
    border-radius: 50%;
    font-size: 12px;
    cursor: pointer;
    border: 2px solid white;
    z-index: 3;
}

.profile-pic-wrapper .upload-icon input {
    display: none;
}

.profile-info {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    padding: 60px 15px 15px 15px;
    color: white;
}

.profile-name {
    margin-left: 15px;
    font-size: 18px;
    font-weight: bold;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.profile-name img {
    width: 18px;
    height: 18px;
}

.logout-button {
    background-color: #dc3545;
    color: white;
    padding: 6px 12px;
    font-size: 12px;
    border: none;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    line-height: 1;
}

.loader {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 30px;
    transform: translate(-50%, -50%);
    display: none;
    z-index: 10;
}

/* Stats Cards - Improved spacing */
.stats-container {
    margin: 15px 0;
}

.card {
    margin-bottom: 12px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #e9ecef;
}

.card-body {
    padding: 15px;
}

.card-footer {
    padding: 8px 15px;
    background-color: #f8f9fa;
    border-top: 1px solid #e9ecef;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 4px;
}

/* Task Cards - Better mobile layout */
.task-card {
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
    margin-bottom: 15px;
}

.task-card:hover {
    transform: translateY(-2px);
}

.task-card img {
    height: 120px;
    object-fit: cover;
}

.task-card .card-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
}

.task-card .card-text {
    font-size: 12px;
    margin-bottom: 8px;
}

.task-card .card-footer {
    padding: 10px 15px;
}

/* Premium Banner */
.premium-banner {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    color: white;
    margin: 15px 0;
    padding: 20px;
}

.premium-banner h4 {
    font-size: 18px;
    margin-bottom: 10px;
}

.premium-banner p {
    font-size: 14px;
    margin-bottom: 12px;
}

.premium-banner ul {
    font-size: 13px;
    padding-left: 20px;
}

/* Referral Link */
.referral-section {
    margin: 15px 0;
}

.referral-section .card-body {
    padding: 15px;
}

.referral-section p {
    font-size: 14px;
    margin-bottom: 12px;
}

/* View All Tasks Button */
.view-all-tasks-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    padding: 15px 30px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    margin: 20px 0;
}

.view-all-tasks-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
    color: white;
    text-decoration: none;
}

.view-all-tasks-btn i {
    margin-left: 8px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .container-fluid {
        padding: 0 8px;
    }
    
    .cover-photo {
        height: 140px;
    }

    .profile-pic-wrapper {
        width: 80px;
        height: 80px;
        left: 10px;
        bottom: -40px;
    }

    .profile-info {
        padding: 50px 10px 10px 10px;
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: flex-start;
        gap: 10px;
    }

    .profile-name {
        margin-left: 10px;
        font-size: 16px;
    }

    .logout-button {
        margin-left: auto;
    }

    .card-body {
        padding: 12px;
    }

    .task-card img {
        height: 100px;
    }

    .premium-banner {
        padding: 15px;
    }

    .premium-banner h4 {
        font-size: 16px;
    }

    .view-all-tasks-btn {
        padding: 12px 25px;
        font-size: 14px;
        width: 100%;
        text-align: center;
        margin: 15px 0;
    }
}

@media (max-width: 480px) {
    .profile-name {
        font-size: 14px;
    }
    
    .card-body {
        padding: 10px;
    }
    
    .task-card .card-title {
        font-size: 13px;
    }
    
    .task-card .card-text {
        font-size: 11px;
    }
}

.time-box {
    background: #1E90FF;
    color: white;
    padding: 6px 10px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
    text-align: center;
    min-width: 40px;
}

#countdownBadge {
    font-size: 10px;
    padding: 3px 6px;
    border-radius: 3px;
    margin-left: 8px;
}

/* Total Assets Card Styles */
.total-assets-card {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border-radius: 16px;
    padding: 20px;
    color: white;
    margin-bottom: 15px;
}

.total-assets-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
}

.visibility-toggle {
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    padding: 4px;
}

.balance-display {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
}

.balance-left {
    flex: 1;
}

.main-balance {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 4px;
}

.main-balance .amount {
    font-size: 32px;
    font-weight: 700;
    letter-spacing: -1px;
}

.currency-dropdown {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
}

.currency-dropdown option {
    background: #1a1a2e;
    color: white;
}

.btc-equivalent {
    color: rgba(255, 255, 255, 0.6);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 4px;
}

.todays-pnl {
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.todays-pnl .label {
    color: rgba(255, 255, 255, 0.7);
}

.todays-pnl .value {
    font-weight: 600;
}

.todays-pnl .positive {
    color: #00d4aa;
}

.todays-pnl .negative {
    color: #ff6b6b;
}

.todays-pnl .neutral {
    color: rgba(255, 255, 255, 0.7);
}

.chart-container {
    width: 120px;
    height: 50px;
    position: relative;
}

.trend-chart {
    width: 100%;
    height: 100%;
}

.trend-chart path {
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.trend-chart .positive-trend {
    stroke: #00d4aa;
}

.trend-chart .negative-trend {
    stroke: #ff6b6b;
}

.trend-chart .neutral-trend {
    stroke: rgba(255, 255, 255, 0.5);
}

.hidden-balance .amount,
.hidden-balance .btc-value {
    filter: blur(8px);
    user-select: none;
}

.assets-actions {
    display: flex;
    gap: 10px;
    margin-top: 16px;
}

.assets-actions .btn {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 14px;
}

/* Dark Mode Styles */
body.dark-mode {
    background-color: #121212 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .card {
    background-color: #1e1e1e !important;
    border-color: #333 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .card-footer {
    background-color: #252525 !important;
    border-color: #333 !important;
}

body.dark-mode .bg-light {
    background-color: #252525 !important;
}

body.dark-mode .text-muted {
    color: #aaa !important;
}

body.dark-mode .form-control {
    background-color: #2a2a2a !important;
    border-color: #444 !important;
    color: #e0e0e0 !important;
}

body.dark-mode .footer-nav {
    background: #1a1a1a !important;
    border-top-color: #333 !important;
}

body.dark-mode .footer-nav a {
    color: #aaa !important;
}

body.dark-mode .footer-nav a:hover,
body.dark-mode .footer-nav a.active {
    color: #4da3ff !important;
}

body.dark-mode .alert-info {
    background-color: #1e3a5f !important;
    border-color: #2d5a87 !important;
    color: #8ecaff !important;
}

body.dark-mode .referral-section .card-header {
    background-color: #252525 !important;
}

body.dark-mode .task-card.bg-light {
    background-color: #252525 !important;
}

body.dark-mode h4, 
body.dark-mode h5 {
    color: #e0e0e0 !important;
}

/* ========================================
   NEON GLASSMORPHISM CARDS - ENHANCED
======================================== */

/* Base dark background for dashboard */
.dashboard-bg {
    background: linear-gradient(135deg, #0a0a12 0%, #0f0f1a 50%, #0a0a12 100%);
    min-height: 100vh;
    position: relative;
}

.dashboard-bg::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(ellipse at 20% 20%, rgba(0, 255, 136, 0.03) 0%, transparent 50%),
        radial-gradient(ellipse at 80% 80%, rgba(255, 68, 102, 0.03) 0%, transparent 50%),
        radial-gradient(ellipse at 50% 50%, rgba(0, 212, 255, 0.02) 0%, transparent 50%);
    pointer-events: none;
}

/* Light mode support */
body:not(.dark-mode) .neon-cards-grid {
    background: transparent;
}

body:not(.dark-mode) .neon-card {
    background: linear-gradient(135deg, rgba(15, 15, 30, 0.98) 0%, rgba(20, 20, 40, 0.95) 100%);
}

.neon-cards-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
    margin-bottom: 24px;
    padding: 16px;
    background: linear-gradient(135deg, #0a0a12 0%, #0d0d18 100%);
    border-radius: 20px;
    position: relative;
    overflow: hidden;
}

/* Particle effect background */
.neon-cards-grid::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 10% 20%, rgba(0, 255, 136, 0.15) 0%, transparent 2%),
        radial-gradient(circle at 90% 80%, rgba(255, 68, 102, 0.15) 0%, transparent 2%),
        radial-gradient(circle at 50% 50%, rgba(0, 212, 255, 0.1) 0%, transparent 1.5%),
        radial-gradient(circle at 30% 70%, rgba(191, 90, 242, 0.1) 0%, transparent 1.5%),
        radial-gradient(circle at 70% 30%, rgba(255, 215, 0, 0.1) 0%, transparent 1.5%);
    animation: particleFloat 20s ease-in-out infinite;
    pointer-events: none;
}

@keyframes particleFloat {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0.6; }
    50% { transform: translateY(-10px) scale(1.02); opacity: 1; }
}

.neon-cards-row {
    display: flex;
    gap: 14px;
}

.neon-card {
    position: relative;
    flex: 1;
    border-radius: 18px;
    padding: 18px;
    background: linear-gradient(145deg, rgba(15, 15, 30, 0.95) 0%, rgba(10, 10, 20, 0.98) 100%);
    backdrop-filter: blur(25px);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 
        0 4px 20px rgba(0, 0, 0, 0.5),
        inset 0 1px 0 rgba(255, 255, 255, 0.05);
}

.neon-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 18px;
    padding: 2px;
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2), var(--card-color-1));
    background-size: 200% 200%;
    animation: borderGlow 4s ease infinite;
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    pointer-events: none;
}

@keyframes borderGlow {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.neon-card-glow {
    position: absolute;
    top: -60%;
    left: -60%;
    width: 220%;
    height: 220%;
    background: radial-gradient(circle at center, var(--glow-color) 0%, transparent 45%);
    opacity: 0.12;
    pointer-events: none;
    transition: opacity 0.4s ease;
}

/* Animated particle overlay */
.neon-card::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 400 400' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    opacity: 0.04;
    pointer-events: none;
    border-radius: 18px;
}

/* Green Theme - Completed Tasks */
.neon-card-green {
    --card-color-1: #00ff88;
    --card-color-2: #aaff00;
    --glow-color: #00ff88;
}

/* Blue Theme - Referrals */
.neon-card-blue {
    --card-color-1: #00d4ff;
    --card-color-2: #0088ff;
    --glow-color: #00d4ff;
}

/* Gold Theme - Deposits */
.neon-card-gold {
    --card-color-1: #ffd700;
    --card-color-2: #ff8c00;
    --glow-color: #ffd700;
}

/* Purple Theme - Task Earnings */
.neon-card-purple {
    --card-color-1: #bf5af2;
    --card-color-2: #ff6b9d;
    --glow-color: #bf5af2;
}

/* Red/Pink Theme - Withdrawable */
.neon-card-red {
    --card-color-1: #ff4466;
    --card-color-2: #ff0066;
    --glow-color: #ff4466;
}

.neon-card-full {
    width: 100%;
}

/* ========================================
   CIRCULAR PROGRESS RING - ANIMATED
======================================== */
.progress-ring-container {
    position: relative;
    width: 60px;
    height: 60px;
    flex-shrink: 0;
}

.progress-ring {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}

.progress-ring-bg {
    fill: none;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 4;
}

.progress-ring-progress {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    stroke: url(#greenGradient);
    transition: stroke-dashoffset 1s ease-out;
    filter: drop-shadow(0 0 6px var(--glow-color));
}

.progress-ring-inner {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    align-items: center;
    justify-content: center;
}

.progress-ring-inner i {
    font-size: 22px;
    color: #00ff88;
    filter: drop-shadow(0 0 8px #00ff88);
}

/* Card Content Layout */
.neon-card-content {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 14px;
}

.neon-card-content-wide {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 12px 0;
}

/* Enhanced Icon Rings with Glow */
.neon-card-icon-ring {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    flex-shrink: 0;
    background: radial-gradient(circle, rgba(var(--icon-rgb), 0.15) 0%, transparent 70%);
}

.neon-card-icon-ring::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    padding: 3px;
    background: linear-gradient(135deg, var(--card-color-1), var(--card-color-2));
    -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    animation: ringPulse 2s ease-in-out infinite;
}

@keyframes ringPulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

.neon-card-icon-ring i {
    font-size: 22px;
    filter: drop-shadow(0 0 10px currentColor);
}

.green-ring { --icon-rgb: 0, 255, 136; }
.green-ring i { color: #00ff88; }

.blue-ring { --icon-rgb: 0, 212, 255; }
.blue-ring i { color: #00d4ff; }

.gold-ring { --icon-rgb: 255, 215, 0; }
.gold-ring i { color: #ffd700; }

.purple-ring { --icon-rgb: 191, 90, 242; }
.purple-ring i { color: #bf5af2; }

.red-ring { --icon-rgb: 255, 68, 102; }
.red-ring i { color: #ff4466; }

/* Enhanced Icon (without ring) */
.neon-card-icon {
    font-size: 32px;
    flex-shrink: 0;
}

.neon-card-icon i {
    filter: drop-shadow(0 0 12px currentColor);
}

.blue-icon i { color: #00d4ff; }
.gold-icon i { color: #ffd700; }
.purple-icon i { color: #bf5af2; }

/* Text Styles - Enhanced */
.neon-card-text {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.neon-card-text-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}

.neon-card-label {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: var(--card-color-1);
    line-height: 1.1;
    text-shadow: 0 0 20px var(--glow-color);
}

.neon-card-label-large {
    font-size: 16px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 2px;
    color: #ff4466;
    text-shadow: 0 0 30px #ff4466;
}

.neon-card-value {
    font-size: 26px;
    font-weight: 900;
    color: #ffffff;
    line-height: 1.1;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.neon-card-value-large {
    font-size: 42px;
    font-weight: 900;
    color: #ffffff;
    line-height: 1;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
}

.value-suffix {
    font-size: 13px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
}

.value-suffix-large {
    font-size: 18px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
}

.neon-card-subtext {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
}

.purple-subtext {
    color: #00ff88;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.purple-subtext i {
    font-size: 11px;
    animation: arrowBounce 1s ease-in-out infinite;
}

@keyframes arrowBounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-3px); }
}

.neon-card-status {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
}

.gold-status {
    color: #ffd700;
    font-weight: 600;
}

.neon-card-badge {
    font-size: 12px;
    padding: 6px 16px;
    border-radius: 25px;
    background: rgba(255, 68, 102, 0.15);
    border: 1px solid rgba(255, 68, 102, 0.4);
    color: #ff88aa;
    font-weight: 600;
}

/* CTA Links - Enhanced */
.neon-card-cta {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s ease;
}

.green-cta {
    color: #00ff88;
    text-shadow: 0 0 15px rgba(0, 255, 136, 0.5);
}

.green-cta:hover {
    color: #66ffbb;
    transform: translateX(4px);
}

/* Buttons - Enhanced with Glow */
.neon-card-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 1px solid;
    gap: 6px;
}

.blue-btn {
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.2) 0%, rgba(0, 136, 255, 0.1) 100%);
    border-color: rgba(0, 212, 255, 0.6);
    color: #00d4ff;
    box-shadow: 0 0 15px rgba(0, 212, 255, 0.2);
}

.blue-btn:hover {
    background: linear-gradient(135deg, rgba(0, 212, 255, 0.35) 0%, rgba(0, 136, 255, 0.25) 100%);
    color: #66e5ff;
    box-shadow: 0 0 25px rgba(0, 212, 255, 0.4);
    transform: translateY(-2px);
}

.gold-btn {
    background: linear-gradient(135deg, #ffd700, #ff9500);
    border-color: transparent;
    color: #1a1a1a;
    font-weight: 800;
    box-shadow: 0 4px 20px rgba(255, 215, 0, 0.4);
}

.gold-btn:hover {
    background: linear-gradient(135deg, #ffe44d, #ffaa33);
    color: #1a1a1a;
    box-shadow: 0 6px 30px rgba(255, 215, 0, 0.6);
    transform: translateY(-2px);
}

.purple-btn {
    background: linear-gradient(135deg, rgba(191, 90, 242, 0.2) 0%, rgba(139, 92, 246, 0.1) 100%);
    border-color: rgba(191, 90, 242, 0.6);
    color: #bf5af2;
    box-shadow: 0 0 15px rgba(191, 90, 242, 0.2);
}

.purple-btn:hover {
    background: linear-gradient(135deg, rgba(191, 90, 242, 0.35) 0%, rgba(139, 92, 246, 0.25) 100%);
    color: #d48cff;
    box-shadow: 0 0 25px rgba(191, 90, 242, 0.4);
    transform: translateY(-2px);
}

.neon-card-btn-large {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 14px 36px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 800;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid;
    margin-top: 10px;
    gap: 8px;
}

.red-btn {
    background: linear-gradient(135deg, rgba(255, 68, 102, 0.25) 0%, rgba(255, 0, 102, 0.15) 100%);
    border-color: #ff4466;
    color: #ff4466;
    box-shadow: 
        0 0 25px rgba(255, 68, 102, 0.35),
        inset 0 0 20px rgba(255, 68, 102, 0.1);
    animation: redGlow 2s ease-in-out infinite;
}

@keyframes redGlow {
    0%, 100% { box-shadow: 0 0 25px rgba(255, 68, 102, 0.35), inset 0 0 20px rgba(255, 68, 102, 0.1); }
    50% { box-shadow: 0 0 40px rgba(255, 68, 102, 0.5), inset 0 0 30px rgba(255, 68, 102, 0.15); }
}

.red-btn:hover {
    background: linear-gradient(135deg, rgba(255, 68, 102, 0.45) 0%, rgba(255, 0, 102, 0.35) 100%);
    color: #ff6688;
    transform: translateY(-3px);
    box-shadow: 0 8px 40px rgba(255, 68, 102, 0.5);
}

/* Animated Coin Icon for Withdrawable */
.coin-stack-animated {
    position: relative;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.coin-stack-animated::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 68, 102, 0.3) 0%, transparent 70%);
    animation: coinPulse 2s ease-in-out infinite;
}

@keyframes coinPulse {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.2); opacity: 1; }
}

.coin-stack-animated i {
    font-size: 36px;
    color: #ffd700;
    filter: drop-shadow(0 0 15px #ffd700);
    animation: coinFloat 3s ease-in-out infinite;
}

@keyframes coinFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

/* Mobile Responsive - Enhanced */
@media (max-width: 480px) {
    .neon-cards-grid {
        padding: 12px;
        gap: 12px;
    }

    .neon-cards-row {
        gap: 10px;
    }

    .neon-card {
        padding: 14px;
        border-radius: 14px;
    }

    .progress-ring-container {
        width: 50px;
        height: 50px;
    }

    .neon-card-icon-ring {
        width: 46px;
        height: 46px;
    }

    .neon-card-icon-ring i {
        font-size: 18px;
    }

    .neon-card-icon {
        font-size: 26px;
    }

    .neon-card-label {
        font-size: 9px;
    }

    .neon-card-value {
        font-size: 20px;
    }

    .neon-card-value-large {
        font-size: 32px;
    }

    .neon-card-btn {
        padding: 8px 14px;
        font-size: 11px;
    }

    .neon-card-btn-large {
        padding: 12px 28px;
        font-size: 13px;
    }

    .coin-stack-animated {
        width: 60px;
        height: 60px;
    }

    .coin-stack-animated i {
        font-size: 30px;
    }
}

/* Hover effects - Enhanced */
.neon-card:hover {
    transform: translateY(-4px);
    box-shadow: 
        0 8px 30px rgba(0, 0, 0, 0.6),
        inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.neon-card:hover .neon-card-glow {
    opacity: 0.25;
}

.neon-card:hover::before {
    animation: borderGlow 1.5s ease infinite;
}
</style>

<body>

<div class="profile-header">
  <div class="cover-photo" style="background-image: url('<?php echo htmlspecialchars($user['cover_photo'] ?? 'assets/images/default-cover.jpg'); ?>');">

    <!-- Cover Upload Form -->
    <form id="coverForm" action="upload_cover.php" method="POST" enctype="multipart/form-data" style="position: absolute; top: 8px; left: 8px;">
      <label for="cover-upload" style="cursor: pointer; background: rgba(0,0,0,0.6); color: white; padding: 5px 8px; border-radius: 4px; font-size: 12px;">
        <i class="fas fa-camera"></i> Cover
      </label>
      <input id="cover-upload" type="file" name="cover_photo" accept="image/*" style="display: none;">
    </form>

    <!-- Loader for Cover -->
    <img id="coverLoader" class="loader" src="assets/images/loader.gif" alt="Loading...">

    <!-- Profile Picture Upload Area -->
    <div class="profile-pic-wrapper">
      <img id="profilePic" src="<?php echo htmlspecialchars(!empty($user['profile_picture']) ? $user['profile_picture'] : 'assets/images/default-avatar.png'); ?>" alt="Profile Picture">

      <form id="profileForm" action="upload_profile.php" method="POST" enctype="multipart/form-data">
        <label class="upload-icon" for="profile-upload">
          <i class="fas fa-camera"></i>
          <input id="profile-upload" type="file" name="profile_picture" accept="image/*">
        </label>
      </form>

      <!-- Loader for Profile -->
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

      <!-- Countdown Badge -->
      <span class="badge bg-primary text-white" id="countdownBadge">
        00:00:00:00
      </span>

      <!-- Check-in Button -->
      <a href="daily_reward" class="btn btn-success" style="font-size: 11px; padding: 4px 8px; margin-left: 8px;">CHECK-IN</a>

      <!-- Promote Button -->
      <a href="/promote_dashboard" class="btn btn-warning" style="font-size: 11px; padding: 4px 8px; margin-left: 8px;">PROMOTE</a>
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

        if (distance <= 0) {
            document.getElementById("countdownBadge").textContent = "00:00:00:00";
            return;
        }

        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        document.getElementById("countdownBadge").textContent =
            String(days).padStart(2, '0') + ":" +
            String(hours).padStart(2, '0') + ":" +
            String(minutes).padStart(2, '0') + ":" +
            String(seconds).padStart(2, '0');
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<div class="container-fluid">
    <!-- Stats Cards -->
    <div class="stats-container">
        <!-- Total Assets Card (New Design) -->
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
                        <select class="currency-dropdown" id="currencySelect">
                            <option value="USD" selected>USD ▼</option>
                            <option value="ETH">ETH ▼</option>
                            <option value="BTC">BTC ▼</option>
                        </select>
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
                        <i class="fas fa-chevron-right" style="font-size: 12px; color: rgba(255,255,255,0.5);"></i>
                    </div>
                </div>
                
                <div class="chart-container">
                    <svg class="trend-chart" viewBox="0 0 120 50" preserveAspectRatio="none">
                        <path id="trendPath" class="<?php echo $todays_pnl > 0 ? 'positive-trend' : ($todays_pnl < 0 ? 'negative-trend' : 'neutral-trend'); ?>" 
                              d="M0,45 Q10,42 20,40 T40,35 T60,30 T80,25 T100,20 T120,<?php echo $todays_pnl >= 0 ? '10' : '40'; ?>">
                        </path>
                    </svg>
                </div>
            </div>
            
            <div class="assets-actions">
                <a href="/wallet" class="btn btn-primary"><i class="fas fa-wallet me-1"></i> Wallet</a>
                <a href="/withdraw" class="btn btn-outline-light"><i class="fas fa-arrow-up me-1"></i> Withdraw</a>
            </div>
        </div>

        <!-- Neon Stats Cards Grid -->
        <div class="neon-cards-grid">
            <!-- SVG Gradient Definitions -->
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
                <!-- Completed Tasks Card (Green theme) with Animated Progress Ring -->
                <div class="neon-card neon-card-green">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <?php 
                        $total_available_tasks = count($recent_tasks);
                        $progress_percent = $total_available_tasks > 0 ? min(100, ($tasks_completed / max(1, $total_available_tasks)) * 100) : 0;
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
                            <div class="progress-ring-inner">
                                <i class="fas fa-check"></i>
                            </div>
                        </div>
                        <div class="neon-card-text">
                            <span class="neon-card-label">COMPLETED</span>
                            <span class="neon-card-label">TASKS</span>
                            <span class="neon-card-value"><?php echo $tasks_completed; ?> / <?php echo max($tasks_completed, 20); ?></span>
                        </div>
                    </div>
                    <a href="/tasks" class="neon-card-cta green-cta">+ Earn more today →</a>
                </div>

                <!-- Referrals Card (Blue/Cyan theme) -->
                <div class="neon-card neon-card-blue">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <div class="neon-card-icon blue-icon">
                            <i class="fas fa-users"></i>
                        </div>
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
                <!-- Deposits Card (Gold/Amber theme) -->
                <div class="neon-card neon-card-gold">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <div class="neon-card-icon-ring gold-ring">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="neon-card-text">
                            <span class="neon-card-label">DEPOSITS</span>
                            <span class="neon-card-value"><?php echo number_format((float)$total_deposits, 2); ?> <span class="value-suffix">USDT</span></span>
                            <span class="neon-card-status gold-status">Status: Active</span>
                        </div>
                    </div>
                    <a href="/deposit" class="neon-card-btn gold-btn"><i class="fas fa-wallet"></i> Add Funds</a>
                </div>

                <!-- Task Earnings Card (Purple/Violet theme) -->
                <div class="neon-card neon-card-purple">
                    <div class="neon-card-glow"></div>
                    <div class="neon-card-content">
                        <div class="neon-card-icon-ring purple-ring">
                            <i class="fas fa-chart-line"></i>
                        </div>
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

            <!-- Row 3: Withdrawable Amount (Full Width - Red/Pink theme) -->
            <div class="neon-card neon-card-red neon-card-full">
                <div class="neon-card-glow"></div>
                <div class="neon-card-content-wide">
                    <div class="coin-stack-animated">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="neon-card-text-center">
                        <span class="neon-card-label-large">WITHDRAWABLE</span>
                        <span class="neon-card-value-large"><?php echo number_format((float)$available_for_withdrawal, 2); ?> <span class="value-suffix-large">USDT</span></span>
                        <span class="neon-card-badge">Ready to withdraw</span>
                    </div>
                    <a href="/withdraw" class="neon-card-btn-large red-btn"><i class="fas fa-paper-plane"></i> Withdraw Now</a>
                </div>
            </div>
        </div>
    </div>
    <!-- Premium Banner (if not premium) -->
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
                <div class="mt-2 small text-light">
                    Only 10 USDT needed to upgrade
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Referral Link -->
    <div class="referral-section">
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">Your Referral Link</h5>
            </div>
            <div class="card-body">
                <p>Share this link with friends and earn USDT when they sign up!</p>
                <div class="input-group">
                    <input type="text" id="referralLink" class="form-control" value="<?php echo $referral_link; ?>" readonly>
                    <button class="btn btn-primary" onclick="copyReferralLink()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Tasks -->
    <div class="mb-3 d-flex justify-content-between align-items-center">
        <h4>Available Tasks</h4>
    </div>

    <div class="row">
        <?php if (!empty($recent_tasks)): ?>
            <?php foreach ($recent_tasks as $task): ?>
                <?php
                    $is_completed = in_array($task['id'], $completed_task_ids);
                    $is_premium_task = $task['is_premium'] == 1;
                    $can_view = !$is_premium_task || ($is_premium_task && $user['is_premium'] == 1);
                ?>
                <div class="col-12 col-md-4 col-lg-4">
                    <div class="card h-100 task-card <?php echo $is_completed ? 'bg-light' : ''; ?> <?php echo $is_premium_task ? 'border-premium' : ''; ?>">
                        <div class="position-relative">
                            <img src="<?php echo !empty($task['thumbnail']) ? $task['thumbnail'] : 'assets/images/placeholder.jpg'; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($task['title']); ?>">
                            <div class="position-absolute top-0 end-0 m-2">
                                <span class="badge <?php echo $task['type'] == 'video' ? 'bg-primary' : 'bg-info'; ?>" style="font-size: 9px;">
                                    <?php echo ucfirst($task['type']); ?>
                                </span>
                                <?php if ($is_premium_task): ?>
                                    <span class="badge bg-warning ms-1" style="font-size: 9px;">Premium</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($is_completed): ?>
                                <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50">
                                    <span class="badge bg-success px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i> Done
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars(substr($task['title'], 0, 25)) . (strlen($task['title']) > 25 ? '...' : ''); ?></h5>
                            <p class="card-text small text-muted"><?php echo substr(strip_tags($task['description']), 0, 60) . '...'; ?></p>
                            <p class="card-text">
                                <strong>Reward:</strong> <?php echo $task['reward']; ?> USDT
                                <?php if ($user['is_premium']): ?>
                                    <span class="text-warning">(x2)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <?php if ($is_completed): ?>
                                <button class="btn btn-outline-success w-100 btn-sm" disabled>
                                    <i class="fas fa-check-circle me-1"></i> Completed
                                </button>
                            <?php elseif (!$can_view): ?>
                                <button class="btn btn-outline-warning w-100 btn-sm" disabled>
                                    <i class="fas fa-crown me-1"></i> Premium Only
                                </button>
                            <?php else: ?>
                                <a href="index.php?page=tasks&action=view&id=<?php echo $task['id']; ?>" class="btn btn-primary w-100 btn-sm">
                                    Start Task
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info text-center">No available tasks at the moment.</div>
            </div>
        <?php endif; ?>
    </div>

<!-- View All Tasks Button -->
<div class="text-center">
    <a href="/tasks" class="view-all-tasks-btn">
        <i class="fas fa-tasks"></i> View All Tasks
        <i class="fas fa-arrow-right"></i>
    </a>
</div>

<!-- Full-width Clickable Banner with Sharp Edges -->
<div class="mt-4 mb-5" style="margin-left: -15px; margin-right: -15px;">
    <a href="/promote_dashboard" target="_blank">
        <img src="assets/images/banner-social-media.jpg" alt="Grow your social media page" style="width: 100%; display: block; border-radius: 0;" />
    </a>
</div>
</div> <!-- Ensure this closing div corresponds to an earlier opened one -->
<script>
function copyReferralLink() {
    const referralLink = document.getElementById('referralLink');
    referralLink.select();
    referralLink.setSelectionRange(0, 99999);
    document.execCommand('copy');
    
    // Show success message
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    button.classList.add('btn-success');
    button.classList.remove('btn-primary');
    
    setTimeout(() => {
        button.innerHTML = originalText;
        button.classList.add('btn-primary');
        button.classList.remove('btn-success');
    }, 2000);
}

document.addEventListener('DOMContentLoaded', function () {
    const coverUpload = document.getElementById('cover-upload');
    const profileUpload = document.getElementById('profile-upload');

    coverUpload.addEventListener('change', function () {
      document.getElementById('coverLoader').style.display = 'block';
      document.getElementById('coverForm').submit();
    });

    profileUpload.addEventListener('change', function () {
      document.getElementById('profileLoader').style.display = 'block';
      document.getElementById('profileForm').submit();
    });
});
</script>

<script>
// Balance visibility toggle and currency conversion
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('toggleBalance');
    const balanceDisplay = document.getElementById('balanceDisplay');
    const visibilityIcon = document.getElementById('visibilityIcon');
    const mainBalance = document.getElementById('mainBalance');
    const btcEquivalent = document.getElementById('btcEquivalent');
    
    // Check saved preference
    const isHidden = localStorage.getItem('balanceHidden') === 'true';
    if (isHidden) {
        balanceDisplay.classList.add('hidden-balance');
        visibilityIcon.classList.remove('fa-eye');
        visibilityIcon.classList.add('fa-eye-slash');
    }
    
    toggleBtn.addEventListener('click', function() {
        const isCurrentlyHidden = balanceDisplay.classList.contains('hidden-balance');
        
        if (isCurrentlyHidden) {
            balanceDisplay.classList.remove('hidden-balance');
            visibilityIcon.classList.remove('fa-eye-slash');
            visibilityIcon.classList.add('fa-eye');
            localStorage.setItem('balanceHidden', 'false');
        } else {
            balanceDisplay.classList.add('hidden-balance');
            visibilityIcon.classList.remove('fa-eye');
            visibilityIcon.classList.add('fa-eye-slash');
            localStorage.setItem('balanceHidden', 'true');
        }
    });
    
    // Currency conversion
    const currencySelect = document.getElementById('currencySelect');
    const originalBalance = <?php echo (float)($user['balance'] ?? 0); ?>;
    
    // Approximate conversion rates
    const rates = {
        'USD': 1,
        'ETH': 0.00029,
        'BTC': 0.0000103
    };
    
    currencySelect.addEventListener('change', function() {
        const currency = this.value;
        const convertedAmount = originalBalance * rates[currency];
        
        if (currency === 'USD') {
            mainBalance.textContent = convertedAmount.toFixed(2);
        } else {
            mainBalance.textContent = convertedAmount.toFixed(8);
        }
    });
    
    // Apply dark mode from localStorage
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
    }
});
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer_navbar.php'; ?>
