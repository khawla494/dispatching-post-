<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db.php';

// Check if the database connection was successful
if (!isset($pdo)) {
    die("Error: Database connection not established in db.php");
}

// Assign the PDO connection object to $conn
$conn = $pdo;

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, language, profile_picture FROM users WHERE id = ?");
$stmt->bindParam(1, $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch();

// Process form submissions
$message = '';
$messageType = '';

// Update profile information
if (isset($_POST['update_profile'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    if (!empty($username) && !empty($email)) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
        $stmt->bindParam(1, $username, PDO::PARAM_STR);
        $stmt->bindParam(2, $email, PDO::PARAM_STR);
        $stmt->bindParam(3, $user_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $message = "Profil mis à jour avec succès !";
            $messageType = "success";

            // Refresh user data
            $user['username'] = $username;
            $user['email'] = $email;
        } else {
            $message = "Erreur lors de la mise à jour du profil.";
            $messageType = "danger";
        }
    } else {
        $message = "Tous les champs sont requis.";
        $messageType = "warning";
    }
}

// Update profile picture
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_picture']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $upload_dir = 'uploads/profile_pictures/';

        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Generate a unique filename
        $new_filename = uniqid('profile_') . '.' . $ext;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
            // Update database with new profile picture path
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bindParam(1, $destination, PDO::PARAM_STR);
            $stmt->bindParam(2, $user_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = "Photo de profil mise à jour avec succès !";
                $messageType = "success";
                $user['profile_picture'] = $destination;
            } else {
                $message = "Erreur lors de la mise à jour de la photo de profil.";
                $messageType = "danger";
            }
        } else {
            $message = "Erreur lors du téléchargement du fichier.";
            $messageType = "danger";
        }
    } else {
        $message = "Type de fichier non autorisé. Veuillez télécharger une image (jpg, jpeg, png, gif).";
        $messageType = "warning";
    }
}

// Update password
if (isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Get current password from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bindParam(1, $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user_data = $stmt->fetch();

    if ($user_data && password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bindParam(1, $hashed_password, PDO::PARAM_STR);
            $stmt->bindParam(2, $user_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = "Mot de passe mis à jour avec succès !";
                $messageType = "success";
            } else {
                $message = "Erreur lors de la mise à jour du mot de passe.";
                $messageType = "danger";
            }
        } else {
            $message = "Les nouveaux mots de passe ne correspondent pas.";
            $messageType = "warning";
        }
    } else {
        $message = "Mot de passe actuel incorrect.";
        $messageType = "danger";
    }
}

// Update language
if (isset($_POST['update_language'])) {
    $language = $_POST['language'];
    $stmt = $conn->prepare("UPDATE users SET language = ? WHERE id = ?");
    $stmt->bindParam(1, $language, PDO::PARAM_STR);
    $stmt->bindParam(2, $user_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Update session language
        $_SESSION['language'] = $language;

        // Update user data
        $user['language'] = $language;

        $message = "Langue mise à jour avec succès !";
        $messageType = "success";

        // Redirect to refresh the page with new language
        header("Location: settings.php");
        exit();
    } else {
        $message = "Erreur lors de la mise à jour de la langue.";
        $messageType = "danger";
    }
}

// Load the correct language file based on user preference
$language = isset($user['language']) ? $user['language'] : 'fr';

// Validate language to prevent directory traversal
$validLanguages = ['fr', 'en'];
if (!in_array($language, $validLanguages)) {
    $language = 'fr'; // Default to French if invalid
}

// Include the appropriate language file
include "languages/{$language}.php";

