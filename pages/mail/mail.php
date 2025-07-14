<?php
session_start();
require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../db.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['username'];
$api = new qOverflowAPI(API_KEY);

// 1. Get inbox messages (where user is receiver)
$inboxMessages = $api->getMail($user)['messages'] ?? [];

// 2. Build list of peers from inbox (senders)
$peers = [];
foreach ($inboxMessages as $msg) {
    $peer = $msg['sender'];
    if ($peer !== $user && !in_array($peer, $peers)) $peers[] = $peer;
}

// 3. Add all recipients I've ever sent a message to (from database)
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $stmt = $pdo->prepare("SELECT recipient FROM sent_peers WHERE username = :username");
    $stmt->execute(['username' => $user]);
    $sentPeers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sentPeers as $sentPeer) {
        if ($sentPeer !== $user && !in_array($sentPeer, $peers)) $peers[] = $sentPeer;
    }
} catch (Exception $e) {
    // If DB fails, just skip sentPeers
}

// 4. Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = trim($_POST['recipient'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($recipient && $subject && $body) {
        if ($recipient === $user) {
            $error = "You cannot send messages to yourself.";
        } else {
            // Check if recipient user exists
            $userCheck = $api->getUser($recipient);
            if (isset($userCheck['error'])) {
                $error = "User doesn't exist.";
            } else {
                $resp = $api->sendMail($user, $recipient, $subject, $body);
                if (!isset($resp['error'])) {
                    // Add recipient to sent_peers table
                    try {
                        $pdo = new PDO($dsn, $dbUser, $dbPass);
                        $stmt = $pdo->prepare("INSERT INTO sent_peers (username, recipient) VALUES (:username, :recipient) ON CONFLICT DO NOTHING");
                        $stmt->execute(['username' => $user, 'recipient' => $recipient]);
                    } catch (Exception $e) {}
                    // Use createdAt from API response if available, else fallback to current time
                    $createdAt = isset($resp['message']['createdAt']) ? $resp['message']['createdAt'] : round(microtime(true) * 1000);
                    $participants = [$user, $recipient];
                    sort($participants);
                    $base = implode('-', $participants) . '::' . trim($subject) . '::' . $createdAt;
                    $threadKey = hash('sha256', $base);
                    header("Location: mail.php?thread=" . urlencode($threadKey));
                    exit;
                } else {
                    $error = "Failed to send message: " . htmlspecialchars($resp['error']);
                }
            }
        }
    } else {
        $error = "All fields are required.";
    }
}

// 5. For each peer, fetch sent messages from the API (where you are the sender)
$sent = [];
foreach ($peers as $peer) {
    $peerInbox = $api->getMail($peer)['messages'] ?? [];
    foreach ($peerInbox as $msg) {
        if ($msg['sender'] === $user && $msg['receiver'] === $peer) {
            $sent[] = $msg;
        }
    }
}

// 6. Merge and group into threads
$allMessages = array_merge($inboxMessages, $sent);
$threadBuckets = [];
$threadActivity = [];

// Helper: Find or create a unique thread key for each message
function getUniqueThreadKey(&$threadBuckets, $msg, $user) {
    $peer = $msg['sender'] === $user ? $msg['receiver'] : $msg['sender'];
    $subject = $msg['subject'];
    // Only group messages if they have the same sender, receiver, subject, and first message timestamp
    foreach ($threadBuckets as $key => $msgs) {
        $first = $msgs[0];
        $peer1 = $first['sender'] === $user ? $first['receiver'] : $first['sender'];
        if ($peer1 === $peer && $first['subject'] === $subject && $first['createdAt'] === $msg['createdAt']) {
            return $key;
        }
    }
    // Otherwise, create a new thread key using sender, receiver, subject, and this message's timestamp
    $participants = [$user, $peer];
    sort($participants);
    $base = implode('-', $participants) . '::' . trim($subject) . '::' . $msg['createdAt'];
    return hash('sha256', $base);
}

