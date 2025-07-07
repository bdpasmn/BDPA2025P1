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
    <div class="flex justify-between items-center h-16">

      <div class="flex items-center space-x-3">
        <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9 w-auto">
        <span class="text-2xl font-bold text-white">qOverflow</span>
      </div>

      <div class="flex items-center space-x-4">
        <a href="/pages/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Login
        </a>
        <a href="/pages/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Join Now
        </a>
      </div>

    </div>
  </div>
</nav>

</body>
</html>
