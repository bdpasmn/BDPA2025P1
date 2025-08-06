<?php
session_start();

  require_once '../Api/api.php';
  require_once '../Api/key.php';


$api = new qOverflowAPI(API_KEY);


  $pramtags = isset($_GET['query']) ? trim($tags['tags']) : '';
  //$searchTags = '';
  //$searchTags = is_array($searchTags) ? reset($searchTags) : trim($searchTags);


// Gets user input from URL
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$searchQuery = '';
$datetime = '';

// Distinguishes if query is a date or text
if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $query)) {
    $datetime = $query;
} else {
    $searchQuery = $query;
}

$searchQuery = is_array($searchQuery) ? reset($searchQuery) : trim($searchQuery);
$datetime = trim($datetime);

// Initilaize match arrays
$dateMatches = [];
$titleMatches = [];
$textMatches = [];
$creatorMatches = [];
$tagMatches = [];

// Search by date
try {
    if (!empty($datetime)) {
       $date = DateTime::createFromFormat('m/d/Y', $datetime, new DateTimeZone('UTC'));

        if ($date) {
            $startOfDay = $date->setTime(0, 0, 0)->getTimestamp();
            $endOfDay = $date->setTime(23, 59, 59)->getTimestamp();

            $params = ['limit' => 100];
            $results = $api->searchQuestions($params);
            if (is_array($results)) {
              foreach ($results['questions'] as $question) {
                if (is_array($question)) {
                        $qTime = $question['createdAt'] ?? null;
                        $qTime = $question['createdAt'] ?? null;
                        if ($qTime !== null && is_numeric($qTime)) {
                            $qTimeInSeconds = (int)($qTime / 1000);

                            if ($qTimeInSeconds >= $startOfDay && $qTimeInSeconds <= $endOfDay) {
                                $dateMatches[] = [
                                    'title' => $question['title'],
                                    'creator' => $question['creator'] ?? 'Unknown',
                                    'createdAt' => $question['createdAt'],
                                    'question_id' => $question['question_id']
                                ];
                            }
                        }
                  }
                }
            }
        }
    }

    // Search by tags 
    if (!empty($pramtags)) {
        $tagList = array_filter(array_map('trim', explode(',', strtolower($pramtags))));

        if (!empty($tagList)) {
        $sqlParts = [];
        $params = [];

        foreach ($tagList as $tag) {
          $sqlPArts [] = "LOWER(tags) LIKE ?";
          $params[] = '%' . $tags . '%';
        }

        
        $sql = "SELECT * FROM questions WHERE " . implode(" OR ", $sqlParts) . " LIMIT 100 ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($tagList);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tagResults as $question) {
        $tagMatches[] = [
        'title' => $question['title'],
        'creator' => $question['creator'] ?? 'Unknown',
        'createdAt' => $question['createdAt'] ?? null,
        'question_id' => $question['question_id'] ?? 'Unknown'
                                ];
                              }
                            }
                          }

    // Search by text, title, creator
    if (!empty($searchQuery)) {
        $params = ['query' => $searchQuery];
        $results = $api->searchQuestions($params);
        $searchQueryLower = strtolower($searchQuery);
/*
        if (is_array($results)) {
            foreach ($results as $question) {
                if (is_array($question)) {
                    if (isset($question['title']) && strpos(strtolower($question['title']), $searchQueryLower) !== false) {
                        $titleMatches[] = $question['title'];
                    }
                    if (isset($question['text']) && strpos(strtolower($question['text']), $searchQueryLower) !== false) {
                        $textMatches[] = [
                            'snippet' => $question['text'],
                            'title' => $question['title']
                        ];
                    }
*/
                      if (is_array($results)) {
                      foreach ($results as $question) {
                          if (is_array($question)) {
                              if (isset($question['title']) && strpos(strtolower($question['title']), $searchQueryLower) !== false) {
                                /* 
                                $titleMatches[] = $question['title'];
                                */
                                $titleMatches[] = [
                                'title' => $question['title'],
                                'creator' => $question['creator'] ?? 'Unknown',
                                'createdAt' => $question['createdAt'] ?? null,
                                'question_id' => $subval['question_id'] ?? 'Unknown'
                            ];

                              }
                              if (isset($question['text']) && strpos(strtolower($question['text']), $searchQueryLower) !== false) {
                                  $textMatches[] = [
                                  'snippet' => $question['text'],
                                  'title' => $question['title'],
                                  'creator' => $question['creator'] ?? 'Unknown',
                                  'createdAt' => $question['createdAt'] ?? null,
                                  'question_id' => $subval['question_id'] ?? 'Unknown'
                                ];
                              }


                    
                    if (isset($question['creator']) && strpos(strtolower($question['creator']), $searchQueryLower) !== false) {
                        $creatorMatches[] = [
                            'title' => $question['title'],
                            'creator' => $question['creator'],
                             'createdAt' => $question['createdAt'] ?? null,
                             'question_id' => $subval['question_id'] ?? 'Unknown'
                            
                        ];
                    }
                    /*
                    foreach ($question as $subval) {
                        if (is_array($subval)) {
                            if (isset($subval['title']) && strpos(strtolower($subval['title']), $searchQueryLower) !== false) {
                                $titleMatches[] = $subval['title'];
                            }
                            if (isset($subval['text']) && strpos(strtolower($subval['text']), $searchQueryLower) !== false) {
                                $textMatches[] = [
                                    'snippet' => $subval['text'],
                                    'title' => $subval['title']
                                ];
                            }
                                */
                         foreach ($question as $subval) {
                       if (is_array($subval)) {
                           if (isset($subval['title']) && strpos(strtolower($subval['title']), $searchQueryLower) !== false) {
                               /*$titleMatches[] = $subval['title'];*/
                                $titleMatches[] = [
                                'title' => $subval['title'],
                                'createdAt' => $subval['createdAt'] ?? null,
                                'creator' => $subval['creator'] ?? 'Unknown',
                                'question_id' => $subval['question_id'] ?? 'Unknown'
                            ];
                               
                           }
                           if (isset($subval['text']) && strpos(strtolower($subval['text']), $searchQueryLower) !== false) {
                            $textMatches[] = [
                                'snippet' => $subval['text'],
                                'title' => $subval['title'],
                                'creator' => $subval['creator'],
                                'createdAt' => $subval['createdAt'] ?? null,
                                'question_id' => $subval['question_id'] ?? 'Unknown'
                            ];
                          }



                            if (isset($subval['creator']) && strpos(strtolower($subval['creator']), $searchQueryLower) !== false) {
                                $creatorMatches[] = [
                                    'title' => $subval['title'],
                                    'creator' => $subval['creator'],
                                    'createdAt' => $subval['createdAt'] ?? null,
                                    'question_id' => $subval['question_id'] ?? 'Unknown'
                                ];
                            }
                            if (isset($subval['createdAt']) && strpos(strtolower($subval['createdAt']), $searchQueryLower) !== false) {
                                $creatorMatches[] = [
                                'title' => $subval['title'], // Use title for link
                                'creator' => $subval['creator']
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
    // Sort helper
    function sortByCreatedAtDesc($a, $b) {
        return ($b['createdAt'] ?? 0) <=> ($a['createdAt'] ?? 0);
    }

    if (isset($question['tags']) && strpos(strtolower($question['text']), $searchQueryLower) !== false) {
                                  $textMatches[] = [
                                  'snippet' => $question['text'],
                                  'title' => $question['title'],
                                  'creator' => $question['creator'] ?? 'Unknown',
                                  'createdAt' => $question['createdAt'] ?? null,
                                  'question_id' => $subval['question_id'] ?? 'Unknown'
                                ];
                              }

    // Sort the matches by date descending. Latest results appear first
    usort($titleMatches, 'sortByCreatedAtDesc');
    usort($textMatches, 'sortByCreatedAtDesc');
    usort($creatorMatches, 'sortByCreatedAtDesc');
    usort($tagMatches, 'sortByCreatedAtDesc');

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/dompurify@2.4.0/dist/purify.min.js"></script>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Search Results • qOverflow</title>
      <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 text-white font-sans">
    <div class="mb-6">
    
    <?php 
      // which navbar should be displayed
      if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        include 'navBarLogOut.php';
      } else {
        include 'navBarLogIn.php';
      } 
    ?>
  </div>
    
      <h1 class="text-3xl font-bold mb-2 text-center">Search Results</h1>

      <?php if (!empty($searchQuery)): ?>
        <p class="mb-4 text-lg text-center">Your Query: <span class="text-blue-400"><?= htmlspecialchars($searchQuery) ?></span></p>
      <?php endif; ?>

      <?php if (!empty($datetime)): ?>
        <p class="mb-4 text-lg text-center">Your Query: <span class="text-blue-400"><?= htmlspecialchars($datetime) ?></span></p>
      <?php endif; ?>      
      
      <?php if ($titleMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center "> 📌 Title Matches:</h2>
      <ul class="space-y-2">
      <?php foreach ($titleMatches as $match): ?>
        <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>&questionId=<?= urlencode($match['question_id']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?>
             <br>
          <div class="flex justify-between text-gray-400 mt-2">
          <small class="text-gray-400">Created on:
            <?= $match['createdAt'] ? date('m/d/y', (int)($match['createdAt'] / 1000)) : 'Unknown' ?>
          </small>
          <br>
          <small class="text-gray-400">Created by:
          <?= htmlspecialchars($match['creator']) ?>
          </small>
          </div>

          </a>
        </li>
      <?php endforeach; ?>
       </div>
      <?php endif; ?>
       
      <?php if ($textMatches): ?>
         <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center "> 📝 Body Text Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($textMatches as $match): 
            $rawMarkdown = $match['snippet'] ?? '';
            ?>
        <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>&questionId=<?= urlencode($match['question_id']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
         <div data-markdown="<?= htmlspecialchars($rawMarkdown, ENT_QUOTES) ?>"> <?= htmlspecialchars($match['snippet']) ?> </div>
           <div class="flex justify-between text-gray-400 mt-2">
          <small class="text-gray-400">Created on:
            <?= $match['createdAt'] ? date('m/d/y', (int)($match['createdAt'] / 1000)) : 'Unknown' ?>
          </small>
          <br>
          <small class="text-gray-400">Created by:
          <?= htmlspecialchars($match['creator']) ?>
          </small>
          </div>
        </a>
       </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>

       <?php if ($tagMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center " > Titles of Tag Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($tagMatches as $match): ?>
          <li>
          <a  href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>&questionId=<?= urlencode($match['question_id']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?>
            <br>

            
            <div class="flex justify-between mt-2">
            <small class="text-gray-400">Created on:
            <?= $match['createdAt'] ? date('m/d/y', (int)($match['createdAt'] / 1000)) : 'Unknown' ?>
          </small>
          <br>
          <small class="text-gray-400">Created by:
          <?= htmlspecialchars($match['creator']) ?>
          </small>
          </div>
          </a>
          </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
      

      
      <?php if ($creatorMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center " > 👤 Titles of Creator Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($creatorMatches as $match): ?>
          <li>
          <a  href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>&questionId=<?= urlencode($match['question_id']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?> - made by <?= htmlspecialchars($match['creator']) ?>
          <a  href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>&questionId=<?= urlencode($match['question_id']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?>
            <br>
            <div class="flex justify-between mt-2">
            <small class="text-gray-400">Created on:
            <?= $match['createdAt'] ? date('m/d/y', (int)($match['createdAt'] / 1000)) : 'Unknown' ?>
          </small>
          <br>
          <small class="text-gray-400">Created by:
          <?= htmlspecialchars($match['creator']) ?>
          </small>
          </div>
          </a>
          </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>


      
      <?php if ($dateMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6  w-full max-w-4xl mx-auto mb-6">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center ">📆 Date Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($dateMatches as $match): ?>
          <li>
          <a  href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>&questionId=<?= urlencode($match['question_id']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?> - made by <?= htmlspecialchars($match['creator']) ?>
            <br>
            <div class="flex justify-between mt-2">
            <small class="text-gray-400">Created on:
            <?= $match['createdAt'] ? date('m/d/y', (int)($match['createdAt'] / 1000)) : 'Unknown' ?>
          </small>
          <br>
          <small class="text-gray-400">Created by:
          <?= htmlspecialchars($match['creator']) ?>
          </small>
          </div>
          </a>
          </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>

     
      <?php 
      // Fallback if no matches
      if (!$titleMatches && !$textMatches && !$creatorMatches && !$dateMatches): ?>
        <div class="bg-gray-800 rounded-lg p-4 w-[500px] h-[100px] mx-auto text-center">
        <p class="mt-6 text-red-400">No matching results found.</p>
        </div>

      <?php endif; ?>
      <script>
          function decodeHTMLEntities(text) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            return textarea.value;
          }
          // Markdown
          document.addEventListener('DOMContentLoaded', () => {
          document.querySelectorAll('[data-markdown]').forEach(el => {
              const rawMarkdown = decodeHTMLEntities(el.getAttribute('data-markdown') || '');
              const html = marked.parse(rawMarkdown);
              el.innerHTML = DOMPurify.sanitize(html);
            })
            });
      </script>

    </body>
    </html>
    <?php

} catch (Exception $e) {
    echo "<p class='text-red-500'>Search failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>