foreach ($allMessages as $msg) {
    if ($msg['sender'] !== $user && $msg['receiver'] !== $user) continue;
    $key = getUniqueThreadKey($threadBuckets, $msg, $user);
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

// Load pinned threads for the current user
$pinnedThreads = [];
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    $stmt = $pdo->prepare("SELECT thread_key FROM pinned_threads WHERE username = :username");
    $stmt->execute(['username' => $user]);
    $pinnedThreads = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Handle pin/unpin actions
if (isset($_POST['pin_thread']) && isset($_POST['thread_key'])) {
    $threadKey = $_POST['thread_key'];
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass);
        if ($_POST['pin_thread'] === '1') {
            $stmt = $pdo->prepare("INSERT INTO pinned_threads (username, thread_key) VALUES (:username, :thread_key) ON CONFLICT DO NOTHING");
            $stmt->execute(['username' => $user, 'thread_key' => $threadKey]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM pinned_threads WHERE username = :username AND thread_key = :thread_key");
            $stmt->execute(['username' => $user, 'thread_key' => $threadKey]);
        }
    } catch (Exception $e) {}
    // Redirect to avoid form resubmission
    header("Location: mail.php?thread=" . urlencode($threadKey));
    exit;
}

// Sort threads: pinned first, then others
$pinned = [];
$unpinned = [];
foreach ($threads as $key => $msgs) {
    if (in_array($key, $pinnedThreads)) {
        $pinned[$key] = $msgs;
    } else {
        $unpinned[$key] = $msgs;
    }
}

// Determine active thread
$activeKey = $_GET['thread'] ?? array_key_first($threads);
$showCompose = isset($_GET['compose']) && $_GET['compose'] == '1';
$activeThread = !$showCompose && $activeKey && isset($threads[$activeKey]) ? $threads[$activeKey] : [];

// Pagination config
$threadsPerPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Paginate pinned and unpinned threads separately
$pinnedKeys = array_keys($pinned);
$unpinnedKeys = array_keys($unpinned);

$pinnedTotal = count($pinnedKeys);
$unpinnedTotal = count($unpinnedKeys);

