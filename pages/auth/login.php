<!DOCTYPE html>
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../Api/api.php';
$api = new qOverflowAPI(API_KEY);

define('MAX_ATTEMPTS', 3);
define('LOCKOUT_TIME', 3600); 

$error = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $rawPassword = isset($_POST['password']) ? $_POST['password'] : '';
    $remember_me = isset($_POST['remember_me']);

    if (empty($username) || empty($rawPassword)) {
        $error = "Enter both username and password.";
    } else {
     // echo $username . '<br /' ;
        $userResponse = $api->getUserByUsername($username);

        //print_r($userResponse);

//echo "<pre>";
//print_r($userResponse);
//echo "</pre>";
//exit;

        if (!isset($userResponse ['user']['salt'])) {
    $error = "Login failed. User not found or salt missing.";
} else {
    $salt = $userResponse['user']['salt'];
    //$key = hash('SHA-256', $salt . $rawPassword);
    $passwordHash = hash_pbkdf2("sha256", $rawPassword, $salt, 100000, 128, false); 
    $authResponse = $api->authenticateUser($username, $passwordHash);
    
    print_r($authResponse);

    if (isset($authResponse['success']) && $authResponse['success'] === true) {
    $_SESSION['username'] = $username;
    header("Location: dashboard.php");
    exit;
    } else {
        $error = "Login failed. Authentication failed.";
      }
    //if ($time_since_last_fail > LOCKOUT_TIME) {
      //  $_SESSION['failed_attempts'] = 0;
    //}
/*
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $remember_me = isset($_POST['remember_me']);

        $saltData = json_decode($response, true);
        $salt = is_array($saltData) && isset($saltData[0]['salt']) ? $saltData[0]['salt'] : null;
        
        if (!$salt) {
            $_SESSION['failed_attempts']++;
            $_SESSION['last_failed_time'] = time();
            $attempts_left = MAX_ATTEMPTS - $_SESSION['failed_attempts'];
            $error = "Username not found. $attempts_left Attempts.";
        } else {
            $key = hash('sha512', $salt . $password);
            $authResponse = $api->authenticateUser($username, $key);

            if ($authResponse && isset($authResponse['id'])) {
                $_SESSION['user_id'] = $authResponse['id'];
                $_SESSION['username'] = $authResponse['username'];

                $_SESSION['failed_attempts'] = 0;
                $_SESSION['last_failed_time'] = 0;

                if ($remember_me) {
                    setcookie('remember_me', $authResponse['username'], time() + 60 * 60 * 24 * 30, "/");
                }

                header("Location: authedDashboard.php");
                exit;
            } else {
                $_SESSION['failed_attempts']++;
                $_SESSION['last_failed_time'] = time();
                $attempts_left = MAX_ATTEMPTS - $_SESSION['failed_attempts'];
                $error = $attempts_left <= 0
                    ? "Too many failed attempts. You are locked out for 1 hour."
                    : "Username or password incorrect.$attempts_left Attempts left:.";
            }
        }
      } 
*/
    }
  }
}

// Optional: restore session from cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $_SESSION['username'] = $_COOKIE['remember_me'];
    header("Location: authedDashboard.php");
    exit;
}
?>

<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login Page</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-900 text-white">
  <section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">

    <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
      <h2 class="text-2xl font-bold mb-6 text-white">Login</h2>

      <?php if (isset($error)): ?>
        <p class="text-red-500 mb-4"><?php echo htmlspecialchars($error); ?></p>
      <?php endif; ?>

      <form class="space-y-6" method="POST" action="">
        <div>
          <label class="block mb-2 text-md font-medium text-white">Username</label>
          <input name="username" type="text"
                 placeholder="Enter your username"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-600" required />
        </div>

        <div>
          <label class="block mb-2 text-md font-medium text-white">Password</label>
          <input name="password" type="password"
                 placeholder="Enter your password"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-blue-600" required />
        </div>

        <div>
          <label class="inline-flex items-center text-white">
            <input type="checkbox" name="remember_me" class="mr-2">
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
        <a href="accountRecovery.php" class="text-blue-400 hover:underline">Reset password</a>
      </p>
      
      <p class="mt-4 text-sm text-gray-300">
        Don’t have an account?
        <a href="signup.php" class="text-blue-400 hover:underline">Sign up</a>
      </p>
    </div>
  </section>

</body>
</html>