?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo $lang['settings']; ?> - MultiPost</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Favicon -->
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #00c8b3;
            --text-color: #333;
            --border-color: #e0e0e0;
            --bg-color: #f8f9fa;
            --white: #ffffff;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
        }
        
        .sidebar {
            width: 260px;
            background-color: var(--white);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 991px) {
            .sidebar {
                left: -260px;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .content {
                margin-left: 0 !important;
            }
        }
        
        .logo {
            padding: 1.5rem;
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            text-align: center;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: var(--text-color);
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .menu-item:hover, .menu-item.active {
            background-color: rgba(0, 200, 179, 0.1);
            color: var(--primary-color);
        }
        
        .menu-item i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .create-btn {
            margin: 1rem 1.5rem;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .create-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            color: white;
        }
        
        .create-btn i {
            margin-right: 8px;
        }
        
        .content {
            margin-left: 260px;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        
        .settings-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .nav-pills .nav-link {
            color: var(--text-color);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .nav-pills .nav-link i {
            margin-right: 8px;
        }
        
        .tab-content {
            padding-top: 1.5rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(0, 200, 179, 0.25);
            border-color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #00b3a0;
            border-color: #00b3a0;
        }
        
        .profile-pic {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 600;
            margin: 0 auto 1.5rem;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }
        
        .profile-pic img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-pic .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-pic:hover .overlay {
            opacity: 1;
        }
        
        .profile-pic .overlay i {
            color: white;
            font-size: 2rem;
        }
        
        .file-input {
            display: none;
        }
        
        .menu-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            background-color: var(--white);
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            cursor: pointer;
            z-index: 900;
            display: none;
        }
        
        @media (max-width: 991px) {
            .menu-toggle {
                display: flex;
            }
        }
        
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        .overlay.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </div>
    
    <!-- Overlay for Mobile -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">MultiPost</div>
        
        <a href="#" class="create-btn">
            <i class="bi bi-plus-circle"></i>
            <span><?php echo $lang['create']; ?></span>
        </a>
        
        <a href="dashboard.php" class="menu-item">
            <i class="bi bi-house-door"></i>
            <span><?php echo $lang['home']; ?></span>
        </a>
        
        <a href="media.php" class="menu-item">
            <i class="bi bi-card-image"></i>
            <span><?php echo $lang['media']; ?></span>
        </a>
        
        <a href="explore.php" class="menu-item">
            <i class="bi bi-compass"></i>
            <span><?php echo $lang['explore']; ?></span>
        </a>
        
        <a href="add_platformes.php" class="menu-item">
            <i class="bi bi-globe"></i>
            <span><?php echo $lang['platforms']; ?></span>
        </a>
        
        <a href="settings.php" class="menu-item active">
            <i class="bi bi-gear"></i>
            <span><?php echo $lang['settings']; ?></span>
        </a>
    </div>
    
    <!-- Content Area -->
    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4"><?php echo $lang['settings']; ?></h1>
                    
                    <?php if(!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="settings-card">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                            <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="true">
                                <i class="bi bi-person"></i> <?php echo $lang['profile']; ?>
                            </button>
                            <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                                <i class="bi bi-shield-lock"></i> <?php echo $lang['security']; ?>
                            </button>
                            <button class="nav-link" id="language-tab" data-bs-toggle="pill" data-bs-target="#language" type="button" role="tab" aria-controls="language" aria-selected="false">
                                <i class="bi bi-translate"></i> <?php echo $lang['language']; ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <div class="settings-card">
                        <div class="tab-content" id="v-pills-tabContent">
                            <!-- Profile Tab -->
                            <div class="tab-pane fade show active" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                <h4 class="mb-4"><?php echo $lang['profile']; ?></h4>
                                
                                <form action="" method="post" enctype="multipart/form-data">
                                    <div class="profile-pic" id="profilePicContainer">
                                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                            <img src="<?php echo $user['profile_picture']; ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        <?php endif; ?>
                                        
                                        <div class="overlay">
                                            <i class="bi bi-camera"></i>
                                        </div>
                                        <input type="file" name="profile_picture" id="profilePicInput" class="file-input" accept="image/*">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label"><?php echo $lang['username']; ?></label>
                                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label"><?php echo $lang['email']; ?></label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="update_profile" class="btn btn-primary"><?php echo $lang['save_changes']; ?></button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Security Tab -->
                            <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                                <h4 class="mb-4"><?php echo $lang['security']; ?></h4>
                                
                                <form action="" method="post">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label"><?php echo $lang['current_password']; ?></label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label"><?php echo $lang['new_password']; ?></label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label"><?php echo $lang['confirm_password']; ?></label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" name="update_password" class="btn btn-primary"><?php echo $lang['update_password']; ?></button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Language Tab -->
<div class="tab-pane fade" id="language" role="tabpanel" aria-labelledby="language-tab">
    <h4 class="mb-4"><?php echo $lang['language']; ?></h4>
    
    <form method="POST" action="">
    <div class="mb-3">
        <label for="language" class="form-label"><?php echo $lang['language']; ?></label>
        <select name="language" id="language" class="form-select">
            <option value="fr" <?php if ($language == 'fr') echo 'selected'; ?>>Français</option>
            <option value="en" <?php if ($language == 'en') echo 'selected'; ?>>English</option>
        </select>
    </div>
    <button type="submit" name="update_language" class="btn btn-primary"><?php echo $lang['save_changes']; ?></button>
</form>

</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('overlay').classList.toggle('show');
        });
        
        document.getElementById('overlay').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('overlay').classList.remove('show');
        });
        
        // Profile picture upload
        document.getElementById('profilePicContainer').addEventListener('click', function() {
            document.getElementById('profilePicInput').click();
        });
        
        document.getElementById('profilePicInput').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                // Automatically submit the form when a file is selected
                this.form.submit();
            }
        });
    </script>
</body>
</html>