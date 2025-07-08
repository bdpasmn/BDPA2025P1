<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$showPopup = false;
$recoveryLink = '';
$error = '';

// Find user by email helper
function getUserByEmail($api, $email) {
    $response = $api->listUsers();
    $users = $response['users'] ?? [];
    foreach ($users as $u) {
        if (isset($u['email']) && strtolower($u['email']) === strtolower($email)) {
            return $u;
        }
    }
    return null;
}

// Handle POST form
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'] ?? '';
    $captcha = $_POST['captcha'] ?? '';

    if (intval($captcha) === ($_SESSION['captcha_answer'] ?? -1) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $user = getUserByEmail($api, $email);

        if ($user && isset($user['username'])) {
            $token = bin2hex(random_bytes(16));
            $host = $_SERVER['HTTP_HOST'];
            $recoveryLink = "http://$host/pages/resetPassword.php?token=$token";
            $showPopup = true;

            // Store token in API (in `salt` field)
            $username = $user['username'];
            $response = $api->updateUser($username, ['salt' => $token]);

            if (isset($response['error'])) {
                $error = "Failed to generate recovery link.";
                $showPopup = false;
            }
        } else {
            $error = "Email not found in our records.";
        }
    } else {
        $error = "Invalid captcha or email format.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Forgot Password</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
</head>

<?php if ($showPopup): ?>
<script>
  window.onload = () => {
    document.getElementById("popup").classList.remove("hidden");
  };
</script>
<?php endif; ?>

<body class="bg-gray-900 text-white">
<section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">

  <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
    <h1 class="mb-2 text-2xl font-bold leading-tight tracking-tight text-white md:text-3xl">
      Forgot your password?
    </h1>
    
    <br  
    
    />

    <?php if (!empty($error)): ?>
      <p class="text-red-400 mb-4"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form class="space-y-6" method="POST" action="">
      <div>
        <label for="email" class="block mb-2 text-md font-medium text-white">Your email</label>
        <input type="email" name="email" id="email" placeholder="name@example.com" required
               class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3">
      </div>

      <div>
       <label for="captcha" class="block mb-2 text-md font-medium text-white">
        What is <?= $num1 ?> + <?= $num2 ?>?
      </label>
        <input type="text" id="captcha" name="captcha" placeholder="Answer"
               class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3">
      </div>

      <button type="submit"
              class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-md px-6 py-3 
                     focus:outline-none focus:ring-4 focus:ring-blue-800">
        Send Recovery Link
      </button>
    </form>
  </div>
</section>

<?php if ($showPopup): ?>
<div id="popup" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50">
  <div class="bg-gray-800 rounded-xl shadow-xl p-8 w-[22rem] text-center">
    <h2 class="text-xl font-bold text-white mb-3">Recovery Link Generated</h2>
    <p class="text-base text-gray-300 mb-5">
      Use the following link to reset your password:
    </p>
    <div class="text-blue-400 text-sm bg-gray-700 p-3 rounded mb-5 break-all">
      <?= htmlspecialchars($recoveryLink) ?>
    </div>
    <button onclick="document.getElementById('popup').classList.add('hidden');"
            class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-md">
      Close
    </button>
  </div>
</div>
<?php endif; ?>

</body>
</html>