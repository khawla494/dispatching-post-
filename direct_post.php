<?php
session_start();
require_once 'db.php';
require_once 'config.php';
require_once 'process_post.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];

// Handle GET request to check post status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['queue_ids'])) {
    // Get queue IDs
    $queueIds = array_map('intval', explode(',', $_GET['queue_ids']));
    
    if (empty($queueIds)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No queue IDs provided']);
        exit();
    }
    
    // Get post status for these queue IDs
    $placeholders = implode(',', array_fill(0, count($queueIds), '?'));
    $stmt = $pdo->prepare("
        SELECT pq.id, pq.status, pq.last_error, p.platforms
        FROM post_queue pq
        JOIN posts p ON pq.post_id = p.id
        WHERE pq.id IN ($placeholders) AND p.user_id = ?
    ");
    
    $params = array_merge($queueIds, [$userId]);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if all posts are completed or failed
    $allProcessed = true;
    $anyFailed = false;
    $errors = [];
    
    foreach ($posts as $post) {
        if ($post['status'] !== 'completed' && $post['status'] !== 'failed') {
            $allProcessed = false;
        }
        
        if ($post['status'] === 'failed') {
            $anyFailed = true;
            $errors[] = "Failed to post to {$post['platforms']}: {$post['last_error']}";
        }
    }
    
    // Return appropriate response
    header('Content-Type: application/json');
    
    if (!$allProcessed) {
        echo json_encode([
            'success' => false, 
            'status' => 'processing',
            'message' => 'Posts are still being processed'
        ]);
    } else if ($anyFailed) {
        echo json_encode([
            'success' => false, 
            'status' => 'failed',
            'error' => implode(' ', $errors)
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'status' => 'completed',
            'message' => 'All posts published successfully'
        ]);
    }
    exit();
}

// Handle direct post request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get post data
    $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    
    // Validate post ownership
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
    $stmt->execute([$postId, $userId]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Post not found or access denied']);
        exit();
    }
    
    // Get queue item for this post
    $stmt = $pdo->prepare("SELECT * FROM post_queue WHERE post_id = ? LIMIT 1");
    $stmt->execute([$postId]);
    $queueItem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$queueItem) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Queue item not found']);
        exit();
    }
    
    // Process the post immediately
    $result = processPostsNow([$queueItem['id']]);
    
    header('Content-Type: application/json');
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Post published successfully'
        ]);
    } else {
        $error = '';
        foreach ($result['results'] as $postResult) {
            if (!$postResult['success']) {
                $error .= $postResult['error'] . ' ';
            }
        }
        
        echo json_encode([
            'success' => false,
            'error' => trim($error) ?: 'Failed to publish post'
        ]);
    }
    exit();
}

// Alternative endpoint for processing specific queue IDs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['queue_ids'])) {
    $queueIds = $_POST['queue_ids'];
    
    // Validate queue items ownership
    $placeholders = implode(',', array_fill(0, count($queueIds), '?'));
    $stmt = $pdo->prepare("
        SELECT pq.id
        FROM post_queue pq
        JOIN posts p ON pq.post_id = p.id
        WHERE pq.id IN ($placeholders) AND p.user_id = ?
    ");
    
    $params = array_merge($queueIds, [$userId]);
    $stmt->execute($params);
    $validQueueIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($validQueueIds) !== count($queueIds)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Some queue items not found or access denied']);
        exit();
    }
    
    // Process the posts immediately
    $result = processPostsNow($validQueueIds);
    
    header('Content-Type: application/json');
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Posts published successfully'
        ]);
    } else {
        $error = '';
        foreach ($result['results'] as $postResult) {
            if (!$postResult['success']) {
                $error .= $postResult['error'] . ' ';
            }
        }
        
        echo json_encode([
            'success' => false,
            'error' => trim($error) ?: 'Failed to publish posts'
        ]);
    }
    exit();
}

// Default response for incorrect requests
header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Invalid request']);