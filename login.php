<?php
session_start();
require 'db.php'; // Database connection

$error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email']; // Store email in session
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Email ou mot de passe incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>MultiPost - Connexion</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #4285F4; 
      --secondary-color: #168983; 
      --accent-color:#238681; 
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
      width: 45%;
      min-width: 450px;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2.5rem;
      background-color: white;
    }

    .form-container {
      width: 100%;
      max-width: 500px;
      padding: 2.5rem;
    }

    .logo {
      text-align: center;
      margin-bottom: 2.5rem;
    }

      .logo h1 {
    font-size: 3rem;
    font-weight: 700;
    color: var(--text-color); /* Changed to the dark text color */
    margin-bottom: 1rem;
  }

  .logo-teal {
    color: var(--secondary-color);
  }


    .form-group {
      margin-bottom: 1.8rem;
    }

    .form-group label {
      display: block;
      margin-bottom: 0.8rem;
      font-size: 1.1rem;
      color: var(--text-color);
      font-weight: 500;
    }

    .form-group input {
      width: 100%;
      padding: 1rem 1.1rem;
      border: 1px solid var(--medium-gray);
      border-radius: 9px;
      font-size: 1rem;
      transition: all 0.2s ease;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--accent-color);
      box-shadow: 0 0 0 3px rgba(102, 171, 184, 0.1);
    }

    .options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 1.1rem 0 1.8rem;
      font-size: 1rem;
    }

    .remember-me {
      display: flex;
      align-items: center;
    }

    .remember-me input {
      margin-right: 0.8rem;
      transform: scale(1.2);
    }

    .remember-me label {
      font-size: 1rem;
    }

    .forgot-password a {
      color: var(--accent-color);
      text-decoration: none;
      font-weight: 500;
      font-size: 1rem;
    }

    .forgot-password a:hover {
      text-decoration: underline;
    }

    .btn-login {
      width: 100%;
      padding: 1rem;
      background-color: var(--accent-color);
      color: white;
      border: none;
      border-radius: 9px;
      font-size: 1.1rem;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s;
      margin-top: 1rem;
    }

    .btn-login:hover {
      background-color: #1a6d6a;
    }

    .signup-link {
      text-align: center;
      font-size: 1.1rem;
      color: var(--dark-gray);
      margin-top: 2.5rem;
    }

    .signup-link a {
      color: var(--accent-color);
      font-weight: 500;
      text-decoration: none;
    }

    .signup-link a:hover {
      text-decoration: underline;
    }

    .register-link {
      text-align: center;
      font-size: 1.1rem; /* Increased font size */
      color: var(--dark-gray);
      margin-top: 2rem; /* Increased margin */
    }

    .register-link a {
      color: var(--accent-color);
      font-weight: 500;
      text-decoration: none;
    }

    .register-link a:hover {
      text-decoration: underline;
    }
    

    /* Right side - Image */
    .right-side {
      width: 55%;
      background: url('photos/loginn.png') no-repeat center center;
      background-size: cover;
      position: relative;
    }

    .error-message {
      color: var(--error-color);
      font-size: 1rem;
      margin-bottom: 1.5rem;
      text-align: center;
      padding: 0.8rem;
      background-color: rgba(231, 76, 60, 0.1);
      border-radius: 6px;
    }

    @media (max-width: 1024px) {
      .container {
        flex-direction: column;
      }
      .left-side, .right-side {
        width: 100%;
        min-width: auto;
      }
      .right-side {
        height: 300px;
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
  <p>Content de vous revoir</p>
</div>
        <?php if (!empty($error)): ?>
          <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="login.php">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="Entrez votre adresse email" required>
          </div>
          
          <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" placeholder="Au moins 8 caractères" required>
          </div>
          
          <div class="options">
            <div class="remember-me">
              <input type="checkbox" id="remember" name="remember">
              <label for="remember">Se souvenir de moi</label>
            </div>
            <div class="forgot-password">
              <a href="forgot-password.php">Mot de passe oublié ?</a>
            </div>
          </div>
          
          <button type="submit" class="btn-login">Se connecter</button>
        </form>
        <br>
        <div class="register-link">
          Vous n'avez pas de compte ? <a href="register.php">S'inscrire</a></br>
        </div>
      </div>
    </div>
    
    <div class="right-side">
      <!-- Background image will be displayed here -->
    </div>
  </div>
</body>
</html>