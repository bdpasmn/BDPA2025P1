<?php
session_start();
// $_SESSION['username'] = 'user2'; hardcoded for testing

require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';

$api = new qOverflowAPI(API_KEY);

// Get the user from session
$user = $_SESSION['username'] ?? null;
if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';
$showCompose = true;

// Get the selected thread if any
$recipient = $_GET['with'] ?? '';
$subject = $_GET['subject'] ?? '';
$activeKey = $recipient && $subject ? $recipient . '::' . $subject : null;

// Check if the recipient exists in the system, in case they are deleted or don't exist
$recipientExists = true;
if ($recipient && $recipient !== $user) {
    $resp = $api->getUser($recipient);
    if (isset($resp['error'])) {
        $recipientExists = false;
    }
}

// Handles sending the mail
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $recipient = trim($_POST['recipient'] ?? '');

    if (!$recipient || !$subject || !$body) {
        $error = 'All fields are required.';
    } elseif (strlen($subject) > 75 || strlen($body) > 150) {
        $error = 'Subject max 75 characters. Body max 150 characters.';
    } else {
        $resp = $api->sendMail($user, $recipient, $subject, $body);
        if (isset($resp['error'])) {
            $error = str_contains($resp['error'], 'not found') ?
                'User not found. Please check the recipient username.' :
                'Message failed: ' . htmlspecialchars($resp['error']);
        } else {
            // Redirect to the thread after sending message
            header("Location: mail.php?with=" . urlencode($recipient) . "&subject=" . urlencode($subject) . "&success=1");
            exit;
        }
    }
}

if (isset($_GET['success'])) {
    $success = 'Message sent successfully!';
}

// Get received messages for the current user
$inboxMessages = $api->getMail($user)['messages'] ?? [];

// Get messages the user sent (if viewing a thread)
$outboxMessages = [];
if (!empty($recipient) && $recipient !== $user) {
    $outboxMessages = $api->getMail($recipient)['messages'] ?? [];
}

// Combine inbox and outbox into one array
$allMessages = array_merge($inboxMessages, $outboxMessages);

// Group all messages into threads
$threads = [];
foreach ($allMessages as $msg) {
    if ($msg['sender'] !== $user && $msg['receiver'] !== $user) continue;

    $peer = $msg['sender'] === $user ? $msg['receiver'] : $msg['sender'];
    $key = $peer . '::' . $msg['subject'];

    if (!isset($threads[$key])) $threads[$key] = [];
    $threads[$key][] = $msg;
}

// Sort messages in each thread based on creation time
foreach ($threads as &$msgs) {
    usort($msgs, fn($a, $b) => $a['createdAt'] <=> $b['createdAt']);
}

// If viewing a thread, get that thread's messages
$activeThread = $activeKey && isset($threads[$activeKey]) ? $threads[$activeKey] : [];
$showCompose = !$activeKey;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mail • qOverflow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Showdown for Markdown -->
  <script src="https://cdn.jsdelivr.net/npm/showdown/dist/showdown.min.js"></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
    }
  </style>
</head>
<body class="text-white">
<!-- Navbar -->
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

<!--  message for error or success -->
<?php if ($error): ?>
  <div class="bg-red-600 text-white text-sm py-2 text-center"><?= $error ?></div>
<?php elseif ($success): ?>
  <div class="bg-green-600 text-white text-sm py-2 text-center"><?= $success ?></div>
<?php endif; ?>

