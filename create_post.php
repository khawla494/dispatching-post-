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
$errorMsg = '';
$successMsg = '';

// Get connected accounts from database
$accounts = getAccountConfig($userId);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $platforms = isset($_POST['platforms']) ? $_POST['platforms'] : [];
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    $scheduleOption = isset($_POST['schedule_option']) ? $_POST['schedule_option'] : 'now';
    $scheduledTime = isset($_POST['scheduled_time']) ? $_POST['scheduled_time'] : '';
    $selectedAccounts = isset($_POST['account_ids']) ? $_POST['account_ids'] : [];
    
    // Validate form data
    if (empty($platforms)) {
        $errorMsg = "Please select at least one platform.";
    } elseif (empty($content)) {
        $errorMsg = "Post content cannot be empty.";
    } elseif ($scheduleOption === 'schedule' && empty($scheduledTime)) {
        $errorMsg = "Please select a scheduled time.";
    } elseif (count($selectedAccounts) === 0) {
        $errorMsg = "Please select at least one account.";
    } else {
        // Handle file upload if any
        $mediaPath = '';
        
        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = UPLOAD_DIR . '/' . $userId . '/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . basename($_FILES['media']['name']);
            $uploadFile = $uploadDir . $fileName;
            
            // Check file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'video/quicktime'];
            $fileType = $_FILES['media']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errorMsg = "Invalid file type. Allowed types: JPG, PNG, GIF, MP4.";
            } elseif (!move_uploaded_file($_FILES['media']['tmp_name'], $uploadFile)) {
                $errorMsg = "Failed to upload file.";
            } else {
                $mediaPath = $userId . '/' . $fileName;
            }
        }
        
        if (empty($errorMsg)) {
            // Set scheduled time
            $scheduledAt = new DateTime();
            if ($scheduleOption === 'schedule') {
                $scheduledAt = new DateTime($scheduledTime);
            }
            
            // Format scheduled time for database
            $scheduledAtFormatted = $scheduledAt->format('Y-m-d H:i:s');
            
            // Convert platforms array to comma-separated string
            $platformsStr = implode(',', $platforms);
            
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                $postIds = []; // Array to hold created post IDs
                $queueIds = []; // Array to hold queue IDs
                
                // For each selected account
                foreach ($selectedAccounts as $accountId) {
                    // Extract platform from account ID
                    list($platform, $id) = explode('_', $accountId);
                    
                    // Insert into posts table
                    $stmt = $pdo->prepare("INSERT INTO posts (user_id, platforms, content, media_path, scheduled_at, created_at, status, account_id) 
                                          VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)");
                    $stmt->execute([
                        $userId,
                        $platform,
                        $content,
                        $mediaPath,
                        $scheduledAtFormatted,
                        ($scheduleOption === 'now' ? 'pending' : 'scheduled'),
                        $id
                    ]);
                    
                    $postId = $pdo->lastInsertId();
                    $postIds[] = $postId;
                    
                    // Insert into post_queue table
                    $stmt = $pdo->prepare("INSERT INTO post_queue (post_id, platforms, status, attempts, created_at, account_id, scheduled_at)
                                          VALUES (?, ?, ?, ?, NOW(), ?, ?)");
                    $stmt->execute([
                        $postId,
                        $platform,
                        ($scheduleOption === 'now' ? 'pending' : 'scheduled'),
                        0,
                        $id,
                        $scheduledAtFormatted
                    ]);
                    
                    $queueIds[] = $pdo->lastInsertId();
                }
                
                // Commit transaction
                $pdo->commit();
        

// If user selected "Post Now", process the posts immediately
if ($scheduleOption === 'now') {
    // Process posts immediately
    require_once 'process_post.php';
    
    try {
        // Process the posts we just created
        $processingResult = processPostsNow($queueIds);
        
        if ($processingResult['success']) {
            $successMsg = "Your post has been published successfully to the selected platforms.";
        } else {
            // Show specific error but don't attempt to rollback since we've already committed
            $errorDetails = '';
            foreach ($processingResult['results'] as $result) {
                if (!$result['success']) {
                    $errorDetails .= $result['error'] . ' ';
                }
            }
            $errorMsg = "Failed to publish post: " . trim($errorDetails);
            
            // Log the error for admin
            error_log("Failed to process post immediately: " . ($processingResult['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        // Log the exception but don't attempt to rollback since we've already committed
        error_log("Exception in immediate post processing: " . $e->getMessage());
        $errorMsg = "An error occurred while processing your post. It has been saved and queued for later processing.";
    }
} else {
    $successMsg = "Your post has been scheduled for " . $scheduledAt->format('F j, Y \a\t g:i A');
}
                
                // Clear form data
                $content = '';
                $platforms = [];
                $scheduleOption = 'now';
                $scheduledTime = '';
                $selectedAccounts = [];
                
            } catch (PDOException $e) {
                // Rollback transaction if error occurs
                $pdo->rollBack();
                $errorMsg = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Get current date and time (for min attribute in datetime-local input)
$now = new DateTime();
$minScheduleTime = $now->format('Y-m-d\TH:i');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - MultiPost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .post-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .platform-selector {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        .platform-selector input[type="checkbox"] {
            display: none;
        }
        .platform-selector label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            border-radius: 12px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 10px;
        }
        .platform-selector label:hover {
            border-color: #ced4da;
            background-color: #f8f9fa;
        }
        .platform-selector input[type="checkbox"]:checked + label {
            border-color: #0d6efd;
            background-color: #e7f1ff;
        }
        .platform-selector .platform-icon {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        .platform-selector .platform-name {
            font-size: 0.8rem;
            text-align: center;
        }
        .fa-facebook { color: #1877F2; }
        .fa-instagram { color: #E4405F; }
        .fa-telegram { color: #0088cc; }
        .fa-whatsapp { color: #25D366; }
        .fa-mastodon { color: #6364FF; }
        .account-selector {
            margin-bottom: 10px;
        }
        .account-preview {
            width: 100%;
            min-height: 300px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            background-color: #fff;
            padding: 20px;
            margin-top: 20px;
        }
        .media-preview {
            max-width: 100%;
            max-height: 250px;
            display: block;
            margin: 10px 0;
            border-radius: 10px;
        }
        .file-input-wrapper {
            position: relative;
        }
        .file-input-wrapper input[type="file"] {
            opacity: 0;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        .character-count {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .content-textarea {
            resize: vertical;
            min-height: 120px;
        }
        .schedule-options {
            margin-top: 15px;
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
                <h1 class="mb-4">Create New Post</h1>
                
                <?php if (!empty($errorMsg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($errorMsg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($successMsg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($successMsg); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <p class="lead">Compose and schedule your content across multiple social media platforms.</p>
                <div class="d-flex justify-content-end mb-3">
                    <a href="manage_accounts.php" class="btn btn-outline-primary">
                        <i class="fas fa-list"></i> Manage Posts
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card post-card">
                    <div class="card-body p-4">
                        <form action="create_post.php" method="POST" enctype="multipart/form-data" id="post-form">
                            <!-- Platform Selection -->
                            <div class="mb-4">
                                <h5 class="mb-3">Select Platforms</h5>
                                <div>
                                    <?php 
                                    $platformIcons = [
                                        'facebook' => 'fa-facebook',
                                        'instagram' => 'fa-instagram',
                                        'telegram' => 'fa-telegram',
                                        'whatsapp' => 'fa-whatsapp',
                                        'pixelfed' => 'fa-mastodon'
                                    ];
                                    
                                    foreach ($platformIcons as $platform => $icon) {
                                        $isConnected = !empty($accounts[$platform]);
                                        $isDisabled = !$isConnected ? 'disabled' : '';
                                        $isChecked = in_array($platform, $platforms ?? []) ? 'checked' : '';
                                    ?>
                                    <div class="platform-selector">
                                        <input type="checkbox" name="platforms[]" id="platform-<?php echo $platform; ?>" 
                                            value="<?php echo $platform; ?>" <?php echo $isDisabled . ' ' . $isChecked; ?> 
                                            class="platform-checkbox">
                                        <label for="platform-<?php echo $platform; ?>" <?php echo $isDisabled ? 'class="opacity-50"' : ''; ?>>
                                            <i class="fab <?php echo $icon; ?> platform-icon"></i>
                                            <span class="platform-name"><?php echo ucfirst($platform); ?></span>
                                        </label>
                                    </div>
                                    <?php } ?>
                                </div>
                                <?php if (empty(array_filter($accounts, function($acc) { return !empty($acc); }))): ?>
                                <div class="mt-2 text-danger">
                                    <small>You haven't connected any accounts yet. <a href="add_platforms.php">Connect accounts</a> to start posting.</small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Account Selection -->
                            <div class="mb-4" id="account-selection">
                                <h5 class="mb-3">Select Accounts</h5>
                                <div id="account-options">
                                    <p class="text-muted">Please select at least one platform first.</p>
                                </div>
                            </div>
                            
                            <!-- Content Input -->
                            <div class="mb-4">
                                <h5 class="mb-3">Compose Your Post</h5>
                                <div class="mb-3">
                                    <textarea class="form-control content-textarea" name="content" id="content" rows="5" placeholder="What do you want to share?"><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                                    <div class="d-flex justify-content-end mt-2">
                                        <span class="character-count">0/280</span>
                                    </div>
                                </div>
                                
                                <!-- Media Upload -->
                                <div class="mb-3">
                                    <div class="input-group file-input-wrapper">
                                        <button class="btn btn-outline-secondary" type="button" id="media-btn">
                                            <i class="fas fa-image me-2"></i>Add Media
                                        </button>
                                        <input type="file" name="media" id="media-input" accept="image/jpeg,image/png,image/gif,video/mp4,video/quicktime" class="form-control">
                                        <span id="media-file-name" class="ms-2 align-self-center text-muted"></span>
                                    </div>
                                    <div id="media-preview-container" class="mt-3" style="display: none;">
                                        <img id="media-preview" class="media-preview">
                                        <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="remove-media">
                                            <i class="fas fa-times me-1"></i>Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Scheduling Options -->
                            <div class="mb-4">
                                <h5 class="mb-3">When to Post</h5>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="schedule_option" id="post-now" value="now" 
                                        <?php echo (!isset($scheduleOption) || $scheduleOption === 'now') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="post-now">Post Now</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="schedule_option" id="post-schedule" value="schedule"
                                        <?php echo (isset($scheduleOption) && $scheduleOption === 'schedule') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="post-schedule">Schedule</label>
                                </div>
                                
                                <div class="schedule-options mt-3" id="schedule-time-container" 
                                    style="display: <?php echo (isset($scheduleOption) && $scheduleOption === 'schedule') ? 'block' : 'none'; ?>;">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="datetime-local" class="form-control" name="scheduled_time" id="scheduled-time" 
                                            min="<?php echo $minScheduleTime; ?>" value="<?php echo isset($scheduledTime) ? str_replace(' ', 'T', $scheduledTime) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>
                                    <span id="submit-btn-text">Post Now</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="sticky-top pt-3">
                    <h5 class="mb-3">Post Preview</h5>
                    <div class="account-preview">
                        <div id="preview-content"></div>
                        <div id="preview-media-container" style="display: none;">
                            <img id="preview-media" class="media-preview">
                        </div>
                        <div class="mt-3 text-muted small">
                            <i class="far fa-clock me-1"></i>
                            <span id="preview-time"></span>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-body">
                            <h5 class="card-title">Tips for Great Posts</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Keep your posts concise and engaging</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Use relevant hashtags for better reach</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Include eye-catching media when possible</li>
                                <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Schedule posts during peak engagement times</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i>Customize content for each platform</li>
                            </ul>
                        </div>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Character count
            $('#content').on('input', function() {
                const maxChars = 280;
                const currentLength = $(this).val().length;
                $('.character-count').text(currentLength + '/' + maxChars);
                
                if (currentLength > maxChars) {
                    $('.character-count').addClass('text-danger');
                } else {
                    $('.character-count').removeClass('text-danger');
                }
                
                // Update preview
                updatePreview();
            });
            
            // Media upload preview
            $('#media-input').on('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#media-preview').attr('src', e.target.result);
                        $('#preview-media').attr('src', e.target.result);
                        $('#media-preview-container').show();
                        $('#preview-media-container').show();
                        $('#media-file-name').text(file.name);
                    }
                    reader.readAsDataURL(file);
                }
            });
            
            // Remove media
            $('#remove-media').on('click', function() {
                $('#media-input').val('');
                $('#media-preview-container').hide();
                $('#preview-media-container').hide();
                $('#media-file-name').text('');
            });
            
            // Media button click
            $('#media-btn').on('click', function() {
                $('#media-input').click();
            });
            
            // Schedule options toggle
            $('input[name="schedule_option"]').on('change', function() {
                if ($(this).val() === 'schedule') {
                    $('#schedule-time-container').show();
                    $('#submit-btn-text').text('Schedule Post');
                } else {
                    $('#schedule-time-container').hide();
                    $('#submit-btn-text').text('Post Now');
                }
                updatePreview();
            });
            
            // Platform selection
            $('.platform-checkbox').on('change', function() {
                updateAccountSelection();
                updatePreview();
            });
            
            // Update the account selection based on selected platforms
            function updateAccountSelection() {
                const selectedPlatforms = [];
                $('.platform-checkbox:checked').each(function() {
                    selectedPlatforms.push($(this).val());
                });
                
                if (selectedPlatforms.length === 0) {
                    $('#account-options').html('<p class="text-muted">Please select at least one platform first.</p>');
                    return;
                }
                
                let accountsHtml = '';
                <?php foreach ($accounts as $platform => $platformAccounts): ?>
                    if (selectedPlatforms.includes('<?php echo $platform; ?>')) {
                        if (<?php echo count($platformAccounts); ?> > 0) {
                            accountsHtml += '<div class="mb-3"><h6><?php echo ucfirst($platform); ?> Accounts</h6>';
                            <?php foreach ($platformAccounts as $account): ?>
                                accountsHtml += `
                                <div class="form-check account-selector">
                                    <input class="form-check-input" type="checkbox" name="account_ids[]" 
                                        id="account-<?php echo $platform; ?>-<?php echo $account['id']; ?>" 
                                        value="<?php echo $platform; ?>_<?php echo $account['id']; ?>">
                                    <label class="form-check-label" for="account-<?php echo $platform; ?>-<?php echo $account['id']; ?>">
                                        <?php echo htmlspecialchars($account['account_name']); ?>
                                        <?php if ($account['is_default']): ?><span class="badge bg-primary ms-1">Default</span><?php endif; ?>
                                    </label>
                                </div>
                                `;
                            <?php endforeach; ?>
                            accountsHtml += '</div>';
                        } else {
                            accountsHtml += `
                            <div class="mb-3">
                                <h6><?php echo ucfirst($platform); ?> Accounts</h6>
                                <p class="text-muted">No <?php echo ucfirst($platform); ?> accounts connected. 
                                <a href="add_platforms.php">Connect now</a></p>
                            </div>`;
                        }
                    }
                <?php endforeach; ?>
                
                $('#account-options').html(accountsHtml);
            }
            
            // Update preview
            function updatePreview() {
                const content = $('#content').val();
                $('#preview-content').html(content.replace(/\n/g, '<br>'));
                
                // Update time
                const now = new Date();
                let timeText = 'Now';
                
                if ($('#post-schedule').is(':checked')) {
                    const scheduledTime = $('#scheduled-time').val();
                    if (scheduledTime) {
                        const scheduledDate = new Date(scheduledTime);
                        timeText = scheduledDate.toLocaleString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric',
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        });
                    }
                }
                
                $('#preview-time').text(timeText);
            }
            
            // Initialize
            updateAccountSelection();
            updatePreview();
            
            // Form validation
            $('#post-form').on('submit', function(e) {
                const platforms = $('.platform-checkbox:checked').length;
                const accounts = $('input[name="account_ids[]"]:checked').length;
                const content = $('#content').val().trim();
                const scheduleOption = $('input[name="schedule_option"]:checked').val();
                const scheduledTime = $('#scheduled-time').val();
                
                let isValid = true;
                let errorMsg = '';
                
                if (platforms === 0) {
                    errorMsg = 'Please select at least one platform.';
                    isValid = false;
                } else if (accounts === 0) {
                    errorMsg = 'Please select at least one account.';
                    isValid = false;
                } else if (content === '') {
                    errorMsg = 'Post content cannot be empty.';
                    isValid = false;
                } else if (scheduleOption === 'schedule' && !scheduledTime) {
                    errorMsg = 'Please select a scheduled time.';
                    isValid = false;
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert(errorMsg);
                }
            });
        });
       // Form submission handler with immediate post feedback
$('#post-form').on('submit', function(e) {
    const platforms = $('.platform-checkbox:checked').length;
    const accounts = $('input[name="account_ids[]"]:checked').length;
    const content = $('#content').val().trim();
    const scheduleOption = $('input[name="schedule_option"]:checked').val();
    const scheduledTime = $('#scheduled-time').val();
    
    // Basic validation
    if (platforms === 0 || accounts === 0 || content === '' || 
        (scheduleOption === 'schedule' && !scheduledTime)) {
        // The existing validation will handle this
        return;
    }
    
    // If posting now, show a loading state
    if (scheduleOption === 'now') {
        // Disable the submit button and show loading state
        const $submitBtn = $(this).find('button[type="submit"]');
        const originalText = $submitBtn.html();
        $submitBtn.prop('disabled', true)
                  .html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Posting...');
        
        // We'll proceed with the normal form submission, 
        // but modify the success message handling in the PHP
    }
});

// Function to check post status and update UI
function checkPostStatus(queueIds) {
    $.ajax({
        url: 'direct_post.php',
        method: 'GET',
        data: { queue_ids: queueIds.join(',') },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Show success message
                const alertHtml = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Your post has been published successfully to the selected platforms.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('.container').prepend(alertHtml);
            } else {
                // Show error message
                const alertHtml = `
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        ${response.error || 'There was an issue with immediate publishing. Please check your post status.'}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('.container').prepend(alertHtml);
            }
        },
        error: function() {
            // Show generic error
            const alertHtml = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    There was an error checking the post status. Please check your posts page.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('.container').prepend(alertHtml);
        }
    });
}
    </script>
</body>
</html>