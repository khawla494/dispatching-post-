<?php
// Inclure la connexion à la base de données
require_once 'db.php';

// Vérifier si la table post_status existe déjà
$tableExists = $pdo->query("SHOW TABLES LIKE 'post_status'")->rowCount() > 0;

if (!$tableExists) {
    // Créer la table post_status
    $createSQL = "
    CREATE TABLE `post_status` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(50) NOT NULL,
      `description` text,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        // Exécuter la création de la table
        $pdo->exec($createSQL);
        echo "Table post_status créée avec succès.<br>";
        
        // Insérer les valeurs par défaut
        $insertSQL = "
        INSERT INTO `post_status` (`name`, `description`) VALUES
        ('scheduled', 'Post programmé pour publication future'),
        ('pending', 'Post en attente d\'approbation'),
        ('published', 'Post publié avec succès'),
        ('failed', 'Échec de la publication du post'),
        ('draft', 'Post enregistré comme brouillon');
        ";
        
        $pdo->exec($insertSQL);
        echo "Valeurs de statut par défaut insérées.<br>";
        
        // Vérifier si la colonne status_id existe dans la table posts
        $columnExists = $pdo->query("SHOW COLUMNS FROM `posts` LIKE 'status_id'")->rowCount() > 0;
        
        if (!$columnExists) {
            // Ajouter la colonne status_id à la table posts
            $pdo->exec("ALTER TABLE `posts` ADD COLUMN `status_id` int(11) NULL AFTER `status`");
            echo "Colonne status_id ajoutée à la table posts.<br>";
            
            // Mettre à jour les status_id basés sur les valeurs de status existantes
            $pdo->exec("
                UPDATE `posts` p 
                JOIN `post_status` ps ON p.status = ps.name 
                SET p.status_id = ps.id
                WHERE p.status IS NOT NULL
            ");
            echo "Les références status_id dans la table posts ont été mises à jour.<br>";
        }
        
        echo "Configuration complète!";
    } catch (PDOException $e) {
        echo "Erreur: " . $e->getMessage();
    }
} else {
    echo "La table post_status existe déjà.";
}