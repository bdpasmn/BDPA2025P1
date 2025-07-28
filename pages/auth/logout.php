<?php
session_start();

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// Remove remember-me cookie if set
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, "/");
}

// Rediresct to index
header("Location: http://127.0.0.1:3000/");
exit;
?>
