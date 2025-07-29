<?php
session_start();

if (isset($_SESSION['username'])) {
  header('Location: /pages/buffet/buffet.php');
  exit();
}

include __DIR__ . '/../../components/navBarLogOut.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../Api/api.php';

$api = new qOverflowAPI(API_KEY);

$error = null;
$userInfo = '';

// Login attempt restrictions
define('MAX_ATTEMPTS', 3);
define('LOCKOUT_TIME', 3600);

// Restore session from remember_me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($cookieUsername, $cookieToken) = explode('|', $_COOKIE['remember_me'], 2);
    $expectedToken = hash_hmac('sha256', $cookieUsername, API_KEY);
    if (hash_equals($expectedToken, $cookieToken)) {
        $userResponse = $api->getUser($cookieUsername);
        if (isset($userResponse['user'])) {
            $_SESSION['username'] = $cookieUsername;
            $_SESSION['user_id'] = $userResponse['user']['id'];
            $_SESSION['points'] = $userResponse['user']['points'] ?? 0;
            header("Location: /pages/buffet/buffet.php");
            exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $rawPassword = isset($_POST['password']) ? $_POST['password'] : '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($username) || empty($rawPassword)) {
        $error = "Enter both username and password.";
    } else {
        if (isset($_SESSION['failed_attempts']) && $_SESSION['failed_attempts'] >= MAX_ATTEMPTS) {
            $last_failed = $_SESSION['last_failed_time'] ?? 0;
            $time_since_last_fail = time() - $last_failed;

            if ($time_since_last_fail < LOCKOUT_TIME) {
                $minutes_left = ceil((LOCKOUT_TIME - $time_since_last_fail) / 60);
                $error = "Too many failed attempts. Try again in $minutes_left minutes.";
            } else {
                $_SESSION['failed_attempts'] = 0;
                $_SESSION['last_failed_time'] = 0;
            }
        }

        if (!$error) {
            $userResponse = $api->getUser($username);

            if (!isset($userResponse['user']['salt'])) {
                $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;
                $_SESSION['last_failed_time'] = time();
                $attempts_left = MAX_ATTEMPTS - $_SESSION['failed_attempts'];
                $error = $attempts_left <= 0
                    ? "Too many failed attempts. You are locked out for 1 hour."
                    : "User not found. $attempts_left attempt(s) left.";
            } else {
                $salt = $userResponse['user']['salt'];
                $passwordHash = hash_pbkdf2("sha256", $rawPassword, $salt, 100000, 128, false); 
                
                $authResponse = $api->authenticateUser($username, $passwordHash);

                if (isset($authResponse['success']) && $authResponse['success'] === true) {
                    $_SESSION['username'] = $username;
                    $_SESSION['user_id'] = $authResponse['id'] ?? null;

                    $userInfo = $api->getUser($username);
                    $_SESSION['points'] = $userInfo['user']['points'];

                    $_SESSION['failed_attempts'] = 0;
                    $_SESSION['last_failed_time'] = 0;

                    // Set remember_me cookie using HMAC token
                    if ($remember_me) {
                        $token = hash_hmac('sha256', $username, API_KEY);
                        setcookie('remember_me', $username . '|' . $token, time() + 60 * 60 * 24 * 30, "/");
                    }

                    header("Location: /pages/buffet/buffet.php");
                    exit;
                } else {
                    $_SESSION['failed_attempts'] = ($_SESSION['failed_attempts'] ?? 0) + 1;
                    $_SESSION['last_failed_time'] = time();
                    $attempts_left = MAX_ATTEMPTS - $_SESSION['failed_attempts'];
                    $error = $attempts_left <= 0
                        ? "Too many failed attempts. You are locked out for 1 hour."
                        : "Incorrect password. $attempts_left attempt(s) left.";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login Page</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
  <section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">

    <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
      <h2 class="text-2xl font-bold mb-6 text-white">Login</h2>

      <!-- Spinner -->
      <div id="spinner" class="flex justify-center items-center py-20">
        <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
      </div>

      <!-- Form Container: Hidden until DOM is ready) -->
      <div id="login-form" class="hidden">
        <?php if (isset($error)): ?>
          <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form class="space-y-6" method="POST" action="">

          <!-- Username input-->
          <div>
            <label class="block mb-2 text-md font-medium text-white">Username</label>
            <input name="username" type="text"
                   placeholder="Enter your username"
                   class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-600" required />
          </div>

          <!-- Password input -->
          <div>
            <label class="block mb-2 text-md font-medium text-white">Password</label>
            <input name="password" type="password"
                   placeholder="Enter your password"
                   class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-600" required />
          </div>

          <!-- Remember Me checkbox -->
          <div>
            <label class="inline-flex items-center text-white">
              <input type="checkbox" name="remember_me" id="remember_me" <?= isset($_COOKIE['remember_me']) ? 'checked' : '' ?>>
              Remember Me
            </label>
          </div>

          <button type="submit"
                  class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-md px-6 py-3 
                         focus:outline-none focus:ring-4 focus:ring-blue-800">
              Login
          </button>
        </form>

        <p class="mt-4 text-sm text-gray-300">
          Forgot password?
          <a href="../../pages/accountRecovery/accountRecovery.php" class="text-blue-400 hover:underline">Reset password</a>
        </p>

        <p class="mt-4 text-sm text-gray-300">
          Don’t have an account?
          <a href="signup.php" class="text-blue-400 hover:underline">Sign up</a>
        </p>
      </div>
    </div>
  </section>

  <script>
    // Hide spinner once page is fully loaded 
    window.addEventListener('DOMContentLoaded', () => {
      document.getElementById('spinner').classList.add('hidden');
      document.getElementById('login-form').classList.remove('hidden');
    });
  </script>
</body>
</html>