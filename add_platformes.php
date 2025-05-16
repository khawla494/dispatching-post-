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

// Get the list of already connected accounts
$stmt = $pdo->prepare("SELECT platforms FROM social_accounts WHERE user_id = ?");
$stmt->execute([$userId]);
$connectedPlatforms = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Platforms - MultiPost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .platform-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
            height: 100%;
        }
        .platform-card:hover {
            transform: translateY(-5px);
        }
        .platform-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .fa-facebook { color: #1877F2; }
        .fa-instagram { color: #E4405F; }
        .fa-telegram { color: #0088cc; }
        .fa-whatsapp { color: #25D366; }
        .fa-mastodon { color: #6364FF; }
        .platform-btn {
            border-radius: 20px;
            padding: 8px 20px;
        }
        .platform-connected {
            background-color: #e9ecef;
            color: #6c757d;
            pointer-events: none;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-4">Add Social Media Platforms</h1>
                
                <?php if (isset($_SESSION['auth_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['auth_error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['auth_error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['auth_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_SESSION['auth_success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['auth_success']); ?>
                <?php endif; ?>

                <p class="lead">Connect your social media accounts to start scheduling and publishing content across multiple platforms.</p>
                <div class="d-flex justify-content-end mb-3">
                    <a href="manage_accounts.php" class="btn btn-outline-primary">
                        <i class="fas fa-cog"></i> Manage Connected Accounts
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Facebook -->
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card platform-card">
                    <div class="card-body text-center p-4">
                        <i class="fab fa-facebook platform-icon"></i>
                        <h4 class="card-title">Facebook</h4>
                        <p class="card-text">Connect your Facebook Pages to publish posts directly to your timeline.</p>
                        <a href="facebook/auth_facebook.php" class="btn btn-primary platform-btn<?php echo in_array('facebook', $connectedPlatforms) ? ' platform-connected' : ''; ?>">
                            <?php echo in_array('facebook', $connectedPlatforms) ? 'Connected' : 'Connect'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Instagram -->
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card platform-card">
                    <div class="card-body text-center p-4">
                        <i class="fab fa-instagram platform-icon"></i>
                        <h4 class="card-title">Instagram</h4>
                        <p class="card-text">Connect your Instagram Business account to schedule and publish content.</p>
                        <a href="auth_instagram.php" class="btn btn-primary platform-btn<?php echo in_array('instagram', $connectedPlatforms) ? ' platform-connected' : ''; ?>">
                            <?php echo in_array('instagram', $connectedPlatforms) ? 'Connected' : 'Connect'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Telegram -->
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card platform-card">
                    <div class="card-body text-center p-4">
                        <i class="fab fa-telegram platform-icon"></i>
                        <h4 class="card-title">Telegram</h4>
                        <p class="card-text">Connect your Telegram channel or group to automatically post content.</p>
                        <a href="connect_telegram.php" class="btn btn-primary platform-btn<?php echo in_array('telegram', $connectedPlatforms) ? ' platform-connected' : ''; ?>">
                            <?php echo in_array('telegram', $connectedPlatforms) ? 'Connected' : 'Connect'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- WhatsApp -->
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card platform-card">
                    <div class="card-body text-center p-4">
                        <i class="fab fa-whatsapp platform-icon"></i>
                        <h4 class="card-title">WhatsApp</h4>
                        <p class="card-text">Connect your WhatsApp Business account to schedule messages to your contacts.</p>
                        <a href="connect_whatsapp.php" class="btn btn-primary platform-btn<?php echo in_array('whatsapp', $connectedPlatforms) ? ' platform-connected' : ''; ?>">
                            <?php echo in_array('whatsapp', $connectedPlatforms) ? 'Connected' : 'Connect'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pixelfed (Mastodon) -->
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card platform-card">
                    <div class="card-body text-center p-4">
                        <i class="fab fa-mastodon platform-icon"></i>
                        <h4 class="card-title">Pixelfed</h4>
                        <p class="card-text">Connect your Pixelfed account to share images with the Fediverse.</p>
                        <a href="connect_pixelfed.php" class="btn btn-primary platform-btn<?php echo in_array('pixelfed', $connectedPlatforms) ? ' platform-connected' : ''; ?>">
                            <?php echo in_array('pixelfed', $connectedPlatforms) ? 'Connected' : 'Connect'; ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Coming Soon Platform -->
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card platform-card bg-light">
                    <div class="card-body text-center p-4">
                        <i class="fas fa-plus-circle platform-icon text-secondary"></i>
                        <h4 class="card-title">More Platforms</h4>
                        <p class="card-text">More social media platforms coming soon. Stay tuned for updates!</p>
                        <button class="btn btn-outline-secondary platform-btn" disabled>Coming Soon</button>
                    </div>
                </div>
            </div>
        </div>
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