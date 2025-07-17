<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php'; 
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../Api/api.php';
$api = new qOverflowAPI(API_KEY);

// Error placeholders for form validation
$usernameerror = '';
$emailerror = '';
$passworderror = '';
$captchaerror = '';
$strengthMessage = ''; // Initialize strength message

// CAPTCHA generation and validation
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
    $haserror = false;
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $rawPassword = $_POST['password'];
    $captcha = trim($_POST['captcha']);
    
    // Measure password length
    $passwordLength = strlen($rawPassword);

    // Password strength message
    if ($passwordLength <= 10) {
        $passwordStrength = "Weak";
    } elseif ($passwordLength <= 17) {
        $passwordStrength = "Moderate";
    } else {
        $passwordStrength = "Strong";
    }
    $strengthMessage = "Password strength: $passwordStrength.";

    // CAPTCHA validation
    if (!isset($_SESSION['captcha_answer']) || $captcha != $_SESSION['captcha_answer']) {
        $captchaerror = "Incorrect answer";
        $haserror = true;
    }

    // Enforce username requirements
    if (
        !preg_match('/^[a-zA-Z0-9_-]+$/', $username) || // Only these charcters are allowed
        !preg_match('/[a-zA-Z]/', $username) ||         // Must contain at least one letter
        !preg_match('/[0-9]/', $username)               // Must contain at least one digit
    ) {
        $usernameerror = "Username must include both letters and numbers and may include dashes and underscores.";
        $haserror = true;
    }

    // Enforce strong password requirements
    if (
        strlen($rawPassword) < 11 || // Must be more than 10 characters
        !preg_match("/[A-Z]/", $rawPassword) || // Must contain at least one uppercase letter
        !preg_match("/[a-z]/", $rawPassword) || // Must contain at least one lowercase letter
        !preg_match("/[0-9]/", $rawPassword) || // Must contain at least one number
        !preg_match("/[\W]/", $rawPassword) // Must contain at least one special character
    ) {
        $passworderror = "Password must have: > 10 characters, uppercase letters, lowercase letters, numbers, and special characters";
        $haserror = true;
    }

    // If no validation errors, proceed to DB and API registration
    if (!$haserror) {
        // Create a secure salt and derive a password hash using PBKDF2  
        $salt = bin2hex(random_bytes(16));
        //$passwordHash = hash_pbkdf2("sha256", $rawPassword, $salt, 100000, 128, false); 

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Check if username exists
            $userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $userCheck->execute([$username]);
            if ($userCheck->fetchColumn() > 0) {
                $usernameerror = "Username already exists.";
                $haserror = true;
            }

            // Check if email exists
            $emailCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $emailCheck->execute([$email]);
            if ($emailCheck->fetchColumn() > 0) {
                $emailerror = "Email already exists.";
                $haserror = true;
            }

            // If still no errors, insert the user
            if (!$haserror) {
                $insertQuery = "INSERT INTO users (username, email) VALUES (?, ?)";
                $stmt = $pdo->prepare($insertQuery);
                $stmt->execute([$username, $email]);
                
                // Register user with external API
                $result = $api->createUser($username, $email, $salt, $passwordHash);
                if ($result['error']) {
                    $error = "Note: User was added to the database, but API call failed: " . $result['error'];
                } else {
                    $_SESSION['user_id'] = $result['id'] ?? null;
                }

                // Store username and redirect to login
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
  <meta name="viewport" content="width=device-width, initial-scale=1" />
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

        <div 
          class="<?= !empty($usernameerror) ? 'text-red-500' : 'text-gray-400' ?>">
        </div>

        <?php if (!empty($usernameerror)): ?>
          <div class="text-red-500 mt-1"><?= htmlspecialchars($usernameerror) ?></div>
        <?php endif; ?>

        <div>
          <label class="block mb-2 text-md font-medium text-white">Email</label>
          <input name="email" type="email" required
                 value="<?= htmlspecialchars($email ?? '') ?>"
                 placeholder="Enter your email"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
        </div>

        <?php if (!empty($emailerror)): ?>
          <div class="text-red-500 mt-1"><?= htmlspecialchars($emailerror) ?></div>
        <?php endif; ?>

        <div>
          <label class="block mb-2 text-md font-medium text-white">Password</label>
          <input name="password" type="password" required
                 placeholder="Enter your password"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
        </div>

        <div 
          class="mt-1 <?= !empty($passworderror) ? 'text-red-500' : 'text-gray-400' ?>">
        </div>

        <?php if (!empty($strengthMessage)): ?>
          <div style="color: 
            <?php
              if (strpos($strengthMessage, 'Weak') !== false) {
                  echo 'red';
              } elseif (strpos($strengthMessage, 'Moderate') !== false) {
                  echo 'orange';
              } else {
                  echo 'green';
              }
            ?>;
          ">
            <?= htmlspecialchars($strengthMessage) ?>
          </div>
        <?php endif; ?>

        <div>
          <label for="captcha" class="block mb-2 text-md font-medium text-white">
            What is <?= $num1 ?> + <?= $num2 ?>?
          </label>
          <input type="text" id="captcha" name="captcha" placeholder="Answer"
                 class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3">
        </div>

        <?php if (!empty($captchaerror)): ?>
          <div style="color: red;"><?= htmlspecialchars($captchaerror) ?> </div>
        <?php endif; ?>

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
