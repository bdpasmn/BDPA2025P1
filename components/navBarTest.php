<?php
session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

$api = new qOverflowAPI(API_KEY);

// Get the search query from GET, default to 'php' if not set
$searchQuery = isset($_GET['query']) ? trim($_GET['query']) : 'php';
$params = ['query' => $searchQuery];

try {
    $results = $api->searchQuestions($params);
    //dumps API results for debugging
    /*echo '<pre>';
    var_dump($results);
    echo '</pre>';*/

    echo "<h2>Search Results for '" . htmlspecialchars($searchQuery) . "'</h2>";
    echo '<h3>Titles:</h3>';
    $searchQueryLower = strtolower($searchQuery);
    $found = false;
    if (is_array($results)) {
        foreach ($results as $key => $question) {
            if (is_array($question) && isset($question['title'])) {
                if (strpos(strtolower($question['title']), $searchQueryLower) !== false) {
                    echo htmlspecialchars($question['title']) . '<br>';
                    $found = true;
                }
            } elseif (is_array($question)) {
                foreach ($question as $subkey => $subval) {
                    if (is_array($subval) && isset($subval['title'])) {
                        if (strpos(strtolower($subval['title']), $searchQueryLower) !== false) {
                            echo htmlspecialchars($subval['title']) . '<br>';
                            $found = true;
                        }
                    }
                }
            }
        }
    }
    if (!$found) {
        echo 'No matching titles found.';
    }
    echo '<hr><strong>Debug keys:</strong><br>';
    if (is_array($results)) {
        foreach ($results as $key => $val) {
            echo 'Key: ' . htmlspecialchars((string)$key) . ' | Type: ' . gettype($val) . '<br>';
        }
    }

} catch (Exception $e) {
    echo "Search failed: " . $e->getMessage();
}
?>