<?php
session_start();

  require_once '../Api/api.php';
  require_once '../Api/key.php';

$api = new qOverflowAPI(API_KEY);

// Get input
$searchQuery = isset($_GET['query']) ? $_GET['query'] : '';
$datetime = isset($_GET['datetime']) ? $_GET['datetime'] : '';

$searchQuery = is_array($searchQuery) ? reset($searchQuery) : trim($searchQuery);
$datetime = trim($datetime);

$dateMatches = [];
$titleMatches = [];
$textMatches = [];
$creatorMatches = [];

try {
    // Accept MM/DD/YYYY format for datetime
    if (!empty($datetime)) {
        $date = DateTime::createFromFormat('m/d/Y', $datetime, new DateTimeZone('UTC'));
        if ($date) {
            $startOfDay = $date->setTime(0, 0, 0)->getTimestamp() * 1000;
            $endOfDay = $date->setTime(23, 59, 59)->getTimestamp() * 1000 + 999;

            $params = ['limit' => 100];
            $results = $api->searchQuestions($params);

            if (is_array($results)) {
                foreach ($results as $question) {
                    if (is_array($question)) {
                        $qTime = $question['createdAt'] ?? null;
                        if ($qTime !== null && is_numeric($qTime) && $qTime >= $startOfDay && $qTime <= $endOfDay) {
                            $dateMatches[] = $question['title'];
                        }
                    }
                }
            }
        }
    }

    // If query is provided, search by text, title, or creator
    if (!empty($searchQuery)) {
        $params = ['query' => $searchQuery];
        $results = $api->searchQuestions($params);
        $searchQueryLower = strtolower($searchQuery);

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
                    if (isset($question['creator']) && strpos(strtolower($question['creator']), $searchQueryLower) !== false) {
                        $creatorMatches[] = [
                            'title' => $question['title'],
                            'creator' => $question['creator']
                        ];
                    }
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
                            if (isset($subval['creator']) && strpos(strtolower($subval['creator']), $searchQueryLower) !== false) {
                                $creatorMatches[] = [
                                    'title' => $subval['title'],
                                    'creator' => $subval['creator']
                                ];
                            }
                            if (isset($subval['createdAt']) && strpos(strtolower($subval['createdAt']), $searchQueryLower) !== false) {
                                $creatorMatches[] = $subval['title']; // Use title for link
                            }
                        }
                    }
                }
            }
        }
    }

    // Display results
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>qOverflow — Search Results</title>
      <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-900 text-white font-sans">
<!--
    <div class="mb-6">
    <?php 
    /*
      if (!isset($_SESSION['username']) || empty($_SESSION['username'])) {
        include 'navBarLogOut.php';
      } else {
        include 'navBarLogIn.php';
      }
        */
    ?>
  </div>
    -->
      <h1 class="text-3xl font-bold mb-2 text-center">Search Results</h1>

      <?php if (!empty($searchQuery)): ?>
        <p class="mb-4 text-lg text-center">Your Query: <span class="text-blue-400"><?= htmlspecialchars($searchQuery) ?></span></p>
      <?php endif; ?>

      <?php if (!empty($datetime)): ?>
        <p class="mb-4 text-lg">Date: <span class="text-blue-400"><?= htmlspecialchars($datetime) ?></span></p>
      <?php endif; ?>

      
      <?php if ($titleMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center ">Title Matches:</h2>
      <ul class="space-y-2">
      <?php foreach ($titleMatches as $title): ?>
        <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($title) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition">
            <?= htmlspecialchars($title) ?>
          </a>
        </li>
      <?php endforeach; ?>
       </div>
      <?php endif; ?>
     
      <?php if ($textMatches): ?>
         <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center ">Body Text Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($textMatches as $match): ?>
        <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition">
          <?= htmlspecialchars($match['snippet']) ?>
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
          <a  href="../pages/q&a/q&a.php?questionName=<?= urlencode($match['title']) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition">
            <?= htmlspecialchars($match['title']) ?> - made by <?= htmlspecialchars($match['creator']) ?>
          </a>
          </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>


      
      <?php if ($dateMatches): ?>
        <div class="bg-gray-800 rounded-lg p-6 mx-auto mb-6 w-full max-w-4xl">
        <h2 class="text-2xl font-bold mb-4 border-b border-gray-700 text-center ">Date Matches:</h2>
        <ul class="space-y-2">
        <?php foreach ($dateMatches as $title): ?>
          <li>
          <a href="../pages/q&a/q&a.php?questionName=<?= urlencode($title) ?>" class="block px-4 py-2 rounded-md bg-gray-700 hover:bg-blue-600 transition">
            <?= htmlspecialchars($title) ?>
          </a>
        </li>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>


      <?php if (!$titleMatches && !$textMatches && !$creatorMatches && !$dateMatches): ?>
        <div class="bg-gray-800 rounded-lg p-4 w-[500px] h-[100px] mx-auto text-center">
        <p class="mt-6 text-red-400">No matching results found.</p>
        </div>
      <?php endif; ?>

    </body>
    </html>
    <?php

} catch (Exception $e) {
    echo "<p class='text-red-500'>Search failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>