<?php
session_start();
require 'db.php';
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// WhatsApp (Twilio) credentials
$account_sid = 'ACdd92e202ea810341d2c819e7a27c99e2'; // Your Twilio Account SID
$auth_token = 'c3a0cde1f528bae04da29e7ecbed912a'; // Your Twilio Auth Token
$phone_number_id = 'whatsapp:+14155238886'; // Twilio Sandbox number
$to = 'whatsapp:+213656531992'; // Recipient number (your personal WhatsApp)
$accountName = 'WhatsAppTwilio';

try {
    // Check if account already exists
    $stmt = $pdo->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platforms = 'whatsapp' AND user_id_platform = ?");
    $stmt->execute([$userId, $to]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['auth_error'] = "Ce compte WhatsApp est déjà connecté";
        header("Location: manage_accounts.php");
        exit();
    }

    // Determine if this is the first account (thus default)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM social_accounts WHERE user_id = ? AND platforms = 'whatsapp'");
    $stmt->execute([$userId]);
    $count = $stmt->fetchColumn();
    $isDefault = ($count == 0) ? 1 : 0;

    // Test API connection
    $url = 'https://dispatchmypost.lovestoblog.com';
    $body = "Bonjour ! Merci de nous contacter. Cliquez ici : $url";
    
    $api_url = "https://api.twilio.com/2010-04-01/Accounts/$account_sid/Messages.json";
    
    $data = [
        'From' => $phone_number_id,
        'To' => $to,
        'Body' => $body
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_USERPWD => "$account_sid:$auth_token",
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ],
        // SSL options - choose ONE of these:
        // Option 1: Disable verification (development only)
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        
        // OR Option 2: Use proper certificate bundle
        // CURLOPT_CAINFO => "C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem",
        // CURLOPT_CAPATH => "C:/wamp64/bin/php/php8.3.14/extras/ssl"
    ]);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $_SESSION['auth_error'] = "Erreur cURL : " . curl_error($ch);
        header("Location: add_platformes.php");
        exit();
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result = json_decode($response, true);
    curl_close($ch);
    
    if ($http_code === 201 && isset($result['sid'])) {
        // Store the account information
        $stmt = $pdo->prepare("INSERT INTO social_accounts (
            user_id, 
            platforms, 
            account_name, 
            access_token, 
            user_id_platform, 
            phone_number_id,
            business_account_id,
            client_id,
            client_secret,
            is_default
        ) VALUES (?, 'whatsapp', ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $success = $stmt->execute([
            $userId,
            $accountName,
            $auth_token, // Stored as access_token
            $to, // user_id_platform
            $phone_number_id,
            $account_sid, // business_account_id
            $account_sid, // client_id
            $auth_token, // client_secret
            $isDefault
        ]);
        
        if ($success) {
            $_SESSION['auth_success'] = "Compte WhatsApp connecté avec succès !";
            header("Location: manage_accounts.php?success=whatsapp_connected");
            exit();
        } else {
            $_SESSION['auth_error'] = "Erreur lors de l'enregistrement du compte WhatsApp";
            header("Location: add_platformes.php");
            exit();
        }
    } else {
        $errorMsg = $result['message'] ?? 'Réponse inattendue de Twilio';
        $_SESSION['auth_error'] = "Erreur API Twilio ($http_code): $errorMsg";
        header("Location: add_platformes.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['auth_error'] = "Erreur de base de données: " . $e->getMessage();
    header("Location: add_platformes.php");
    exit();
} catch (Exception $e) {
    $_SESSION['auth_error'] = "Erreur: " . $e->getMessage();
    header("Location: add_platformes.php");
    exit();
}