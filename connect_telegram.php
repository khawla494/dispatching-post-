<?php
session_start();
require 'db.php';
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Telegram credentials
$bot_token = '7420359805:AAERN0ne0SuXC93jWJxLdKf_WRMZWYKePqg';
$user_id_platform = '5123549684';
$account_name = 'MonBotSiteWeb';

try {
    // Check if account exists
    $stmt = $pdo->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platforms = 'telegram' AND user_id_platform = ?");
    $stmt->execute([$user_id, $user_id_platform]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($result) > 0) {
        $_SESSION['auth_error'] = "Ce compte Telegram est déjà connecté";
        header("Location: manage_accounts.php");
        exit();
    }

    // Check if this is the first account (default)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM social_accounts WHERE user_id = ? AND platforms = 'telegram'");
    $stmt->execute([$user_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    $is_default = ($count == 0) ? 1 : 0;

    // Insert new account
    $stmt = $pdo->prepare("INSERT INTO social_accounts (
        user_id,
        platforms,
        account_name,
        user_id_platform,
        access_token,
        is_default,
        created_at
    ) VALUES (?, 'telegram', ?, ?, ?, ?, NOW())");
    
    if ($stmt->execute([$user_id, $account_name, $user_id_platform, $bot_token, $is_default])) {
        $_SESSION['auth_success'] = "Compte Telegram connecté avec succès !";
        header("Location: manage_accounts.php?success=telegram_connected");
        exit();
    } else {
        $_SESSION['auth_error'] = "Erreur lors de l'enregistrement du compte Telegram";
        header("Location: add_platformes.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['auth_error'] = "Erreur de base de données: " . $e->getMessage();
    header("Location: add_platformes.php");
    exit();
}