<?php
session_start();
require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';

$api = new qOverflowAPI(API_KEY);

if (empty($_SESSION['username'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user = $_SESSION['username'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to      = trim($_POST['recipient']);
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');

    if (!$to || !$subject || !$body) {
        $error = 'All fields are required.';
    } elseif (strlen($subject) > 75 || strlen($body) > 150) {
        $error = 'Subject max 75 characters. Body max 150 characters.';
    } else {
        $resp = $api->sendMail($user, $to, $subject, $body);
        if (isset($resp['error'])) {
            $error = 'Message failed: ' . htmlspecialchars($resp['error']);
        } else {
            $success = 'Message sent successfully!';
        }
    }
}

$inbox = $api->getMail($user);
$messages = $inbox['mail'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Mail • qOverflow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
    }
  </style>
</head>
<body class="text-white">

<!-- Navbar -->
<nav class="bg-gray-900 shadow-md border-b border-gray-700 backdrop-blur-sm">
  <div class="max-w-7xl mx-auto px-6 h-16 flex justify-between items-center">
    <div class="flex items-center space-x-3">
      <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" class="h-8" alt="BDPA Logo" />
      <span class="text-xl font-bold">qOverflow</span>
    </div>
    <div class="flex space-x-4 text-sm items-center">
      <a href="../buffet/buffet.php" class="text-gray-300 hover:text-white">Questions</a>
      <a href="../dashboard/dashboard.php" class="text-gray-300 hover:text-white">Dashboard</a>
      <a href="mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-all duration-150 shadow hover:-translate-y-0.5 hover:shadow-lg">
        Mail
      </a>
      <a href="../auth/logout.php" class="text-red-400 hover:text-red-300">Logout</a>
    </div>
  </div>
</nav>

<!-- Alerts -->
<?php if ($error): ?>
  <div class="bg-red-600 text-white text-sm py-2 text-center"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
  <div class="bg-green-600 text-white text-sm py-2 text-center"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Layout -->
<div class="flex h-[calc(100vh-4rem)] px-6 pt-6 space-x-6 overflow-hidden">
  
  <!-- Sidebar: Inbox -->
  <aside class="w-72 bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-700 shadow-md">
    <button class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-all duration-150 shadow hover:-translate-y-0.5 hover:shadow-lg mb-4">
      + Compose
    </button>
    <div class="flex-1 overflow-y-auto space-y-3">
      <?php foreach ($messages as $msg): ?>
        <div class="bg-gray-700 hover:bg-gray-600 rounded-lg p-3 border border-gray-600 transition">
          <h4 class="font-semibold text-white text-sm truncate"><?= htmlspecialchars($msg['subject']) ?></h4>
          <p class="text-xs text-gray-300 truncate">
            <span class="font-bold"><?= htmlspecialchars($msg['sender']) ?>:</span>
            <?= htmlspecialchars($msg['text']) ?>
          </p>
          <p class="text-[11px] text-blue-400 text-right mt-1">
            <?= htmlspecialchars(date('n/j g:ia', strtotime($msg['created_at'] ?? ''))) ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- Main View -->
  <main class="flex-1 bg-gray-800 rounded-xl p-6 flex flex-col border border-gray-700 shadow-md overflow-hidden">
    <?php if (!empty($messages)): ?>
      <?php $first = $messages[0]; ?>
      <div class="mb-5 border-b border-gray-700 pb-3">
        <h3 class="text-lg font-bold truncate"><?= htmlspecialchars($first['subject']) ?></h3>
        <p class="text-sm text-gray-400">From: <span class="text-blue-400 font-semibold"><?= htmlspecialchars($first['sender']) ?></span></p>
      </div>
      <div class="flex-1 overflow-y-auto mb-6 text-sm leading-relaxed whitespace-pre-wrap">
        <?= htmlspecialchars($first['text']) ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-gray-400">No messages in your inbox.</p>
    <?php endif; ?>

    <!-- Reply Form -->
    <form method="POST" class="mt-auto">
      <label for="reply" class="text-sm font-medium block mb-2">Reply (max 150 characters):</label>
      <textarea id="reply" name="body" rows="3" maxlength="150"
        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none mb-4"
        placeholder="Write your reply..."><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>

      <input type="hidden" name="recipient" value="<?= htmlspecialchars($first['sender'] ?? '') ?>">
      <input type="hidden" name="subject" value="Re: <?= htmlspecialchars($first['subject'] ?? '') ?>">

      <div class="flex justify-between items-center">
        <span class="text-xs text-gray-400">This will send a direct message to the sender.</span>
        <button type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md text-sm font-medium transition-all duration-150 shadow hover:-translate-y-0.5 hover:shadow-lg">
          Send Reply
        </button>
      </div>
    </form>
  </main>
</div>
</body>
</html>
