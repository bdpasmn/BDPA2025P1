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
  </style>
</head>
<body class="text-white">

<nav class="bg-gray-900 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between h-auto py-4 gap-4">

      <!-- Left: Logo and Nav Links -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-6 w-full sm:w-auto">
        <div class="flex items-center space-x-3 mb-2 sm:mb-0">
          <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9 w-auto">
          <span class="text-2xl font-bold text-white">qOverflow</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
            Dashboard
          </a>
          <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
            Mail
          </a>
          <a href="/pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
            Buffet
          </a>
          <form action="logout.php" method="post" class="inline-block">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded custom-shadow2 text-sm font-medium">
              Logout
            </button>
          </form>
        </div>
      </div>

      <!-- Center: Points and Level -->
      <div class="flex flex-col text-center text-yellow-300 text-sm font-semibold flex-1 min-w-[200px] sm:items-center">
        <span>Points: <span class="text-yellow-200">125</span></span>
        <span>Level: <span class="text-yellow-200">4</span></span>
      </div>

      <!-- Right: Search + Gravatar -->
      <div class="flex flex-col sm:flex-row sm:items-center gap-3 w-full sm:w-auto">
        <form class="flex flex-grow sm:flex-grow-0 gap-2" method="get" action="navBarSearch.php">
          <input type="text" name="query" placeholder="Search titles, body text or creator"
                 class="bg-gray-700 border border-gray-600 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full sm:w-80">
          <button type="submit" class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded text-lg">
            🔍
          </button>
        </form>
        <a href="/pages/dashboard/dashboard.php" class="flex justify-center">
          <img src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=identicon" alt="Profile" class="h-10 w-10 rounded-full border-2 border-blue-600">
        </a>
      </div>

    </div>
  </div>
</nav>

</body>
</html>