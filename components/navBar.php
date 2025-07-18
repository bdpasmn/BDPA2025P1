<!-- Do not use this code at all this is a test page for me(reference)-->
<!DOCTYPE html>
<html>
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

      <div class="hidden md:flex flex-col text-center text-yellow-300 text-sm font-semibold">
        <span>Points: <span class="text-yellow-200">125</span></span>
        <span>Level: <span class="text-yellow-200">4</span></span>
      </div>

      <div class="flex items-center space-x-4">
        <a href="/pages/dashboard/dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Dashboard
        </a>
        <a href="/pages/mail/mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Mail
        </a>
        <a href="/pages/buffet/Buffet.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Buffet
        </a>
        <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded custom-shadow text-sm font-medium">
          Log Out
        </a>
        <img src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=identicon" alt="Profile" class="h-10 w-10 rounded-full border-2 border-blue-600" href="/pages/dashboard/dashboard.php">
      </div>
<!--Make sure to replace the Gravatar URL with the actual user's Gravatar link if available.
//Make sure to link the profile image to the user's dashboard or profile page.
//Make sure to make the points and level dynamic based on the user's data-->
    </div>
  </div>
</nav>

</body>
</html>
