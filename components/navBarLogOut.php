<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>qOverflow Navbar</title>
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

      <!-- Logo -->
      <div class="flex items-center space-x-3 w-full sm:w-auto justify-center sm:justify-start">
        <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9 w-auto">
        <span class="text-2xl font-bold text-white">qOverflow</span>
      </div>

      <!-- Search Form -->
      <form class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto items-center justify-center" method="get" action="/BDPA2025P1/components/navBarSearch.php">
        <input
          type="text"
          name="query"
          placeholder="Search titles, creators, dates, or body text"
          class="bg-gray-700 border border-gray-600 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full sm:w-64"
          autocomplete="off"
        />
        <button type="submit" class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded text-lg">
          🔍
        </button>
      </form>

      <!-- Nav Buttons -->
      <?php
        $current = $_SERVER['REQUEST_URI'];
        // Get gravatar URL from session if available
        $gravatarUrl = $_SESSION['gravatarUrl'] ?? 'https://www.gravatar.com/avatar/00000000000000000000000000000000?d=identicon';
      ?>
      <div class="flex flex-wrap gap-2 justify-center sm:justify-end w-full sm:w-auto">
        <a href="../buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
          Buffet
        </a>
        <a href="../auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/auth/login.php') !== false ? ' active' : ''; ?>">
          Login
        </a>
        <a href="../auth/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/auth/signup.php') !== false ? ' active' : ''; ?>">
          Sign Up
        </a>
      </div>

    </div>
  </div>
</nav>

</body>
</html>