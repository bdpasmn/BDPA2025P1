<?php
session_start();

// Clear session data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    session_unset();
    session_destroy();

    // Remove remember-me cookie
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', time() - 3600, "/");
    }

    // Redirect to landing page
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Logout</title>
</head>
<body>

<form action="logout.php" method="post">
  <button type="submit" class="text-white bg-red-600 hover:bg-red-700 font-medium rounded px-4 py-2">
    Logout
  </button>
</form>

</body>
</html>
