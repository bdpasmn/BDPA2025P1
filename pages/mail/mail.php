<?php
session_start();
file_put_contents(__DIR__ . '/mail_debug.log', "TOP OF FILE: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
file_put_contents(__DIR__ . '/mail_debug.log', "REQUEST_METHOD: " . (isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'N/A') . "\nPOST: " . print_r($_POST, true) . "\n", FILE_APPEND);
require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../db.php';

$appUser = $_SESSION['username'] ?? null;
if (!$appUser) {
    header("Location: ../auth/login.php");
    exit;
}

$api = new qOverflowAPI(API_KEY);

// Create a single PDO connection for all DB queries
try {
    $pdo = new PDO($dsn, $user, $pass);
} catch (Exception $e) {
    $error = "DB ERROR: " . $e->getMessage();
    header("Location: mail.php?error=" . urlencode($error));
    exit;
}

// 1. Get inbox messages (where user is receiver)
$inboxMessages = $api->getMail($appUser)['messages'] ?? [];

// 2. Build list of peers from inbox (senders)
$peers = [];
foreach ($inboxMessages as $msg) {
    $peer = $msg['sender'];
    if ($peer !== $appUser) $peers[$peer] = true;
}

// 3. Add all recipients I've ever sent a message to (from database)
try {
    $stmt = $pdo->prepare("SELECT recipient FROM sent_peers WHERE username = :username");
    $stmt->execute(['username' => $appUser]);
    $sentPeers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sentPeers as $sentPeer) {
        if ($sentPeer !== $appUser) $peers[$sentPeer] = true;
    }
} catch (Exception $e) {
    $error = "DB ERROR: " . $e->getMessage();
    header("Location: mail.php?error=" . urlencode($error));
    exit;
}
// Convert associative array keys to a simple array
$peers = array_keys($peers);

