<?php
session_start();


require_once '../../api/key.php';
require_once '../../api/api.php';
include '../../levels/getUserLevel.php';
include '../../levels/updateUserPoints.php';


try {
    require_once '../../db.php';


    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    $pdo = null;
}


$api = new qOverflowAPI(API_KEY);


$sort = $_GET['sort'] ?? 'recent';
$currentPage = isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1;
$perPage = 10;
$maxItems = 100;


$match = [];
$params = [];


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
    default:
        break;
}


if (!empty($match)) {
    $params['match'] = json_encode($match);
}


// Posting a question
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['title'], $_POST['text'], $_SESSION['username'])) {
      $title = trim(strip_tags($_POST['title']));
      $text = trim($_POST['text']);
      $username = $_SESSION['username'];


      $question = $api->createQuestion($username, $title, $text);


      if (isset($question['question']['question_id'])) {
          $questionId = $question['question']['question_id'];


          if (!empty($_POST['tags'])) {
              $tags = trim($_POST['tags']);


              try {
                  $stmt = $pdo->prepare("INSERT INTO question_tags (question_id, tags) VALUES (:qid, :tags)");
                  $stmt->execute([
                      ':qid' => $questionId,
                      ':tags' => $tags
                  ]);
              } catch (PDOException $e) {
                  error_log("Tag insert error: " . $e->getMessage());
              }
          }


          updateUserPoints($username, 1);
          header("Location: buffet.php?sort=$sort&page=$currentPage");
          exit();
      } else {
          echo "<p class='text-red-500'>Failed to post question. Please try again.</p>";
      }
  }
}


$all = [];
$after = null;


$totalPagesEstimate = ceil($maxItems / $perPage);


$neededItems = $currentPage * $perPage;


for ($page = 1; $page <= $totalPagesEstimate && count($all) < $neededItems; $page++) {
    if ($after !== null) {
        $params['after'] = $after;
    }


    $res = $api->searchQuestions($params);
    $batch = $res['questions'] ?? [];


    if (empty($batch)) break;


    $all = array_merge($all, $batch);
    $after = end($batch)['question_id'];
}


$startIndex = ($currentPage - 1) * $perPage;
$questions = array_slice($all, $startIndex, $perPage);


$totalCount = min(count($all), $maxItems);


$totalPages = max(ceil($totalCount / $perPage), 1);


if ($currentPage > $totalPages) {
    header("Location: buffet.php?sort=$sort&page=$totalPages");
    exit();
}


// User-friendly timestamp
function format_relative_time($timestamp_ms) {
    $time = $timestamp_ms / 1000;
    $now = time();
    $diff = $now - $time;


    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minute' . (floor($diff / 60) == 1 ? '' : 's') . ' ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hour' . (floor($diff / 3600) == 1 ? '' : 's') . ' ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' day' . (floor($diff / 86400) == 1 ? '' : 's') . ' ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' month' . (floor($diff / 2592000) == 1 ? '' : 's') . ' ago';
    return floor($diff / 31536000) . ' year' . (floor($diff / 31536000) == 1 ? '' : 's') . ' ago';
}
?>
<!DOCTYPE html>
<html lang="en" class="bg-gray-900 text-white">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Buffet • qOverflow</title>
  <link rel="icon" href="../../favicon.ico" type="image/x-icon" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/dompurify@2.4.0/dist/purify.min.js"></script>
  <style>
    .hide-scrollbar::-webkit-scrollbar {
      display: none;
    }
    .hide-scrollbar {
      -ms-overflow-style: none;
      scrollbar-width: none;
    }
  </style>
</head>


