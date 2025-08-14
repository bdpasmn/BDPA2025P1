<?php

require_once(__DIR__ . '/../db.php'); 
require_once(__DIR__ . '/../levels/getUserLevel.php'); 
require_once(__DIR__ . '/../badges/updateBadges.php');

// Defult values for unauthoterized users
$points = 1;
$level = 1;
$email = '';

if (isset($_SESSION['username'])) {

  // Only check badges occasionally (every 10th page load) to reduce API calls
  // but still allow deleted badges to be restored
  $badgeCheckCounter = $_SESSION['badge_check_counter'] ?? 0;
  if ($badgeCheckCounter % 10 == 0) {
    updateBadges($_SESSION['username']);
  }
  $_SESSION['badge_check_counter'] = $badgeCheckCounter + 1;

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Fetch user points and level from API
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

// Gravatar
if ($pdo) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => ($_SESSION['username'])]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['email'])) {
        $email = trim(strtolower($row['email']));
    }
}

// Generate a hashed email for Gravatar
$hashedEmail = md5($email);
$gravatarUrl = "https://www.gravatar.com/avatar/$hashedEmail?d=identicon";
$levelInfo = getUserLevel($_SESSION['username']);

// Fetch badge counts grouped by tier for the logged-in user
$badgeCounts = ['gold' => 0, 'silver' => 0, 'bronze' => 0];
if (isset($_SESSION['username'])) {
    $stmt = $pdo->prepare("
        SELECT tier, COUNT(*) AS count
        FROM user_badges
        WHERE username = ?
        GROUP BY tier
    ");
    $stmt->execute([$_SESSION['username']]);
    $rawCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rawCounts as $row) {
        $tier = strtolower($row['tier']);
        if (isset($badgeCounts[$tier])) {
            $badgeCounts[$tier] = (int)$row['count'];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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

      <!-- Track current page URL to mark active links -->
      <?php
        $current = $_SERVER['REQUEST_URI'];
      ?>

       <!-- Logo -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-6">
        <div class="flex items-center space-x-3">
          <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9 w-auto">
          <a href="/pages/buffet/buffet.php" >
          <span class="text-2xl font-bold text-white">qOverflow</span>
          </a>
        </div>

        <!-- Nav buttons visible on larger screens -->
        <!-- Buffet -->
        <div class="flex flex-wrap gap-2 mt-2 sm:mt-0 sm:text-xs sm-font-small hidden sm:flex">       
        <a href="/pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
            Buffet
          </a> 

        <!-- Dashboard -->
        <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/dashboard/dashboard.php') !== false ? ' active' : ''; ?>">
            Dashboard
          </a>

          <!-- Mail -->
          <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/mail/mail.php') !== false ? ' active' : ''; ?>">
            Mail
          </a>
          
          <!-- Logout -->
          <a href="/pages/auth/logout.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/auth/logout.php') !== false ? ' active' : ''; ?>">
            Logout
          </a>
          </form>
        </div>
      </div>


      <!-- Nav buttons visible on smaller screens -->
      <!-- Menu -->
      <div  class="sm:hidden flex justify-start">
        <button id="MenuButton" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Menu
        </button>
      </div>

      <!-- Buffet -->
      <div id="MenuDrop" class=" gap-2 mt-2 hidden sm:hidden flex flex-wrap gap-2 mt-2 sm:mt-0 sm:text-xs sm-font-small">       
        <a href="/pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
            Buffet
          </a>
          
        <!-- Dashboard -->
        <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/dashboard/dashboard.php') !== false ? ' active' : ''; ?>">
            Dashboard
          </a>

          <!-- Mail -->
          <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/mail/mail.php') !== false ? ' active' : ''; ?>">
            Mail
          </a>
          
          <!-- Logout -->
          <a href="/pages/auth/logout.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/auth/logout.php') !== false ? ' active' : ''; ?>">
            LogOut
          </a>
          </form>
        </div>
      
  <div class="flex flex-col sm:flex-row sm:items-center gap-4 w-full sm:w-auto">
  <div class="flex items-center gap-4">
    
  <!-- Badges Count Display -->
  <div class="flex flex-col text-blue-400 text-sm font-semibold sm:items-start mt-1">
    <?= $badgeCounts['gold'] ?> Gold 🥇|  <?= $badgeCounts['silver'] ?> Silver 🥈| <?= $badgeCounts['bronze'] ?> Bronze 🥉
  </div>

  <!-- Points and Level -->
  <div class="flex flex-col text-blue-400 text-sm font-semibold sm:items-start">
    <span>Level: <span class="text-blue-400"><?php echo htmlspecialchars($levelInfo['level']); ?></span></span>
    <span>Points: <span class="text-blue-400"><?php echo htmlspecialchars($_SESSION['points']); ?></span></span>
  </div>

 <!-- Moblie profile image -->
  <a href="/pages/dashboard/dashboard.php" class="sm:hidden">
    <img
      src="<?= $gravatarUrl ?>"
      alt="Profile"
      class="h-10 w-10 rounded-full border-2 border-blue-600"
    />
  </a>
</div>

    <div class="flex items-center gap-2 w-full sm:w-auto">

  <!-- Desktop Search form -->
  <form class="w-full sm:w-auto" method="get" action="/components/navBarSearch.php">
  <div class="relative">
    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-sm">🔍</span>

    <input
      type="text"
      name="query"
      placeholder="Search titles, creators, dates.."
      class="bg-gray-700 border border-gray-600 pl-10 pr-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-64 max-xl:w-40 max-sm:w-full"
    />
  </div>
</form>

  <!-- Desktop profile image -->
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
    //Toggle mobile menu
    document.getElementById('MenuButton').addEventListener('click', function() {
      const menuDrop = document.getElementById('MenuDrop');
      menuDrop.classList.toggle('hidden');
    });
  </script>
</body>
</html>