$pinnedStart = ($page - 1) * $threadsPerPage;
$pinnedPageKeys = array_slice($pinnedKeys, $pinnedStart, $threadsPerPage);
$unpinnedStart = ($page - 1) * $threadsPerPage;
$unpinnedPageKeys = array_slice($unpinnedKeys, $unpinnedStart, $threadsPerPage);
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
    <div id="pin-spinner" class="hidden absolute inset-0 bg-black bg-opacity-40 flex items-center justify-center z-20">
      <svg class="animate-spin h-8 w-8 text-yellow-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
    </div>
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
    <div id="pinned-threads">
    <?php if (!empty($pinned)): ?>
      <div class="mb-2">
        <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Pinned</div>
        <?php foreach ($pinnedPageKeys as $key): $msgs = $pinned[$key]; ?>
          <?php
            $first = $msgs[0];
            $peer = $first['sender'] === $user ? $first['receiver'] : $first['sender'];
            $isActive = $key === $activeKey;
            $baseClass = 'block rounded-lg p-3 mb-2 border transition';
            $classes = $isActive ? 'bg-blue-700 border-blue-500' : 'bg-gray-700 hover:bg-gray-600 border-gray-600';
            $last = end($msgs);
            $lastSender = $last['sender'] === $user ? 'You' : htmlspecialchars($last['sender']);
            $lastText = trim(explode("\n", $last['text'])[0]);
            if ($lastText === '') $lastText = mb_strimwidth($last['text'], 0, 40, '...');
            $lastPreview = "<span class='font-bold text-blue-300'>" . $lastSender . "</span>: " . htmlspecialchars($lastText);
          ?>
          <div class="relative group thread-row" data-thread-key="<?= htmlspecialchars($key) ?>" data-pinned="1" data-peer="<?= htmlspecialchars($peer) ?>">
            <a href="?thread=<?= urlencode($key) ?>" class="<?= $baseClass . ' ' . $classes ?>">
              <h4 class="font-semibold text-white text-sm truncate flex items-center mb-1">
                <?= htmlspecialchars($first['subject']) ?>
                <form method="POST" class="ml-auto inline-block" style="margin-left:auto;" action="mail.php?thread=<?= urlencode($key) ?>" onsubmit="event.stopPropagation();">
                  <input type="hidden" name="thread_key" value="<?= htmlspecialchars($key) ?>">
                  <input type="hidden" name="pin_thread" value="0">
                  <button type="submit" title="Unpin" class="ml-2 text-yellow-400 hover:text-yellow-300 focus:outline-none">
                    ★
                  </button>
                </form>
              </h4>
              <div class="text-xs text-gray-100 preview-block truncate" data-md="<?= htmlspecialchars($last['text']) ?>">
                <?php echo $lastPreview; ?>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      </div>
      <hr class="border-gray-700 mb-2">
    <?php endif; ?>
    <?php foreach ($unpinnedPageKeys as $key): $msgs = $unpinned[$key]; ?>
      <?php
        $first = $msgs[0];
        $peer = $first['sender'] === $user ? $first['receiver'] : $first['sender'];
        $isActive = $key === $activeKey;
        $baseClass = 'block rounded-lg p-3 mb-2 border transition';
        $classes = $isActive ? 'bg-blue-700 border-blue-500' : 'bg-gray-700 hover:bg-gray-600 border-gray-600';
        $last = end($msgs);
        $lastSender = $last['sender'] === $user ? 'You' : htmlspecialchars($last['sender']);
        $lastText = trim(explode("\n", $last['text'])[0]);
        if ($lastText === '') $lastText = mb_strimwidth($last['text'], 0, 40, '...');
        $lastPreview = "<span class='font-bold text-blue-300'>" . $lastSender . "</span>: " . htmlspecialchars($lastText);
      ?>
      <div class="relative group thread-row" data-thread-key="<?= htmlspecialchars($key) ?>" data-pinned="0" data-peer="<?= htmlspecialchars($peer) ?>">
        <a href="?thread=<?= urlencode($key) ?>" class="<?= $baseClass . ' ' . $classes ?>">
          <h4 class="font-semibold text-white text-sm truncate flex items-center mb-1">
            <?= htmlspecialchars($first['subject']) ?>
            <form method="POST" class="ml-auto inline-block" style="margin-left:auto;" action="mail.php?thread=<?= urlencode($key) ?>" onsubmit="event.stopPropagation();">
              <input type="hidden" name="thread_key" value="<?= htmlspecialchars($key) ?>">
              <input type="hidden" name="pin_thread" value="1">
              <button type="submit" title="Pin" class="ml-2 text-gray-400 hover:text-yellow-400 focus:outline-none">
                ☆
              </button>
            </form>
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
          $currentPageThreadCount = count($pinnedPageKeys) + count($unpinnedPageKeys);
          $hasMoreThreads = $currentPageThreadCount >= $threadsPerPage;
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
    <?php if (isset($error)): ?>
      <div class="bg-red-600 text-white text-sm py-2 text-center mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!$showCompose): ?>
      <!-- Message History -->
      <div id="thread-messages" class="flex-1 overflow-y-auto space-y-4 mb-4">
        <?php foreach ($activeThread as $i => $msg): ?>
          <?php $isMine = $msg['sender'] === $user; ?>
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
        <input type="hidden" name="recipient" value="<?= htmlspecialchars($activeThread[0]['sender'] === $user ? $activeThread[0]['receiver'] : $activeThread[0]['sender']) ?>">
        <input type="hidden" name="subject" value="<?= htmlspecialchars($activeThread[0]['subject']) ?>">
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

  // Render messages (in thread view and sidebar previews)
  document.querySelectorAll('.message-body').forEach(el => {
    const raw = el.getAttribute('data-md') || '';
    if (isPreFormatted(raw)) {
      el.innerHTML = '<pre class="whitespace-pre-wrap font-mono text-sm">' + raw + '</pre>';
    } else {
      el.innerHTML = converter.makeHtml(raw);
    }
  });

  // Live preview for Compose (only if present)
  const textarea = document.getElementById('body');
  const preview = document.getElementById('preview');
  if (textarea && preview) {
    textarea.addEventListener('input', () => {
      const raw = textarea.value;
      if (isPreFormatted(raw)) {
        preview.innerHTML = '<pre class="whitespace-pre-wrap font-mono text-sm">' + raw + '</pre>';
      } else {
        preview.innerHTML = converter.makeHtml(raw);
      }
    });
    preview.innerHTML = converter.makeHtml(textarea.value);
  }

// Optimistic UI for pin/unpin with spinner
function optimisticStarToggle(e, btn, isPinned) {
  e.preventDefault();
  e.stopPropagation();
  // Toggle icon instantly
  if (isPinned) {
    btn.innerHTML = '☆';
    btn.classList.remove('text-yellow-400');
    btn.classList.add('text-gray-400');
  } else {
    btn.innerHTML = '★';
    btn.classList.remove('text-gray-400');
    btn.classList.add('text-yellow-400');
  }
  // Show spinner
  document.getElementById('pin-spinner').classList.remove('hidden');
  // Submit the form after a short delay for effect
  setTimeout(function() {
    btn.closest('form').submit();
  }, 0);
}

document.querySelectorAll('form[action^="mail.php"][onsubmit]').forEach(form => {
  form.addEventListener('submit', function(e) {
    const btn = this.querySelector('button[type="submit"]');
    const isPinned = this.querySelector('input[name="pin_thread"]').value === '0';
    optimisticStarToggle(e, btn, isPinned);
  });
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