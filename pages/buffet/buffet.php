<?php
session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

$api = new qOverflowAPI(API_KEY);
$_SESSION['username'] = 'user7';

$sort = $_GET['sort'] ?? 'recent';
$currentPage = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$maxItems = 100;

$match = [];
$params = ['limit' => $perPage];

switch ($sort) {
    case 'best':
        $params['sort'] = 'u';
        break;
    case 'interesting':
        $params['sort'] = 'uvc';
        $match['answers'] = 0;
        break;
    case 'hottest':
        $params['sort'] = 'uvac';
        $match['hasAcceptedAnswer'] = false;
        break;
    case 'recent':
    default:
        $sort = 'recent';
        break;
}

if (!empty($match)) {
    $params['match'] = json_encode($match);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['title'], $_POST['text'])) {
    if (!isset($_SESSION['username'])) {
        header("Location: /login.php");
        exit;
    }
    $creator = $_SESSION['username'];
    $title = $_POST['title'];
    $text = $_POST['text'];
    $api->createQuestion($creator, $title, $text);
    header("Location: buffet.php?sort=$sort&page=$currentPage");
    exit;
}

$all = [];
$after = null;
for ($page = 1; $page <= ceil($maxItems / $perPage) && count($all) < ($currentPage * $perPage); $page++) {
    if ($after) $params['after'] = $after;
    $res = $api->searchQuestions($params);
    $batch = $res['questions'] ?? [];
    $all = array_merge($all, $batch);
    if (empty($batch)) break;
    $after = end($batch)['question_id'];
}

$questions = array_slice($all, ($currentPage - 1) * $perPage, $perPage);
$totalCount = min(count($all), $maxItems);
$totalPages = max(1, ceil($totalCount / $perPage));

if ($currentPage > $totalPages) {
    header("Location: buffet.php?sort=$sort&page=$totalPages");
    exit;
}

function count_textarea_rows($text) {
    $lines = explode("\n", $text);
    $row_count = 0;
    foreach ($lines as $line) {
        $row_count += max(1, ceil(mb_strlen($line) / 80));
    }
    return $row_count;
}