<!-- sidebar and main panel -->
<div class="flex h-[calc(100vh-4rem)] px-6 pt-6 space-x-6 overflow-hidden">
  <aside class="w-72 bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-700 shadow-md overflow-y-auto">
    <a href="mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 mb-4 rounded-md text-sm font-medium shadow text-center">+ Compose</a>
    <?php foreach ($threads as $key => $msgs): ?>
      <?php $first = $msgs[0];
            $peer = $first['sender'] === $user ? $first['receiver'] : $first['sender']; ?>
      <a href="?with=<?= urlencode($peer) ?>&subject=<?= urlencode($first['subject']) ?>"
         class="block bg-gray-700 hover:bg-gray-600 rounded-lg p-3 mb-2 border border-gray-600 transition">
        <h4 class="font-semibold text-white text-sm truncate"><?= htmlspecialchars($first['subject']) ?></h4>
        <div class="text-xs text-gray-400 mt-1 line-clamp-3 preview-block" data-md="<?= htmlspecialchars($first['text']) ?>"></div>
      </a>
    <?php endforeach; ?>
  </aside>
  <!-- thread view or compose screen -->
  <main class="flex-1 bg-gray-800 rounded-xl p-6 flex flex-col border border-gray-700 shadow-md overflow-hidden">
    <?php if (!$showCompose): ?>
      <?php if (!$recipientExists): ?>
        <div class="bg-yellow-600 text-white text-sm px-4 py-2 mb-4 rounded">
          ⚠️ The user "<strong><?= htmlspecialchars($recipient) ?></strong>" no longer exists. You can no longer message them.
        </div>
      <?php endif; ?>


      <!-- Message history thread -->
      <div class="flex-1 overflow-y-auto space-y-4 mb-4">
        <?php foreach ($activeThread as $msg): ?>
          <?php $isMine = $msg['sender'] === $user; ?>
          <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
            <div class="<?= $isMine ? 'bg-blue-600 text-white' : 'bg-gray-700 text-white' ?> max-w-[75%] p-4 rounded-lg border border-gray-600">
              <div class="text-xs text-gray-300 mb-1">
                <?= $isMine ? 'You → ' . htmlspecialchars($msg['receiver']) : htmlspecialchars($msg['sender']) . ' → You' ?>
              </div>
              <div class="message-body text-sm" data-md="<?= htmlspecialchars($msg['text']) ?>"></div>
              <div class="text-[11px] text-gray-400 mt-1 text-right"><?= date('n/j g:ia', intval($msg['createdAt'] / 1000)) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

       <!-- Reply form -->
      <form method="POST" class="border-t border-gray-700 pt-4" <?= !$recipientExists ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
        <input type="hidden" name="recipient" value="<?= htmlspecialchars($recipient) ?>">
        <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">
        <label class="text-sm font-medium mb-1 block">Reply to Thread:</label>
        <textarea name="body" rows="3" maxlength="150" required
          class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none mb-4"
          placeholder="Type your reply..."></textarea>
        <div class="flex justify-end">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium shadow">
            Send Reply
          </button>
        </div>
      </form>

     <!-- Compose Area -->
    <?php else: ?>
      <form method="POST" class="flex flex-col space-y-4">
        <h2 class="text-white font-bold text-lg">Compose New Message</h2>
        <label class="text-sm font-medium">To (username):</label>
        <input type="text" name="recipient" maxlength="100" required class="p-3 bg-gray-700 border border-gray-600 rounded-md text-white">
        <label class="text-sm font-medium">Subject:</label>
        <input type="text" name="subject" maxlength="75" required class="p-3 bg-gray-700 border border-gray-600 rounded-md text-white">
        <label class="text-sm font-medium">Message:</label>
        <textarea name="body" id="body" rows="5" maxlength="150" required class="p-3 bg-gray-700 border border-gray-600 rounded-md text-white"></textarea>
        <label class="text-sm font-medium">Live Preview:</label>
        <div id="preview" class="p-3 bg-gray-700 border border-gray-600 rounded-md text-white text-sm min-h-[5rem]"></div>
        <div class="flex justify-end">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium shadow">
            Send
          </button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>

<!-- JS for Markdown rendering -->
<script>
  const converter = new showdown.Converter();

  // Renders all markdown blocks
  document.querySelectorAll('.message-body, .preview-block').forEach(el => {
    const raw = el.getAttribute('data-md') || '';
    el.innerHTML = converter.makeHtml(raw);
  });

   // Live preview 
  const textarea = document.getElementById('body');
  const preview = document.getElementById('preview');
  if (textarea && preview) {
    textarea.addEventListener('input', () => {
      preview.innerHTML = converter.makeHtml(textarea.value);
    });
    preview.innerHTML = converter.makeHtml(textarea.value);
  }
</script>
</body>
</html>
