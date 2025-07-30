<?php
session_start(); 

require_once '../Api/api.php';
require_once '../Api/key.php';

$api = new qOverflowAPI(API_KEY); 

// Retrieve query and date parameters from the GET request
$searchQuery = isset($_GET['query']) ? $_GET['query'] : '';
$datetime = isset($_GET['datetime']) ? $_GET['datetime'] : '';

// Sanitize input
$searchQuery = is_array($searchQuery) ? reset($searchQuery) : trim($searchQuery);
$datetime = trim($datetime);

// Initialize arrays to hold matches
$dateMatches = [];
$titleMatches = [];
$textMatches = [];
$creatorMatches = [];

try {
    // If a date is given, attempt to parse it in MM/DD/YYYY format
    if (!empty($datetime)) {
        $date = DateTime::createFromFormat('m/d/Y', $datetime, new DateTimeZone('UTC'));

        if ($date) {
            // Get timestamp range for the entire day in milliseconds
            $startOfDay = $date->setTime(0, 0, 0)->getTimestamp() * 1000;
            $endOfDay = $date->setTime(23, 59, 59)->getTimestamp() * 1000 + 999;

            // Fetch a batch of questions (limit to 100)
            $params = ['limit' => 100];
            $results = $api->searchQuestions($params);

            // Filter results by questions created within the date range
            if (is_array($results)) {
                foreach ($results as $question) {
                    if (is_array($question)) {
                        $qTime = $question['createdAt'] ?? null;

                        if ($qTime !== null && is_numeric($qTime) && $qTime >= $startOfDay && $qTime <= $endOfDay) {
                            $dateMatches[] = [
                                'title' => $question['title'],
                                'creator' => $question['creator'] ?? 'Unknown',
                            ];
                        }
                    }
                }
            }
        }
    }

    // If a keyword query is provided, search by title, text, or creator fields
    if (!empty($searchQuery)) {
        $params = ['query' => $searchQuery];
        $results = $api->searchQuestions($params);
        $searchQueryLower = strtolower($searchQuery); // Normalize for case-insensitive search

        if (is_array($results)) {
            foreach ($results as $question) {
                if (is_array($question)) {
                    // Match by title
                    if (isset($question['title']) && strpos(strtolower($question['title']), $searchQueryLower) !== false) {
                        $titleMatches[] = [
                            'title' => $question['title'],
                            'creator' => $question['creator'] ?? 'Unknown',
                            'createdAt' => $question['createdAt'] ?? null
                        ];
                    }

                    // Match by text body
                    if (isset($question['text']) && strpos(strtolower($question['text']), $searchQueryLower) !== false) {
                        $textMatches[] = [
                            'snippet' => $question['text'],
                            'title' => $question['title'],
                            'creator' => $question['creator'] ?? 'Unknown',
                            'createdAt' => $question['createdAt'] ?? null
                        ];
                    }

                    // Match by creator username
                    if (isset($question['creator']) && strpos(strtolower($question['creator']), $searchQueryLower) !== false) {
                        $creatorMatches[] = [
                            'title' => $question['title'],
                            'creator' => $question['creator'],
                            'createdAt' => $question['createdAt'] ?? null
                        ];
                    }

                    // Nested sub-objects in each question
                    foreach ($question as $subval) {
                        if (is_array($subval)) {
                            // Match nested title
                            if (isset($subval['title']) && strpos(strtolower($subval['title']), $searchQueryLower) !== false) {
                                $titleMatches[] = [
                                    'title' => $subval['title'],
                                    'createdAt' => $subval['createdAt'] ?? null,
                                    'creator' => $subval['creator'] ?? 'Unknown'
                                ];
                            }

                            // Match nested text
                            if (isset($subval['text']) && strpos(strtolower($subval['text']), $searchQueryLower) !== false) {
                                $textMatches[] = [
                                    'snippet' => $subval['text'],
                                    'title' => $subval['title'],
                                    'creator' => $subval['creator'],
                                    'createdAt' => $subval['createdAt'] ?? null
                                ];
                            }

                            // Match nested creator
                            if (isset($subval['creator']) && strpos(strtolower($subval['creator']), $searchQueryLower) !== false) {
                                $creatorMatches[] = [
                                    'title' => $subval['title'],
                                    'creator' => $subval['creator'],
                                    'createdAt' => $subval['createdAt'] ?? null
                                ];
                            }

                            if (isset($subval['createdAt']) && strpos(strtolower($subval['createdAt']), $searchQueryLower) !== false) {
                                $creatorMatches[] = $subval['title']; // Add title for match
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

    // Sort matches
    usort($titleMatches, 'sortByCreatedAtDesc');
    usort($textMatches, 'sortByCreatedAtDesc');
    usort($creatorMatches, 'sortByCreatedAtDesc');

    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/dompurify@2.4.0/dist/purify.min.js"></script>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 text-white font-sans">

    <div class="mb-6">
    <?php 
   
      if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        include 'navBarLogOut.php';
      } else {
        include 'navBarLogIn.php';
      }
     
    ?>
  </div>
    
      <h1 class="text-3xl font-bold mb-2 text-center">Search Results</h1>

      <!-- Show the user's search query if available -->
      <?php if (!empty($searchQuery)): ?>
        <p class="mb-4 text-lg text-center">Your Query: <span class="text-blue-400"><?= htmlspecialchars($searchQuery) ?></span></p>
      <?php endif; ?>

      <!-- Show the search date if provided -->
      <?php if (!empty($datetime)): ?>
        <p class="mb-4 text-lg">Date: <span class="text-blue-400"><?= htmlspecialchars($datetime) ?></span></p>
      <?php endif; ?> 

      <!-- Render results that matched the title -->
      <?php if ($titleMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center "> 📌 Title Matches:</h2>
      <ul class="space-y-2">
      <?php foreach ($titleMatches as $match): ?>
        <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?>
             <br>
          <!-- Display creation metadata -->
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

      <!-- Render results that matched the body content -->
      <?php if ($textMatches): ?>
         <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center "> 📝 Body Text Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($textMatches as $match): 
            $rawMarkdown = $match['snippet'] ?? '';
            ?>
        <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
         <div data-markdown="<?= htmlspecialchars($rawMarkdown, ENT_QUOTES) ?>"> <?= htmlspecialchars($match['snippet']) ?> </div>
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
      

      <!-- Render results that matched the body content -->
      <?php if ($creatorMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center " > 👤 Titles of Creator Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($creatorMatches as $match): ?>
          <li>
          <a  href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars($match['title']) ?> - made by <?= htmlspecialchars($match['creator']) ?>
            <br>
            <small class="text-gray-400">Created on:
            <?= $match['createdAt'] ? date('m/d/y', (int)($match['createdAt'] / 1000)) : 'Unknown' ?>
          </small>
          </a>
          </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Render titles that matched the given date -->
      <?php if ($dateMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6  w-full max-w-4xl mx-auto mb-6">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center ">📆 Date Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($dateMatches as $title): ?>
          <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($title) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition hover:underline block break-words">
            <?= htmlspecialchars(string: $title) ?>
          </a>
        </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- If no matches found, show fallback -->
      <?php if (!$titleMatches && !$textMatches && !$creatorMatches && !$dateMatches): ?>
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