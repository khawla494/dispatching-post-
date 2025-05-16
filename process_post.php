<?php
/**
 * Process posts immediately when "Post Now" is selected
 */

require_once 'db.php';
require_once 'config.php';

/**
 * Process an individual post by sending it to the appropriate social media platform
 */
function processPost($post) {
    global $pdo;
    
    $pdo->beginTransaction();
    
    try {
        // Update attempt count
        $updateStmt = $pdo->prepare("UPDATE post_queue SET attempts = attempts + 1, last_attempt = NOW() WHERE id = ?");
        $updateStmt->execute([$post['id']]);
        
        $platform = $post['account_platform'];
        $content = $post['content'];
        $mediaPath = $post['media_path'];
        $mediaFullPath = $mediaPath ? UPLOAD_DIR . '/' . $mediaPath : '';
        
        error_log("[" . date('Y-m-d H:i:s') . "] Processing {$platform} post (ID: {$post['post_id']})");
        
        $result = false;
        
        // Process based on platform
        switch ($platform) {
            case 'facebook':
                $result = postToFacebook($post, $content, $mediaFullPath);
                break;
            case 'instagram':
                $result = postToInstagram($post, $content, $mediaFullPath);
                break;
            case 'telegram':
                $result = postToTelegram($post, $content, $mediaFullPath);
                break;
            case 'whatsapp':
                $result = postToWhatsapp($post, $content, $mediaFullPath);
                break;
            case 'pixelfed':
                $result = postToPixelfed($post, $content, $mediaFullPath);
                break;
            default:
                $errorMessage = "Unsupported platform: {$platform}";
                error_log($errorMessage);
                throw new Exception($errorMessage);
        }
        
        if ($result === true) {
            // Update status to published
            $updateStmt = $pdo->prepare("UPDATE post_queue SET status = 'published', published_at = NOW(), error_message = NULL WHERE id = ?");
            $updateStmt->execute([$post['id']]);
            
            $updatePostStmt = $pdo->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = ?");
            $updatePostStmt->execute([$post['post_id']]);
            
            error_log("[" . date('Y-m-d H:i:s') . "] Successfully published {$platform} post (ID: {$post['post_id']})");
            $pdo->commit();
            return true;
        } else {
            throw new Exception(is_array($result) ? $result['error'] : "Unknown error posting to {$platform}");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Exception processing post: " . $e->getMessage();
        error_log($errorMessage);
        
        $updateStmt = $pdo->prepare("UPDATE post_queue SET status = 'failed', error_message = ? WHERE id = ?");
        $updateStmt->execute([$errorMessage, $post['id']]);
        return false;
    }
}

/**
 * Post to Facebook with proper error handling
 */
function postToFacebook($post, $content, $mediaPath = '') {
    try {
        $accessToken = $post['access_token'];
        $pageId = $post['page_id'];
        
        if (empty($accessToken) || empty($pageId)) {
            throw new Exception('Missing Facebook credentials');
        }
        
        if (!empty($mediaPath) && file_exists($mediaPath)) {
            return postFacebookMedia($pageId, $accessToken, $content, $mediaPath);
        }
        
        $endpoint = "https://graph.facebook.com/v22.0/{$pageId}/feed";
        $params = [
            'message' => $content,
            'access_token' => $accessToken
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: $error");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['id'])) {
            return true;
        } else {
            $errorMsg = $responseData['error']['message'] ?? 'Unknown Facebook API error';
            throw new Exception($errorMsg);
        }
    } catch (Exception $e) {
        error_log("Facebook post error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Post media to Facebook with proper error handling
 */
function postFacebookMedia($pageId, $accessToken, $content, $mediaPath) {
    try {
        $mimeType = mime_content_type($mediaPath);
        $isVideo = strpos($mimeType, 'video/') === 0;
        
        // First upload the media
        $uploadEndpoint = "https://graph.facebook.com/v22.0/{$pageId}/photos";
        if ($isVideo) {
            $uploadEndpoint = "https://graph.facebook.com/v22.0/{$pageId}/videos";
        }
        
        $postFields = [
            'message' => $content,
            'access_token' => $accessToken,
            'source' => new CURLFile($mediaPath)
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $uploadEndpoint,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $isVideo ? 300 : 30, // Longer timeout for videos
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("CURL Error: $error");
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300 && (isset($responseData['id']) || isset($responseData['post_id']))) {
            return true;
        } else {
            $errorMsg = $responseData['error']['message'] ?? 'Unknown Facebook media upload error';
            throw new Exception($errorMsg);
        }
    } catch (Exception $e) {
        error_log("Facebook media post error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Post to Instagram with proper media handling
 */
function postToInstagram($post, $content, $mediaPath = '') {
    try {
        $accessToken = $post['access_token'];
        $businessAccountId = $post['business_account_id'];
        
        if (empty($accessToken) || empty($businessAccountId)) {
            throw new Exception('Missing Instagram credentials');
        }
        
        if (empty($mediaPath) || !file_exists($mediaPath)) {
            throw new Exception('Media file is required for Instagram posts');
        }
        
        // Upload media first
        $uploadEndpoint = "https://graph.facebook.com/v22.0/{$businessAccountId}/media";
        
        $mimeType = mime_content_type($mediaPath);
        $isVideo = strpos($mimeType, 'video/') === 0;
        
        $params = [
            'caption' => $content,
            'access_token' => $accessToken
        ];
        
        if ($isVideo) {
            $params['media_type'] = 'VIDEO';
            $params['video_url'] = getPublicMediaUrl($mediaPath);
        } else {
            $params['media_type'] = 'IMAGE';
            $params['image_url'] = getPublicMediaUrl($mediaPath);
        }
        
        $containerId = makeApiRequest($uploadEndpoint, $params);
        
        if ($isVideo) {
            $statusEndpoint = "https://graph.facebook.com/v22.0/{$containerId}?fields=status_code&access_token={$accessToken}";
            $maxAttempts = 30; // 30 attempts with 5s delay = 2.5min max wait
            $attempt = 0;
            
            while ($attempt < $maxAttempts) {
                sleep(5);
                $statusData = makeApiRequest($statusEndpoint, [], false);
                
                if ($statusData['status_code'] === 'FINISHED') {
                    break;
                } elseif ($statusData['status_code'] === 'ERROR') {
                    throw new Exception('Error processing video for Instagram');
                }
                $attempt++;
            }
            
            if ($attempt >= $maxAttempts) {
                throw new Exception('Timeout waiting for Instagram video processing');
            }
        }
        
        // Publish the container
        $publishEndpoint = "https://graph.facebook.com/v22.0/{$businessAccountId}/media_publish";
        $publishParams = [
            'creation_id' => $containerId,
            'access_token' => $accessToken
        ];
        
        $publishData = makeApiRequest($publishEndpoint, $publishParams);
        
        if (isset($publishData['id'])) {
            return true;
        } else {
            throw new Exception('Error publishing to Instagram');
        }
    } catch (Exception $e) {
        error_log("Instagram post error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Post to Telegram with proper media handling
 */
function postToTelegram($post, $content, $mediaPath = '') {
    try {
        $botToken = $post['access_token'];
        $channelId = $post['user_id_platform'];
        
        if (empty($botToken) || empty($channelId)) {
            throw new Exception('Missing Telegram credentials');
        }
        
        $endpoint = "https://api.telegram.org/bot{$botToken}/";
        
        if (!empty($mediaPath) && file_exists($mediaPath)) {
            $mimeType = mime_content_type($mediaPath);
            
            if (strpos($mimeType, 'image/') === 0) {
                $method = 'sendPhoto';
                $mediaParam = 'photo';
            } elseif (strpos($mimeType, 'video/') === 0) {
                $method = 'sendVideo';
                $mediaParam = 'video';
            } else {
                $method = 'sendDocument';
                $mediaParam = 'document';
            }
            
            $postFields = [
                'chat_id' => $channelId,
                'caption' => $content,
                $mediaParam => new CURLFile($mediaPath)
            ];
            
            $response = makeApiRequest($endpoint . $method, $postFields, true, true);
        } else {
            $params = [
                'chat_id' => $channelId,
                'text' => $content,
                'parse_mode' => 'HTML'
            ];
            
            $response = makeApiRequest($endpoint . 'sendMessage', $params);
        }
        
        if ($response['ok'] === true) {
            return true;
        } else {
            throw new Exception($response['description'] ?? 'Unknown Telegram API error');
        }
    } catch (Exception $e) {
        error_log("Telegram post error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Post to WhatsApp Business API
 */
function postToWhatsapp($post, $content, $mediaPath = '') {
    try {
        $accessToken = $post['access_token'];
        $phoneNumberId = $post['phone_number_id'];
        $recipientNumber = $post['user_id_platform'];
        
        if (empty($accessToken) || empty($phoneNumberId) || empty($recipientNumber)) {
            throw new Exception('Missing WhatsApp credentials');
        }
        
        $endpoint = "https://graph.facebook.com/v22.0/{$phoneNumberId}/messages";
        
        if (!empty($mediaPath) && file_exists($mediaPath)) {
            // First upload media
            $mediaUploadEndpoint = "https://graph.facebook.com/v22.0/{$phoneNumberId}/media";
            $mimeType = mime_content_type($mediaPath);
            
            $postFields = [
                'file' => new CURLFile($mediaPath),
                'type' => $mimeType,
                'messaging_product' => 'whatsapp'
            ];
            
            $headers = [
                'Authorization: Bearer ' . $accessToken,
            ];
            
            $mediaResponse = makeApiRequest($mediaUploadEndpoint, $postFields, true, false, $headers);
            $mediaId = $mediaResponse['id'];
            
            // Then send media message
            $mediaType = 'document';
            if (strpos($mimeType, 'image/') === 0) $mediaType = 'image';
            elseif (strpos($mimeType, 'video/') === 0) $mediaType = 'video';
            elseif (strpos($mimeType, 'audio/') === 0) $mediaType = 'audio';
            
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipientNumber,
                'type' => $mediaType,
                $mediaType => [
                    'id' => $mediaId,
                    'caption' => $content
                ]
            ];
        } else {
            // Text message
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipientNumber,
                'type' => 'text',
                'text' => [
                    'body' => $content
                ]
            ];
        }
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $response = makeApiRequest($endpoint, $data, false, false, $headers);
        
        if (isset($response['messages']) && !empty($response['messages'])) {
            return true;
        } else {
            throw new Exception($response['error']['message'] ?? 'Unknown WhatsApp API error');
        }
    } catch (Exception $e) {
        error_log("WhatsApp post error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Post to Pixelfed
 */
function postToPixelfed($post, $content, $mediaPath = '') {
    try {
        // Add validation
        if (empty($post['instance_url'])) {
            throw new Exception('Pixelfed instance URL not configured');
        }
        
        if (empty($post['api_key'])) {
            throw new Exception('Pixelfed API key missing');
        }
        
        if (empty($mediaPath) || !file_exists($mediaPath)) {
            throw new Exception('Media file missing or invalid');
        }
        
        $instanceUrl = rtrim($post['instance_url'], '/');
        $apiKey = $post['api_key'];
        
        // 1. Upload media with better error handling
        $uploadEndpoint = $instanceUrl . '/api/v1/media';
        $ch = curl_init($uploadEndpoint);
        
        $postFields = [
            'file' => new CURLFile($mediaPath),
            'description' => substr($content, 0, 150) // Truncate if too long
        ];
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 60 // Longer timeout for media upload
        ]);
        
        $uploadResponse = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !isset($uploadResponse['id'])) {
            $error = $uploadResponse['error'] ?? 'Unknown upload error';
            throw new Exception("Media upload failed: " . print_r($error, true));
        }
        
        // 2. Create post with media
        $statusEndpoint = $instanceUrl . '/api/v1/statuses';
        $postData = [
            'status' => $content,
            'media_ids[]' => $uploadResponse['id'], // Note array format
            'visibility' => 'public' // or 'unlisted', 'private'
        ];
        
        $ch = curl_init($statusEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        $response = json_decode(curl_exec($ch), true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && isset($response['id'])) {
            return true;
        } else {
            $error = $response['error'] ?? 'Unknown posting error';
            throw new Exception("Post creation failed: " . print_r($error, true));
        }
    } catch (Exception $e) {
        error_log("Pixelfed Error: " . $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

/**
 * Helper function to make API requests
 */
function makeApiRequest($url, $data, $isPost = true, $isMultipart = false, $headers = []) {
    $ch = curl_init();
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ];
    
    if ($isPost) {
        $options[CURLOPT_POST] = true;
        if ($isMultipart) {
            $options[CURLOPT_POSTFIELDS] = $data;
        } else {
            $options[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : $data;
        }
    }
    
    if (!empty($headers)) {
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    
    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("CURL Error: $error");
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 400 || isset($responseData['error'])) {
        $errorMsg = $responseData['error']['message'] ?? $responseData['error'] ?? 'Unknown API error';
        throw new Exception($errorMsg);
    }
    
    return $responseData;
}

/**
 * Get public URL for media file
 */
function getPublicMediaUrl($filePath) {
    // In a real implementation, you would upload the file to a public location
    // and return its URL. This is just a placeholder.
    $baseUrl = rtrim(SITE_URL, '/');
    return $baseUrl . '/uploads/' . basename($filePath);
}

/**
 * Process posts immediately
 */
function processPostsNow($queueIds) {
    global $pdo;
    
    $result = [
        'success' => false,
        'results' => [],
        'error' => null
    ];
    
    if (empty($queueIds)) {
        $result['error'] = 'No queue IDs provided';
        return $result;
    }
    
    $allSuccess = true;
    
    foreach ($queueIds as $queueId) {
        $itemResult = ['success' => false, 'error' => null];
        
        try {
            // Get queue item with account details
            $stmt = $pdo->prepare("SELECT pq.*, p.content, p.media_path, p.user_id, 
                                  sa.access_token, sa.account_data
                                  FROM post_queue pq 
                                  JOIN posts p ON pq.post_id = p.id 
                                  JOIN social_accounts sa ON pq.account_id = sa.id
                                  WHERE pq.id = ?");
            $stmt->execute([$queueId]);
            $queue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$queue) {
                $itemResult['error'] = "Queue item {$queueId} not found";
                $result['results'][] = $itemResult;
                $allSuccess = false;
                continue;
            }
            
            // Merge account data
            $accountData = json_decode($queue['account_data'], true) ?? [];
            $queue = array_merge($queue, $accountData);
            
            // Process the post
            $postResult = processPost($queue);
            
            if ($postResult) {
                $itemResult['success'] = true;
            } else {
                $itemResult['error'] = "Failed to post to {$queue['platforms']}";
                $allSuccess = false;
            }
        } catch (Exception $e) {
            $itemResult['error'] = "Exception: " . $e->getMessage();
            $allSuccess = false;
        }
        
        $result['results'][] = $itemResult;
    }
    
    $result['success'] = $allSuccess;
    return $result;
}