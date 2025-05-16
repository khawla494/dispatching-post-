<?php
session_start();
require 'db.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Configurations Instagram
$client_id = '8359122077546387';       // Stocké dans client_id
$client_secret = '528630fb912e9686924b56d759d88252'; // Stocké dans client_secret
$redirectUri = 'https://dispatchingpost.rf.gd/MultiPost/auth_instagram.php';

// Fonction pour faire une requête HTTP POST avec cURL
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

// Fonction pour faire une requête HTTP GET avec cURL
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

if (isset($_GET['code'])) {
    // Échanger le code d'autorisation contre un access token
    try {
        $tokenUrl = 'https://graph.facebook.com/v22.0/oauth/access_token';
        $tokenParams = [
            'client_id' => $client_id,
            'redirect_uri' => $redirectUri,
            'client_secret' => $client_secret,
            'code' => $_GET['code']
        ];
        $tokenResponse = curlGet($tokenUrl . '?' . http_build_query($tokenParams));

        if (!isset($tokenResponse['access_token'])) {
            $_SESSION['auth_error'] = "Impossible d'obtenir le token d'accès.";
            header("Location: add_platformes.php");
            exit();
        }

        $shortLivedToken = $tokenResponse['access_token'];

        // Convertir en token longue durée
        $longTokenUrl = 'https://graph.facebook.com/v22.0/oauth/access_token';
        $longTokenParams = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'fb_exchange_token' => $shortLivedToken
        ];
        $longTokenResponse = curlGet($longTokenUrl . '?' . http_build_query($longTokenParams));

        if (!isset($longTokenResponse['access_token'])) {
            $_SESSION['auth_error'] = "Impossible d'obtenir le token longue durée.";
            header("Location: add_platformes.php");
            exit();
        }

        $accessToken = $longTokenResponse['access_token'];

        // Récupérer les pages Facebook gérées par l'utilisateur
        $pagesUrl = "https://graph.facebook.com/v22.0/me/accounts?access_token=" . urlencode($accessToken);
        $pagesResponse = curlGet($pagesUrl);

        if (!isset($pagesResponse['data'])) {
            $_SESSION['auth_error'] = "Impossible de récupérer les pages Facebook.";
            header("Location: add_platformes.php");
            exit();
        }

        $instagramAccounts = [];

        foreach ($pagesResponse['data'] as $page) {
            $pageId = $page['id'];
            $pageAccessToken = $page['access_token'];

            // Vérifier si la page a un compte Instagram Business associé
            $igAccountUrl = "https://graph.facebook.com/v22.0/{$pageId}?fields=instagram_business_account&access_token=" . urlencode($pageAccessToken);
            $igAccountResponse = curlGet($igAccountUrl);

            if (isset($igAccountResponse['instagram_business_account']['id'])) {
                $instagramId = $igAccountResponse['instagram_business_account']['id'];

                // Récupérer les infos du compte Instagram Business
                $igDetailsUrl = "https://graph.facebook.com/v22.0/{$instagramId}?fields=name,username&access_token=" . urlencode($pageAccessToken);
                $igDetailsResponse = curlGet($igDetailsUrl);

                $instagramAccounts[] = [
                    'page_id' => $pageId,
                    'page_access_token' => $pageAccessToken,
                    'instagram_id' => $instagramId,
                    'instagram_name' => $igDetailsResponse['username'] ?? $igDetailsResponse['name'] ?? 'Instagram Account',
                    'business_account_id' => $instagramId
                ];
            }
        }

        if (count($instagramAccounts) > 0) {
            $account = $instagramAccounts[0]; // Choix simplifié

            // Connexion à la base de données
            $conn = new mysqli($host, $user, $pass, $db);
            if ($conn->connect_error) {
                die("Erreur de connexion à la base: " . $conn->connect_error);
            }

            // Vérifier si le compte existe déjà
            $stmt = $conn->prepare("SELECT id FROM social_accounts WHERE user_id = ? AND platforms = 'instagram' AND user_id_platform = ?");
            $stmt->bind_param("is", $userId, $account['instagram_id']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Mettre à jour
                $accountId = $result->fetch_assoc()['id'];
                $stmt = $conn->prepare("UPDATE social_accounts SET 
                    account_name = ?,
                    page_id = ?,
                    access_token = ?,
                    business_account_id = ?,
                    client_id = ?,
                    client_secret = ?
                    WHERE id = ?");
                $stmt->bind_param("ssssssi", 
                    $account['instagram_name'],
                    $account['page_id'],
                    $account['page_access_token'],
                    $account['business_account_id'],
                    $client_id,
                    $client_secret,
                    $accountId);
                $stmt->execute();
            } else {
                // Ajouter nouveau compte
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM social_accounts WHERE user_id = ? AND platforms = 'instagram'");
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
                    business_account_id,
                    client_id,
                    client_secret
                ) VALUES (?, 'instagram', ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssisss", 
                    $userId, 
                    $account['instagram_name'],
                    $account['page_id'],
                    $account['instagram_id'],
                    $account['page_access_token'],
                    $isDefault,
                    $account['business_account_id'],
                    $client_id,
                    $client_secret);
                $stmt->execute();
            }

            $_SESSION['auth_success'] = "Compte Instagram '{$account['instagram_name']}' connecté avec succès !";
            header("Location: manage_accounts.php?success=account_added");
            exit();

        } else {
            $_SESSION['auth_error'] = "Aucun compte Instagram Business trouvé. Veuillez connecter un compte Instagram à votre page Facebook.";
            header("Location: add_platformes.php");
            exit();
        }

    } catch (Exception $e) {
        $_SESSION['auth_error'] = 'Erreur : ' . $e->getMessage();
        header("Location: add_platformes.php");
        exit();
    }
} else {
    // Générer l'URL d'autorisation Facebook/Instagram
    $authUrl = "https://www.facebook.com/v22.0/dialog/oauth?" . http_build_query([
        'client_id' => $client_id,
        'redirect_uri' => $redirectUri,
        'scope' => 'instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement',
        'response_type' => 'code',
        'auth_type' => 'rerequest' // Pour forcer la demande de permissions si refusées
    ]);

    header("Location: $authUrl");
    exit();
}