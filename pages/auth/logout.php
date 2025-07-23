<?php
session_start();
$_SESSION = [];
session_unset();
session_destroy();
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, "/");
}
header("Location: http://127.0.0.1:3000/");
exit;
?>
