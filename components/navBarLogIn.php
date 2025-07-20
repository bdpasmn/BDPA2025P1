<?php
require_once '../../db.php';

// Get points and level from session if available, default to 1 if not set
$points = isset($_SESSION['points']) ? $_SESSION['points'] : 1;
$level = isset($_SESSION['level']) ? $_SESSION['level'] : 1;

// Generate gravatar URL from session email
// SHA-256 is a cryptographic hash function
$email = isset($_SESSION['email']) ? strtolower(trim($_SESSION['email'])) : '';
$gravatarUrl = 'https://www.gravatar.com/avatar/' . hash('sha256', $email) . '?d=identicon';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>qOverflow — Logged-In Navbar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
      font-family: 'Inter', sans-serif;
    }
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
<body class="text-white">

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
          <span class="text-2xl font-bold text-white">qOverflow</span>
        </div>
        <div class="flex flex-wrap gap-2 mt-2 sm:mt-0">
          <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/dashboard/dashboard.php') !== false ? ' active' : ''; ?>">
            Dashboard
          </a>
          <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/mail/mail.php') !== false ? ' active' : ''; ?>">
            Mail
          </a>
          <a href="/pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
            Buffet
          </a>
          <a href="/pages/q&a/q&a.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/q&a/q&a.php') !== false ? ' active' : ''; ?>">
            Q&A
          </a>
          <a href="/index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/index.php') !== false ? ' active' : ''; ?>">
            LogOut
          </a>
          </form>
        </div>
      </div>

      <!-- Right Side: Points/Level + Search + Avatar -->
<div class="flex flex-col sm:flex-row sm:items-center gap-4 w-full sm:w-auto">

  <!-- Points and Level -->
  <div class="flex flex-col text-white text-sm font-semibold sm:items-start">
    <span>Points: <span class="text-white"><?php echo htmlspecialchars($points); ?></span></span>
    <span>Level: <span class="text-white"><?php echo htmlspecialchars($level); ?></span></span>
  </div>

  <!-- Search form -->
  <form class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto" method="get" action="/components/navBarSearch.php">
    <input
      type="text"
      name="query"
      placeholder="Search titles, creators, dates, or body text"
      class="bg-gray-700 border border-gray-600 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full sm:w-48"
    />
    <button
      type="submit"
      class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded text-lg self-stretch sm:self-auto"
    >
      🔍
    </button>
  </form>

  <!-- Profile image -->
  <a href="/pages/dashboard/dashboard.php" class="flex justify-center">
    <img
      src="<?php echo htmlspecialchars($gravatarUrl); ?>"
      alt="Profile"
      class="h-10 w-10 rounded-full border-2 border-blue-600"
    />
  </a>
</div>
    </div>
  </div>
</nav>
</body>
</html>