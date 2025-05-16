<?php
session_start();
require 'db.php';
require 'config.php'; // If you have any global configurations

// Function for error logging - crucial for debugging Instagram API issues
function log_instagram_error($message, $data = null) {
    $log_file = 'instagram_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    if ($data !== null) {
        $log_message .= "Data: " . print_r($data, true) . "\n";
    }
    
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

// HTTP request functions - consolidated from your auth_instagram.php
function curlPost($url, $params) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($response, true);
}

function curlGet($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        throw new Exception('Curl error: ' . curl_error($ch));
    }
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * Function to post a photo to Instagram using Graph API
 * Follows the required two-step process: 
 * 1. Create container
 * 2. Publish container
 */
function post_to_instagram($access_token, $instagram_account_id, $caption, $image_url) {
    try {
        log_instagram_error("Attempting to post to Instagram", [
            'instagram_id' => $instagram_account_id,
            'image_url' => $image_url
        ]);
        
        // STEP 1: Create a media container
        $create_container_url = "https://graph.facebook.com/v22.0/{$instagram_account_id}/media";
        
        $container_params = [
            'image_url' => $image_url,
            'caption' => $caption,
            'access_token' => $access_token
        ];
        
        // First API call - create the media container
        $container_response = curlPost($create_container_url, $container_params);
        
        // Check if container was created successfully
        if (!isset($container_response['id'])) {
            log_instagram_error("Failed to create media container", $container_response);
            return [
                'success' => false,
                'message' => 'Failed to create media container: ' . 
                    ($container_response['error']['message'] ?? 'Unknown error')
            ];
        }
        
        $creation_id = $container_response['id'];
        log_instagram_error("Media container created successfully", ['creation_id' => $creation_id]);
        
        // STEP 2: Publish the media container
        $publish_url = "https://graph.facebook.com/v22.0/{$instagram_account_id}/media_publish";
        
        $publish_params = [
            'creation_id' => $creation_id,
            'access_token' => $access_token
        ];
        
        // Second API call - publish the media
        $publish_response = curlPost($publish_url, $publish_params);
        
        // Check if publication was successful
        if (!isset($publish_response['id'])) {
            log_instagram_error("Failed to publish media", $publish_response);
            return [
                'success' => false,
                'message' => 'Failed to publish to Instagram: ' . 
                    ($publish_response['error']['message'] ?? 'Unknown error')
            ];
        }
        
        $post_id = $publish_response['id'];
        log_instagram_error("Post published successfully", ['post_id' => $post_id]);
        
        return [
            'success' => true,
            'post_id' => $post_id,
            'message' => 'Successfully posted to Instagram'
        ];
        
    } catch (Exception $e) {
        log_instagram_error("Exception in post_to_instagram", $e->getMessage());
        return [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}

// Main publishing function
function publish_to_instagram($user_id, $caption, $image_path) {
    global $pdo;
    
    try {
        // Get the user's Instagram account from the database
        $stmt = $pdo->prepare("SELECT * FROM social_accounts 
                              WHERE user_id = ? AND platforms = 'instagram' AND is_default = 1 
                              LIMIT 1");
        $stmt->execute([$user_id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$account) {
            return [
                'success' => false,
                'message' => 'No Instagram account connected or set as default'
            ];
        }
        
        // Post to Instagram
        $result = post_to_instagram(
            $account['access_token'],
            $account['user_id_platform'], // This should be the Instagram business account ID
            $caption,
            $image_path
        );
        
        if ($result['success']) {
            // Record the successful post in database if needed
            $stmt = $pdo->prepare("INSERT INTO posts (user_id, platform, post_id, content, media_url, created_at) 
                                  VALUES (?, 'instagram', ?, ?, ?, NOW())");
            $stmt->execute([$user_id, $result['post_id'], $caption, $image_path]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        log_instagram_error("Exception in publish_to_instagram", $e->getMessage());
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

// Usage example (you would put this in your post publishing script)
if (isset($_POST['publish_post']) && isset($_POST['platforms']) && in_array('instagram', $_POST['platforms'])) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    $user_id = $_SESSION['user_id'];
    $caption = $_POST['caption'] ?? '';
    $image_path = $_POST['image_url'] ?? ''; // This should be a publicly accessible URL
    
    // Validate image URL - it MUST be publicly accessible
    if (empty($image_path) || !filter_var($image_path, FILTER_VALIDATE_URL)) {
        $_SESSION['publish_error'] = "Invalid image URL. Instagram requires a publicly accessible image URL.";
        header("Location: create_post.php");
        exit();
    }
    
    $result = publish_to_instagram($user_id, $caption, $image_path);
    
    if ($result['success']) {
        $_SESSION['publish_success'] = "Successfully published to Instagram!";
    } else {
        $_SESSION['publish_error'] = "Failed to post to Instagram: " . $result['message'];
    }
    
    // Continue with other platforms or redirect
    // header("Location: dashboard.php");
    // exit();
}
?>