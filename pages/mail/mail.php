<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Mail • qOverflow</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
      font-family: 'Inter', sans-serif;
    }
    .glow-button {
      background: linear-gradient(90deg, #3b82f6, #6366f1);
      box-shadow: 0 0 16px rgba(99, 102, 241, 0.5);
      transition: box-shadow 0.3s ease, transform 0.2s ease;
    }
    .glow-button:hover {
      box-shadow: 0 0 25px rgba(99, 102, 241, 0.8);
      transform: translateY(-2px);
    }
    .inbox-message:hover {
      background-color: rgba(255, 255, 255, 0.05);
    }
    .truncate-two {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
  </style>
</head>
<body class="text-white">

<nav class="bg-gray-900 shadow-lg">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex justify-between items-center h-16">
      <div class="flex items-center space-x-3">
        <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" alt="BDPA Logo" class="h-9">
        <span class="text-2xl font-bold">qOverflow</span>
      </div>
      <div class="flex space-x-4 items-center">
        <a href="pages/buffet.php" class="px-3 py-2 rounded-md text-sm font-medium hover:text-white text-gray-300">Questions</a>
        <a href="dashboard.php" class="px-3 py-2 rounded-md text-sm font-medium hover:text-white text-gray-300">Dashboard</a>
        <a href="mail.php" class="px-3 py-2 rounded-md text-sm font-medium glow-button">Mail</a>
        <a href="logout.php" class="px-3 py-2 text-sm font-medium text-red-400 hover:underline">Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="flex h-[calc(100vh-4rem)] pt-4 px-4 space-x-6 overflow-hidden">

  <aside class="w-80 bg-gray-800 p-4 rounded-lg shadow border border-gray-700 flex flex-col">
    <button class="glow-button mb-4 py-2 text-sm font-semibold rounded-lg">+ Compose</button>
    <div class="flex-1 overflow-y-auto space-y-2 pr-1">

      <div class="inbox-message cursor-pointer bg-gray-700 p-3 rounded-lg border border-gray-600">
        <h4 class="text-md font-bold text-white truncate">Welcome to qOverflow!</h4>
        <p class="text-sm text-gray-300 truncate">
          <span class="font-semibold text-white">admin:</span> We're excited to have you here.
        </p>
        <p class="text-xs text-blue-400 mt-1 text-right">10:45 AM</p>
      </div>

      <div class="inbox-message cursor-pointer bg-gray-700 p-3 rounded-lg border border-gray-600">
        <h4 class="text-md font-bold text-white truncate">Clarification on your PHP answer</h4>
        <p class="text-sm text-gray-300 truncate">
          <span class="font-semibold text-white">mentor_user:</span> Could you explain your second approach?
        </p>
        <p class="text-xs text-blue-400 mt-1 text-right">Yesterday</p>
      </div>


    </div>
  </aside>

  <main class="flex-1 bg-gray-800 p-6 rounded-lg shadow border border-gray-700 flex flex-col overflow-hidden">
    
    <header class="mb-4 border-b border-gray-700 pb-3">
      <h3 class="text-xl font-bold truncate">Welcome to qOverflow!</h3>
      <p class="text-sm text-gray-400">From: <span class="font-semibold text-blue-400">admin</span></p>
    </header>

    <div class="flex-1 overflow-y-auto prose prose-invert text-gray-100 max-w-none">
      <p>Hey there 👋 — we're excited to have you in the qOverflow community!</p>
      <p>This platform lets you ask questions, post answers, and grow your rep across BDPA chapters.</p>
      <p>Click "Questions" above to explore, or reply here to get started.</p>
    </div>

    <form class="mt-6 space-y-4">
      <label for="reply" class="text-sm font-medium">Reply (max 150 chars, Markdown supported):</label>
      <textarea id="reply" rows="3" maxlength="150"
        class="w-full p-3 bg-gray-700 rounded-md text-white border border-gray-600 placeholder-gray-400"
        placeholder="Write your reply..."></textarea>

      <div class="flex justify-between items-center">
        <span class="text-xs text-gray-400">Your message will be added to this conversation.</span>
        <button type="submit" class="glow-button px-6 py-2 rounded-md font-semibold">Send Reply</button>
      </div>
    </form>

  </main>

</div>

</body>
</html>
