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

try {
    // Call the API to search for questions
    $results = $api->searchQuestions($params);

    echo "<h2>Search Results for '" . htmlspecialchars($searchQuery) . "'</h2>";

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
            }
        }
    }

    // Display the results for each match type
    if (count($titleMatches) > 0) {
        echo '<h3>Titles:</h3>';
        foreach ($titleMatches as $title) {
            echo htmlspecialchars($title) . '<br>';
        }
    }
    if (count($textMatches) > 0) {
        echo '<h3>Body Text:</h3>';
        foreach ($textMatches as $text) {
            echo htmlspecialchars($text) . '<br>';
        }
    }
    if (count($creatorMatches) > 0) {
        echo '<h3>Creator:</h3>';
        foreach ($creatorMatches as $creator) {
            // Only display the creator name, since title is not available in this context
            echo htmlspecialchars($creator) . '<br>';
        }
    }
    // If no matches found, display a message
    if (count($titleMatches) === 0 && count($textMatches) === 0 && count($creatorMatches) === 0) {
        echo 'No matching titles, creator, or body text found.';
    }

} catch (Exception $e) {
    // Display error if API call fails
    echo "Search failed: " . $e->getMessage();
}
?>