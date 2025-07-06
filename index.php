<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>qOverflow — BDPA Knowledge Hub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
      font-family: 'Inter', sans-serif;
    }
    .glow-button {
      background: lignear-gradient(90deg, #3b82f6, #6366f1);
      box-shadow: 0 0 16px rgba(99, 102, 241, 0.5);
      transition: box-shadow 0.3s ease, transform 0.2s ease;
    }
    .glow-button:hover {
      box-shadow: 0 0 25px rgba(99, 102, 241, 0.8);
      transform: translateY(-2px);
    }
    .feature-card {
      transition: all 0.3s ease;
    }
    .feature-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 6px 25px rgba(0, 0, 0, 0.3);
    }
  </style>
</head>
<body class="text-white font-sans">

<nav class="bg-gray-900 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center space-x-3">
        <img class="h-9 w-auto" src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo">
        <span class="text-2xl font-bold text-white">qOverflow</span>
      </div>
      <div class="hidden sm:flex space-x-4 items-center">
        <a href="pages/login.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Login</a>
        <a href="pages/signup.php" class="glow-button px-5 py-2 rounded-lg text-white font-medium">Join Now</a>
      </div>
    </div>
  </div>
</nav>

<section class="text-center px-6 py-28">
  <h1 class="text-5xl sm:text-6xl font-extrabold leading-tight bg-clip-text text-transparent bg-gradient-to-r from-blue-500 to-purple-500">
    Your Questions, Our Community.
  </h1>
  <p class="mt-5 text-lg text-gray-300 max-w-2xl mx-auto">
    Welcome to BDPA's official knowledge-sharing hub. Ask questions, find solutions, and connect with experts from across the nation.
  </p>
  <div class="mt-10 flex justify-center gap-4 flex-wrap">
    <a href="pages/signup.php" class="glow-button text-white px-6 py-3 text-lg rounded-lg font-semibold">
      🚀 Get Started Now
    </a>
    <a href="pages/buffet.php" class="text-blue-400 border border-blue-400 hover:bg-blue-600 hover:text-white px-6 py-3 text-lg rounded-lg font-semibold transition">
      🔍 Browse Questions
    </a>
  </div>
</section>

<section class="bg-gray-800/30 py-20">
  <div class="max-w-6xl mx-auto px-6 text-center">
    <h2 class="text-3xl font-bold mb-12 text-white">How qOverflow Works</h2>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
      <div class="bg-gray-800 p-6 rounded-xl shadow border border-gray-700 feature-card">
        <div class="text-5xl mb-4 text-blue-400">❓</div>
        <h3 class="text-xl font-semibold mb-2">Ask & Explore</h3>
        <p class="text-gray-400">Post questions or browse hundreds of answered ones across tech, BDPA topics, and more.</p>
      </div>
      <div class="bg-gray-800 p-6 rounded-xl shadow border border-gray-700 feature-card">
        <div class="text-5xl mb-4 text-green-400">📈</div>
        <h3 class="text-xl font-semibold mb-2">Earn Reputation</h3>
        <p class="text-gray-400">Gain points by helping others. Level up to unlock powerful moderation tools and features.</p>
      </div>
      <div class="bg-gray-800 p-6 rounded-xl shadow border border-gray-700 feature-card">
        <div class="text-5xl mb-4 text-pink-400">📬</div>
        <h3 class="text-xl font-semibold mb-2">Private Messaging</h3>
        <p class="text-gray-400">Chat securely with other users through the built-in mail system — perfect for mentors and teams.</p>
      </div>
    </div>
  </div>
</section>

<footer class="bg-gray-900 border-t border-gray-700 text-center py-6 mt-10 text-gray-400 text-sm">
  © <?= date("Y") ?> qOverflow - Built by BDPA Southern MN. All rights reserved.
</footer>

</body>
</html>
