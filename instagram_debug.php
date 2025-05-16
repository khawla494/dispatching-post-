<?php
session_start();
require 'db.php';
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$accounts = getAccountConfig($userId);

// Fonction pour tester la connexion à l'API Instagram
function testInstagramApi($account) {
    try {
        $userId = $account['user_id_platform'];
        $accessToken = $account['access_token'];
        
        // Tester l'accès à l'API
        $userUrl = "https://graph.facebook.com/v22.0/$userId?fields=username,profile_picture_url&access_token=" . urlencode($accessToken);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            curl_close($ch);
            return ['success' => false, 'error' => 'Erreur cURL: ' . curl_error($ch)];
        }
        
        curl_close($ch);
        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            return [
                'success' => false, 
                'error' => "Erreur API: " . ($result['error']['message'] ?? 'Inconnue')
            ];
        }
        
        return [
            'success' => true,
            'username' => $result['username'] ?? 'N/A',
            'profile_picture' => $result['profile_picture_url'] ?? null,
            'user_id' => $userId
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

$testResult = null;
$image_result = null;

// Si un compte est sélectionné pour test
if (isset($_POST['test_account'])) {
    $accountId = (int)$_POST['account_id'];
    
    // Trouver le compte Instagram sélectionné
    foreach ($accounts['instagram'] as $account) {
        if ($account['id'] == $accountId) {
            $testResult = testInstagramApi($account);
            break;
        }
    }
}

// Si une URL d'image est testée
if (isset($_POST['test_image'])) {
    $imageUrl = $_POST['image_url'];
    
    // Tester si l'image est accessible
    $headers = @get_headers($imageUrl, 1);
    
    if (!$headers || strpos($headers[0], '200') === false) {
        $image_result = ['success' => false, 'message' => 'Image inaccessible: vérifiez que l\'URL est correcte et publiquement accessible'];
    } else {
        $contentType = $headers['Content-Type'] ?? '';
        if (is_array($contentType)) {
            $contentType = end($contentType);
        }
        
        if (strpos($contentType, 'image/') === false) {
            $image_result = ['success' => false, 'message' => 'L\'URL ne pointe pas vers une image. Type détecté: ' . $contentType];
        } else {
            $image_result = ['success' => true, 'message' => 'Image valide et accessible!', 'type' => $contentType];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test d'API Instagram - MultiPost</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Copiez votre CSS existant ici -->
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Diagnostic de l'API Instagram</h4>
                    </div>
                    <div class="card-body">
                        <h5 class="mb-3">1. Tester la connexion au compte Instagram</h5>
                        
                        <?php if (!empty($accounts['instagram'])): ?>
                            <form method="post" class="mb-4">
                                <div class="mb-3">
                                    <label for="account_id" class="form-label">Sélectionner un compte Instagram</label>
                                    <select name="account_id" id="account_id" class="form-select">
                                        <?php foreach ($accounts['instagram'] as $account): ?>
                                            <option value="<?php echo $account['id']; ?>">
                                                <?php echo htmlspecialchars($account['account_name']); ?>
                                                <?php echo $account['is_default'] ? ' (par défaut)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" name="test_account" class="btn btn-primary">Tester la connexion</button>
                            </form>
                            
                            <?php if ($testResult): ?>
                                <div class="alert <?php echo $testResult['success'] ? 'alert-success' : 'alert-danger'; ?>">
                                    <?php if ($testResult['success']): ?>
                                        <h5><i class="bi bi-check-circle"></i> Connexion API réussie!</h5>
                                        <p>Nom d'utilisateur: <strong><?php echo $testResult['username']; ?></strong></p>
                                        <p>ID utilisateur: <strong><?php echo $testResult['user_id']; ?></strong></p>
                                    <?php else: ?>
                                        <h5><i class="bi bi-x-circle"></i> Échec de la connexion</h5>
                                        <p>Erreur: <?php echo $testResult['error']; ?></p>
                                        <p>Solution possible: Reconnectez votre compte Instagram dans la section Plateformes.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <p>Aucun compte Instagram connecté. <a href="add_platformes.php">Connectez un compte Instagram</a> d'abord.</p>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">2. Tester une URL d'image</h5>
                        <form method="post">
                            <div class="mb-3">
                                <label for="image_url" class="form-label">URL de l'image à tester</label>
                                <input type="url" name="image_url" id="image_url" class="form-control" 
                                       placeholder="https://example.com/image.jpg" required>
                                <div class="form-text">Entrez l'URL complète d'une image accessible publiquement</div>
                            </div>
                            <button type="submit" name="test_image" class="btn btn-primary">Vérifier l'image</button>
                        </form>
                        
                        <?php if ($image_result): ?>
                            <div class="mt-3 alert <?php echo $image_result['success'] ? 'alert-success' : 'alert-danger'; ?>">
                                <?php if ($image_result['success']): ?>
                                    <h5><i class="bi bi-check-circle"></i> Image valide!</h5>
                                    <p><?php echo $image_result['message']; ?></p>
                                    <p>Type de contenu: <?php echo $image_result['type']; ?></p>
                                    <div class="mt-2">
                                        <img src="<?php echo htmlspecialchars($_POST['image_url']); ?>" class="img-fluid border" style="max-height: 200px;" alt="Image testée">
                                    </div>
                                <?php else: ?>
                                    <h5><i class="bi bi-x-circle"></i> Problème avec l'image</h5>
                                    <p><?php echo $image_result['message']; ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <hr class="my-4">
                        
                        <h5>Conseils de dépannage</h5>
                        <ul class="list-group">
                            <li class="list-group-item">
                                <i class="bi bi-key"></i> <strong>Problème de token:</strong> Reconnectez votre compte Instagram dans la section Plateformes.
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-link"></i> <strong>Problème d'URL d'image:</strong> Assurez-vous que l'image est:
                                <ul>
                                    <li>Accessible publiquement (pas d'authentification requise)</li>
                                    <li>Au format JPEG ou PNG</li>
                                    <li>D'une taille raisonnable (moins de 8 MB)</li>
                                </ul>
                            </li>
                            <li class="list-group-item">
                                <i class="bi bi-instagram"></i> <strong>Exigences d'Instagram:</strong> Utilisation d'un compte professionnel, lié à une Page Facebook.
                            </li>
                        </ul>
                        
                        <div class="mt-4">
                            <a href="add_platformes.php" class="btn btn-outline-primary me-2">
                                <i class="bi bi-arrow-left"></i> Retour aux plateformes
                            </a>
                            <a href="create_post.php" class="btn btn-outline-secondary">
                                <i class="bi bi-pencil"></i> Créer une publication
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>