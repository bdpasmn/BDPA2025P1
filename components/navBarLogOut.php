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
  </style>
</head>
<body class="text-white">

<nav class="bg-gray-900 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex flex-col sm:flex-row sm:flex-wrap items-center justify-between gap-4 py-4">

      <!-- Logo -->
      <div class="flex items-center space-x-3 w-full sm:w-auto justify-center sm:justify-start">
        <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9 w-auto">
        <span class="text-2xl font-bold text-white">qOverflow</span>
      </div>

      <!-- Search + DateTime Form -->
      <form class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto items-center justify-center" method="get" action="navBarSearch.php">
        <input
          type="text"
          name="query"
          placeholder="Search titles, body text or creator"
          class="bg-gray-700 border border-gray-600 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full sm:w-64"
        />
        <input
          type="datetime-local"
          id="datetimeInput"
          name="datetime"
          class="bg-gray-700 border border-gray-600 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm w-full sm:w-auto"
        />
        <button type="submit" class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded text-lg">
          🔍
        </button>
        <span id="unixTime" class="text-xs text-gray-400 mt-1 sm:ml-2"></span>
      </form>

      <!-- Nav Buttons -->
      <div class="flex flex-wrap gap-2 justify-center sm:justify-end w-full sm:w-auto">
        <a href="../../pages/buffet/buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Buffet
        </a>
        <a href="../../pages/auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Login
        </a>
        <a href="../../pages/auth/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Sign Up
        </a>
      </div>

    </div>
  </div>
</nav>

<script>
  const datetimeInput = document.getElementById('datetimeInput');
  const unixTimeSpan = document.getElementById('unixTime');
  if (datetimeInput) {
    datetimeInput.addEventListener('input', function () {
      if (this.value) {
        const date = new Date(this.value);
        unixTimeSpan.textContent = 'Unix ms: ' + date.getTime();
      } else {
        unixTimeSpan.textContent = '';
      }
    });
  }
</script>

</body>
</html>