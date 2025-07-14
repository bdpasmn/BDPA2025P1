<?php
session_start();

require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';
require_once __DIR__ . '/../../db.php'; // Add this near the top after other requires

$api = new qOverflowAPI(API_KEY);

$user = $_SESSION['username'] ?? null;
if (!$user) {
    header('Location: ../auth/login.php');
    exit;
}

$error = '';
$success = '';
$showCompose = true;
$activeKey = $_GET['threadKey'] ?? $_GET['thread'] ?? null;
$composingNew = isset($_GET['compose']) && $_GET['compose'] == '1';

// Get inbox messages (where user is receiver)
$inboxMessages = $api->getMail($user)['messages'] ?? [];

// Build Inbox threads
$inboxThreads = [];
$inboxThreadActivity = [];
foreach ($inboxMessages as $msg) {
    if ($msg['receiver'] !== $user) continue;
    $peer = $msg['sender'];
    $threadKey = getThreadKey($peer, $user, $msg['subject']);
    $inboxThreads[$threadKey][] = $msg;
    $inboxThreadActivity[$threadKey] = max($inboxThreadActivity[$threadKey] ?? 0, $msg['createdAt']);
}
foreach ($inboxThreads as $key => $msgs) {
    usort($msgs, fn($a, $b) => $a['createdAt'] <=> $b['createdAt']);
}
uksort($inboxThreads, function($a, $b) use ($inboxThreadActivity) {
    $aTime = $inboxThreadActivity[$a] ?? 0;
    $bTime = $inboxThreadActivity[$b] ?? 0;
    return $bTime <=> $aTime;
});

// Build Sent threads
$sentThreads = [];
$sentThreadActivity = [];
// Build list of peers from inbox (senders)
$peers = [];
foreach ($inboxMessages as $msg) {
    $peer = $msg['sender'];
    if ($peer !== $user && !in_array($peer, $peers)) $peers[] = $peer;
}
// Add all recipients I've ever sent a message to (from database)
try {
    $pdo = new PDO($dsn, $user, $pass);
    $stmt = $pdo->prepare("SELECT recipient FROM sent_peers WHERE username = :username");
    $stmt->execute(['username' => $user]);
    $sentPeers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sentPeers as $sentPeer) {
        if ($sentPeer !== $user && !in_array($sentPeer, $peers)) $peers[] = $sentPeer;
    }
} catch (Exception $e) {
    // If DB fails, just skip sentPeers
}

// When I send a message, add the recipient to the sent_peers table
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = trim($_POST['recipient'] ?? '');
    if ($recipient && $user) {
        try {
            $pdo = new PDO($dsn, $user, $pass);
            $stmt = $pdo->prepare("INSERT INTO sent_peers (username, recipient) VALUES (:username, :recipient) ON CONFLICT DO NOTHING");
            $stmt->execute(['username' => $user, 'recipient' => $recipient]);
        } catch (Exception $e) {
            // Ignore DB errors for now
        }
    }
}

foreach ($peers as $peer) {
    $peerInbox = $api->getMail($peer)['messages'] ?? [];
    foreach ($peerInbox as $msg) {
        if ($msg['sender'] === $user && $msg['receiver'] === $peer) {
            $threadKey = getThreadKey($user, $peer, $msg['subject']);
            $sentThreads[$threadKey][] = $msg;
            $sentThreadActivity[$threadKey] = max($sentThreadActivity[$threadKey] ?? 0, $msg['createdAt']);
        }
    }
}
foreach ($sentThreads as $key => $msgs) {
    usort($msgs, fn($a, $b) => $a['createdAt'] <=> $b['createdAt']);
}
uksort($sentThreads, function($a, $b) use ($sentThreadActivity) {
    $aTime = $sentThreadActivity[$a] ?? 0;
    $bTime = $sentThreadActivity[$b] ?? 0;
    return $bTime <=> $aTime;
});

// Thread Key Helper
function getThreadKey($user1, $user2, $subject) {
    $participants = [$user1, $user2];
    sort($participants);
    $base = implode('-', $participants) . '::' . strtolower(trim($subject));
    return hash('sha256', $base);
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $recipient = trim($_POST['recipient'] ?? '');

    if ($recipient === $user) {
        $error = "You can't send messages to yourself.";
    } elseif (!$recipient || !$subject || !$body) {
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
            $sentMsg = $resp['message'] ?? null;
            // Add recipient to sent_peers table (already handled above)
            $threadKey = getThreadKey($user, $recipient, $subject);
            if ($composingNew) {
                header("Location: mail.php?compose=1&success=1");
                exit;
            } else {
                header("Location: mail.php?thread=" . urlencode($threadKey) . "&success=1&threadKey=" . urlencode($threadKey) . "#scroll");
                exit;
            }
        }
    }
    // Always add recipient to $peers if not already present (for new conversations)
    if (!in_array($recipient, $peers)) {
        $peers[] = $recipient;
    }
}

// Pull sent messages from user's own outbox 
$sent = [];
foreach ($peers as $peer) { // Use $peers from the new code
    $peerInbox = $api->getMail($peer)['messages'] ?? [];
    foreach ($peerInbox as $msg) {
        if ($msg['sender'] === $user && $msg['receiver'] === $peer) {
            $sent[] = $msg;
        }
    }
}
if (isset($sentMsg)) {
    $sent[] = $sentMsg;
}

