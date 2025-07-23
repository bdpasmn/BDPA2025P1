<?php
session_start();
include __DIR__ . '/../../components/navBarLogOut.php';
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
$strengthMessage = '';
$error = '';

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
    $haserror = false;
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $rawPassword = $_POST['password'];
    $captcha = trim($_POST['captcha']);

    // Create PDO connection early, before DB queries
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        $error = "Database connection failed: " . $e->getMessage();
        $haserror = true;
    }

    // Password strength
    $passwordLength = strlen($rawPassword);
    if ($passwordLength <= 10) {
        $passwordStrength = "Weak";
    } elseif ($passwordLength <= 17) {
        $passwordStrength = "Moderate";
    } else {
        $passwordStrength = "Strong";
    }
    $strengthMessage = "Password strength: $passwordStrength.";

    // CAPTCHA
    if (!isset($_SESSION['captcha_answer']) || $captcha != $_SESSION['captcha_answer']) {
        $captchaerror = "Incorrect answer";
        $haserror = true;
    }

    // Username validation
    if (
        !preg_match('/^[a-zA-Z0-9_-]+$/', $username) ||
        !preg_match('/[a-zA-Z]/', $username) ||
        !preg_match('/[0-9]/', $username)
    ) {
        $usernameerror = "Username must include both letters and numbers and may include dashes and underscores.";
        $haserror = true;
    }

    // Password validation
    if (
        strlen($rawPassword) < 11 ||
        !preg_match("/[A-Z]/", $rawPassword) ||
        !preg_match("/[a-z]/", $rawPassword) ||
        !preg_match("/[0-9]/", $rawPassword) ||
        !preg_match("/[\W]/", $rawPassword)
    ) {
        $passworderror = "Password must have: > 10 characters, uppercase letters, lowercase letters, numbers, and special characters.";
        $haserror = true;
    }

    // If no error so far and $pdo exists, check for username/email uniqueness
    if (!$haserror && isset($pdo)) {
        // Username exists?
        $userCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $userCheck->execute([$username]);
        if ($userCheck->fetchColumn() > 0) {
            $usernameerror = "Username already exists.";
            $haserror = true;
        }

        // Email exists?
        $emailCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $emailCheck->execute([$email]);
        if ($emailCheck->fetchColumn() > 0) {
            $emailerror = "Email already exists.";
            $haserror = true;
        }
    }

    if (!$haserror && isset($pdo)) {
        $salt = bin2hex(random_bytes(16));
        $passwordHash = hash_pbkdf2("sha256", $rawPassword, $salt, 100000, 128, false); 

        try {
            $insertQuery = "INSERT INTO users (username, email) VALUES (?, ?)";
            $stmt = $pdo->prepare($insertQuery);
            $stmt->execute([$username, $email]);

            $result = $api->createUser($username, $email, $salt, $passwordHash);
            if ($result['error']) {
                $error = "Login failed, try again later" . $result['error'];
            } else {
                $_SESSION['user_id'] = $result['id'] ?? null;
            }

            //$_SESSION['username'] = $username;
            header("Location: login.php");
            exit;
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
  <title>Sign Up</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-900 text-white">
  <section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">

    <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
      <h2 class="text-2xl font-bold mb-6 text-white">Sign Up</h2>

      <?php if (!empty($error)): ?>
        <p class="text-red-500 font-semibold mb-4"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form class="space-y-6" method="POST" action="">

      <!-- Username -->
      <div>
        <label class="block mb-2 text-md font-medium text-white">Username</label>
        <input name="username" type="text" required
          value="<?= htmlspecialchars($username ?? '') ?>"
          placeholder="Enter your username"
          class="w-full bg-gray-700 placeholder-gray-400 text-white text-md rounded-lg p-3 border 
                <?= !empty($usernameerror) ? 'border-red-500' : 'border-gray-600' ?>"
        />
        <p class="text-sm mt-1 <?= !empty($usernameerror) ? 'text-red-500' : 'text-gray-400' ?>">
          Must include letters, numbers, dashes, and underscores.
        </p>
        <?php if (!empty($usernameerror)): ?>
          <p class="text-red-500 mt-1 font-semibold"><?= htmlspecialchars($usernameerror) ?></p>
        <?php endif; ?>
      </div>

      <!-- Email -->
      <div>
        <label class="block mb-2 text-md font-medium text-white">Email</label>
        <input name="email" type="email" required
          value="<?= htmlspecialchars($email ?? '') ?>"
          placeholder="Enter your email"
          class="w-full bg-gray-700 placeholder-gray-400 text-white text-md rounded-lg p-3 border 
                <?= !empty($emailerror) ? 'border-red-500' : 'border-gray-600' ?>"
        />
        <?php if (!empty($emailerror)): ?>
          <p class="text-red-500 mt-1 font-semibold"><?= htmlspecialchars($emailerror) ?></p>
        <?php endif; ?>
      </div>

      <!-- Password -->
      <div>
        <label class="block mb-2 text-md font-medium text-white">Password</label>
        <input name="password" type="password" required
          placeholder="Enter your password"
          class="w-full bg-gray-700 placeholder-gray-400 text-white text-md rounded-lg p-3 border 
                <?= !empty($passworderror) ? 'border-red-500' : 'border-gray-600' ?>"
        />
        <p class="text-sm mt-1 <?= !empty($passworderror) ? 'text-red-500' : 'text-gray-400' ?>">
          Must be >10 chars, with upper/lowercase, number & symbol.
        </p>
        <?php if (!empty($passworderror)): ?>
          <p class="text-red-500 mt-1 font-semibold"><?= htmlspecialchars($passworderror) ?></p>
        <?php endif; ?>
        <?php if (!empty($strengthMessage)): ?>
          <p class="mt-1 font-semibold" style="color:
            <?php
              echo (strpos($strengthMessage, 'Weak') !== false) ? 'red' :
                   ((strpos($strengthMessage, 'Moderate') !== false) ? 'orange' : 'limegreen');
            ?>;">
            <?= htmlspecialchars($strengthMessage) ?>
          </p>
        <?php endif; ?>
      </div>

      <!-- CAPTCHA -->
      <div>
        <label for="captcha" class="block mb-2 text-md font-medium text-white">
          What is <?= $num1 ?> + <?= $num2 ?>?
        </label>
        <input type="text" id="captcha" name="captcha" placeholder="Answer"
              class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3">
        <?php if (!empty($captchaerror)): ?>
          <p class="text-red-500 mt-1 font-semibold"><?= htmlspecialchars($captchaerror) ?></p>
        <?php endif; ?>
      </div>

      <!-- Submit -->
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

