<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!-- Ce fichier ne contient que la partie navbar, pas la structure HTML complète -->
<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="dashboard.php">MultiPost</a>
    
    <?php if(isset($_SESSION['user_id'])): ?>
      <div class="dropdown text-end ms-auto">
        <a href="#" class="d-block link-dark text-decoration-none dropdown-toggle profile-dropdown" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
          <?php 
          $username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Utilisateur';
          $initial = strtoupper(substr($username, 0, 1));
          ?>
          <div class="avatar-placeholder">
            <?php echo $initial; ?>
          </div>
          <i class="bi bi-caret-down-fill"></i>
        </a>
        <ul class="dropdown-menu text-small shadow dropdown-menu-end" aria-labelledby="dropdownUser">
          <li class="px-3 py-2">
            <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Utilisateur'; ?></strong>
            <div class="user-email"><?php echo isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : ''; ?></div>
          </li>
          
          <li><hr class="dropdown-divider"></li>
          
          <li>
            <a class="dropdown-item" href="settings.php">
              <i class="bi bi-gear"></i> Paramètres
            </a>
          </li>
          
          <li>
            <a class="dropdown-item" href="channels.php">
              <i class="bi bi-diagram-3"></i> Canaux
            </a>
          </li>
          
          <li>
            <a class="dropdown-item" href="refer.php">
              <i class="bi bi-share"></i> Inviter un ami
            </a>
          </li>
          
          <li><hr class="dropdown-divider"></li>
          
          <li>
            <a class="dropdown-item text-danger" href="logout.php">
              <i class="bi bi-box-arrow-right"></i> Déconnexion
            </a>
          </li>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</nav>