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

// Handle sending a new message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
  <script src="https://cdn.jsdelivr.net/npm/showdown/dist/showdown.min.js"></script>
  <style>
    body { font-family: 'Inter', sans-serif; background: radial-gradient(ellipse at top, #0f172a, #0b1120); }
  </style>
</head>
<body class="text-white min-h-screen flex flex-col">
<?php include __DIR__ . '/../../components/navBarLogin.php'; ?>

<div class="flex-1 flex items-center justify-center py-10 px-2">
  <div class="w-full max-w-2xl bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 flex flex-col">
    <!-- Tabs -->
    <div class="flex justify-center gap-2 mt-6 mb-2">
      <a href="mail_minimal.php" class="px-5 py-2 rounded-md text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-500
        <?= $activeTab === 'inbox' ? 'bg-blue-600 text-white shadow' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">Inbox</a>
      <a href="mail_minimal.php?compose=1" class="px-5 py-2 rounded-md text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-blue-500
        <?= $activeTab === 'compose' ? 'bg-blue-600 text-white shadow' : 'bg-gray-700 text-gray-300 hover:bg-gray-600' ?>">Compose</a>
    </div>
    <div class="px-6 pb-8 pt-2 flex-1 flex flex-col">
      <?php if ($activeTab === 'compose'): ?>
        <?php if (isset($mail_success)): ?>
          <div class="bg-green-600 text-white text-base py-2 px-4 text-center mb-4 rounded shadow">Message sent successfully!</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
          <div class="bg-red-600 text-white text-base py-2 px-4 text-center mb-4 rounded shadow"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
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
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-7 py-2 rounded-lg text-base font-semibold shadow transition">Send</button>
          </div>
        </form>
      <?php else: ?>
        <div class="mb-2">
          <input type="text" id="inbox-search" placeholder="Search by sender or subject..." class="w-full p-2 rounded bg-gray-700 border border-gray-600 text-white text-sm focus:ring-2 focus:ring-blue-500" autocomplete="off">
        </div>
        <div class="mb-2 text-xs text-gray-400">Note: Only the first line of each message is shown here.</div>
        <div id="inbox-list" class="flex flex-col gap-2"></div>
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
  const converter = new showdown.Converter({
    simpleLineBreaks: true,
    openLinksInNewWindow: true,
    emoji: true
  });

  // Inbox pagination and search
  const inboxMessages = <?php echo json_encode($inboxMessages); ?>;
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
      div.className = 'bg-gray-700 rounded-lg p-3 border border-gray-600 shadow hover:border-blue-500 transition flex flex-col gap-1 relative';
      // Get only the first line or up to 60 chars
      let firstLine = (msg.text || '').split(/\r?\n/)[0];
      if (firstLine.length > 60) firstLine = firstLine.slice(0, 57) + '...';
      // Escape HTML for preview
      const previewLine = escapeHtml(firstLine);
      // Unique id for expand/collapse
      const msgId = `msg-${start + idx}`;
      div.innerHTML = `
        <div class=\"font-semibold text-white text-base mb-1 truncate\">${escapeHtml(msg.subject)}</div>
        <div class=\"flex items-baseline gap-2\">
          <span class=\"font-bold text-blue-300 text-sm whitespace-nowrap\">${escapeHtml(msg.sender)}:</span>
          <span class=\"text-sm text-gray-200 whitespace-pre-line\" id=\"${msgId}-preview\">${previewLine}</span>
        </div>
        <button class=\"text-xs text-blue-400 hover:underline focus:outline-none mt-1 mb-6 self-start\" id=\"${msgId}-toggle\">View Full Message</button>
        <div class=\"hidden mt-1 mb-6\" id=\"${msgId}-full\"></div>
        <div class=\"absolute bottom-2 right-3 text-xs text-gray-400\">${formatDate(msg.createdAt)}</div>
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
          // Render full message (preserve whitespace)
          if (/\n\s*\n/.test(msg.text || '')) {
            fullDiv.innerHTML = `<pre class='whitespace-pre-wrap font-mono text-sm text-gray-200'>${escapeHtml(msg.text || '')}</pre>`;
          } else {
            fullDiv.innerHTML = `<span class='text-sm message-body text-gray-200 whitespace-pre-line'>${converter.makeHtml(msg.text || '')}</span>`;
          }
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
      const raw = el.getAttribute('data-md') || '';
      el.innerHTML = converter.makeHtml(raw);
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

  // Live preview for Compose (only if present)
  const textarea = document.getElementById('body');
  const previewCard = document.getElementById('preview-card');
  const composeUsername = document.getElementById('compose-username');
  const composeSubject = document.getElementById('compose-subject');
  function renderPreviewCard() {
    if (!previewCard) return;
    const username = composeUsername && composeUsername.value.trim() ? composeUsername.value.trim() : 'You';
    const subject = composeSubject && composeSubject.value.trim() ? composeSubject.value.trim() : '(No Subject)';
    const raw = textarea ? textarea.value : '';
    // If message has multiple blank lines, use <pre> for whitespace preservation
    let messageHtml;
    if (/\n\s*\n/.test(raw)) {
      messageHtml = `<pre class='whitespace-pre-wrap font-mono text-sm text-gray-200'>${escapeHtml(raw)}</pre>`;
    } else {
      messageHtml = `<span class='text-sm message-body text-gray-200 whitespace-pre-line'>${converter.makeHtml(raw)}</span>`;
    }
    previewCard.innerHTML = `
      <div class='bg-gray-700 rounded-lg p-3 border border-gray-600 shadow flex flex-col gap-1 relative min-h-[90px]'>
        <div class='font-semibold text-white text-base mb-1 truncate'>${escapeHtml(subject)}</div>
        <div class='flex items-baseline gap-2 mb-6'>
          <span class='font-bold text-blue-300 text-sm whitespace-nowrap'>${escapeHtml(username)}:</span>
          ${messageHtml}
        </div>
        <div class='absolute bottom-2 right-3 text-xs text-gray-400'>Preview</div>
      </div>
    `;
  }
  if (textarea && previewCard) {
    textarea.addEventListener('input', renderPreviewCard);
    if (composeUsername) composeUsername.addEventListener('input', renderPreviewCard);
    if (composeSubject) composeSubject.addEventListener('input', renderPreviewCard);
    renderPreviewCard();
  }
</script>
</body>
</html> 