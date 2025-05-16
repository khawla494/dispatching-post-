<?php
session_start();
require 'db.php';
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Configuration - store these securely (in config.php or database)
$client_id = '1684391'; // Your Pixelfed app client_id
$client_secret = 'mlAHJEKIgpjZr9bsUiTkYRVEZr6vDsDBohQ1mTro'; // Your Pixelfed app client_secret
$redirect_uri = 'https://dispatchingpost.rf.gd/MultiPost/connect_pixelfed.php'; // Your redirect URI
$instance_url = 'https://pixelfed.social'; // Pixelfed instance URL
$scope = 'read write'; // Requested permissions

// Step 1: Authorization - Redirect to Pixelfed's OAuth server
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    
    $auth_url = $instance_url . '/oauth/authorize?' . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'scope' => $scope,
        'state' => $state,
    ]);
    
    header("Location: $auth_url");
    exit;
}

// Step 2: User has returned with an authorization code
if (isset($_GET['code'])) {
    // Verify state parameter
    if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
        $_SESSION['auth_error'] = "Invalid state parameter";
        header("Location: add_platformes.php");
        exit();
    }

    // Exchange authorization code for access token
    $token_url = $instance_url . '/oauth/token';
    $code = $_GET['code'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $token_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'code' => $code,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $response = curl_exec($ch);
    $token_data = json_decode($response, true);
    curl_close($ch);

    if (!isset($token_data['access_token'])) {
        $_SESSION['auth_error'] = "Failed to obtain access token";
        header("Location: add_platformes.php");
        exit();
    }

    // Get user account info
    $account_url = $instance_url . '/api/v1/accounts/verify_credentials';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $account_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token_data['access_token']
        ],
    ]);

    $account_response = curl_exec($ch);
    $account_data = json_decode($account_response, true);
    curl_close($ch);

    if (!isset($account_data['id'])) {
        $_SESSION['auth_error'] = "Failed to retrieve account information";
        header("Location: add_platformes.php");
        exit();
    }

    try {
        // Check if account exists
        $stmt = $pdo->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platforms = 'pixelfed' AND user_id_platform = ?");
        $stmt->execute([$userId, $account_data['id']]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($result) > 0) {
            // Update existing account
            $stmt = $pdo->prepare("UPDATE social_accounts SET 
                                  access_token = ?,
                                  account_name = ?,
                                  instance_url = ?,
                                  client_id = ?,
                                  client_secret = ?
                                  WHERE id = ?");
            $stmt->execute([
                $token_data['access_token'],
                $account_data['username'],
                $instance_url,
                $client_id,
                $client_secret,
                $result[0]['id']
            ]);
        } else {
            // Insert new account
            $stmt = $pdo->prepare("INSERT INTO social_accounts (
                user_id, 
                platforms, 
                account_name, 
                access_token, 
                user_id_platform, 
                instance_url,
                client_id,
                client_secret,
                is_default,
                created_at
            ) VALUES (?, 'pixelfed', ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            // Check if this is the first account (default)
            $count = $pdo->query("SELECT COUNT(*) FROM social_accounts WHERE user_id = $userId AND platforms = 'pixelfed'")->fetchColumn();
            $isDefault = ($count == 0) ? 1 : 0;
            
            $stmt->execute([
                $userId, 
                $account_data['username'], 
                $token_data['access_token'], 
                $account_data['id'], 
                $instance_url,
                $client_id,
                $client_secret,
                $isDefault
            ]);
        }

        $_SESSION['auth_success'] = "Compte Pixelfed connecté avec succès !";
        header("Location: manage_accounts.php?success=pixelfed_connected");
        exit();
    } catch (PDOException $e) {
        $_SESSION['auth_error'] = "Erreur de base de données: " . $e->getMessage();
        header("Location: add_platformes.php");
        exit();
    }
}

// Fallback redirect
header("Location: add_platformes.php");
exit();