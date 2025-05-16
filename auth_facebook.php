<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Facebook App credentials (will be stored in client_id and client_secret columns)
$client_id = '1124570516143834'; // Replace with your Meta Developer App ID
$client_secret = '426725c6a8c85571054e8961a3e6ec6c'; // Replace with your Meta Developer App Secret

// Define redirect URL
$redirectUrl = 'https://dispatchingpost.rf.gd/MultiPost/auth_facebook.php';

// Handle the redirect from Facebook with the authorization code
if (isset($_GET['code'])) {
    try {
        // Exchange authorization code for access token
        $tokenUrl = "https://graph.facebook.com/v22.0/oauth/access_token";
        $tokenUrl .= "?client_id=" . $client_id;
        $tokenUrl .= "&redirect_uri=" . urlencode($redirectUrl);
        $tokenUrl .= "&client_secret=" . $client_secret;
        $tokenUrl .= "&code=" . $_GET['code'];
        
        // Make the request to get the access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $accessTokenData = json_decode($response, true);
        
        if (!isset($accessTokenData['access_token'])) {
            $_SESSION['auth_error'] = "Failed to obtain access token.";
            header("Location: add_platformes.php");
            exit();
        }
        
        $accessToken = $accessTokenData['access_token'];
        
        // Convert short-lived token to long-lived token
        $longLivedTokenUrl = "https://graph.facebook.com/v22.0/oauth/access_token";
        $longLivedTokenUrl .= "?grant_type=fb_exchange_token";
        $longLivedTokenUrl .= "&client_id=" . $client_id;
        $longLivedTokenUrl .= "&client_secret=" . $client_secret;
        $longLivedTokenUrl .= "&fb_exchange_token=" . $accessToken;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $longLivedTokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $longLivedTokenData = json_decode($response, true);
        
        if (!isset($longLivedTokenData['access_token'])) {
            $_SESSION['auth_error'] = "Failed to convert to long-lived access token.";
            header("Location: add_platformes.php");
            exit();
        }
        
        $longLivedAccessToken = $longLivedTokenData['access_token'];
        
        // Get user profile info
        $userUrl = "https://graph.facebook.com/v22.0/me";
        $userUrl .= "?access_token=" . $longLivedAccessToken;
        $userUrl .= "&fields=name,id,picture";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $userData = json_decode($response, true);
        $accountName = $userData['name'];
        $userIdPlatform = $userData['id'];
        
        // Prepare account data for storage
        $accountData = [
            'user' => $userData,
            'token_info' => $longLivedTokenData
        ];
        
        // Get Facebook Pages the user manages
        $pagesUrl = "https://graph.facebook.com/v22.0/me/accounts";
        $pagesUrl .= "?access_token=" . $longLivedAccessToken;
        $pagesUrl .= "&fields=id,name,access_token,link,picture";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pagesUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $pagesData = json_decode($response, true);
        
        // If the user has Facebook Pages, use the first one
        if (isset($pagesData['data']) && count($pagesData['data']) > 0) {
            $page = $pagesData['data'][0];
            $pageId = $page['id'];
            $pageName = $page['name'];
            $pageAccessToken = $page['access_token'];
            
            // Add page data to account data
            $accountData['page'] = $page;
            
            // Connect to database
            $conn = new mysqli($host, $user, $pass, $db);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
            
            // Check if this account already exists
            $stmt = $conn->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platforms = 'facebook' AND page_id = ?");
            $stmt->bind_param("is", $userId, $pageId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing account
                $accountId = $result->fetch_assoc()['id'];
                $stmt = $conn->prepare("UPDATE social_accounts SET 
                    account_name = ?,
                    access_token = ?,
                    user_id_platform = ?,
                    account_data = ?,
                    client_id = ?,
                    client_secret = ?
                    WHERE id = ?");
                $stmt->bind_param("ssssssi", 
                    $pageName,
                    $pageAccessToken,
                    $userIdPlatform,
                    json_encode($accountData),
                    $client_id,
                    $client_secret,
                    $accountId);
                $stmt->execute();
            } else {
                // Insert new account
                // Check if this is the first Facebook account for this user
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM social_accounts WHERE user_id = ? AND platforms = 'facebook'");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $count = $stmt->get_result()->fetch_assoc()['count'];
                $isDefault = ($count == 0) ? 1 : 0;
                
                $stmt = $conn->prepare("INSERT INTO social_accounts (
                    user_id, 
                    platforms, 
                    account_name, 
                    page_id, 
                    user_id_platform, 
                    access_token, 
                    is_default,
                    account_data,
                    client_id,
                    client_secret
                ) VALUES (?, 'facebook', ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssisss", 
                    $userId, 
                    $pageName,
                    $pageId,
                    $userIdPlatform,
                    $pageAccessToken,
                    $isDefault,
                    json_encode($accountData),
                    $client_id,
                    $client_secret);
                $stmt->execute();
            }
            
            $_SESSION['auth_success'] = "Facebook page '{$pageName}' connected successfully!";
            header("Location: manage_accounts.php?success=account_added");
            exit();
        } else {
            // No Facebook Pages found
            $_SESSION['auth_error'] = "No Facebook Pages found. Please create a Facebook Page first.";
            header("Location: add_platformes.php");
            exit();
        }
        
    } catch (Exception $e) {
        // General error
        $_SESSION['auth_error'] = 'An error occurred: ' . $e->getMessage();
        header("Location: add_platformes.php");
        exit();
    }
} else {
    // Generate login URL
    $state = bin2hex(random_bytes(16));
    $_SESSION['fb_state'] = $state;
    
    // Permissions needed for Facebook Pages
    $permissions = 'pages_show_list,pages_read_engagement,pages_manage_posts';
    
    // Generate the Facebook login URL
    $loginUrl = "https://www.facebook.com/v22.0/dialog/oauth";
    $loginUrl .= "?client_id=" . $client_id;
    $loginUrl .= "&redirect_uri=" . urlencode($redirectUrl);
    $loginUrl .= "&state=" . $state;
    $loginUrl .= "&scope=" . $permissions;
    
    // Redirect to Facebook login
    header("Location: " . $loginUrl);
    exit();
}
?>