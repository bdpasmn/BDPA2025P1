<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php'; 
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../Api/api.php';
$api = new qOverflowAPI(API_KEY);

// CAPTCHA generation
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['num1'] = $num1;
    $_SESSION['num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
} else {
    $num1 = $_SESSION['num1'] ?? '?';
    $num2 = $_SESSION['num2'] ?? '?';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $rawPassword = $_POST['password'];
    $captcha = trim($_POST['captcha']);

    if (!isset($_SESSION['captcha_answer']) || $captcha != $_SESSION['captcha_answer']) {
        $error = "Incorrect CAPTCHA answer.";
    } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        $error = "Username must be alphanumeric and may include dashes and underscores.";
    } elseif (
        strlen($rawPassword) < 11 ||
        !preg_match("/[A-Z]/", $rawPassword) ||
        !preg_match("/[a-z]/", $rawPassword) ||
        !preg_match("/[0-9]/", $rawPassword) ||
        !preg_match("/[\W]/", $rawPassword)
    ) {
        $error = "Password must be at least 11 characters and include uppercase, lowercase, number, and special character.";
    } else {
        $salt = bin2hex(random_bytes(16));
        //$key = hash('sha256', $salt . $rawPassword);
        //$passwordHash = password_hash($rawPassword, PASSWORD_DEFAULT);
        //$username = 'gabby16';
        //$email = 'gabby15@example.com';
        //$password = '2005871036?aA';

  
        $passwordHash = hash_pbkdf2("sha256", $rawPassword, $salt, 100000, 128, false); 

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $checkQuery = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
            $checkStmt = $pdo->prepare($checkQuery);
            $checkStmt->execute([$username, $email]);

            if ($checkStmt->fetchColumn() > 0) {
                $error = "Username or email already exists in database.";
            } else {
                $insertQuery = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute([$username, $email, $passwordHash]);

                $result = $api->createUser($username, $email, $salt, $passwordHash);
//print_r($result['error']);
                if ($result['error']) {
                    $error = "Note: User was added to the database, but API call failed: " . $result['error'];
                } else {
                    $_SESSION['user_id'] = $result['id'] ?? null;
                }

               $_SESSION['username'] = $username;
               header("Location: login.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sign Up Page</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-900 text-white">
  <section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">

    <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
      <h2 class="text-2xl font-bold mb-6 text-white">Sign Up</h2>

      <form class="space-y-6" method="POST" action="">
        <div>
          <label class="block mb-2 text-md font-medium text-white">Username</label>
          <input name="username" type="text" required
                 placeholder="Enter your username"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
        </div>

        <div>
          <label class="block mb-2 text-md font-medium text-white">Email</label>
          <input name="email" type="email" required
                 placeholder="Enter your email"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
        </div>

        <div>
          <label class="block mb-2 text-md font-medium text-white">Password</label>
          <input name="password" type="password" required
                 placeholder="Enter your password"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
        </div>

        <div>
          <label for="captcha" class="block mb-2 text-md font-medium text-white">
            What is <?= $num1 ?> + <?= $num2 ?>?
          </label>
          <input type="text" id="captcha" name="captcha" placeholder="Answer"
                 class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3">
        </div>

        <button type="submit"
                class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-md px-6 py-3">
          Sign Up
        </button>
      </form>

      <p class="mt-4 text-sm text-gray-300">
        Already have an account?
        <a href="login.php" class="text-blue-400 hover:underline">Log in</a>
      </p>
    </div>
  </section>
</body>
</html>