<?php
session_start();
require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../lib/Parsedown.php'; // make sure Parsedown.php is in /lib

$api = new qOverflowAPI(API_KEY);
$Parsedown = new Parsedown();
$Parsedown->setSafeMode(true);

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
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
    }
    #preview {
      background-color: #1f2937; /* gray-800 */
      padding: 1rem;
      border-radius: 0.5rem;
      border: 1px solid #374151; /* gray-700 */
      color: #e5e7eb; /* gray-100 */
    }
  </style>
</head>
<body class="text-white">

<nav class="bg-gray-900 shadow-md border-b border-gray-700">
  <div class="max-w-7xl mx-auto px-6 h-16 flex justify-between items-center">
    <a href="/index.php" class="flex items-center space-x-3">
      <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" class="h-8" alt="BDPA Logo" />
      <span class="text-xl font-bold text-white">qOverflow</span>
    </a>
    <div class="flex space-x-4 text-sm items-center">
      <a href="../buffet/buffet.php" class="text-gray-300 hover:text-white">Questions</a>
      <a href="../dashboard/dashboard.php" class="text-gray-300 hover:text-white">Dashboard</a>
      <a href="mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow">Mail</a>
      <a href="../auth/logout.php" class="text-red-400 hover:text-red-300">Logout</a>
    </div>
  </div>
</nav>

<?php if ($error): ?>
  <div class="bg-red-600 text-white text-sm py-2 text-center"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
  <div class="bg-green-600 text-white text-sm py-2 text-center"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="flex h-[calc(100vh-4rem)] px-6 pt-6 space-x-6 overflow-hidden">

  <!-- Sidebar -->
  <aside class="w-72 bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-700 shadow-md">
    <button onclick="document.getElementById('reply').focus()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium shadow mb-4">
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

  <!-- Main Content -->
  <main class="flex-1 bg-gray-800 rounded-xl p-6 flex flex-col border border-gray-700 shadow-md overflow-hidden">
    <?php if (!empty($messages)): ?>
      <?php $first = $messages[0]; ?>
      <div class="mb-5 border-b border-gray-700 pb-3">
        <h3 class="text-lg font-bold"><?= htmlspecialchars($first['subject']) ?></h3>
        <p class="text-sm text-gray-400">From: <span class="text-blue-400 font-semibold"><?= htmlspecialchars($first['sender']) ?></span></p>
      </div>
      <div class="flex-1 overflow-y-auto mb-6 text-sm leading-relaxed prose prose-invert max-w-full">
        <?= $Parsedown->text($first['text']) ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-gray-400">No messages in your inbox.</p>
    <?php endif; ?>

    <!-- Reply Form -->
    <form method="POST" class="mt-auto">
      <label for="reply" class="text-sm font-medium block mb-2">Reply (Markdown supported, 150 characters max):</label>
      <textarea id="reply" name="body" rows="3" maxlength="150"
        class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none mb-4"
        placeholder="Write your reply using Markdown..."><?= htmlspecialchars($_POST['body'] ?? '') ?></textarea>

      <!-- Live Preview -->
      <label class="block mb-1 text-sm text-gray-400 font-medium">Preview:</label>
      <div id="preview" class="text-sm mb-4"></div>

      <input type="hidden" name="recipient" value="<?= htmlspecialchars($first['sender'] ?? '') ?>">
      <input type="hidden" name="subject" value="Re: <?= htmlspecialchars($first['subject'] ?? '') ?>">

      <div class="flex justify-between items-center">
        <span class="text-xs text-gray-400">This will send a direct message using Markdown.</span>
        <button type="submit"
          class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-md text-sm font-medium shadow">
          Send Reply
        </button>
      </div>
    </form>
  </main>
</div>

<script>
  const textarea = document.getElementById('reply');
  const preview = document.getElementById('preview');

  textarea.addEventListener('input', () => {
    const raw = textarea.value;
    preview.innerHTML = marked.parse(raw);
  });

  // Initialize preview if content already in textarea
  preview.innerHTML = marked.parse(textarea.value || '');
</script>

</body>
</html>
