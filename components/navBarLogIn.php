<?php
//require_once '../../db.php';
require_once(__DIR__ . '/../db.php'); // ✅ This is reliable
require_once(__DIR__ . '/../levels/getUserLevel.php'); // ✅ This is reliable

// Get points and level from session if available, default to 1 if not set
/*
$points = isset($_SESSION['points']) ? $_SESSION['points'] : 1;
$level = isset($_SESSION['level']) ? $_SESSION['level'] : 1;
*/
$points = 1;
$level = 1;

if (isset($_SESSION['username'])) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $stmt = $pdo->prepare("SELECT points, level FROM users WHERE username = :username");
        $stmt->execute(['username' => $_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $points = intval($user['points'] ?? 1);
            $level = intval($user['level'] ?? 1);
        }
    } catch (PDOException $e) {
        error_log("Error updating user points: " . $e->getMessage());
    }
}


// Generate gravatar URL from session email
// SHA-256 is a cryptographic hash function
/*
$email = isset($_SESSION['email']) ? strtolower(trim($_SESSION['email'])) : '';
$gravatarUrl = "https://www.gravatar.com/avatar/$email?d=identicon" . hash('sha256', $email) . '?d=identicon';
*/

/*
$email = $loggedInUser['email'] ?? ' '; //gets email of logged in user, if not found, sets to empty string
$NormalizedEmail = strtolower(trim($email)); // Normalize email by converting to lowercase and trimming whitespace
$HashedEmail = md5( $NormalizedEmail); // Hash the normalized email using SHA-256
$gravatarUrl = "https://www.gravatar.com/avatar/$HashedEmail?d=identicon"; // Generate Gravatar URL with identicon fallback
*/

/*
$email = '';

// Try to get email from logged-in user
if (isset($_SESSION['username'])) {
    try {
        $userInfo = $api->getUser($_SESSION['username']);
        if (!empty($userInfo['user']['email'])) {
            $email = strtolower(trim($userInfo['user']['email']));
        }
    } catch (Exception $e) {
        // Handle error silently or log it if needed
        $email = '';
    }
}
*/

  $email = '';

  if ($pdo) {
      $stmt = $pdo->prepare("SELECT email FROM users WHERE username = :username LIMIT 1");
      $stmt->execute(['username' => ($_SESSION['username'])]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row && !empty($row['email'])) {
          $email = trim(strtolower($row['email']));
      }
  }
  
$hashedEmail = md5($email);
$gravatarUrl = "https://www.gravatar.com/avatar/$hashedEmail?d=identicon";

 $levelInfo = getUserLevel($_SESSION['username']);
?>




<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>qOverflow — Logged-In Navbar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .custom-shadow {
      box-shadow: 0 0 16px rgba(59, 130, 246, 0.5);
      transition: box-shadow 0.3s ease, transform 0.2s ease;
    }
    .custom-shadow2 {
      box-shadow: 0 0 16px rgba(220, 38, 38, 0.5);
      transition: box-shadow 0.3s ease, transform 0.2s ease;
    }
    .custom-shadow2:hover {
      box-shadow: 0 0 25px rgba(220, 38, 38, 0.8);
      transform: translateY(-2px);
    }
    .custom-shadow:hover {
      box-shadow: 0 0 25px rgba(59, 130, 246, 0.8);
      transform: translateY(-2px);
    }
    .active {
      text-decoration: underline;
      background-color: #2563eb !important;
      box-shadow: 0 0 25px rgba(59, 130, 246, 0.8);
    }
  </style>
</head>
<body class="text-white bg-gray-900">
<nav class="sticky top-0 z-50 bg-gray-900 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between py-4 gap-4 flex-wrap">

      <!-- Left: Logo and Nav Links -->
      <?php
        $current = $_SERVER['REQUEST_URI'];
      ?>
      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-6">
        <div class="flex items-center space-x-3">
          <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9 w-auto">
          <a href="/pages/buffet/buffet.php" >
          <span class="text-2xl font-bold text-white">qOverflow</span>
          </a>
        </div>




        <div class="flex flex-wrap gap-2 mt-2 sm:mt-0 sm:text-xs sm-font-small hidden sm:flex">       
        <a href="/pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
            Buffet
          </a> 
        <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/dashboard/dashboard.php') !== false ? ' active' : ''; ?>">
            Dashboard
          </a>
          <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/mail/mail.php') !== false ? ' active' : ''; ?>">
            Mail
          </a>    
          <a href="/pages/auth/logout.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/auth/logout.php') !== false ? ' active' : ''; ?>">
            LogOut
          </a>
          </form>
        </div>
      </div>

      <div  class="sm:hidden flex justify-start">
        <button id="MenuButton" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Menu
        </button>
      </div>

      <div id="MenuDrop" class=" gap-2 mt-2 hidden sm:hidden flex flex-wrap gap-2 mt-2 sm:mt-0 sm:text-xs sm-font-small">       
        <a href="/pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
            Buffet
          </a> 
        <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/dashboard/dashboard.php') !== false ? ' active' : ''; ?>">
            Dashboard
          </a>
          <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/mail/mail.php') !== false ? ' active' : ''; ?>">
            Mail
          </a>    
          <a href="/pages/auth/logout.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/auth/logout.php') !== false ? ' active' : ''; ?>">
            LogOut
          </a>
          </form>
        </div>
      

      <!-- Right Side: Points/Level + Search + Avatar -->
<div class="flex flex-col sm:flex-row sm:items-center gap-4 w-full sm:w-auto">
  
<div class="flex items-center gap-4">
  <!-- Points and Level -->
  <div class="flex flex-col text-blue-400 text-sm font-semibold sm:items-start">
    <span>Points: <span class="text-blue-400"><?php echo htmlspecialchars($_SESSION['points']); ?></span></span>
    <span>Level: <span class="text-blue-400"><?php echo htmlspecialchars($levelInfo['level']); ?></span></span>
  </div>

 <!-- Profile image -->
  <a href="/pages/dashboard/dashboard.php" class="sm:hidden">
    <img
      src="<?= $gravatarUrl ?>"
      alt="Profile"
      class="h-10 w-10 rounded-full border-2 border-blue-600"
    />
  </a>

</div>


    <div class="flex items-center gap-2 w-full sm:w-auto">


  <!-- Search form -->
  <form class="w-full sm:w-auto" method="get" action="/components/navBarSearch.php">
  <div class="relative">
    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-sm">🔍</span>

    <input
      type="text"
      name="query"
      placeholder="Search titles, creators, dates.."
      class="bg-gray-700 border border-gray-600 pl-10 pr-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-64 max-xl:w-40 max-sm:w-full"
    />
    <!-- max-xl:w-40 max-sm:w-full-->
  </div>
</form>


  <!-- Profile image -->
  <a href="/pages/dashboard/dashboard.php" class="flex justify-center hidden sm:flex">
    <img
      src="<?= $gravatarUrl ?>"
      alt="Profile"
      class="h-10 w-10 rounded-full border-2 border-blue-600"
    />
  </a>
</div>
    </div>
    </div>
  </div>
</nav>
  </div>
  <script>
    document.getElementById('MenuButton').addEventListener('click', function() {
      const menuDrop = document.getElementById('MenuDrop');
      menuDrop.classList.toggle('hidden');
    });
  </script>
</body>
</html>