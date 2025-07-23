<?php
session_start();
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../Api/api.php';

$api = new qOverflowAPI(API_KEY);

$user_id = $_GET['user_id'] ?? '';
$error = '';

if (!$user_id) {
    header('Location: http://127.0.0.1:3000/');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$user_id || !$password || !$confirm) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (
        strlen($password) < 11) {
        $error = "Password must be more than 10 character";
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

            $salt = bin2hex(random_bytes(16));
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
  <script>
    function checkStrength(pw) {
      const strength = document.getElementById("strength");
      if (!pw) {
        strength.textContent = "";
        return;
      }

      let lengthCategory = "Weak (11 characters minimum)";
      if (pw.length > 17) lengthCategory = "Strong";
      else if (pw.length >= 11) lengthCategory = "Moderate";

      const message = `Password strength: ${lengthCategory}`;
      strength.textContent = message;
    }

  </script>
</head>
<body class="bg-gray-900 text-white">
<?php include __DIR__ . '/../../components/navBarLogOut.php'; ?>

<section class="min-h-screen flex flex-col items-center justify-center x-auto">
  <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">
    <h1 class="mb-2 text-3xl font-bold leading-tight tracking-tight text-white">
      Reset Your Password
    </h1>

    <?php if ($error): ?>
        <p class="text-gray-300 font-bold"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST" class="space-y-6" novalidate>
      <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id) ?>">

      <div>
        <label for="password" class="block mb-2 text-md font-medium text-white">New Password</label>
        <input type="password" name="password" id="password" required
               class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3"
               placeholder="Enter a new password" autocomplete="new-password" minlength="11"
               oninput="checkStrength(this.value)">
        <div id="strength" class="text-white text-sm mt-1"></div>
      </div>

      <div>
        <label for="confirm" class="block mb-2 text-md font-medium text-white">Confirm Password</label>
        <input type="password" name="confirm" id="confirm" required
               class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3"
               placeholder="Confirm your password" autocomplete="new-password" minlength="11">
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