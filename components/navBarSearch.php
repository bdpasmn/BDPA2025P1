<?php
// Start the session for user authentication
session_start();
// Include API key and API wrapper
require_once '../../api/key.php';
require_once '../../api/api.php';

// Initialize the API with the provided key
$api = new qOverflowAPI(API_KEY);

// Get the search query from GET parameters, default to 'php' if not set
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : 'php';
$params = ['query' => $searchQuery];

// Check if query is in YYYY-MM-DD format
$dateMatches = [];
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $searchQuery)) {
    $date = DateTime::createFromFormat('Y-m-d', $searchQuery);
    if ($date) {
        $startOfDay = $date->setTime(0, 0, 0)->getTimestamp() * 1000;
        $endOfDay = $date->setTime(23, 59, 59)->getTimestamp() * 1000 + 999;
        $params['startTime'] = $startOfDay;
        $params['endTime'] = $endOfDay;
    }
}

try {
    // Call the API to search for questions
    $results = $api->searchQuestions($params);

    echo "<h1>Search Results for '" . htmlspecialchars($searchQuery) . "'</h1>";

    // Prepare variables for matches
    $searchQueryLower = strtolower($searchQuery);
    $titleMatches = [];
    $textMatches = [];
    $creatorMatches = [];

    // Check if results are an array
    if (is_array($results)) {
        foreach ($results as $key => $question) {
            if (is_array($question)) {
                // Check for title match
                if (isset($question['title']) && strpos(strtolower($question['title']), $searchQueryLower) !== false) {
                    $titleMatches[] = $question['title'];
                }
                // Check for text/body match
                if (isset($question['text']) && strpos(strtolower($question['text']), $searchQueryLower) !== false) {
                    $textMatches[] = $question['text'];
                }
                // Check for creator match
                if (isset($question['creator']) && strpos(strtolower($question['creator']), $searchQueryLower) !== false) {
                    $creatorMatches[] = $question['creator'];
                }
                // Check nested arrays for matches
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
                // Date match (if query is date)
                if (isset($params['startTime']) && isset($question['time'])) {
                    if ($question['time'] >= $params['startTime'] && $question['time'] <= $params['endTime']) {
                        $dateMatches[] = $question['title'];
                    }
                }
            }
        }
    }

    // Display the results for each match type
    if (count($titleMatches) > 0) {
        echo '<h1>Titles:</h1>';
        foreach ($titleMatches as $title) {
            echo '<a class="text-2xl font-bold text-blue-400 hover:underline" href="../q&a/q&a.php?questionName=' . urlencode($title) . '">' . htmlspecialchars($title) . '</a><br>';
        }
    }
    if (count($textMatches) > 0) {
        echo '<h1>Body Text:</h1>';
        foreach ($textMatches as $text) {
            echo '<a class="text-2xl font-bold text-blue-400 hover:underline" href="../q&a/q&a.php?questionName=' . urlencode($text) . '">' . htmlspecialchars($text) . '</a><br>';
        }
    }
    if (count($creatorMatches) > 0) {
        echo '<h1>Creator:</h1>';
        foreach ($creatorMatches as $creator) {
            // Only display the creator name, since title is not available in this context
            echo '<a class="text-2xl font-bold text-blue-400 hover:underline" href="../q&a/q&a.php?questionName=' . urlencode($creator) . '">' . htmlspecialchars($creator) . '</a><br>';
        }
    }
    if (count($dateMatches) > 0) {
        echo '<h1>Date Matches (' . htmlspecialchars($searchQuery) . '):</h1>';
        foreach ($dateMatches as $title) {
            echo '<a class="text-2xl font-bold text-blue-400 hover:underline" href="../q&a/q&a.php?questionName=' . urlencode($title) . '">' . htmlspecialchars($title) . '</a><br>';
        }
    }
    // If no matches found, display a message
    if (count($titleMatches) === 0 && count($textMatches) === 0 && count($creatorMatches) === 0 && count($dateMatches) === 0) {
        echo 'No matching titles, creator, body text, or dates found.';
    }

} catch (Exception $e) {
    // Display error if API call fails
    echo "Search failed: " . $e->getMessage();
}
?>
<html>
    <head>
          <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>qOverflow — Logged-In Navbar</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background: radial-gradient(ellipse at top, #0f172a, #0b1120);
      font-family: 'Inter', sans-serif;
      color: white;
      size: 14px;
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
</html>