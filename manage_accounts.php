<?php
session_start();
require 'db.php';
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Handle account deletion
if (isset($_POST['delete_account']) && isset($_POST['account_id'])) {
    $accountId = intval($_POST['account_id']);
    
    // Check if account belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        // If this was the default account, need to set a new default
        $needNewDefault = $account['is_default'] == 1;
        $platforms = $account['platforms'];
        
        // Delete the account
        $stmt = $pdo->prepare("DELETE FROM social_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        
        // If needed, set a new default account for this platform
        if ($needNewDefault) {
            $stmt = $pdo->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platforms = ? LIMIT 1");
            $stmt->execute([$userId, $platforms]);
            $newDefault = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($newDefault) {
                $stmt = $pdo->prepare("UPDATE social_accounts SET is_default = 1 WHERE id = ?");
                $stmt->execute([$newDefault['id']]);
            }
        }
        
        $_SESSION['manage_success'] = "Account successfully deleted.";
    } else {
        $_SESSION['manage_error'] = "Account not found or you don't have permission to delete it.";
    }
    
    header("Location: manage_accounts.php");
    exit();
}

// Handle setting default account
if (isset($_POST['set_default']) && isset($_POST['account_id'])) {
    $accountId = intval($_POST['account_id']);
    
    // Check if account belongs to the user
    $stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($account) {
        // First, remove default status from all accounts of this platform
        $stmt = $pdo->prepare("UPDATE social_accounts SET is_default = 0 WHERE user_id = ? AND platforms = ?");
        $stmt->execute([$userId, $account['platforms']]);
        
        // Then set this account as default
        $stmt = $pdo->prepare("UPDATE social_accounts SET is_default = 1 WHERE id = ?");
        $stmt->execute([$accountId]);
        
        $_SESSION['manage_success'] = "Default account updated successfully.";
    } else {
        $_SESSION['manage_error'] = "Account not found or you don't have permission to modify it.";
    }
    
    header("Location: manage_accounts.php");
    exit();
}

// Get all connected accounts for this user
$stmt = $pdo->prepare("SELECT * FROM social_accounts WHERE user_id = ? ORDER BY platforms, is_default DESC");
$stmt->execute([$userId]);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize accounts by platform
$accountsByPlatform = [];
foreach ($accounts as $account) {
    if (!isset($accountsByPlatform[$account['platforms']])) {
        $accountsByPlatform[$account['platforms']] = [];
    }
    $accountsByPlatform[$account['platforms']][] = $account;
}

// Platform display names and icons
$platformInfo = [
    'facebook' => ['name' => 'Facebook', 'icon' => 'fab fa-facebook', 'color' => '#1877F2'],
    'instagram' => ['name' => 'Instagram', 'icon' => 'fab fa-instagram', 'color' => '#E4405F'],
    'telegram' => ['name' => 'Telegram', 'icon' => 'fab fa-telegram', 'color' => '#0088cc'],
    'whatsapp' => ['name' => 'WhatsApp', 'icon' => 'fab fa-whatsapp', 'color' => '#25D366'],
    'pixelfed' => ['name' => 'Pixelfed', 'icon' => 'fab fa-mastodon', 'color' => '#6364FF']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - MultiPost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .account-card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .account-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .platform-icon {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        .default-badge {
            background-color: #198754;
            color: white;
            font-size: 0.7rem;
            margin-left: 8px;
            padding: 3px 8px;
            border-radius: 12px;
        }
        .account-actions {
            display: flex;
            gap: 10px;
        }
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        .section-title {
            margin-bottom: 0;
            margin-left: 10px;
        }
        .platform-section {
            margin-bottom: 40px;
        }
        .empty-state {
            background-color: #e9ecef;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
        }
        .empty-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Manage Your Connected Accounts</h1>
            <a href="add_platformes.php" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add New Account
            </a>
        </div>

        <?php if (isset($_SESSION['manage_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['manage_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['manage_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['manage_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['manage_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['manage_error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['auth_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['auth_success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['auth_success']); ?>
        <?php endif; ?>

        <?php if (count($accounts) === 0): ?>
            <div class="empty-state">
                <i class="fas fa-link-slash empty-icon"></i>
                <h3>No Connected Accounts</h3>
                <p class="text-muted">You haven't connected any social media accounts yet.</p>
                <a href="add_platformes.php" class="btn btn-primary mt-3">Connect Your First Account</a>
            </div>
        <?php else: ?>
            <?php foreach ($accountsByPlatform as $platform => $platformAccounts): ?>
                <div class="platform-section">
                    <div class="section-header">
                        <i class="<?php echo $platformInfo[$platform]['icon']; ?> platform-icon" style="color: <?php echo $platformInfo[$platform]['color']; ?>"></i>
                        <h2 class="section-title"><?php echo $platformInfo[$platform]['name']; ?> Accounts</h2>
                    </div>
                    
                    <div class="row">
                        <?php foreach ($platformAccounts as $account): ?>
                            <div class="col-lg-6 col-md-12">
                                <div class="card account-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="card-title">
                                                <?php echo htmlspecialchars($account['account_name']); ?>
                                                <?php if ($account['is_default'] == 1): ?>
                                                    <span class="default-badge">Default</span>
                                                <?php endif; ?>
                                            </h5>
                                            <div class="account-actions">
                                                <?php if ($account['is_default'] != 1): ?>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                        <button type="submit" name="set_default" class="btn btn-sm btn-outline-success" title="Set as default">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to disconnect this account? This action cannot be undone.');">
                                                    <input type="hidden" name="account_id" value="<?php echo $account['id']; ?>">
                                                    <button type="submit" name="delete_account" class="btn btn-sm btn-outline-danger" title="Delete account">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="card-text">
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <?php if ($platform === 'facebook'): ?>
                                                        Page ID: <?php echo htmlspecialchars($account['page_id'] ?? $account['user_id_platform']); ?>
                                                    <?php elseif ($platform === 'instagram'): ?>
                                                        Account ID: <?php echo htmlspecialchars($account['user_id_platform']); ?>
                                                    <?php elseif ($platform === 'telegram'): ?>
                                                        Chat ID: <?php echo htmlspecialchars($account['user_id_platform']); ?>
                                                    <?php elseif ($platform === 'whatsapp'): ?>
                                                        Phone Number ID: <?php echo htmlspecialchars($account['user_id_platform']); ?>
                                                    <?php elseif ($platform === 'pixelfed'): ?>
                                                       Username: <?php echo htmlspecialchars($account['user_id_platform'] ?? ''); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle"></i> Connected
                                                </span>
                                                <?php if ($platform === 'facebook' || $platform === 'instagram'): ?>
                                                    <a href="refresh_token.php?id=<?php echo $account['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-sync"></i> Refresh Token
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>MultiPost</h5>
                    <p>Your all-in-one social media management platform</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> MultiPost. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>