<body class="min-h-screen font-sans flex flex-col">
  <!-- Including NavBar -->
  <div class="mb-6">
    <?php
      if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        include '../../components/navBarLogOut.php';
      } else {
        include '../../components/navBarLogIn.php';
      }
    ?>
  </div>


  <!-- Welcome + Ask Question/Login/Signup (Phone/Tablet View) -->
  <div class="md:hidden px-4 py-5 bg-gray-800 border border-gray-700 rounded-xl mx-4 my-4 shadow-md">
    <div class="flex justify-between items-center">
      <div class="text-3xl text-white font-semibold">
        <?php $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>
        Welcome
        <span class="text-blue-400 text-4xl block truncate max-w-[12rem] sm:max-w-[16rem]" title="<?= htmlspecialchars($username) ?>">
          <?= htmlspecialchars($username) ?>
        </span>
      </div>
      <div class="flex gap-2">
        <?php if (isset($_SESSION['username'])): ?>
          <button onclick="document.getElementById('modal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
            Ask Question
          </button>
        <?php else: ?>
          <a href="../auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">Login</a>
          <a href="../auth/signup.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">Sign Up</a>
        <?php endif; ?>
      </div>
    </div>
  </div>


  <!-- Welcome + Ask Question/Login/Signup (Computer) View) -->
  <div class="flex-1 flex flex-col md:flex-row max-h-[84vh] w-full h-full px-5 md:px-5 gap-5 md:gap-5">
    <aside class="hidden md:flex w-80 bg-gray-800 rounded-2xl p-6 flex-col max-h-[calc(100vh-3rem)] sticky top-6 border border-gray-700">
      <h1 class="text-3xl font-bold mb-6 leading-tight text-white">
        <?php $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest'; ?>
        Welcome <br />
        <div class="flex items-center h-auto max-w-full overflow-hidden">
          <span class="inline-block truncate text-blue-400 text-4xl font-bold leading-none pt-[2px] pb-[2px]" title="<?= htmlspecialchars($username) ?>">
            <?= htmlspecialchars($username) ?>
          </span>
        </div>
      </h1>


      <?php if (isset($_SESSION['username'])): ?>
        <button class="mt-auto bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold transition duration-200 shadow-md" onclick="document.getElementById('modal').classList.remove('hidden')">Ask Question</button>
      <?php else: ?>
        <div class="mt-auto space-y-4">
          <a href="../auth/login.php" class="block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-center transition duration-200 shadow-md">Login</a>
          <a href="../auth/signup.php" class="block bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-lg font-semibold text-center transition duration-200 shadow-md">Sign Up</a>
        </div>
      <?php endif; ?>
    </aside>


    <!-- Main content (Questions + Tabs) -->
    <main class="flex-1 flex flex-col overflow-y-auto hide-scrollbar">
      <!-- Sorting tabs -->
      <form method="GET" class="mb-4 border-b border-gray-700 flex-shrink-0 overflow-x-auto">
        <input type="hidden" name="page" id="pageInput" value="<?= $currentPage ?>">
        <ul class="flex gap-4 text-base font-medium whitespace-nowrap">
          <?php
            $tabs = ['recent' => 'Recent', 'best' => 'Best', 'interesting' => 'Most Interesting', 'hottest' => 'Hottest'];
            foreach ($tabs as $key => $label):
              $active = $key == $sort;
              $class = $active ? 'border-blue-600 text-blue-400' : 'border-transparent text-gray-400 hover:text-blue-400 hover:border-blue-600';
          ?>
          <li>
            <button type="submit" name="sort" value="<?= $key ?>" class="pb-2 border-b-2 <?= $class ?> sort-btn disabled pointer-events-none hover:text-blue-400 hover:border-blue-600" disabled onclick="document.getElementById('pageInput').value=1;">
              <?= htmlspecialchars($label) ?>
            </button>
          </li>
          <?php endforeach; ?>
        </ul>
      </form>
     
      <div id="spinner" class="flex justify-center items-center py-20">
        <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
      </div>


      <!-- Question Boxes -->
      <div id="question-list" class="space-y-6 hidden">
        <?php foreach ($questions as $q):
          $rawMarkdown = $q['text'] ?? '';
          $creator = htmlspecialchars($q['creator']);
          $levelInfo = getUserLevel($creator);
          $createdAt = $q['createdAt'] ?? null;
          $relative = $createdAt ? format_relative_time($createdAt) : 'unknown';
          $exact = $createdAt ? (new DateTime('@' . ($createdAt / 1000)))->setTimezone(new DateTimeZone('America/Chicago'))->format('m/d/Y h:i:s A') : '';
          $status = $q['status'];
          $isClosed = $status == 'closed';
          $isProtected = $status == 'protected';


          $viewerLevel = isset($_SESSION['username']) ? getUserLevel($_SESSION['username'])['level'] : 0;
          $canAccessClosed = !$isClosed || $viewerLevel >= 7;


          $tags = null;
          if ($pdo) {
            $stmt = $pdo->prepare("SELECT tags FROM question_tags WHERE question_id = :qid LIMIT 1");
            $stmt->execute([':qid' => $q['question_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty(trim($row['tags']))) {
              $tags = explode(',', $row['tags']);
              $tags = array_map('trim', $tags);
              $tags = array_filter($tags, fn($tag) => $tag !== '');
            }
          }
        ?>
        <div class="bg-gray-800 p-5 rounded-xl border border-gray-700 hover:border-gray-500 transition" data-id="<?= $q['question_id'] ?>">
          <div class="flex flex-wrap items-center gap-2">
            <?php if ($isClosed): ?>
              <span class="text-lg sm:text-xl font-semibold text-blue-400 break-words cursor-not-allowed">
                <?= htmlspecialchars($q['title']) ?>
              </span>
            <?php else: ?>
              <a class="text-lg sm:text-xl font-semibold text-blue-400 hover:underline break-words" href="../q&a/q&a.php?questionName=<?= urlencode($q['title']) ?>&questionId=<?= urlencode($q['question_id']) ?>">
                <?= htmlspecialchars($q['title']) ?>
              </a>
            <?php else: ?>
              <span class="text-lg sm:text-xl font-semibold text-blue-400 break-words cursor-not-allowed" title="Closed - Level 7+ required">
                <?= htmlspecialchars($q['title']) ?>
              </span>
            <?php endif; ?>


            <?php if ($isClosed): ?>
              <span class="text-xs border border-gray-600 text-gray-400 bg-gray-700 text-white px-1 py-0.5 rounded-md">Closed</span>
            <?php endif; ?>
            <?php if ($isProtected): ?>
              <span class="text-xs border border-gray-600 text-gray-400 bg-gray-700 text-white px-1 py-0.5 rounded-md">Protected</span>
            <?php endif; ?>
            <?php if (!empty($q['hasAcceptedAnswer'])): ?>
              <span class="text-xs border border-green-600 text-green-400 bg-gray-700 px-1 py-0.5 rounded-md">Answer Accepted</span>
            <?php endif; ?>
          </div>


            <div class="flex flex-wrap gap-1 items-center max-w-[24%] justify-end">
              <?php if ($tags && count($tags) > 0): ?>
                <?php foreach ($tags as $tag): ?>
                  <span class="text-xs text-gray-200 bg-gray-700 px-2 py-0.5 rounded-md border border-gray-600 truncate max-w-full" title="<?= htmlspecialchars($tag) ?>">
                    <?= htmlspecialchars($tag) ?>
                  </span>
                <?php endforeach; ?>
              <?php else: ?>
                <!-- Nothing if no tags -->
              <?php endif; ?>
            </div>
          </div>


          <div id="md-box-<?= $q['question_id'] ?>" class="mt-1 px-3 py-2 bg-gray-700 rounded-md text-white prose prose-invert max-w-full font-sans leading-relaxed text-sm sm:text-base break-words" data-markdown="<?= htmlspecialchars($rawMarkdown, ENT_QUOTES) ?>"></div>


          <div class="text-sm text-gray-400 flex flex-wrap gap-4 mt-2">
            <?php $upvotes = intval($q['upvotes'] ?? 0); $downvotes = intval($q['downvotes'] ?? 0); $votes = $upvotes - $downvotes; ?>
            <span class="vote-count"><?= $votes ?> vote<?= abs($votes) === 1 ? '' : 's' ?></span>
            <span class="answer-count"><?= intval($q['answers'] ?? 0) ?> answer<?= intval($q['answers'] ?? 0) == 1 ? '' : 's' ?></span>
            <span class="view-count"><?= intval($q['views'] ?? 0) ?> view<?= intval($q['views'] ?? 0) == 1 ? '' : 's' ?></span>
          </div>


          <div class="flex justify-between text-sm mt-2 text-gray-300 flex-wrap">
            <?php
              $email = '';
              if ($pdo) {
                $stmt = $pdo->prepare("SELECT email FROM users WHERE username = :username LIMIT 1");
                $stmt->execute(['username' => $creator]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['email'])) {
                  $email = trim(strtolower($row['email']));
                }
              }
              $gravatarHash = md5($email);
              $gravatarUrl = "https://www.gravatar.com/avatar/$gravatarHash?d=identicon";
            ?>
            <span class="flex items-center gap-1">
              <img src="<?= htmlspecialchars($gravatarUrl) ?>" alt="Avatar" class="w-6 h-6 rounded-full border border-gray-600">
              <?= $creator ?>
              <span class="text-xs text-gray-400 bg-gray-700 px-1 py-0.5 rounded-md border border-gray-600 ml-1">Level <?= $levelInfo['level'] ?></span>
            </span>
            <span><?= $relative ?> <span class="text-gray-500">•</span> <?= $exact ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>


      <!-- Pagination -->
      <?php if ($totalCount > $perPage): ?>
      <div class="flex flex-wrap justify-end mt-4 gap-1 text-sm text-gray-400">
        <form method="GET" class="flex flex-wrap items-center gap-1">
          <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <?php if ($currentPage > 1): ?>
            <button type="submit" name="page" value="<?= $currentPage - 1 ?>" class="px-2 py-1 bg-gray-700 rounded hover:bg-gray-600 pagination-btn">Preview</button>
          <?php endif; ?>
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <button type="submit" name="page" value="<?= $i ?>" class="px-2 py-1 rounded pagination-btn <?= $i == $currentPage ? 'bg-blue-600 text-white' : 'bg-gray-700 hover:bg-gray-600' ?>"><?php echo $i; ?></button>
          <?php endfor; ?>
          <?php if ($currentPage < $totalPages): ?>
            <button type="submit" name="page" value="<?= $currentPage + 1 ?>" class="px-2 py-1 bg-gray-700 rounded hover:bg-gray-600 pagination-btn">Next</button>
          <?php endif; ?>
        </form>
      </div>
      <?php endif; ?>
    </main>
  </div>


  <!-- Ask Question Modal -->
  <div id="modal" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 md:p-10 hidden z-[70] overflow-auto">
    <form id="askForm" method="POST" class="bg-gray-800 w-full max-w-3xl p-6 md:p-8 rounded-xl shadow-xl space-y-5 relative" onsubmit="return validateTags()">
      <div class="flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-white">Ask a Question</h2>
        <button type="button" class="text-gray-400 hover:text-white" onclick="document.getElementById('modal').classList.add('hidden')">✕</button>
      </div>
      <div>
        <label class="text-gray-300 text-sm">Question Title • Max 150 characters</label>
        <input name="title" type="text" maxlength="150" placeholder="Enter your question title..." required class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400" />
      </div>
      <div>
        <label class="text-gray-300 text-sm">Question Body • Max 3000 characters</label>
        <textarea id="questionText" name="text" rows="8" maxlength="3000" placeholder="Explain your question in detail..." required class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400 resize-none"></textarea>
      </div>
      <div class="text-sm text-gray-400">
        <strong>Formatting examples:</strong> Use **bold**, *italic*, `code`, and [links](https://example.com)
      </div>
      <div>
        <label for="tagsInput" class="text-gray-300 text-sm">Tags • Up to tags, comma separated, alphanumeric only</label>
        <input id="tagsInput" name="tags" type="text" placeholder="e.g. example,question,name" class="w-full mt-1 p-3 bg-gray-700 border border-gray-600 rounded-md text-white placeholder-gray-400" />
        <p id="tagsError" class="text-red-500 text-xs mt-1 hidden"></p>
      </div>
      <div class="flex justify-between items-center pt-2">
        <button type="button" onclick="previewMarkdown()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition duration-200 shadow-md">Preview Markdown</button>
        <div class="flex space-x-3">
          <button type="button" onclick="document.getElementById('modal').classList.add('hidden')" class="px-5 py-2.5 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition duration-200 shadow-md">Cancel</button>
          <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition duration-200 shadow-md">Post</button>
        </div>
      </div>
    </form>
  </div>


  <!-- Markdown Preview Modal -->
  <div id="previewModal" class="fixed inset-0 bg-black/30 flex items-center justify-center p-4 md:p-10 hidden z-[90] overflow-auto">
    <div class="bg-gray-800 w-full max-w-2xl p-6 md:p-8 rounded-xl shadow-xl max-h-[90vh] overflow-y-auto">
      <div class="flex justify-between mb-4 items-center">
        <h2 class="text-xl font-semibold text-white">Preview Question</h2>
        <button onclick="document.getElementById('previewModal').classList.add('hidden')" class="text-white text-xl font-bold">✕</button>
      </div>
      <div id="previewContent" class="bg-gray-700 border border-gray-600 rounded-md p-4 text-white whitespace-pre-wrap"></div>
    </div>
  </div>


  <script>
    // Markdown stuff
    function decodeHTMLEntities(text) {
      const textarea = document.createElement('textarea');
      textarea.innerHTML = text;
      return textarea.value;
    }


    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('[data-markdown]').forEach(el => {
        const rawMarkdown = decodeHTMLEntities(el.getAttribute('data-markdown') || '');
        const html = marked.parse(rawMarkdown);
        el.innerHTML = DOMPurify.sanitize(html);
      });
    });


    // Preview markdown function
    function previewMarkdown() {
      const rawMarkdown = document.getElementById('questionText').value;
      const html = marked.parse(rawMarkdown);
      const sanitized = DOMPurify.sanitize(html);
      document.getElementById('previewContent').innerHTML = sanitized;
      document.getElementById('previewModal').classList.remove('hidden');
    }


    window.addEventListener('DOMContentLoaded', () => {
      document.getElementById('spinner').classList.add('hidden');
      document.getElementById('question-list').classList.remove('hidden');


      const sortButtons = document.querySelectorAll('.sort-btn');
      sortButtons.forEach(button => {
        button.disabled = false;
        button.classList.remove('disabled', 'pointer-events-none');
      });
    });


    // Disable sort buttons
    document.addEventListener('DOMContentLoaded', () => {
      const sortButtons = document.querySelectorAll('form button[name="sort"]');


      sortButtons.forEach(button => {
        button.addEventListener('click', () => {
          setTimeout(() => {
            sortButtons.forEach(btn => {
              btn.disabled = true;
              btn.classList.remove('hover:text-blue-400', 'hover:border-blue-600');
              btn.classList.add('pointer-events-none');
            });
          }, 50);
        });
      });
    });


    // Disable pagination buttons
    document.addEventListener('DOMContentLoaded', () => {
      const paginationButtons = document.querySelectorAll('form button[name="page"]');


      paginationButtons.forEach(button => {
        button.addEventListener('click', () => {
          setTimeout(() => {
            paginationButtons.forEach(btn => {
              btn.disabled = true;
              btn.classList.add('pointer-events-none');
            });
          }, 50);
        });
      });
    });


    // Ajax - Update question info
    function updateQuestionStats() {
      const questionElements = document.querySelectorAll('#question-list [data-id]');
      const ids = Array.from(questionElements).map(el => el.getAttribute('data-id')).filter(Boolean);


      if (ids.length === 0) return;


      fetch(`../../api/getQuestionStats.php?ids=${ids.join(',')}&action=batch`)
        .then(res => res.text())
        .then(text => {
          try {
            const data = JSON.parse(text);
            ids.forEach(id => {
              const container = document.querySelector(`[data-id="${id}"]`);
              if (!container || !data[id]) return;


              container.querySelector('.view-count').textContent = `${data[id].views} view${data[id].views == 1 ? '' : 's'}`;
              const upvotes = data[id].upvotes || 0;
              const downvotes = data[id].downvotes || 0;
              const votes = upvotes - downvotes;
              container.querySelector('.vote-count').textContent = `${votes} vote${Math.abs(votes) == 1 ? '' : 's'}`;
              container.querySelector('.answer-count').textContent = `${data[id].answers} answer${data[id].answers == 1 ? '' : 's'}`;
            });
          } catch (e) {
            console.error("⚠️ Not valid JSON. Got HTML instead:\n", text);
          }
        });
    }


    document.addEventListener('DOMContentLoaded', () => {
      setInterval(updateQuestionStats, 10000);
    });


    function validateTags() {
      const tagsInput = document.getElementById('tagsInput');
      const errorMsg = document.getElementById('tagsError');
      const rawTags = tagsInput.value.trim();
      const askForm = document.getElementById('askForm');
      const postBtn = askForm?.querySelector('button[type="submit"]');


      if (!rawTags) {
        errorMsg.classList.add('hidden');
      } else {
        const tags = rawTags.split(',').map(t => t.trim().toLowerCase()).filter(t => t.length > 0);


        if (tags.length > 5) {
          errorMsg.textContent = 'Please enter no more than 5 tags.';
          errorMsg.classList.remove('hidden');
          return false;
        }


        const tagRegex = /^[a-z0-9]+$/;
        for (const tag of tags) {
          if (!tagRegex.test(tag)) {
            errorMsg.textContent = 'Tags can only contain letters and digits, no spaces or special characters.';
            errorMsg.classList.remove('hidden');
            return false;
          }
        }
 
        tagsInput.value = tags.join(',');
        errorMsg.classList.add('hidden');
      }


      if (postBtn) {
        postBtn.disabled = true;
        postBtn.textContent = 'Posting...';
        postBtn.classList.add('opacity-50', 'cursor-not-allowed');
      }


      return true;
    }
  </script>
</body>
</html>