function format_relative_time($timestamp_ms) {
    $time = $timestamp_ms / 1000;
    $now = time();
    $diff = $now - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minute' . ($diff < 120 ? '' : 's') . ' ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hour' . ($diff < 7200 ? '' : 's') . ' ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' day' . ($diff < 172800 ? '' : 's') . ' ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' month' . ($diff < 5184000 ? '' : 's') . ' ago';
    return floor($diff / 31536000) . ' year' . ($diff < 63072000 ? '' : 's') . ' ago';
}
?>
<!DOCTYPE html>
<html lang="en" class="bg-gray-900 text-white">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Home • qOverflow</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/showdown@<version>/dist/showdown.min.js"></script> 
</head>
<body class="min-h-screen font-sans flex flex-col">
  <div class="flex w-full h-screen p-6 gap-1">
    <aside class="w-80 bg-gray-800 rounded-2xl p-6 hidden md:flex flex-col max-h-[calc(100vh-3rem)] sticky top-6 border border-gray-700">
      <h1 class="text-3xl font-bold mb-6 leading-tight text-white">Welcome<?php if (isset($_SESSION['username'])): ?><span class="text-blue-400"> <?= htmlspecialchars($_SESSION['username']) ?></span><?php else: ?><span class="text-blue-400"> Guest</span><?php endif; ?>!</h1>

      <?php if (isset($_SESSION['username'])): ?>
        <button class="mt-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold transition duration-200 shadow-md" onclick="document.getElementById('modal').classList.remove('hidden')">
          Ask Question
        </button>
      <?php else: ?>
        <div class="mt-auto space-y-4">
          <a href="../auth/login.php" class="block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-center transition duration-200 shadow-md">Login</a>
          <a href="../auth/signup.php" class="block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-center transition duration-200 shadow-md">Sign Up</a>
        </div>
      <?php endif; ?>
    </aside>

    <main class="flex-1 flex flex-col min-h-0 overflow-y-auto pl-4 pr-2">
      <form method="GET" class="mb-4 border-b border-gray-700 flex-shrink-0">
        <input type="hidden" name="page" id="pageInput" value="<?= $currentPage ?>">
        <ul class="flex gap-6 text-sm font-medium select-none">
          <?php
          $tabs = [
              'recent' => 'Recent',
              'best' => 'Best',
              'interesting' => 'Most Interesting',
              'hottest' => 'Hottest',
          ];
          foreach ($tabs as $key => $label):
              $active = ($key == $sort);
          ?>
            <li>
              <button type="submit" name="sort" value="<?= $key ?>" class="pb-2 border-b-2 <?= $active ? 'border-blue-600 text-blue-400' : 'border-transparent text-gray-400 hover:text-blue-400 hover:border-blue-600' ?>" onclick="document.getElementById('pageInput').value=1;">
                <?= htmlspecialchars($label) ?>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>
      </form>

      <div class="space-y-6">
        <?php if (empty($questions)): ?>
          <p class="text-gray-400">No questions found.</p>
        <?php else: ?>
          <?php foreach ($questions as $q): 
            $text = $q['text'] ?? '';
            $rows = count_textarea_rows($text);
            $createdAt = $q['createdAt'] ?? null;
            $relativeTime = $createdAt ? format_relative_time($createdAt) : 'unknown';
            $exactTime = $createdAt ? (new DateTime('@' . ($createdAt / 1000)))->setTimezone(new DateTimeZone('America/Chicago'))->format('m/d/Y h:i:s A') : '';
          ?>
            <div class="bg-gray-800 p-5 rounded-xl border border-gray-700 hover:border-gray-500 transition">
              <a class="text-lg font-semibold text-blue-400 hover:underline" href="../q&a/q&a.php?questionName=<?= urlencode($q['title']) ?>">
                <?= htmlspecialchars($q['title']) ?>
              </a>
              
              <textarea
                readonly
                style="resize: none; pointer-events: none;"
                rows="<?= $rows ?>"
                class="w-full mt-1 p-3 bg-gray-700 border border-gray-700 rounded-md text-white placeholder-gray-600"
              ><?= htmlspecialchars($text) ?></textarea>

              <div class="text-sm text-gray-400 flex gap-6 mt-2">
                <span><?= intval($q['upvotes'] ?? 0) ?> vote<?= (intval($q['upvotes'] ?? 0) == 1 ? '' : 's') ?></span>
                <span><?= intval($q['answers'] ?? 0) ?> answer<?= (intval($q['answers'] ?? 0) == 1 ? '' : 's') ?></span>
                <span><?= intval($q['views'] ?? 0) ?> view<?= (intval($q['views'] ?? 0) == 1 ? '' : 's') ?></span>
              </div>

              <div class="flex justify-between text-sm mt-2">
                <span class="text-gray-300">
                  <span class="w-2 h-2 bg-white rounded-full inline-block"></span>
                  <?= htmlspecialchars($q['creator'] ?? 'unknown') ?>
                </span>
                <span class="text-gray-300"><?= $relativeTime ?> <span class="text-gray-500">•</span> <?= $exactTime ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <?php if ($totalCount > $perPage): ?>
        <div class="flex justify-end mt-4 gap-1 text-sm text-gray-400">
          <form method="GET" class="flex items-center gap-1">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <?php if ($currentPage > 1): ?>
              <button type="submit" name="page" value="<?= $currentPage - 1 ?>" class="px-2 py-1 bg-gray-700 rounded hover:bg-gray-600">Previous</button>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <button type="submit" name="page" value="<?= $i ?>" class="px-2 py-1 rounded <?= $i == $currentPage ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?>"><?= $i ?></button>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
              <button type="submit" name="page" value="<?= $currentPage + 1 ?>" class="px-2 py-1 bg-gray-700 rounded hover:bg-gray-600">Next</button>
            <?php endif; ?>
          </form>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <div id="modal" class="fixed inset-0 bg-black/60 flex items-center justify-center hidden z-50">
    <form method="POST" class="bg-gray-800 w-full max-w-3xl p-8 rounded-xl shadow-xl space-y-5">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-white">Ask a Question</h2>
        <button type="button" class="text-gray-400 hover:text-white" onclick="document.getElementById('modal').classList.add('hidden')">✕</button>
      </div>
      <div>
        <label class="text-gray-300 text-sm">Question Title • Max 150 characters</label>
        <input name="title" type="text" maxlength="150" placeholder="Enter your question title..." required class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400"/>
      </div>
      <div>
        <label class="text-gray-300 text-sm">Question Body • Max 3000 characters</label>
        <textarea name="text" rows="8" maxlength="3000" placeholder="Explain your question in detail..." required class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none"></textarea>
      </div>
      <div class="flex justify-end space-x-3 pt-4">
        <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-6 py-3 rounded bg-gray-700 hover:bg-gray-600">Cancel</button>
        <button type="submit" class="px-6 py-3 rounded bg-blue-600 hover:bg-blue-700 font-semibold">Post</button>
      </div>
    </form>
  </div>
</body>
</html>