<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../../api/key.php';
require_once '../../api/api.php';


$api = new qOverflowAPI(API_KEY);


$showPopup = false;
$recoveryLink = '';
$error = '';
$email = '';
$email_err = '';
$captcha_err = '';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
   $num1 = rand(1, 10);
   $num2 = rand(1, 10);
   $_SESSION['num1'] = $num1;
   $_SESSION['num2'] = $num2;
   $_SESSION['captcha_answer'] = $num1 + $num2;
} else {
   $num1 = $_SESSION['num1'] ?? '?';
   $num2 = $_SESSION['num2'] ?? '?';
}


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


if ($_SERVER["REQUEST_METHOD"] === "POST") {
   $email = trim($_POST['email'] ?? '');
   $captcha = trim($_POST['captcha'] ?? '');


   if (empty($email)) {
       $email_err = "Please enter your email.";
   } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
       $email_err = "Please enter a valid email address.";
   }

<<<<<<< Updated upstream
=======
            // Store token in API (in `key` field)
            $username = $user['username'];
            //$response = $api->updateUser($username, ['key' => $token]);
            $response = $api->updateUser($username, ['reset_token' => $token]);
            if (
        strlen($value) < 11 ||
        !preg_match("/[A-Z]/", $value) || // Check for uppercase letter
        !preg_match("/[a-z]/", $value) ||// Check for lowercase letter
        !preg_match("/[0-9]/", $value) ||// Check for number
        !preg_match("/[\W]/", $value)// Check for special character
    ) {
        echo "Password must be at least 11 characters and include uppercase, lowercase, number, and special character.";
        exit();
    }
      $salt = bin2hex(random_bytes(16));// Generate a secure random salt
      $passwordHash = hash_pbkdf2("sha256", $value,  $salt,100000, 128, false);// Hash the password with the salt
      //$UpdatesInSupabase = json_encode(['password' => $passwordHash]);

      $UpdatesInApi = $api->updateUser($username, [ // Prepare the data for API update
            'key' => $passwordHash,// Hash the password
            'salt' => $salt// Include the salt
        ]);

      $UpdatesInSupabase = $pdo->prepare("UPDATE users SET password = :password WHERE username = :username"); // Prepare the SQL statement for Supabase
      $UpdatesInSupabase->execute(['password' => $passwordHash, 'username' => $username]);// Execute the SQL statement
      echo "Successfully updated password."; 
        exit();
    }
  }
>>>>>>> Stashed changes

   if (empty($captcha)) {
       $captcha_err = "Please answer the captcha.";
   } elseif (intval($captcha) !== ($_SESSION['captcha_answer'] ?? -1)) {
       $captcha_err = "Incorrect captcha answer.";
   }


   if (empty($email_err) && empty($captcha_err)) {
       $user = getUserByEmail($api, $email);


       if ($user) {
           $idForUrl = $user['user_id'] ?? $user['username'] ?? null;


           if ($idForUrl === null) {
               $error = "User record missing identifier.";
           } else {
               $host = $_SERVER['HTTP_HOST'];
               $recoveryLink = "http://$host/pages/accountRecovery/resetPassword.php?user_id=" . urlencode($idForUrl);
               $showPopup = true;
           }
       } else {
           $error = "Email not found in our records.";
       }
   }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
 <meta charset="UTF-8" />
 <title>Forgot Password</title>
 <script src="https://cdn.tailwindcss.com"></script>
 <script>
   window.addEventListener('DOMContentLoaded', () => {
     document.getElementById("spinner").classList.add("hidden");
     document.getElementById("form-wrapper").classList.remove("hidden");

     <?php if ($showPopup): ?>
       document.getElementById("popup").classList.remove("hidden");
     <?php endif; ?>
   });
 </script>
</head>
<body class="bg-gray-900 text-white">
<?php include __DIR__ . '/../../components/navBarLogOut.php'; ?>

<section class="min-h-screen flex flex-col items-center justify-center px-6 py-10 mx-auto">
  <div class="w-full bg-gray-800 rounded-2xl shadow-lg border border-gray-700 sm:max-w-lg p-8 sm:p-10">

    <h1 class="mb-2 text-2xl font-bold leading-tight tracking-tight text-white md:text-3xl">
      Forgot Your Password?
    </h1>

    <!-- Spinner -->
    <div id="spinner" class="flex justify-center items-center py-16">
      <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Form Wrapper -->
    <div id="form-wrapper" class="hidden">
      <?php if (!empty($error)): ?>
        <p class="text-gray-300 font-bold mb-4"><?= htmlspecialchars($error) ?></p>
      <?php endif; ?>

      <form class="space-y-6" method="POST" action="">
        <div>
          <label for="email" class="block mb-2 text-md font-medium text-white">Your email</label>
          <input type="email" name="email" id="email" placeholder="name@example.com" required
                 class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3"
                 value="<?= htmlspecialchars($email) ?>">
          <?php if (!empty($email_err)): ?>
            <p class="text-gray-300 font-bold mt-1"><?= htmlspecialchars($email_err) ?></p>
          <?php endif; ?>
        </div>

        <div>
          <label for="captcha" class="block mb-2 text-md font-medium text-white">
            What is <?= $num1 ?> + <?= $num2 ?>?
          </label>
          <input type="text" id="captcha" name="captcha" placeholder="Captcha answer"
                 class="bg-gray-700 border border-gray-600 placeholder-gray-400 text-white text-md rounded-lg block w-full p-3"
                 value="">
          <?php if (!empty($captcha_err)): ?>
            <p class="text-gray-300 font-bold mt-1"><?= htmlspecialchars($captcha_err) ?></p>
          <?php endif; ?>
        </div>

        <button type="submit"
                class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-lg text-md px-6 py-3
                       focus:outline-none focus:ring-4 focus:ring-blue-800">
          Send Recovery Link
        </button>
      </form>
    </div>
  </div>
</section>

<?php if ($showPopup): ?>
<div id="popup" class="fixed inset-0 bg-black/30 flex items-center justify-center p-3 md:p-10 hidden z-[90] overflow-auto">
  <div class="bg-gray-800 w-full max-w-xl p-6 md:p-8 rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between mb-4 items-center">
      <h2 class="text-xl font-semibold text-white">Recovery Link Generated</h2>
      <button onclick="document.getElementById('popup').classList.add('hidden')" class="text-white text-xl font-bold">✕</button>
    </div>
    <p class="text-base text-gray-300 mb-5">Use the link to reset your password:</p>
    <div class="text-blue-400 text-m bg-gray-700 p-3 rounded mb-5 break-all">
      <a href="<?= htmlspecialchars($recoveryLink) ?>" class="underline hover:text-blue-300">
        <?= htmlspecialchars($recoveryLink) ?>
      </a>
    </div>
  </div>
</div>
<?php endif; ?>

</body>
</html>
