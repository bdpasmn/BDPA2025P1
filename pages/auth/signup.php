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
$error = ''; // General error message

// Initialize form values to preserve them on error
$username = '';
$email = '';

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
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rawPassword = $_POST['password'] ?? '';
    $captcha = trim($_POST['captcha'] ?? '');
    
    // Measure password length
    $passwordLength = strlen($rawPassword);

    // Password strength message
    if ($passwordLength == 0) {
        $passwordStrength = "";
        $strengthMessage = "";
    } elseif ($passwordLength <= 10) {
        $passwordStrength = "Weak";
        $strengthMessage = "Password strength: $passwordStrength.";
    } elseif ($passwordLength <= 17) {
        $passwordStrength = "Moderate";
        $strengthMessage = "Password strength: $passwordStrength.";
    } else {
        $passwordStrength = "Strong";
        $strengthMessage = "Password strength: $passwordStrength.";
    }

    // CAPTCHA validation
    if (!isset($_SESSION['captcha_answer']) || $captcha != $_SESSION['captcha_answer']) {
        $captchaerror = "Incorrect answer";
        $haserror = true;
    }

    // Validate username is not empty
    if (empty($username)) {
        $usernameerror = "Username is required.";
        $haserror = true;
    }
    // Enforce username requirements
    elseif (
        !preg_match('/^[a-zA-Z0-9_-]+$/', $username) || // Only these characters are allowed
        !preg_match('/[a-zA-Z]/', $username) ||         // Must contain at least one letter
        !preg_match('/[0-9]/', $username)               // Must contain at least one digit
    ) {
        $usernameerror = "Username must include both letters and numbers and may include dashes and underscores.";
        $haserror = true;
    }

    // Validate email is not empty
    if (empty($email)) {
        $emailerror = "Email is required.";
        $haserror = true;
    }
    // Validate email format
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailerror = "Please enter a valid email address.";
        $haserror = true;
    }

    // Validate password is not empty
    if (empty($rawPassword)) {
        $passworderror = "Password is required.";
        $haserror = true;
    }
    // Enforce strong password requirements
    elseif (strlen($rawPassword) <= 10) {
    $passworderror = "Password must be more than 10 characters.";
    $haserror = true;
}

    // If no validation errors, proceed to DB and API registration
    if (!$haserror) {
        // Create a secure salt and derive a password hash using PBKDF2 for API
        $salt = bin2hex(random_bytes(16));
        $passwordHash = hash_pbkdf2("sha256", $rawPassword, $salt, 100000, 128, false); 

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Check if username exists in local database
            $userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $userCheck->execute([$username]);
            if ($userCheck->fetchColumn() > 0) {
                $usernameerror = "Username already exists.";
                $haserror = true;
            }

            // Check if email exists in local database
            $emailCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $emailCheck->execute([$email]);
            if ($emailCheck->fetchColumn() > 0) {
                $emailerror = "Email already exists.";
                $haserror = true;
            }

            // If still no errors, proceed with registration
            if (!$haserror) {
                // First, register user with external API (where password will be stored)
                $result = $api->createUser($username, $email, $salt, $passwordHash);
                if ($result['error']) {
                    $error = "Registration failed: " . $result['error'];
                } else {
                    // API registration successful, now insert user into local database (without password)
                    $insertQuery = "INSERT INTO users (username, email) VALUES (?, ?)";
                    $stmt = $pdo->prepare($insertQuery);
                    $stmt->execute([$username, $email]);
                    
                    // Store session data
                    //$_SESSION['user_id'] = $result['id'] ?? null;
                    //$_SESSION['username'] = $username;
                    
                    // Redirect to login page
                    header("Location: login.php");
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }

    // Regenerate CAPTCHA for next attempt
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['num1'] = $num1;
    $_SESSION['num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
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

      <?php if (!empty($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form class="space-y-6" method="POST" action="">
        <div>
          <label class="block mb-2 text-md font-medium text-white">Username</label>
          <input name="username" type="text" required
                 pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d_-]+$"
                 title="Must include both letters and numbers and may include dashes and underscores."
                 placeholder="Enter your username"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
                <label class="block mb-2 text-md font-medium text-white">Username must include both letters and numbers and may include dashes and underscores.</label>
          

          <?php if (!empty($usernameerror)): ?>
                <div class="text-red-500 mt-1"><?= htmlspecialchars($usernameerror) ?></div>
              <?php endif; ?>
            </div>


        <div>
          <label class="block mb-2 text-md font-medium text-white">Email</label>
          <input name="email" type="email" required
                 value="<?= htmlspecialchars($email) ?>"
                 placeholder="Enter your email"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />
          <?php if (!empty($emailerror)): ?>
            <div class="text-red-500 mt-1"><?= htmlspecialchars($emailerror) ?></div>
          <?php endif; ?>
        </div>

        <div>
          <label class="block mb-2 text-md font-medium text-white">Password</label>
          <input name="password" type="password" required
                 minlength="11"
                 pattern=".{11,}"
                 title="Password must be at least 11 characters. Weak (≤10) passwords are rejected. Moderate: 11–17. Strong: 18+."
                 placeholder="Enter your password"
                 class="w-full bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg p-3" />

       <?php if (!empty($passworderror)): ?>
            <div class="text-red-500 mt-1"><?= htmlspecialchars($passworderror) ?></div>
          <?php endif; ?>

          <small id="strengthMessage" class="block mt-1"></small>

          <script>
            const passwordInput = document.getElementById("password");
            const strengthMessage = document.getElementById("strengthMessage");

            passwordInput.addEventListener("input", () => {
              const length = passwordInput.value.length;
              if (length === 0) {
                strengthMessage.textContent = "";
              } else if (length <= 10) {
                strengthMessage.textContent = "Weak";
                strengthMessage.style.color = "#ef4444"; // red
              } else if (length <= 17) {
                strengthMessage.textContent = "Moderate";
                strengthMessage.style.color = "#f97316"; // orange
              } else {
                strengthMessage.textContent = "Strong";
                strengthMessage.style.color = "#22c55e"; // green
              }
            });
          </script>

          
          <?php if (!empty($strengthMessage)): ?>
            <div class="mt-1" style="color: 
              <?php
                if (strpos($strengthMessage, 'Weak') !== false) {
                    echo '#ef4444'; // red-500
                } elseif (strpos($strengthMessage, 'Moderate') !== false) {
                    echo '#f97316'; // orange-500
                } else {
                    echo '#22c55e'; // green-500
                }git
              ?>;
            ">
              <?= htmlspecialchars($strengthMessage) ?>
            </div>
          <?php endif; ?>
        </div>

        <div>
          <label for="captcha" class="block mb-2 text-md font-medium text-white">
            What is <?= $num1 ?> + <?= $num2 ?>?
          </label>
          <input type="text" id="captcha" name="captcha" placeholder="Answer"
                 class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3">
          <?php if (!empty($captchaerror)): ?>
            <div class="text-red-500 mt-1"><?= htmlspecialchars($captchaerror) ?></div>
          <?php endif; ?>
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

