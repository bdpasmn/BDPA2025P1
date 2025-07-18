<?php
session_start();
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../Api/api.php';

$api = new qOverflowAPI(API_KEY);

$user_id = $_GET['user_id'] ?? '';
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$user_id || !$password || !$confirm) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $users = $api->listUsers();
        $matchedUser = null;

        foreach ($users['users'] ?? [] as $user) {
            if (isset($user['user_id']) && $user['user_id'] == $user_id) {
                $matchedUser = $user;
                break;
            }
        }

        if ($matchedUser && isset($matchedUser['username'])) {
            $username = $matchedUser['username'];

            // Generate a new random salt (32 hex chars = 16 bytes)
            $salt = bin2hex(random_bytes(16));

            // Use PBKDF2 with sha256, 100000 iterations, output length 128 hex chars (64 bytes)
            $key = hash_pbkdf2("sha256", $password, $salt, 100000, 128, false);

            $res = $api->updateUser($username, [
                'salt' => $salt,
                'key' => $key,
            ]);

            if (isset($res['error'])) {
                $error = "Failed to reset password: " . htmlspecialchars($res['error']);
            } else {
                header('Location: ../auth/login.php');
                exit();
            }
        } else {
            $error = "Invalid or expired reset link.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Reset Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">

<section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">
  <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
    <h1 class="mb-6 text-3xl font-bold leading-tight tracking-tight text-white text-center">
      Reset Your Password
    </h1>

    <?php if ($error): ?>
      <p class="text-red-400 mb-6 text-center"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" class="space-y-6" novalidate>
      <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id) ?>">

      <div>
        <label for="password" class="block mb-2 text-md font-medium text-white">New Password</label>
        <input type="password" name="password" id="password" required
               class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3"
               placeholder="Enter a new password" autocomplete="new-password" minlength="6">
      </div>

      <div>
        <label for="confirm" class="block mb-2 text-md font-medium text-white">Confirm Password</label>
        <input type="password" name="confirm" id="confirm" required
               class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3"
               placeholder="Confirm your password" autocomplete="new-password" minlength="6">
      </div>

      <button type="submit"
              class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-md px-6 py-3 
focus:outline-none focus:ring-4 focus:ring-blue-800">
        Reset Password
      </button>
    </form>
  </div>
</section>

</body>
</html>