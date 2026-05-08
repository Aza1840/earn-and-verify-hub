
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . '/../config/database.php');
require_once(__DIR__ . '/../includes/functions.php');
require_once(__DIR__ . '/../includes/email_notifications.php');
require_once __DIR__ . '/../includes/auth.php';

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo "User not logged in.";
    exit;
}

$stmt = $conn->prepare("SELECT balance, is_premium, deposited_balance, username, email FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($balance, $is_premium, $deposited_balance, $current_username, $user_email);
$stmt->fetch();
$stmt->close();

$current_balance = floatval($balance);
$is_premium = intval($is_premium);
$deposited_balance = floatval($deposited_balance);

// Available subscription plans
// `multiplier` boosts the user's standard earnings.
// 30-day projection assumes a base daily earning of $1 for a standard (un-upgraded) user.
$base_daily_earning = 1.00;
$plans = [
    'silver'   => ['name' => 'Silver Edge',    'price' => 20.00,   'multiplier' => 4,   'icon' => 'fas fa-shield-alt',  'color' => '#9ca3af', 'gradient' => 'linear-gradient(135deg,#d1d5db,#6b7280)'],
    'gold'     => ['name' => 'Gold Surge',     'price' => 50.00,   'multiplier' => 10,  'icon' => 'fas fa-bolt',        'color' => '#f59e0b', 'gradient' => 'linear-gradient(135deg,#fde68a,#d97706)'],
    'platinum' => ['name' => 'Platinum Core',  'price' => 100.00,  'multiplier' => 20,  'icon' => 'fas fa-gem',         'color' => '#06b6d4', 'gradient' => 'linear-gradient(135deg,#a5f3fc,#0891b2)'],
    'diamond'  => ['name' => 'Diamond Flow',   'price' => 200.00,  'multiplier' => 40,  'icon' => 'fas fa-diamond',     'color' => '#3b82f6', 'gradient' => 'linear-gradient(135deg,#bfdbfe,#1d4ed8)'],
    'titan'    => ['name' => 'Titan Vault',    'price' => 500.00,  'multiplier' => 100, 'icon' => 'fas fa-fort-awesome','color' => '#8b5cf6', 'gradient' => 'linear-gradient(135deg,#ddd6fe,#6d28d9)'],
    'apex'     => ['name' => 'Apex Elite',     'price' => 1000.00, 'multiplier' => 200, 'icon' => 'fas fa-crown',       'color' => '#ef4444', 'gradient' => 'linear-gradient(135deg,#fecaca,#b91c1c)'],
];

$selected_plan_key = $_POST['plan'] ?? $_GET['plan'] ?? 'silver';
if (!isset($plans[$selected_plan_key])) {
    $selected_plan_key = 'silver';
}
$selected_plan = $plans[$selected_plan_key];
$upgrade_cost = $selected_plan['price'];

// Check for pending upgrade deposits
$pending_upgrade_stmt = $conn->prepare("SELECT id, amount, crypto_type, created_at FROM deposits WHERE user_id = ? AND upgrade_request = 1 AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$pending_upgrade_stmt->bind_param("i", $user_id);
$pending_upgrade_stmt->execute();
$pending_upgrade_result = $pending_upgrade_stmt->get_result();
$has_pending_upgrade = $pending_upgrade_result->num_rows > 0;
$pending_upgrade_data = $has_pending_upgrade ? $pending_upgrade_result->fetch_assoc() : null;
$pending_upgrade_stmt->close();

// Get site settings for crypto addresses
$settings = getSiteSettings();

// Get supported cryptocurrencies
$supportedCryptos = [
    [
        'name' => 'USDT',
        'code' => 'TRC20 network',
        'type' => 'usdt',
        'icon' => 'fas fa-dollar-sign',
        'address' => $settings['usdt_address'] ?? '',
        'min_deposit' => $settings['min_deposit_usdt'] ?? 10,
        'qr_code' => $settings['usdt_qr'] ?? ''
    ],
    [
        'name' => 'Bitcoin',
        'code' => 'BTC',
        'type' => 'bitcoin',
        'icon' => 'fab fa-bitcoin',
        'address' => $settings['bitcoin_address'] ?? '',
        'min_deposit' => $settings['min_deposit_btc'] ?? 0.001,
        'qr_code' => $settings['bitcoin_qr'] ?? ''
    ],
    [
        'name' => 'Ethereum',
        'code' => 'ETH',
        'type' => 'ethereum',
        'icon' => 'fab fa-ethereum',
        'address' => $settings['ethereum_address'] ?? '',
        'min_deposit' => $settings['min_deposit_eth'] ?? 0.01,
        'qr_code' => $settings['ethereum_qr'] ?? ''
    ],    
    [
        'name' => 'Litecoin',
        'code' => 'LTC',
        'type' => 'litecoin',
        'icon' => 'fab fa-bitcoin',
        'address' => $settings['litecoin_address'] ?? '',
        'min_deposit' => $settings['min_deposit_ltc'] ?? 0.1,
        'qr_code' => $settings['litecoin_qr'] ?? ''
    ],
    [
        'name' => 'Dogecoin',
        'code' => 'DOGE',
        'type' => 'dogecoin',
        'icon' => 'fab fa-bitcoin',
        'address' => $settings['dogecoin_address'] ?? '',
        'min_deposit' => $settings['min_deposit_doge'] ?? 100,
        'qr_code' => $settings['dogecoin_qr'] ?? ''
    ],
    [
        'name' => 'Solana',
        'code' => 'SOL',
        'type' => 'solana',
        'icon' => 'fas fa-sun',
        'address' => $settings['solana_address'] ?? '',
        'min_deposit' => $settings['min_deposit_sol'] ?? 1,
        'qr_code' => $settings['solana_qr'] ?? ''
    ]
];

// Handle deposit form submission for upgrade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crypto_type'], $_POST['amount'], $_POST['transaction_hash'])) {
    $crypto_type = trim($_POST['crypto_type']);
    $amount = floatval($_POST['amount']);
    $transaction_hash = trim($_POST['transaction_hash']);
    
    if ($amount < $upgrade_cost) {
        $error = "Minimum deposit for upgrade is $" . $upgrade_cost;
    } elseif (empty($transaction_hash)) {
        $error = "Transaction hash is required.";
    } else {
        // Check for duplicate transaction hash with user information
        $duplicate_check = $conn->prepare("SELECT d.id, u.username FROM deposits d JOIN users u ON d.user_id = u.id WHERE d.transaction_hash = ?");
        $duplicate_check->bind_param("s", $transaction_hash);
        $duplicate_check->execute();
        $duplicate_result = $duplicate_check->get_result();
        
        if ($duplicate_result->num_rows > 0) {
            $duplicate_row = $duplicate_result->fetch_assoc();
            $existing_username = $duplicate_row['username'];
            
            if ($existing_username === $current_username) {
                $error = "duplicate_transaction_same_user";
                $duplicate_transaction_id = $transaction_hash;
            } else {
                $error = "duplicate_transaction_different_user";
                $duplicate_transaction_id = $transaction_hash;
                $duplicate_user = $existing_username;
            }
            $duplicate_check->close();
        } else {
            $duplicate_check->close();
            
            // Handle file upload for payment proof
            $proof_url = '';
            if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/payment_proofs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $filename = 'upgrade_proof_' . $user_id . '_' . time() . '.' . $file_extension;
                    $file_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $file_path)) {
                        $proof_url = 'uploads/payment_proofs/' . $filename;
                    } else {
                        $error = "Failed to upload proof file.";
                    }
                } else {
                    $error = "Invalid file type. Please upload JPG, PNG, GIF, or PDF files only.";
                }
            }
            
            if (!isset($error)) {
                // Insert deposit record with upgrade flag
                $stmt = $conn->prepare("INSERT INTO deposits (user_id, amount, crypto_type, transaction_hash, proof_url, status, created_at, upgrade_request) VALUES (?, ?, ?, ?, ?, 'pending', NOW(), 1)");
                if ($stmt) {
                    $stmt->bind_param("idsss", $user_id, $amount, $crypto_type, $transaction_hash, $proof_url);
                    if ($stmt->execute()) {
                        $deposit_id = $conn->insert_id;
                        
                        // Insert pending transaction record
                        $details = "Upgrade deposit request of $amount $crypto_type for {$selected_plan['name']} plan";
                        $txn_stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, source, details, status, created_at) VALUES (?, ?, 'deposit', 'upgrade', ?, 'pending', NOW())");
                        if ($txn_stmt) {
                            $txn_stmt->bind_param("ids", $user_id, $amount, $details);
                            $txn_stmt->execute();
                            $txn_stmt->close();
                        }
                        
                        // Send email notifications
                        EmailNotifications::notifyUserUpgradeRequest($user_email, $current_username, $amount, $crypto_type);
                        EmailNotifications::notifyAdminUserUpgradeRequest($user_email, $current_username, $amount, $crypto_type);
                        
                        $success = "Upgrade deposit request for {$selected_plan['name']} (\${$selected_plan['price']}) submitted successfully. You will be upgraded after admin approval.";
                        
                        // Refresh pending upgrade status
                        $pending_upgrade_stmt = $conn->prepare("SELECT id, amount, crypto_type, created_at FROM deposits WHERE user_id = ? AND upgrade_request = 1 AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
                        $pending_upgrade_stmt->bind_param("i", $user_id);
                        $pending_upgrade_stmt->execute();
                        $pending_upgrade_result = $pending_upgrade_stmt->get_result();
                        $has_pending_upgrade = $pending_upgrade_result->num_rows > 0;
                        $pending_upgrade_data = $has_pending_upgrade ? $pending_upgrade_result->fetch_assoc() : null;
                        $pending_upgrade_stmt->close();
                    } else {
                        $error = "Failed to submit upgrade deposit request: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Database error: " . $conn->error;
                }
            }
        }
    }
}