// 4. Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents(__DIR__ . '/mail_debug.log', "IN POST HANDLER\n", FILE_APPEND);
    $recipient = trim($_POST['recipient'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $rootTimestamp = isset($_POST['root_timestamp']) ? $_POST['root_timestamp'] : null;
    $currentThreadKey = $_POST['current_thread_key'] ?? null;
    file_put_contents(__DIR__ . '/mail_debug.log', "POST DATA: recipient=$recipient, subject=$subject, body=$body, rootTimestamp=$rootTimestamp, currentThreadKey=$currentThreadKey\n", FILE_APPEND);
    if ($recipient && $subject && $body) {
        if ($recipient === $appUser) {
            $error = "You cannot send messages to yourself.";
            header("Location: mail.php?compose=1&error=" . urlencode($error));
            exit;
        } else {
            // Check if recipient user exists
            $userCheck = $api->getUser($recipient);
            if (isset($userCheck['error'])) {
                $error = "User doesn't exist.";
                header("Location: mail.php?compose=1&error=" . urlencode($error));
                exit;
            } else {
                $resp = $api->sendMail($appUser, $recipient, $subject, $body);
                if (!isset($resp['error'])) {
                    // Add recipient to sent_peers table
                    try {
                        $stmt = $pdo->prepare("INSERT INTO sent_peers (username, recipient) VALUES (:username, :recipient) ON CONFLICT DO NOTHING");
                        $stmt->execute(['username' => $appUser, 'recipient' => $recipient]);
                    } catch (Exception $e) {
                        file_put_contents(__DIR__ . '/mail_debug.log', "DB ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                    // Auto-fix: Use current_thread_key for replies, only calculate for new messages
                    if ($currentThreadKey) {
                        $_SESSION['mail_success'] = 'Message sent successfully!';
                        file_put_contents(__DIR__ . '/mail_debug.log', "REDIRECTING TO EXISTING THREAD: threadKey=$currentThreadKey\n", FILE_APPEND);
                        header("Location: mail.php?thread=" . urlencode($currentThreadKey));
                        exit;
                    } else {
                        // Compose: calculate thread key as before
                        if (!$rootTimestamp) {
                            $createdAt = isset($resp['message']['createdAt']) ? $resp['message']['createdAt'] : round(microtime(true) * 1000);
                            $rootTimestamp = $createdAt;
                        }
                        $participants = [$appUser, $recipient];
                        sort($participants);
                        $base = implode('-', $participants) . '::' . trim($subject) . '::' . $rootTimestamp;
                        $threadKey = hash('sha256', $base);
                        file_put_contents(__DIR__ . '/mail_debug.log', "REDIRECTING TO NEW THREAD: threadKey=$threadKey, base=$base\n", FILE_APPEND);
                        $_SESSION['mail_success'] = 'Message sent successfully!';
                        header("Location: mail.php?thread=" . urlencode($threadKey));
                        exit;
                    }
                } else {
                    $error = "Failed to send message: " . htmlspecialchars($resp['error']);
                    header("Location: mail.php?error=" . urlencode($error));
                    exit;
                }
            }
        }
    } else {
        $error = "All fields are required.";
        header("Location: mail.php?error=" . urlencode($error));
        exit;
    }
}

// 5. For each peer, fetch sent messages from the API (where you are the sender)
$sent = [];
foreach ($peers as $peer) {
    $peerInbox = $api->getMail($peer)['messages'] ?? [];
    foreach ($peerInbox as $msg) {
        if ($msg['sender'] === $appUser && $msg['receiver'] === $peer) {
            $sent[] = $msg;
        }
    }
}

// 6. Merge and group into threads
$allMessages = array_merge($inboxMessages, $sent);
$threadBuckets = [];
$threadActivity = [];

// Helper: Find or create a unique thread key for each message
function getThreadRootTimestamp($msgs) {
    // The root timestamp is the createdAt of the first message in the thread
    return $msgs[0]['createdAt'];
}

function getThreadKey($user1, $user2, $subject, $rootTimestamp) {
    $participants = [$user1, $user2];
    sort($participants);
    $base = implode('-', $participants) . '::' . trim($subject) . '::' . $rootTimestamp;
    return hash('sha256', $base);
}

foreach ($allMessages as $msg) {
    if ($msg['sender'] !== $appUser && $msg['receiver'] !== $appUser) continue;
    $peer = $msg['sender'] === $appUser ? $msg['receiver'] : $msg['sender'];
    $subject = $msg['subject'];
    // Find the root timestamp for this thread (first message with same participants and subject)
    $rootTimestamp = $msg['createdAt'];
    foreach ($threadBuckets as $key => $msgs) {
        $first = $msgs[0];
        $peer1 = $first['sender'] === $appUser ? $first['receiver'] : $first['sender'];
        if ($peer1 === $peer && $first['subject'] === $subject) {
            $rootTimestamp = $first['createdAt'];
            break;
        }
    }
    $key = getThreadKey($appUser, $peer, $subject, $rootTimestamp);
    $threadBuckets[$key][] = $msg;
    $threadActivity[$key] = max($threadActivity[$key] ?? 0, $msg['createdAt']);
}
$threads = [];
foreach ($threadBuckets as $key => $msgs) {
    usort($msgs, fn($a, $b) => $a['createdAt'] <=> $b['createdAt']);
    $threads[$key] = $msgs;
}
uksort($threads, function($a, $b) use ($threadActivity) {
    $aTime = $threadActivity[$a] ?? 0;
    $bTime = $threadActivity[$b] ?? 0;
    return $bTime <=> $aTime;
});
file_put_contents(__DIR__ . '/mail_debug.log', "THREAD KEYS AFTER BUILD: " . print_r(array_keys($threads), true) . "\n", FILE_APPEND);

// Determine active thread
// Always use the thread key from the URL if present and valid, otherwise default to the first thread
$activeKey = isset($_GET['thread']) && isset($threads[$_GET['thread']]) ? $_GET['thread'] : array_key_first($threads);
$showCompose = isset($_GET['compose']) && $_GET['compose'] == '1';
$activeThread = !$showCompose && $activeKey && isset($threads[$activeKey]) ? $threads[$activeKey] : [];

// Pagination config
$threadsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$threadKeys = array_keys($threads);
$totalThreads = count($threadKeys);
$start = ($page - 1) * $threadsPerPage;
$pageThreadKeys = array_slice($threadKeys, $start, $threadsPerPage);

// Set $error from GET if present
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}
// Set $mail_success from session if present
if (isset($_SESSION['mail_success'])) {
    $mail_success = $_SESSION['mail_success'];
    unset($_SESSION['mail_success']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mail • qOverflow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/showdown/dist/showdown.min.js"></script>
  <style>
    body {
      font-family: 'Inter', sans-serif;
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
    }
  </style>
</head>
<body class="text-white">
<?php include __DIR__ . '/../../components/navBarLogin.php'; ?>

<div class="flex flex-col md:flex-row h-[calc(100vh-4rem)] px-4 md:px-6 pt-4 md:pt-6 gap-4 md:gap-6 overflow-hidden">
  <!-- Sidebar Threads -->
  <aside class="w-72 bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-700 shadow-md overflow-y-auto relative">
    <a href="mail.php?compose=1" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 mb-4 rounded-md text-sm font-medium shadow text-center">+ Compose</a>
    <input type="text" id="thread-search" placeholder="Search by user or subject..." class="mb-3 w-full p-2 rounded bg-gray-700 border border-gray-600 text-white text-sm" autocomplete="off">
    <?php if (empty($threads)): ?>
      <div class="text-gray-400 text-sm text-center mt-4 px-2 space-y-2">
        <p>No conversations yet.</p>
        <p class="bg-gray-700 border border-gray-600 p-3 rounded text-xs text-left">
          📬 Messages you send will appear in your inbox after the recipient replies.
        </p>
      </div>
    <?php endif; ?>
    <div id="thread-list">
    <?php foreach ($pageThreadKeys as $key): $msgs = $threads[$key]; ?>
      <?php
        $first = $msgs[0];
        $peer = $first['sender'] === $appUser ? $first['receiver'] : $first['sender'];
        $isActive = $key === $activeKey;
        $baseClass = 'block rounded-lg p-3 mb-2 border transition';
        $classes = $isActive ? 'bg-blue-700 border-blue-500' : 'bg-gray-700 hover:bg-gray-600 border-gray-600';
        $last = end($msgs);
        $lastSender = $last['sender'] === $appUser ? 'You' : htmlspecialchars($last['sender']);
        $lastText = trim(explode("\n", $last['text'])[0]);
        if ($lastText === '') $lastText = mb_strimwidth($last['text'], 0, 40, '...');
        $lastPreview = "<span class='font-bold text-blue-300'>" . $lastSender . "</span>: " . htmlspecialchars($lastText);
      ?>
      <div class="relative group thread-row" data-thread-key="<?= htmlspecialchars($key) ?>" data-peer="<?= htmlspecialchars($peer) ?>">
        <a href="?thread=<?= urlencode($key) ?>" class="<?= $baseClass . ' ' . $classes ?>">
          <h4 class="font-semibold text-white text-sm truncate flex items-center mb-1">
            <?= htmlspecialchars($first['subject']) ?>
          </h4>
          <div class="text-xs text-gray-100 preview-block truncate" data-md="<?= htmlspecialchars($last['text']) ?>">
            <?php echo $lastPreview; ?>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    </div>
    <!-- Pagination controls -->
    <div class="flex justify-between items-center mt-2">
      <form method="GET" class="inline">
        <?php if ($page > 1): ?>
          <input type="hidden" name="page" value="<?= $page - 1 ?>">
          <?php if (isset($_GET['thread'])): ?><input type="hidden" name="thread" value="<?= htmlspecialchars($_GET['thread']) ?>"><?php endif; ?>
          <button type="submit" class="px-3 py-1 rounded bg-gray-700 text-white text-xs hover:bg-gray-600">Previous</button>
        <?php endif; ?>
      </form>
      <span class="text-xs text-gray-400">Page <?= $page ?></span>
      <form method="GET" class="inline">
        <?php
          $currentPageThreadCount = count($pageThreadKeys);
          $hasMoreThreads = ($start + $currentPageThreadCount) < $totalThreads;
        ?>
        <?php if ($hasMoreThreads): ?>
          <input type="hidden" name="page" value="<?= $page + 1 ?>">
          <?php if (isset($_GET['thread'])): ?><input type="hidden" name="thread" value="<?= htmlspecialchars($_GET['thread']) ?>"><?php endif; ?>
          <button type="submit" class="px-3 py-1 rounded bg-gray-700 text-white text-xs hover:bg-gray-600">Next</button>
        <?php endif; ?>
      </form>
    </div>
  </aside>

  <!-- Thread view or Compose -->
  <main class="flex-1 bg-gray-800 rounded-xl p-6 flex flex-col border border-gray-700 shadow-md overflow-hidden">
    <?php if (isset($mail_success)): ?>
      <div class="bg-green-600 text-white text-sm py-2 text-center mb-4"><?= htmlspecialchars($mail_success) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="bg-red-600 text-white text-sm py-2 text-center mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!$showCompose): ?>
      <!-- Message History -->
      <div id="thread-messages" class="flex-1 overflow-y-auto space-y-4 mb-4">
        <?php foreach ($activeThread as $i => $msg): ?>
          <?php $isMine = $msg['sender'] === $appUser; ?>
          <div class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
            <div class="<?= $isMine ? 'bg-blue-600 text-white' : 'bg-gray-700 text-white' ?> max-w-[75%] p-4 rounded-lg border border-gray-600 thread-message" data-msg-index="<?= $i ?>">
              <div class="text-xs text-gray-300 mb-1">
                <?= $isMine ? 'You → ' . htmlspecialchars($msg['receiver']) : htmlspecialchars($msg['sender']) . ' → You' ?>
              </div>
              <div class="message-body text-sm" data-md="<?= htmlspecialchars($msg['text']) ?>"></div>
              <div class="text-[11px] text-gray-400 mt-1 text-right"><?= date('n/j g:ia', intval($msg['createdAt'] / 1000)) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Reply Form -->
      <form method="POST" class="border-t border-gray-700 pt-4">
        <input type="hidden" name="recipient" value="<?= htmlspecialchars($activeThread[0]['sender'] === $appUser ? $activeThread[0]['receiver'] : $activeThread[0]['sender']) ?>">
        <input type="hidden" name="subject" value="<?= htmlspecialchars($activeThread[0]['subject']) ?>">
        <input type="hidden" name="root_timestamp" value="<?= htmlspecialchars($activeThread[0]['createdAt']) ?>">
        <input type="hidden" name="current_thread_key" value="<?= htmlspecialchars($activeKey) ?>">
        <label class="text-sm font-medium mb-3 ml-1 block">Reply to Thread:</label>
        <textarea name="body" id="reply-body" rows="3" maxlength="150" required class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none mb-4" placeholder="Type your reply..."></textarea>
        <div class="flex justify-end">
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium shadow">Send Reply</button>
        </div>
      </form>

    <?php else: ?>
      <!-- Compose New Message -->
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
          <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md text-sm font-medium shadow">Send</button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</div>

<!-- JS for Markdown  -->
<script>
  const converter = new showdown.Converter({
    simpleLineBreaks: true,
    openLinksInNewWindow: true,
    emoji: true
  });

  // Function to determine if raw text has multiple blank lines
  function isPreFormatted(raw) {
    return raw.includes('\n\n\n') || raw.includes('\n \n'); // triple or spaced blank lines
  }

  // Unified function to render markdown or preformatted text
  function renderMarkdown(raw) {
    if (isPreFormatted(raw)) {
      return '<pre class="whitespace-pre-wrap font-mono text-sm">' + raw + '</pre>';
    } else {
      return converter.makeHtml(raw);
    }
  }

  // Render messages (in thread view and sidebar previews)
  document.querySelectorAll('.message-body').forEach(el => {
    const raw = el.getAttribute('data-md') || '';
    el.innerHTML = renderMarkdown(raw);
  });

  // Live preview for Compose (only if present)
  const textarea = document.getElementById('body');
  const preview = document.getElementById('preview');
  if (textarea && preview) {
    textarea.addEventListener('input', () => {
      const raw = textarea.value;
      preview.innerHTML = renderMarkdown(raw);
    });
    preview.innerHTML = renderMarkdown(textarea.value);
  }

  // Always scroll to the latest message after page load
  window.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
      var threadBox = document.getElementById('thread-messages');
      if (threadBox) {
        var msgs = threadBox.querySelectorAll('.thread-message');
        if (msgs.length > 0) {
          msgs[msgs.length - 1].scrollIntoView({ behavior: 'smooth' });
          // Double-check after another short delay in case layout shifts
          setTimeout(function() {
            msgs[msgs.length - 1].scrollIntoView({ behavior: 'smooth' });
          }, 200);
        }
      }
    }, 300);
  });

// Search bar logic
const searchInput = document.getElementById('thread-search');
if (searchInput) {
  searchInput.style.display = '';
  searchInput.addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    document.querySelectorAll('.thread-row').forEach(row => {
      const subject = row.querySelector('h4').textContent.toLowerCase();
      const preview = row.querySelector('.preview-block').textContent.toLowerCase();
      const peer = row.getAttribute('data-peer').toLowerCase();
      if (subject.includes(q) || preview.includes(q) || peer.includes(q)) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  });
} 
</script>
</body>
</html>