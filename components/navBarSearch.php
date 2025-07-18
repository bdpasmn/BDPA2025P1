<?php
session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

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
    // Check if datetime is provided
    if (!empty($datetime)) {
        $date = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
        if ($date) {
            $startOfDay = $date->setTime(0, 0, 0)->getTimestamp() * 1000;
            $endOfDay = $date->setTime(23, 59, 59)->getTimestamp() * 1000 + 999;

            $params = ['limit' => 100];
            $results = $api->searchQuestions($params);

            if (is_array($results)) {
                foreach ($results as $question) {
                    if (is_array($question)) {
                        $qTime = $question['time'] ?? $question['createdAt'] ?? null;
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
                        $textMatches[] = $question['text'];
                    }
                    if (isset($question['creator']) && strpos(strtolower($question['creator']), $searchQueryLower) !== false) {
                        $creatorMatches[] = $question['creator'];
                    }
                    foreach ($question as $subval) {
                        if (is_array($subval)) {
                            if (isset($subval['title']) && strpos(strtolower($subval['title']), $searchQueryLower) !== false) {
                                $titleMatches[] = $subval['title'];
                            }
                            if (isset($subval['text']) && strpos(strtolower($subval['text']), $searchQueryLower) !== false) {
                                $textMatches[] = $subval['text'];
                            }
                            if (isset($subval['creator']) && strpos(strtolower($subval['creator']), $searchQueryLower) !== false) {
                                $creatorMatches[] = $subval['creator'];
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
      <style>
        body {
          background: radial-gradient(ellipse at top, #0f172a, #0b1120);
          font-family: 'Inter', sans-serif;
          color: white;
        }
        h1 {
          font-size: 2.5rem;
          font-weight: bold;
          margin-bottom: 1rem;
        }
        .custom-shadow {
          box-shadow: 0 0 16px rgba(59, 130, 246, 0.5);
          transition: box-shadow 0.3s ease, transform 0.2s ease;
        }
        .custom-shadow:hover {
          box-shadow: 0 0 25px rgba(59, 130, 246, 0.8);
          transform: translateY(-2px);
        }
      </style>
    </head>
    <body class="p-8">
      <h1>Search Results</h1>

      <?php if (!empty($searchQuery)): ?>
        <p class="mb-4 text-lg">Query: <span class="text-blue-400"><?= htmlspecialchars($searchQuery) ?></span></p>
      <?php endif; ?>

      <?php if (!empty($datetime)): ?>
        <p class="mb-4 text-lg">Date: <span class="text-blue-400"><?= htmlspecialchars($datetime) ?></span></p>
      <?php endif; ?>

      <?php if ($titleMatches): ?>
        <h2 class="text-xl font-semibold mt-6 mb-2">Title Matches:</h2>
        <?php foreach ($titleMatches as $title): ?>
          <a class="block text-blue-400 hover:underline text-lg" href="../q&a/q&a.php?questionName=<?= urlencode($title) ?>"><?= htmlspecialchars($title) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($textMatches): ?>
        <h2 class="text-xl font-semibold mt-6 mb-2">Body Text Matches:</h2>
        <?php foreach ($textMatches as $text): ?>
          <a class="block text-blue-400 hover:underline text-lg" href="../q&a/q&a.php?questionName=<?= urlencode($text) ?>"><?= htmlspecialchars($text) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($creatorMatches): ?>
        <h2 class="text-xl font-semibold mt-6 mb-2">Creator Matches:</h2>
        <?php foreach ($creatorMatches as $creator): ?>
          <a class="block text-blue-400 hover:underline text-lg" href="../q&a/q&a.php?questionName=<?= urlencode($creator) ?>"><?= htmlspecialchars($creator) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($dateMatches): ?>
        <h2 class="text-xl font-semibold mt-6 mb-2">Date Matches:</h2>
        <?php foreach ($dateMatches as $title): ?>
          <a class="block text-blue-400 hover:underline text-lg" href="../q&a/q&a.php?questionName=<?= urlencode($title) ?>"><?= htmlspecialchars($title) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!$titleMatches && !$textMatches && !$creatorMatches && !$dateMatches): ?>
        <p class="mt-6 text-red-400">No matching results found.</p>
      <?php endif; ?>

    </body>
    </html>
    <?php

} catch (Exception $e) {
    echo "<p class='text-red-500'>Search failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>