// Merge and group into threads
$allMessages = array_merge($inboxMessages, $sent); 
$threadBuckets = []; 
$threadActivity = [];
foreach ($allMessages as $msg) {
    if ($msg['sender'] !== $user && $msg['receiver'] !== $user) continue;
    $peer = $msg['sender'] === $user ? $msg['receiver'] : $msg['sender'];
    $threadKey = getThreadKey($user, $peer, $msg['subject']);
    $threadBuckets[$threadKey][] = $msg;
    $threadActivity[$threadKey] = max($threadActivity[$threadKey] ?? 0, $msg['createdAt']);
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

$activeThread = $activeKey && isset($threads[$activeKey]) ? $threads[$activeKey] : [];
$showCompose = !$activeKey || $composingNew;

$replyTo = $activeThread[0] ?? null;
$recipient = $replyTo ? ($replyTo['sender'] === $user ? $replyTo['receiver'] : $replyTo['sender']) : '';
$subject = $replyTo['subject'] ?? '';
$recipientExists = $recipient ? !isset($api->getUser($recipient)['error']) : true;

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = 'Message sent successfully!';
}

if (!empty($activeThread)) {
    echo '<script>window.onload = function() {
        setTimeout(function() {
            var threadBox = document.getElementById("thread-messages");
            if (threadBox) {
                var msgs = threadBox.querySelectorAll(".thread-message");
                if (msgs.length > 0) {
                    msgs[msgs.length - 1].scrollIntoView({ behavior: "smooth" });
                    // Double-check after another short delay in case layout shifts
                    setTimeout(function() {
                        msgs[msgs.length - 1].scrollIntoView({ behavior: "smooth" });
                    }, 200);
                }
            }
        }, 300);
    };</script>';
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


  <?php
    if ($error) {
      echo '<div class="bg-red-600 text-white text-sm py-2 text-center">' . $error . '</div>';
    } elseif (isset($_GET['sent']) && $_GET['sent'] == 1) {
      echo '<div class="bg-green-600 text-white text-sm py-2 text-center">Message sent! The conversation will appear in your inbox after the recipient replies.</div>';
    } elseif ($success) {
      echo '<div class="bg-green-600 text-white text-sm py-2 text-center">' . $success . '</div>';
    }
  ?>


<div class="flex flex-col md:flex-row h-[calc(100vh-4rem)] px-4 md:px-6 pt-4 md:pt-6 gap-4 md:gap-6 overflow-hidden">
  <!-- Sidebar Threads -->
  <aside class="w-72 bg-gray-800 rounded-xl p-4 flex flex-col border border-gray-700 shadow-md overflow-y-auto">
    <a href="mail.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 mb-4 rounded-md text-sm font-medium shadow text-center">+ Compose</a>
    <?php if (empty($threads)): ?>
      <div class="text-gray-400 text-sm text-center mt-4 px-2 space-y-2">
        <p>No conversations yet.</p>
        <p class="bg-gray-700 border border-gray-600 p-3 rounded text-xs text-left">
          📬 Messages you send will appear in your inbox after the recipient replies.
        </p>
      </div>
    <?php endif; ?>
    <?php foreach ($threads as $key => $msgs): ?>
      <?php
        $first = $msgs[0];
        $peer = $first['sender'] === $user ? $first['receiver'] : $first['sender'];
        $isActive = $key === $activeKey;
        $baseClass = 'block rounded-lg p-3 mb-2 border transition';
        $classes = $isActive ? 'bg-blue-700 border-blue-500' : 'bg-gray-700 hover:bg-gray-600 border-gray-600';
        $last = end($msgs);
        $lastSender = $last['sender'] === $user ? 'You' : htmlspecialchars($last['sender']);
        $lastText = trim(explode("\n", $last['text'])[0]); // first line only
        if ($lastText === '') $lastText = mb_strimwidth($last['text'], 0, 40, '...');
        $lastPreview = "<span class='font-bold text-blue-300'>" . $lastSender . "</span>: " . htmlspecialchars($lastText);
      ?>
      <div class="relative">
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
  </aside>

  <!-- Thread view or Compose -->
  <main class="flex-1 bg-gray-800 rounded-xl p-6 flex flex-col border border-gray-700 shadow-md overflow-hidden">
    <?php if (!$showCompose): ?>
      <?php if (!$recipientExists): ?>
        <div class="bg-yellow-600 text-white text-sm px-4 py-2 mb-4 rounded">
          ⚠️ The user "<strong><?= htmlspecialchars($recipient) ?></strong>" no longer exists.
        </div>
      <?php endif; ?>

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
      <form method="POST" class="border-t border-gray-700 pt-4" <?= !$recipientExists ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
        <input type="hidden" name="thread" value="<?= htmlspecialchars($activeKey) ?>">
        <input type="hidden" name="recipient" value="<?= htmlspecialchars($recipient) ?>">
        <input type="hidden" name="subject" value="<?= htmlspecialchars($subject) ?>">
        <label class="text-sm font-medium mb-3 ml-1 block">Reply to Thread:</label>
        <textarea name="body" rows="3" maxlength="150" required class="w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none mb-4" placeholder="Type your reply..."></textarea>
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

  // Live preview for Compose
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
</script>

</body>
</html>