<?php
session_start();
require './api.php';
require './key.php';

header('Content-Type: application/json');

$API = new qOverflowAPI($apiKey);
$action = $_GET['action'] ?? '';

if ($action == 'batch') {
    $idsParam = $_GET['ids'] ?? '';
    $ids = array_filter(explode(',', $idsParam));

    $result = [];

    foreach ($ids as $id) {
        try {
            $q = $API->getQuestion($id);
            $question = $q['question'] ?? [];
            
            $result[$id] = [
                'views' => $question['views'] ?? 0,
                'upvotes' => $question['upvotes'] ?? 0,
                'downvotes' => $question['downvotes'] ?? 0,
                'answers' => $question['answers'] ?? 0            
            ];
        } catch (Exception $e) {
            continue; 
        }
        sleep(0.5);
    }

    echo json_encode($result);
    exit;
}

echo json_encode(['error' => 'Invalid or missing action']);

?>