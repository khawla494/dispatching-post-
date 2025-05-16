<?php
session_start();
require 'db.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($username)) $errors[] = "Le nom d'utilisateur est requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email n'est pas valide.";
    if (strlen($password) < 6) $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    if ($password !== $confirm_password) $errors[] = "Les mots de passe ne correspondent pas.";

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) $errors[] = "Cet email est déjà utilisé.";

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        if ($stmt->execute([$username, $email, $hashed_password])) {
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            header("Location: dashboard.php");
            exit;
        } else {
            $errors[] = "Une erreur est survenue lors de la création du compte.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créez votre compte</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #4285F4; 
      --secondary-color: #168983; 
      --accent-color: #238681; 
      --light-gray: #f5f7fa;
      --medium-gray: #e1e5eb;
      --dark-gray: #6c757d;
      --text-color: #2d3748;
      --error-color: #e74c3c;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Roboto', sans-serif;
    }

    html, body {
      height: 100%;
      width: 100%;
    }

    body {
      display: flex;
      background-color: var(--light-gray);
    }

    .container {
      display: flex;
      width: 100%;
      height: 100vh;
    }

    /* Left side - Form */
    .left-side {
      width: 45%; /* Increased from 40% */
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2rem; /* Increased padding */
      background-color: white;
    }

    .form-container {
      width: 100%;
      max-width: 450px; /* Increased from 380px */
      padding: 2rem; /* Increased padding */
    }

    .logo {
      text-align: center;
      margin-bottom: 2rem; /* Increased margin */
    }

    .logo h1 {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-color); /* Changed to the dark text color */
    margin-bottom: 0.5rem;
  }

  .logo-teal {
    color: var(--secondary-color);
  }
    .form-group {
      margin-bottom: 1.5rem; /* Increased margin */
    }

    .form-group label {
      display: block;
      margin-bottom: 0.8rem; /* Increased margin */
      font-size: 1.1rem; /* Increased font size */
      color: var(--text-color);
      font-weight: 500;
    }

    .form-group input {
      width: 100%;
      padding: 0.9rem 1rem; /* Increased padding */
      border: 1px solid var(--medium-gray);
      border-radius: 8px; /* Increased radius */
      font-size: 1rem; /* Increased font size */
      transition: all 0.2s ease;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--accent-color);
      box-shadow: 0 0 0 3px rgba(102, 171, 184, 0.1); /* Increased shadow */
    }

    .btn-signup {
      width: 100%;
      padding: 1rem; /* Increased padding */
      background-color: var(--accent-color);
      color: white;
      border: none;
      border-radius: 8px; /* Increased radius */
      font-size: 1.1rem; /* Increased font size */
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s;
      margin-top: 1rem; /* Increased margin */
    }

    .btn-signup:hover {
      background-color: #1a6d6a;
    }

    .login-link {
      text-align: center;
      font-size: 1.1rem; /* Increased font size */
      color: var(--dark-gray);
      margin-top: 2rem; /* Increased margin */
    }

    .login-link a {
      color: var(--accent-color);
      font-weight: 500;
      text-decoration: none;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    /* Right side - Image */
    .right-side {
      width: 55%; /* Decreased from 60% */
      background: url('photos/rrr.png') no-repeat center center;
      background-size: cover;
      position: relative;
    }

    .error-message {
      color: var(--error-color);
      font-size: 0.9rem; /* Increased font size */
      margin-bottom: 1.5rem; /* Increased margin */
      padding: 0.8rem; /* Increased padding */
      background-color: rgba(231, 76, 60, 0.1);
      border-radius: 6px; /* Increased radius */
    }

    @media (max-width: 1024px) {
      .container {
        flex-direction: column;
      }
      .left-side, .right-side {
        width: 100%;
      }
      .right-side {
        height: 300px; /* Increased height */
      }
      
      .form-container {
        padding: 1.5rem; /* Increased padding */
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="left-side">
      <div class="form-container">
        <div class="logo">
  <h1>Multi<span class="logo-teal">Post</span></h1>
  <p>Créez votre compte</p>
</div>
        
        <?php if (!empty($errors)): ?>
          <div class="error-message">
            <ul>
              <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        
        <form method="post" action="register.php">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Entrez votre adresse email" value="<?= htmlspecialchars($email ?? '') ?>" required>
          </div>
          
          <div class="form-group">
            <label for="username">Nom d'utilisateur</label>
            <input type="text" id="username" name="username" placeholder="Choisissez un nom d'utilisateur" value="<?= htmlspecialchars($username ?? '') ?>" required>
          </div>
          
          <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" placeholder="Au moins 6 caractères" required>
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirmer le mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password" placeholder="Retapez votre mot de passe" required>
          </div>
          
          <button type="submit" class="btn-signup">S'inscrire</button>
        </form>
    
        <div class="login-link">
          Vous avez déjà un compte ? <a href="login.php">Connectez-vous</a>
          
        </div>
      </div>
    </div>
    
    <div class="right-side">
      <!-- Image remains at original size -->
    </div>
  </div>
</body>
</html>