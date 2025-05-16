<?php
session_start();
require 'db.php';
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];
$errorMsg = '';
$successMsg = '';

// Gérer la suppression de post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $postId = $_POST['post_id'];
    
    try {
        $pdo->beginTransaction();
        
        // D'abord supprimer de post_queue
        $stmt = $pdo->prepare("DELETE FROM post_queue WHERE post_id = ?");
        $stmt->execute([$postId]);
        
        // Puis supprimer de posts
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        
        $pdo->commit();
        
        $successMsg = "Post supprimé avec succès.";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMsg = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Gérer le filtre de statut
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Construire la requête de base
$query = "SELECT p.*, 
                 COUNT(pq.id) AS queue_count,
                 SUM(CASE WHEN pq.status = 'published' THEN 1 ELSE 0 END) AS published_count,
                 SUM(CASE WHEN pq.status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                 SUM(CASE WHEN pq.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                 SUM(CASE WHEN pq.status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled_count,
                 MAX(pq.published_at) as published_at
          FROM posts p
          LEFT JOIN post_queue pq ON p.id = pq.post_id
          WHERE p.user_id = ?";

// Ajouter le filtre de statut si nécessaire
$params = [$userId];
if ($statusFilter !== 'all') {
    if ($statusFilter === 'published') {
        $query .= " AND EXISTS (SELECT 1 FROM post_queue WHERE post_id = p.id AND status = 'published')";
    } elseif ($statusFilter === 'scheduled') {
        $query .= " AND p.status = 'scheduled'";
    } elseif ($statusFilter === 'pending') {
        $query .= " AND p.status = 'pending'";
    } elseif ($statusFilter === 'failed') {
        $query .= " AND EXISTS (SELECT 1 FROM post_queue WHERE post_id = p.id AND status = 'failed') 
                    AND NOT EXISTS (SELECT 1 FROM post_queue WHERE post_id = p.id AND status = 'published')";
    }
}

// Compléter la requête avec groupement et tri
$query .= " GROUP BY p.id ORDER BY p.created_at DESC";

// Récupérer les posts
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Erreur de base de données : " . $e->getMessage();
    $posts = [];
}

// Fonction pour obtenir l'icône de plateforme
function getPlatformIcon($platform) {
    $icons = [
        'facebook' => 'fa-facebook',
        'instagram' => 'fa-instagram',
        'telegram' => 'fa-telegram',
        'whatsapp' => 'fa-whatsapp',
        'pixelfed' => 'fa-mastodon'
    ];
    
    $platformName = explode(',', $platform)[0]; // Prend la première plateforme s'il y en a plusieurs
    return $icons[strtolower($platformName)] ?? 'fa-share-alt';
}

// Fonction pour obtenir le badge de statut
function getStatusBadge($post) {
    if ($post['published_count'] > 0) {
        return '<span class="badge bg-success">Publié</span>';
    } elseif ($post['failed_count'] > 0 && $post['published_count'] == 0) {
        return '<span class="badge bg-danger">Échec</span>';
    } elseif ($post['scheduled_count'] > 0) {
        return '<span class="badge bg-primary">Planifié</span>';
    } elseif ($post['pending_count'] > 0) {
        return '<span class="badge bg-warning text-dark">En attente</span>';
    } else {
        return '<span class="badge bg-secondary">Inconnu</span>';
    }
}

// Fonction pour nettoyer le chemin de l'image
function cleanImagePath($path) {
    // Si le chemin est vide, retourner null
    if (empty($path)) {
        return null;
    }
    
    // Si le chemin contient un chemin absolu Windows (C:\...)
    if (strpos($path, 'C:\\') !== false || strpos($path, 'C:/') !== false) {
        // Extraire juste le nom du fichier après le dernier slash ou backslash
        $path = preg_replace('/^.*[\\\\\/]/', '', $path);
    }
    
    // Si le chemin commence par "uploads/"
    if (strpos($path, 'uploads/') === 0) {
        // On retire "uploads/" pour ne garder que le nom du fichier
        $path = substr($path, 8);
    }
    
    return $path;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Posts - MultiPost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .post-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .post-card:hover {
            transform: translateY(-2px);
        }
        .platform-icon {
            font-size: 1.2rem;
            margin-right: 5px;
        }
        .post-content-preview {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .media-thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .status-filter {
            margin-bottom: 20px;
        }
        .badge {
            font-size: 0.8rem;
            padding: 5px 8px;
        }
        .action-btn {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1 class="mb-4">Gérer les Posts</h1>
                
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
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <a href="create_post.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Créer un nouveau post
                        </a>
                    </div>
                    <div>
                        <a href="manage_accounts.php" class="btn btn-outline-secondary">
                            <i class="fas fa-users-cog me-2"></i>Gérer les comptes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtre par statut -->
        <div class="row mb-4">
            <div class="col">
                <div class="card status-filter">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Filtrer par statut</h5>
                        <div class="btn-group" role="group">
                            <a href="posts.php?status=all" class="btn btn-outline-secondary <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                                Tous les posts
                            </a>
                            <a href="posts.php?status=published" class="btn btn-outline-success <?php echo $statusFilter === 'published' ? 'active' : ''; ?>">
                                Publiés
                            </a>
                            <a href="posts.php?status=scheduled" class="btn btn-outline-primary <?php echo $statusFilter === 'scheduled' ? 'active' : ''; ?>">
                                Planifiés
                            </a>
                            <a href="posts.php?status=pending" class="btn btn-outline-warning <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                                En attente
                            </a>
                            <a href="posts.php?status=failed" class="btn btn-outline-danger <?php echo $statusFilter === 'failed' ? 'active' : ''; ?>">
                                Échecs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Liste des posts -->
        <div class="row">
            <div class="col">
                <?php if (empty($posts)): ?>
                    <div class="card post-card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-newspaper fa-3x text-muted mb-3"></i>
                            <h4>Aucun post trouvé</h4>
                            <p class="text-muted">Vous n'avez pas encore créé de posts.</p>
                            <a href="create_post.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Créer votre premier post
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($posts as $post): ?>
                            <div class="col">
                                <div class="card post-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <i class="fab <?php echo getPlatformIcon($post['platforms']); ?> platform-icon"></i>
                                                    <?php echo htmlspecialchars($post['platforms']); ?>
                                                </h5>
                                                <small class="text-muted">
                                                    Créé le : <?php echo date('d/m/Y à H:i', strtotime($post['created_at'])); ?>
                                                </small>
                                            </div>
                                            <div>
                                                <?php echo getStatusBadge($post); ?>
                                            </div>
                                        </div>
                                        
                                        <p class="card-text post-content-preview mb-3">
                                            <?php echo htmlspecialchars($post['content']); ?>
                                        </p>
                                        
                                        <?php if (!empty($post['media_path'])): ?>
                                            <div class="mb-3">
                                                <?php 
                                                    $cleanPath = cleanImagePath($post['media_path']);
                                                    $imgSrc = 'uploads/' . $cleanPath;
                                                ?>
                                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                                     class="media-thumbnail" 
                                                     alt="Média du post"
                                                     onerror="this.onerror=null; this.src='img/placeholder-image.png';">
                                                <!-- Chemin d'origine : <?php echo htmlspecialchars($post['media_path']); ?> -->
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <?php if (isset($post['scheduled_at']) && $post['status'] === 'scheduled'): ?>
                                                    Planifié pour : <?php echo date('d/m/Y à H:i', strtotime($post['scheduled_at'])); ?>
                                                <?php elseif (isset($post['published_at']) && $post['published_at']): ?>
                                                    Publié le : <?php echo date('d/m/Y à H:i', strtotime($post['published_at'])); ?>
                                                <?php endif; ?>
                                            </small>
                                            
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                                        id="postActionsDropdown<?php echo $post['id']; ?>" 
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                                <ul class="dropdown-menu" aria-labelledby="postActionsDropdown<?php echo $post['id']; ?>">
                                                    <li>
                                                        <a class="dropdown-item" href="view_post.php?id=<?php echo $post['id']; ?>">
                                                            <i class="fas fa-eye me-2"></i>Voir détails
                                                        </a>
                                                    </li>
                                                    <?php if (isset($post['status']) && ($post['status'] === 'scheduled' || $post['status'] === 'pending')): ?>
                                                        <li>
                                                            <a class="dropdown-item" href="edit_post.php?id=<?php echo $post['id']; ?>">
                                                                <i class="fas fa-edit me-2"></i>Modifier
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="posts.php" class="d-inline">
                                                                <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                                                                <button type="submit" name="delete_post" class="dropdown-item text-danger" 
                                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce post ?');">
                                                                    <i class="fas fa-trash me-2"></i>Supprimer
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>MultiPost</h5>
                    <p>Votre plateforme de gestion des réseaux sociaux</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p>&copy; <?php echo date('Y'); ?> MultiPost. Tous droits réservés.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialisation des tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>