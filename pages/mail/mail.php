<?php
require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';

$api = new qOverflowAPI(API_KEY);

$user = 'user';            
$other = 'test_user';      

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');

    if (!$other || !$subject || !$body) {
        $error = 'All fields are required.';
    } elseif (strlen($subject) > 75 || strlen($body) > 150) {
        $error = 'Subject max 75 characters. Body max 150 characters.';
    } else {
        $resp = $api->sendMail($user, $other, $subject, $body);
        if (isset($resp['error'])) {
            $error = 'Message failed: ' . htmlspecialchars($resp['error']);
        } else {
            header('Location: mail.php?thread=' . urlencode($subject) . '&success=1');
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $success = 'Message sent successfully!';
}

$inbox = $api->getMail($user);
$sentbox = $api->getMail($other);

$allMessages = array_merge($inbox['messages'] ?? [], $sentbox['messages'] ?? []);

$conversations = [];
foreach ($allMessages as $msg) {
    $hash = sha1($msg['subject'] . $msg['sender'] . $msg['receiver'] . substr($msg['text'], 0, 50));
    if (!isset($conversations[$hash])) $conversations[$hash] = [];
    $conversations[$hash][] = $msg;
}

$currentHash = $_GET['thread'] ?? array_key_first($conversations);
$currentThread = $conversations[$currentHash] ?? [];

usort($currentThread, fn($a, $b) => $a['createdAt'] <=> $b['createdAt']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mail • qOverflow</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
    }
  </style>
</head>
<body class="text-white">

<nav class="bg-gray-900 shadow-md border-b border-gray-700">
  <div class="max-w-7xl mx-auto px-6 h-16 flex justify-between items-center">
    <div class="flex items-center space-x-3">
      <img src="https://bdpa.org/wp-content/uploads/2020/12/f0e60ae421144f918f032f455a2ac57a.png" class="h-8" alt="BDPA Logo" />
      <span class="text-xl font-bold">qOverflow</span>
    </div>
    <div class="flex space-x-4 text-sm items-center">
      <a href="../buffet/buffet.php" class="text-gray-300 hover:text-white">Questions</a>
      <a href="../dashboard/dashboard.php" class="text-gray-300 hover:text-white">Dashboard</a>
      <a href="mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md shadow">Mail</a>
    </div>
  </div>
</nav>

<?php if ($error): ?>
  <div class="bg-red-600 text-white text-sm py-2 text-center"><?= htmlspecialchars($error) ?></div>
<?php elseif ($success): ?>
  <div class="bg-green-600 text-white text-sm py-2 text-center"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="flex h-[calc(100vh-4rem)] px-6 pt-6 space-x-6 overflow-hidden">
  <aside class="w-72 bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-700 shadow-md overflow-y-auto">
    <a href="mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 mb-4 rounded-md text-sm font-medium shadow text-center">+ Compose</a>
    <?php foreach ($conversations as $hash => $msgs): ?>
      <?php $first = $msgs[0]; ?>
      <a href="?thread=<?= urlencode($hash) ?>" class="block bg-gray-700 hover:bg-gray-600 rounded-lg p-3 mb-2 border border-gray-600 transition">
        <h4 class="font-semibold text-white text-sm truncate"><?= htmlspecialchars($first['subject']) ?></h4>
        <p class="text-xs text-gray-300 truncate">
          <?= htmlspecialchars($first['sender']) ?>: <?= htmlspecialchars($first['text']) ?>
        </p>
      </a>
    <?php endforeach; ?>
  </aside>

  <main class="flex-1 bg-gray-800 rounded-xl p-6 flex flex-col border border-gray-700 shadow-md overflow-hidden">
    <?php if (!empty($currentThread)): ?>
      <div class="flex-1 overflow-y-auto space-y-4">
        <?php foreach ($currentThread as $msg): ?>
          <div class="bg-gray-700 p-4 rounded-md border border-gray-600">
            <div class="flex justify-between text-sm text-gray-400 mb-1">
              <span><strong><?= htmlspecialchars($msg['sender']) ?></strong> to <strong><?= htmlspecialchars($msg['receiver']) ?></strong></span>
              <span><?= date('n/j g:ia', intval($msg['createdAt'] / 1000)) ?></span>
            </div>
            <div class="text-white text-sm leading-relaxed whitespace-pre-line"><?= htmlspecialchars($msg['text']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <form method="POST" class="mt-6 border-t border-gray-700 pt-4">
        <input type="hidden" name="subject" value="<?= htmlspecialchars($currentThread[0]['subject']) ?>">
        <label for="body" class="text-sm font-medium mb-1 block">Reply to Thread:</label>
        <textarea name="body" id="body" rows="3" maxlength="150" required
          class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none mb-4"
          placeholder="Type your reply..."></textarea>
        <div class="flex justify-end">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium shadow">
            Send Reply
          </button>
        </div>
      </form>
    <?php else: ?>
      <form method="POST" class="flex flex-col space-y-4">
        <h2 class="text-white font-bold text-lg">Compose New Message</h2>
        <label class="text-sm font-medium">Subject:</label>
        <input type="text" name="subject" maxlength="75" required class="p-3 bg-gray-700 border border-gray-600 rounded-md text-white">
        <label class="text-sm font-medium">Message:</label>
        <textarea name="body" rows="5" maxlength="150" required class="p-3 bg-gray-700 border border-gray-600 rounded-md text-white"></textarea>
        <div class="flex justify-end">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium shadow">
            Send
          </button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
