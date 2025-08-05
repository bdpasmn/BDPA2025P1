<?php
session_start();
require_once __DIR__ . '/../../Api/api.php';
require_once __DIR__ . '/../../Api/key.php';

$appUser = $_SESSION['username'] ?? null;
if (!$appUser) {
    header("Location: ../auth/login.php");
    exit;
}

$api = new qOverflowAPI(API_KEY);

// Handle AJAX requests for marking messages as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'markAsRead') {
        $messageId = $input['messageId'] ?? null;
        
        if ($messageId) {
            try {
                require_once __DIR__ . '/../../db.php';
                $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                
                // Use PostgreSQL's ON CONFLICT instead of MySQL's ON DUPLICATE KEY UPDATE
                $stmt = $pdo->prepare("INSERT INTO mail_status (message_id, recipient, is_read, read_at) 
                                      VALUES (?, ?, TRUE, NOW()) 
                                      ON CONFLICT (message_id, recipient) 
                                      DO UPDATE SET is_read = TRUE, read_at = NOW()");
                $stmt->execute([$messageId, $appUser]);
                
                // Debug: Log the insert/update
                error_log("Marked message as read: $messageId for user: $appUser");
                
                echo json_encode(['success' => true]);
                exit;
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
                exit;
            }
        }
    }
}

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['CONTENT_TYPE'])) {
    $recipient = trim($_POST['recipient'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($recipient && $subject && $body) {
        if ($recipient === $appUser) {
            $error = "You cannot send messages to yourself.";
        } else {
            $userCheck = $api->getUser($recipient);
            if (isset($userCheck['error'])) {
                $error = "User doesn't exist.";
            } else {
                $resp = $api->sendMail($appUser, $recipient, $subject, $body);
                if (!isset($resp['error'])) {
                    $mail_success = 'Message sent successfully!';
                } else {
                    $error = "Failed to send message: " . htmlspecialchars($resp['error']);
                }
            }
        }
    } else {
        $error = "All fields are required.";
    }
}

// Get inbox messages (where user is receiver)
$inboxMessages = $api->getMail($appUser)['messages'] ?? [];

// Get read status from local database - unread if not in system, read if in system
$readStatus = [];
try {
    require_once __DIR__ . '/../../db.php';
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    foreach ($inboxMessages as $msg) {
        // Create a unique message identifier using sender + subject + createdAt
        $messageId = $msg['sender'] . '|' . $msg['subject'] . '|' . $msg['createdAt'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mail_status WHERE message_id = ? AND recipient = ?");
        $stmt->execute([$messageId, $appUser]);
        
        // If message exists in mail_status table = read, if not = unread
        $readStatus[$messageId] = $stmt->fetchColumn() > 0;
        
        // Debug: Log what we're checking
        error_log("Checking message: $messageId for user: $appUser - Read: " . ($readStatus[$messageId] ? 'true' : 'false'));
    }
} catch (PDOException $e) {
    // If database fails, assume all messages are unread
    foreach ($inboxMessages as $msg) {
        $messageId = $msg['sender'] . '|' . $msg['subject'] . '|' . $msg['createdAt'];
        $readStatus[$messageId] = false;
    }
}

// Determine active tab
$activeTab = isset($_GET['compose']) && $_GET['compose'] == '1' ? 'compose' : 'inbox';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mail • qOverflow</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <style>
    body { font-family: 'Inter', sans-serif; background: radial-gradient(ellipse at top, #0f172a, #0b1120); }
    .message-body ul {
      list-style-type: disc;
      margin-left: 1.5em;
      padding-left: 0;
    }
    .message-body ol {
      list-style-type: decimal;
      margin-left: 1.5em;
      padding-left: 0;
    }
    .message-body hr {
      border: none;
      border-top: 1px solid #4b5563;
      margin: 1em 0;
    }
    .message-body p {
      margin: 0.5em 0;
    }
    .message-body strong {
      font-weight: bold;
    }
    .message-body em {
      font-style: italic;
    }
    #inbox-list .message-body {
      word-wrap: break-word;
      overflow-wrap: break-word;
      white-space: normal;
    }
  </style>
</head>
<body class="text-white min-h-screen flex flex-col">
<?php include __DIR__ . '/../../components/navBarLogin.php'; ?>

<div class="flex-1 flex items-center justify-center py-10 px-2">
  <div class="w-full max-w-2xl bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 flex flex-col">
    <!-- Tabs -->
    <div class="flex justify-center gap-2 mt-6 mb-2">
      <a href="mail.php" class="px-5 py-2 rounded-md text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-500
        <?= $activeTab === 'inbox' ? 'bg-blue-600 text-white shadow' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">Inbox</a>
      <a href="mail.php?compose=1" class="px-5 py-2 rounded-md text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-500
        <?= $activeTab === 'compose' ? 'bg-blue-600 text-white shadow' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">Compose</a>
    </div>
    <div class="px-6 pb-8 pt-2 flex-1 flex flex-col">
      <?php if ($activeTab === 'compose'): ?>
        <?php if (isset($mail_success)): ?>
          <div class="bg-green-600 text-white text-base py-2 px-4 text-center mb-4 rounded shadow auto-hide-banner">Message sent successfully!</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
          <div class="bg-red-600 text-white text-base py-2 px-4 text-center mb-4 rounded shadow auto-hide-banner"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <script>
          setTimeout(() => {
            document.querySelectorAll('.auto-hide-banner').forEach(el => {
              el.style.display = 'none';
            });
          }, 2000);
        </script>
        <form method="POST" class="flex flex-col gap-5 w-full max-w-lg mx-auto">
          <h2 class="text-white font-bold text-2xl mb-2 text-center">Compose Message</h2>
          <div>
            <label class="text-sm font-medium">To (username):</label>
            <input type="text" name="recipient" maxlength="100" required class="mt-1 w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white focus:ring-2 focus:ring-blue-500" id="compose-username">
          </div>
          <div>
            <label class="text-sm font-medium">Subject:</label>
            <input type="text" name="subject" maxlength="75" required class="mt-1 w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white focus:ring-2 focus:ring-blue-500" id="compose-subject">
          </div>
          <div>
            <label class="text-sm font-medium">Message:</label>
            <textarea name="body" id="body" rows="5" maxlength="150" required class="mt-1 w-full p-3 bg-gray-700 border border-gray-600 rounded-md text-white focus:ring-2 focus:ring-blue-500"></textarea>
          </div>
          <div>
            <label class="text-sm font-medium">Live Preview:</label>
            <div id="preview-card" class="mt-2"></div>
          </div>
                      <div class="flex justify-end">
              <button type="submit" id="send-button" class="bg-blue-600 hover:bg-blue-700 text-white px-7 py-2 rounded-lg text-base font-semibold shadow transition">
                <span id="send-text">Send</span>
                <span id="send-spinner" class="hidden">
                  <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></div>
                </span>
              </button>
            </div>
        </form>
      <?php else: ?>
        <div class="mb-2">
          <input type="text" id="inbox-search" placeholder="Search by sender or subject..." class="w-full p-2 rounded bg-gray-700 border border-gray-600 text-white text-sm focus:ring-2 focus:ring-blue-500" autocomplete="off">
        </div>
        <div class="mb-2 text-xs text-gray-400">Note: Only the first line of each message is shown here.</div>
        <div id="inbox-list" class="flex flex-col gap-1"></div>
        <div class="flex justify-between items-center mt-4" id="pagination-controls" style="display:none;">
          <button id="prev-page" class="px-3 py-1 rounded bg-gray-700 text-white text-xs hover:bg-gray-600">Previous</button>
          <span id="page-info" class="text-xs text-gray-400"></span>
          <button id="next-page" class="px-3 py-1 rounded bg-gray-700 text-white text-xs hover:bg-gray-600">Next</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  marked.setOptions({ breaks: true });
  function convertMarkdown(raw) {
    return marked.parse(raw || '');
  }
  // Preprocess to preserve multiple blank lines
  function preserveBlankLines(text) {
    // Replace 2+ consecutive newlines with \n&nbsp;\n to force a visible blank line
    return text.replace(/\n{2,}/g, '\n&nbsp;\n');
  }

  // Inbox pagination and search
  const inboxMessages = <?php echo json_encode($inboxMessages); ?>;
  const readStatus = <?php echo json_encode($readStatus); ?>;
  
  // Debug: Log the read status to console
  console.log('Read status from server:', readStatus);
  console.log('Inbox messages:', inboxMessages);
  const inboxList = document.getElementById('inbox-list');
  const searchInput = document.getElementById('inbox-search');
  const pageInfo = document.getElementById('page-info');
  const prevBtn = document.getElementById('prev-page');
  const nextBtn = document.getElementById('next-page');
  const paginationControls = document.getElementById('pagination-controls');
  const MESSAGES_PER_PAGE = 10;
  let filtered = inboxMessages.slice();
  let page = 1;

  function renderInbox() {
    if (!inboxList) return;
    inboxList.innerHTML = '';
    const start = (page - 1) * MESSAGES_PER_PAGE;
    const end = start + MESSAGES_PER_PAGE;
    const pageMessages = filtered.slice(start, end);
    pageMessages.forEach((msg, idx) => {
      const div = document.createElement('div');
      const messageId = msg.sender + '|' + msg.subject + '|' + msg.createdAt;
      const isRead = readStatus[messageId] || false;
      div.className = 'bg-gray-700 rounded-lg p-3 border border-gray-600 shadow hover:border-blue-500 transition flex flex-col gap-1 relative';
      // Get only the first line or up to 60 chars
      let firstLine = (msg.text || '').split(/\r?\n/)[0];
      if (firstLine.length > 60) {
        // Simple truncation - let the markdown renderer handle incomplete formatting
        firstLine = firstLine.slice(0, 57) + '...';
      }
      // Convert markdown for preview (safe since we're using innerHTML)
      const previewLine = convertMarkdown(firstLine);
      // Unique id for expand/collapse
      const msgId = `msg-${start + idx}`;
      div.innerHTML = `
        <div class=\"font-semibold text-white text-base mb-1 truncate\">${escapeHtml(msg.subject)}</div>
        <div class=\"flex items-baseline gap-2\">
          <span class=\"font-bold text-blue-300 text-sm whitespace-nowrap\">${escapeHtml(msg.sender)}:</span>
          <div class=\"text-sm text-gray-200 message-body flex-1 min-w-0\" id=\"${msgId}-preview\">${previewLine}</div>
        </div>
        <button class=\"text-xs text-blue-400 hover:underline focus:outline-none mt-1 mb-2 self-start\" id=\"${msgId}-toggle\">View Full Message</button>
        <div class=\"hidden mt-1 mb-2\" id=\"${msgId}-full\"></div>
        <div class=\"absolute bottom-2 right-3 text-xs text-gray-400\">${formatDate(msg.createdAt)}</div>
        <div class=\"absolute top-2 right-3 text-xs ${isRead ? 'text-gray-400' : 'text-blue-400 font-semibold'}\">${isRead ? 'Read' : 'Unread'}</div>
      `;
      inboxList.appendChild(div);
      // Add expand/collapse logic
      const toggleBtn = div.querySelector(`#${msgId}-toggle`);
      const fullDiv = div.querySelector(`#${msgId}-full`);
      let expanded = false;
      toggleBtn.addEventListener('click', function() {
        // Collapse all other expanded messages
        document.querySelectorAll('[id$="-full"]').forEach(el => { el.classList.add('hidden'); });
        document.querySelectorAll('[id$="-toggle"]').forEach(el => { el.textContent = 'View Full Message'; });
        if (!expanded) {
          // Update local read status immediately
          readStatus[messageId] = true;
          
          // Update the read/unread text
          const statusDiv = div.querySelector('.absolute.top-2.right-3');
          if (statusDiv) {
            statusDiv.textContent = 'Read';
            statusDiv.className = 'absolute top-2 right-3 text-xs text-gray-400';
          }
          
          // Mark as read in database (non-blocking)
          fetch('/pages/mail/mail.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
              action: 'markAsRead',
              messageId: messageId 
            })
          })
          .then(response => response.json())
          .then(data => {
            if (data.error) {
              console.error('Error marking as read:', data.error);
            }
          })
          .catch(error => {
            console.error('Error marking as read:', error);
          });
          
          // Render full message with markdown support
          fullDiv.innerHTML = `<div class='text-sm message-body text-gray-200'>${convertMarkdown(msg.text || '')}</div>`;
          fullDiv.classList.remove('hidden');
          toggleBtn.textContent = 'Hide Full Message';
          expanded = true;
        } else {
          fullDiv.classList.add('hidden');
          toggleBtn.textContent = 'View Full Message';
          expanded = false;
        }
      });
    });
    // Render markdown for message bodies
    inboxList.querySelectorAll('.message-body').forEach(el => {
      // No need to re-render, already rendered with convertMarkdown
    });
    // Pagination controls
    if (paginationControls) {
      paginationControls.style.display = filtered.length > MESSAGES_PER_PAGE ? '' : 'none';
      if (pageInfo) pageInfo.textContent = `Page ${page} of ${Math.max(1, Math.ceil(filtered.length / MESSAGES_PER_PAGE))}`;
      if (prevBtn) prevBtn.disabled = page <= 1;
      if (nextBtn) nextBtn.disabled = page >= Math.ceil(filtered.length / MESSAGES_PER_PAGE);
    }
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>\"]/g, function (m) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'})[m];
    });
  }
  function formatDate(ts) {
    const d = new Date(parseInt(ts));
    return `${d.getMonth()+1}/${d.getDate()} ${d.getHours()%12||12}:${d.getMinutes().toString().padStart(2,'0')}${d.getHours()<12?'am':'pm'}`;
  }

  if (searchInput) {
    searchInput.addEventListener('input', function() {
      const q = this.value.trim().toLowerCase();
      filtered = inboxMessages.filter(msg =>
        msg.sender.toLowerCase().includes(q) ||
        msg.subject.toLowerCase().includes(q)
      );
      page = 1;
      renderInbox();
    });
  }
  if (prevBtn) prevBtn.addEventListener('click', function() { if (page > 1) { page--; renderInbox(); } });
  if (nextBtn) nextBtn.addEventListener('click', function() { if (page < Math.ceil(filtered.length / MESSAGES_PER_PAGE)) { page++; renderInbox(); } });

  // Initial render
  if (inboxList) renderInbox();
  
  // Form submission spinner
  const composeForm = document.querySelector('form[method="POST"]');
  if (composeForm) {
    composeForm.addEventListener('submit', function(e) {
      const sendText = document.getElementById('send-text');
      const sendSpinner = document.getElementById('send-spinner');
      const sendButton = document.getElementById('send-button');
      
      if (sendText && sendSpinner && sendButton) {
        sendText.classList.add('hidden');
        sendSpinner.classList.remove('hidden');
        sendButton.disabled = true;
      }
    });
  }

  // Live preview for Compose (only if present)
  const textarea = document.getElementById('body');
  const previewCard = document.getElementById('preview-card');
  const composeUsername = document.getElementById('compose-username');
  const composeSubject = document.getElementById('compose-subject');
  function renderPreviewCard() {
    if (!previewCard) return;
    const username = composeUsername && composeUsername.value.trim() ? composeUsername.value.trim() : '(username)';
    const subject = composeSubject && composeSubject.value.trim() ? composeSubject.value.trim() : '(No Subject)';
    const raw = textarea ? textarea.value : '';
    // Preprocess for blank lines
    const processedRaw = preserveBlankLines(raw);
    previewCard.innerHTML = `
      <div class='bg-gray-700 rounded-lg p-3 border border-gray-600 shadow flex flex-col gap-1 relative min-h-[90px]'>
        <div class='font-semibold text-white text-base mb-1 truncate'>${convertMarkdown(subject)}</div>
        <div class='text-sm message-body text-gray-200 mb-6' style='display:flex; align-items:baseline; gap: 4px;'>
          <span class='font-bold text-blue-300 text-sm' style='flex-shrink: 0; white-space:nowrap;'>${convertMarkdown(username).replace(/<\/?(p|div)[^>]*>/g, '')}<span class='text-blue-300'>:</span></span>
          <div style='flex:1; min-width: 0; word-wrap: break-word; overflow-wrap: break-word; white-space: normal;'>
            ${convertMarkdown(processedRaw)}
          </div>
        </div>
        <div class='absolute bottom-2 right-3 text-xs text-gray-400'>Preview</div>
      </div>
    `;
  }
  if (textarea && previewCard) {
    textarea.addEventListener('input', renderPreviewCard);
    textarea.addEventListener('paste', function() { setTimeout(renderPreviewCard, 0); });
    if (composeUsername) composeUsername.addEventListener('input', renderPreviewCard);
    if (composeSubject) composeSubject.addEventListener('input', renderPreviewCard);
    renderPreviewCard();
  }
</script>
</body>
</html> 