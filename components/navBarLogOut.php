<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>qOverflow — Logged-Out Navbar</title>
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
        <a href="pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/buffet/buffet.php') !== false ? ' active' : ''; ?>">
            Buffet                    
          </a> 
          <a href="/pages/auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/auth/login.php') !== false ? ' active' : ''; ?>">
          Login
        </a>
        <a href="/pages/auth/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium<?php echo strpos($current, '/pages/auth/signup.php') !== false ? ' active' : ''; ?>">
          Sign Up
        </a>
      </div>
        </div>

  <!-- Search form -->
  <form class="w-full sm:w-auto" method="get" action="/BDPA2025P1/components/navBarSearch.php">
  <div class="relative">
    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400 text-sm">🔍</span>

    <input
      type="text"
      name="query"
      placeholder="Search titles, creators, dates.."
      class="bg-gray-700 border border-gray-600 pl-10 pr-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full sm:w-64"
    />
  </div>
</form>
    </div>
  </div>
</nav>
</body>
</html>

