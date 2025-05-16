<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to index.html
header('Location: index.html');
exit;
?>