$upgrade_success = false;
$error = isset($error) ? $error : "";

// Check if already upgraded after a successful deposit
if ($is_premium && !$upgrade_success) {
    $upgrade_success = true;
}
?>

<div class="main-content">
    <div class="container-fluid">

        <?php if (!empty($_SESSION['message'])): ?>
            <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error) && !empty($error) && !in_array($error, ["duplicate_transaction_same_user", "duplicate_transaction_different_user"])): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-5 mt-4">
            <h1 class="mb-0">Upgrade to Premium</h1>
            <div class="breadcrumb float-right">
                <a href="dashboard" style="color: #007bff; text-decoration: none;">Dashboard</a> /
                <span style="color: #000;">Upgrade</span>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <?php if ($is_premium): ?>
                    <div class="card shadow-sm mx-auto" style="max-width: 500px; border-radius: 20px; border: 1px solid #28a745; padding: 30px;">
                        <div class="card-body text-center">
                            <img src="assets/images/crown.png" alt="Crown" style="width: 50px; margin-bottom: 15px;">
                            <h2 class="fw-bold mb-2 text-success">Premium Member</h2>
                            <div class="alert alert-success">You're already a Premium member!</div>
                            <p class="mb-4 text-muted">Enjoy exclusive features and better rewards</p>
                        </div>
                    </div>
                <?php elseif ($has_pending_upgrade): ?>
                    <div class="card shadow-sm mx-auto" style="max-width: 500px; border-radius: 20px; border: 1px solid #ffc107; padding: 30px;">
                        <div class="card-body text-center">
                            <img src="assets/images/crown.png" alt="Crown" style="width: 50px; margin-bottom: 15px;">
                            <h2 class="fw-bold mb-2 text-warning">Upgrade Request Pending</h2>
                            <div class="alert alert-warning">
                                <i class="fas fa-clock me-2"></i>
                                Your upgrade deposit request is being reviewed by our team.
                            </div>
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title">Pending Request Details:</h6>
                                    <p class="mb-1"><strong>Amount:</strong> $<?php echo number_format($pending_upgrade_data['amount'], 2); ?></p>
                                    <p class="mb-1"><strong>Cryptocurrency:</strong> <?php echo strtoupper($pending_upgrade_data['crypto_type']); ?></p>
                                    <p class="mb-1"><strong>Submitted:</strong> <?php echo date('M d, Y H:i', strtotime($pending_upgrade_data['created_at'])); ?></p>
                                    <p class="mb-0 text-muted">You will be automatically upgraded to Premium once the request is approved.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card shadow-sm mb-4" style="border-radius: 20px;">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <h3 class="fw-bold mb-2">Choose Your Plan</h3>
                                <p class="text-muted">Select a subscription plan that fits your goals</p>
                            </div>
                            <div class="row g-3">
                                <?php foreach ($plans as $key => $plan): ?>
                                    <?php
                                        $monthly = $base_daily_earning * $plan['multiplier'] * 30;
                                        $daily   = $base_daily_earning * $plan['multiplier'];
                                        $isSel   = $key === $selected_plan_key;
                                    ?>
                                    <div class="col-6 col-md-4">
                                        <a href="?plan=<?php echo $key; ?>" class="text-decoration-none">
                                            <div class="card h-100 plan-card <?php echo $isSel ? 'plan-selected' : ''; ?>"
                                                 style="border-radius: 18px; border: 2px solid <?php echo $isSel ? $plan['color'] : 'rgba(0,0,0,0.06)'; ?>; cursor: pointer; transition: transform .2s, box-shadow .2s; overflow: hidden; <?php echo $isSel ? 'box-shadow: 0 12px 30px -10px '.$plan['color'].'80;' : ''; ?>">
                                                <div style="background: <?php echo $plan['gradient']; ?>; height: 6px;"></div>
                                                <div class="card-body text-center p-3">
                                                    <div class="mx-auto mb-2 d-flex align-items-center justify-content-center"
                                                         style="width:54px;height:54px;border-radius:50%;background: <?php echo $plan['gradient']; ?>;">
                                                        <i class="<?php echo $plan['icon']; ?> fa-lg" style="color:#fff;"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-1" style="color:#222;"><?php echo $plan['name']; ?></h6>
                                                    <div class="fw-bold" style="color: <?php echo $plan['color']; ?>; font-size: 1.15rem;">
                                                        $<?php echo number_format($plan['price'], 0); ?>
                                                    </div>
                                                    <span class="badge mt-1 mb-2" style="background: <?php echo $plan['gradient']; ?>; color:#fff; font-size:.75rem;">
                                                        <i class="fas fa-rocket me-1"></i><?php echo $plan['multiplier']; ?>x Earnings
                                                    </span>
                                                    <div class="earning-calc mt-2 p-2" style="background: rgba(0,0,0,0.04); border-radius: 12px;">
                                                        <div style="font-size:.65rem; color:#6b7280; text-transform:uppercase; letter-spacing:.5px;">
                                                            <i class="far fa-calendar-alt me-1"></i>30-Day Earnings
                                                        </div>
                                                        <div class="fw-bold" style="color: <?php echo $plan['color']; ?>; font-size: 1.05rem;">
                                                            $<?php echo number_format($monthly, 0); ?>
                                                        </div>
                                                        <div style="font-size:.65rem; color:#6b7280;">
                                                            ~$<?php echo number_format($daily, 2); ?>/day
                                                        </div>
                                                    </div>
                                                    <?php if ($isSel): ?>
                                                        <span class="badge bg-primary mt-2"><i class="fas fa-check"></i> Selected</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <style>
                                .plan-card:hover { transform: translateY(-4px); box-shadow: 0 14px 30px -12px rgba(0,0,0,0.2); }
                            </style>
                        </div>
                    </div>

                    <div class="card shadow-sm" style="border-radius: 20px;">
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <img src="assets/images/crown.png" alt="Crown" style="width: 50px; margin-bottom: 15px;">
                                <h2 class="fw-bold mb-2">Upgrade to <?php echo $selected_plan['name']; ?></h2>
                                <p class="mb-4 text-muted">Deposit $<?= number_format($upgrade_cost, 2) ?> in cryptocurrency to activate the <?php echo $selected_plan['name']; ?> plan</p>
                            </div>

                            <ul class="nav nav-pills mb-4" id="depositTabs" role="tablist">
                                <?php foreach($supportedCryptos as $index => $crypto): ?>
                                    <?php if (!empty($crypto['address'])): ?>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                id="<?php echo $crypto['type']; ?>-tab" 
                                                data-bs-toggle="pill" 
                                                data-bs-target="#<?php echo $crypto['type']; ?>-pane" 
                                                type="button" 
                                                role="tab" 
                                                aria-controls="<?php echo $crypto['type']; ?>-pane" 
                                                aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                            <i class="<?php echo $crypto['icon']; ?> me-2"></i> <?php echo $crypto['name']; ?>
                                        </button>
                                    </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            
                            <div class="tab-content" id="depositTabsContent">
                                <?php foreach($supportedCryptos as $index => $crypto): ?>
                                    <?php if (!empty($crypto['address'])): ?>
                                    <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" 
                                         id="<?php echo $crypto['type']; ?>-pane" 
                                         role="tabpanel" 
                                         aria-labelledby="<?php echo $crypto['type']; ?>-tab" 
                                         tabindex="0">
                                        
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <div class="alert alert-info">
                                                    <p class="mb-2"><i class="fas fa-info-circle me-2"></i> How to upgrade with <?php echo $crypto['name']; ?>:</p>
                                                    <ol class="mb-0">
                                                        <li>Send at least $<?= $upgrade_cost ?> worth of <?php echo $crypto['code']; ?> to the address below</li>
                                                        <li>Upload payment proof (screenshot/receipt)</li>
                                                        <li>Enter the transaction ID</li>
                                                        <li>Submit the form</li>
                                                        <li>You will be upgraded after admin approval</li>
                                                    </ol>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Deposit Address</label>
                                                    <div class="input-group mb-3">
                                                        <input type="text" class="form-control" value="<?php echo $crypto['address']; ?>" id="<?php echo $crypto['type']; ?>Address" readonly>
                                                        <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?php echo $crypto['type']; ?>Address')">Copy</button>
                                                    </div>
                                                    <div class="bg-light p-3 text-center mb-3">
                                                        <?php if (!empty($crypto['qr_code'])): ?>
                                                            <img src="<?php echo $crypto['qr_code']; ?>" alt="QR Code" style="max-width: 200px;" class="img-fluid">
                                                        <?php else: ?>
                                                            <div class="bg-secondary text-white p-4 rounded">
                                                                <i class="fas fa-qrcode fa-3x"></i>
                                                                <p class="mt-2 mb-0">QR Code not configured</p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <form action="" method="post" enctype="multipart/form-data" class="needs-validation" novalidate>
                                                    <input type="hidden" name="crypto_type" value="<?php echo $crypto['type']; ?>">
                                                    <input type="hidden" name="plan" value="<?php echo $selected_plan_key; ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label for="<?php echo $crypto['type']; ?>Amount" class="form-label">Amount (<?php echo $crypto['code']; ?>)</label>
                                                        <input type="number" class="form-control" id="<?php echo $crypto['type']; ?>Amount" name="amount" step="0.00000001" min="<?php echo $upgrade_cost; ?>" value="<?php echo $upgrade_cost; ?>" required>
                                                        <div class="form-text">Minimum for upgrade: $<?php echo $upgrade_cost; ?></div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="<?php echo $crypto['type']; ?>TxHash" class="form-label">Transaction Hash/ID</label>
                                                        <input type="text" class="form-control" id="<?php echo $crypto['type']; ?>TxHash" name="transaction_hash" required>
                                                        <div class="form-text">Enter the transaction ID from your wallet</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label for="<?php echo $crypto['type']; ?>Proof" class="form-label">Payment Proof (Required)</label>
                                                        <input type="file" class="form-control" id="<?php echo $crypto['type']; ?>Proof" name="payment_proof" accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                                                        <div class="form-text">Upload screenshot or receipt of your payment</div>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" id="<?php echo $crypto['type']; ?>Confirm" required>
                                                            <label class="form-check-label" for="<?php echo $crypto['type']; ?>Confirm">
                                                                I confirm that I have sent the upgrade payment and uploaded valid proof
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-grid">
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-star me-2"></i> Submit Upgrade Payment
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Info Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4" style="border-radius: 15px;">
                    <div class="card-body text-center">
                        <h3 class="fw-bold mb-3">Premium Benefits</h3>
                        <div class="mb-3">
                            <i class="fas fa-star text-warning fa-2x mb-2"></i>
                            <h5>2x Earnings Multiplier</h5>
                            <p class="text-muted">Double your rewards on all tasks</p>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-crown text-warning fa-2x mb-2"></i>
                            <h5>Exclusive Tasks</h5>
                            <p class="text-muted">Access to premium-only content</p>
                        </div>
                        <div class="mb-3">
                            <i class="fas fa-headset text-primary fa-2x mb-2"></i>
                            <h5>Priority Support</h5>
                            <p class="text-muted">Get help faster than regular users</p>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm" style="border-radius: 15px;">
                    <div class="card-header">
                        <h5 class="mb-0">Account Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Current Plan:</strong>
                            <span class="badge <?= $is_premium ? 'bg-success' : ($has_pending_upgrade ? 'bg-warning' : 'bg-secondary') ?> ms-2">
                                <?= $is_premium ? 'Premium' : ($has_pending_upgrade ? 'Upgrade Pending' : 'Free') ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <strong>Current Balance:</strong>
                            <div class="text-muted">$<?= number_format($current_balance, 2) ?></div>
                        </div>
                        <div class="mb-3">
                            <strong>Selected Plan:</strong>
                            <div style="color: <?php echo $selected_plan['color']; ?>;" class="fw-bold"><?php echo $selected_plan['name']; ?></div>
                        </div>
                        <div class="mb-3">
                            <strong>Upgrade Cost:</strong>
                            <div class="text-primary">$<?= number_format($upgrade_cost, 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyToClipboard(elementId) {
    var copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(copyText.value).then(function() {
        alert("Address copied to clipboard!");
    });
}

// Check for duplicate transaction errors and show toast
<?php if (isset($error) && $error === "duplicate_transaction_same_user"): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('The submitted transaction with ID <?php echo htmlspecialchars($duplicate_transaction_id); ?> has already been submitted by <?php echo htmlspecialchars($current_username); ?>', 'error');
});
<?php elseif (isset($error) && $error === "duplicate_transaction_different_user"): ?>
document.addEventListener('DOMContentLoaded', function() {
    showToast('The submitted transaction with ID <?php echo htmlspecialchars($duplicate_transaction_id); ?> has already been submitted by <?php echo htmlspecialchars($duplicate_user); ?>', 'error');
});
<?php endif; ?>

function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show`;
    toast.style.cssText = 'margin-bottom: 10px; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 5000);
}

(function () {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer_navbar.php'; ?>
