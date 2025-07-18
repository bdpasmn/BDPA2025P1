
<?php

session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

$api = new qOverflowAPI(API_KEY);

// Set current user first line should be removed once windows database works 
$CURRENT_USER = 'test_user';
$_SESSION['username'] = $CURRENT_USER;
$questionName = $_GET['questionName'] ?? '123';

// Initialize data structures with per-question isolation
if (!isset($_SESSION['qa_votes'])) {
    $_SESSION['qa_votes'] = [];
}

if (!isset($_SESSION['qa_answers'])) {
    $_SESSION['qa_answers'] = []; 
}

if (!isset($_SESSION['qa_comments'])) {
    $_SESSION['qa_comments'] = [];
}

// Initialize viewed questions tracking
if (!isset($_SESSION['viewed_questions'])) {
    $_SESSION['viewed_questions'] = [];
}

// Initialize session view counts for questions
if (!isset($_SESSION['question_views'])) {
    $_SESSION['question_views'] = [];
}

if (!isset($_SESSION['next_answer_id'])) {
    $_SESSION['next_answer_id'] = 1;
}

$message = '';
$messageType = 'success';
$question = null;
$answers = [];
$questionComments = [];
$actualQuestionId = null;
$reloadData = false;

// Enhanced debugging function
function debugLog($message, $data = null) {
    $logMessage = "[DEBUG] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage);
}

// Helper function to get question-specific session key
function getQuestionSessionKey($questionId, $type) {
    return $type . '_' . $questionId;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Debug: Log all POST data
    debugLog("POST Request received", $_POST);
    
    try {
        $actualQuestionId = $_POST['question_id'] ?? $questionName;
        debugLog("Processing action: $action with question_id: $actualQuestionId");
        
        switch ($action) {
            case 'vote_question':
                $operation = $_POST['operation'] ?? '';
                if (!in_array($operation, ['upvote', 'downvote'])) {
                    throw new Exception('Invalid vote operation');
                }
                
                $questionKey = getQuestionSessionKey($actualQuestionId, 'question_votes');
                if (!isset($_SESSION['qa_votes'][$questionKey])) {
                    $_SESSION['qa_votes'][$questionKey] = [];
                }
                
                // Store vote
                $_SESSION['qa_votes'][$questionKey][$CURRENT_USER] = ($operation === 'upvote') ? 1 : -1;
                
                debugLog("Question vote stored", $_SESSION['qa_votes'][$questionKey]);
                $message = "Question " . $operation . " successful!";
                break;
                
            case 'vote_answer':
                $answerId = $_POST['answer_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                if (!in_array($operation, ['upvote', 'downvote'])) {
                    throw new Exception('Invalid vote operation');
                }
                
                $answerKey = getQuestionSessionKey($actualQuestionId, 'answer_votes_' . $answerId);
                if (!isset($_SESSION['qa_votes'][$answerKey])) {
                    $_SESSION['qa_votes'][$answerKey] = [];
                }
                
                // Store vote
                $_SESSION['qa_votes'][$answerKey][$CURRENT_USER] = ($operation === 'upvote') ? 1 : -1;
                
                debugLog("Answer vote stored", $_SESSION['qa_votes'][$answerKey]);
                $message = "Answer " . $operation . " successful!";
                break;

            case 'vote_question_comment':
                $commentId = $_POST['comment_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                
                $questionCommentsKey = getQuestionSessionKey($actualQuestionId, 'question_comments');
                if (!isset($_SESSION['qa_comments'][$questionCommentsKey])) {
                    $_SESSION['qa_comments'][$questionCommentsKey] = [];
                }
                
                // Find and update comment
                foreach ($_SESSION['qa_comments'][$questionCommentsKey] as &$comment) {
                    if ($comment['id'] === $commentId) {
                        if (!isset($comment['votes'])) {
                            $comment['votes'] = ['upvotes' => 0, 'downvotes' => 0];
                        }
                        if ($operation === 'upvote') {
                            $comment['votes']['upvotes']++;
                        } else {
                            $comment['votes']['downvotes']++;
                        }
                        break;
                    }
                }
                
                $message = "Question comment " . $operation . " successful!";
                break;

            case 'vote_answer_comment':
                $commentId = $_POST['comment_id'] ?? '';
                $answerId = $_POST['answer_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                
                $answerCommentsKey = getQuestionSessionKey($actualQuestionId, 'answer_comments_' . $answerId);
                if (!isset($_SESSION['qa_comments'][$answerCommentsKey])) {
                    $_SESSION['qa_comments'][$answerCommentsKey] = [];
                }
                
                // Find and update comment 
                foreach ($_SESSION['qa_comments'][$answerCommentsKey] as &$comment) {
                    if ($comment['id'] === $commentId) {
                        if (!isset($comment['votes'])) {
                            $comment['votes'] = ['upvotes' => 0, 'downvotes' => 0];
                        }
                        if ($operation === 'upvote') {
                            $comment['votes']['upvotes']++;
                        } else {
                            $comment['votes']['downvotes']++;
                        }
                        break;
                    }
                }
                
                $message = "Answer comment " . $operation . " successful!";
                break;
                
            case 'add_question_comment':
                $text = trim($_POST['comment_text'] ?? '');
                if ($text === '') throw new Exception('Comment text required');
                if (strlen($text) > 150) throw new Exception('Comment too long');
                
                $questionCommentsKey = getQuestionSessionKey($actualQuestionId, 'question_comments');
                if (!isset($_SESSION['qa_comments'][$questionCommentsKey])) {
                    $_SESSION['qa_comments'][$questionCommentsKey] = [];
                }
                
                // Add comment 
                $_SESSION['qa_comments'][$questionCommentsKey][] = [
                    'id' => uniqid(),
                    'text' => $text,
                    'creator' => $CURRENT_USER,
                    'created' => date('Y-m-d H:i:s'),
                    'votes' => ['upvotes' => 0, 'downvotes' => 0]
                ];
                
                debugLog("Question comment added", $_SESSION['qa_comments'][$questionCommentsKey]);
                $message = "Comment added to question!";
                break;
                
            case 'add_answer_comment':
                $answerId = $_POST['answer_id'] ?? '';
                $text = trim($_POST['comment_text'] ?? '');
                if ($text === '') throw new Exception('Comment text required');
                if (strlen($text) > 150) throw new Exception('Comment too long');
                
                $answerCommentsKey = getQuestionSessionKey($actualQuestionId, 'answer_comments_' . $answerId);
                if (!isset($_SESSION['qa_comments'][$answerCommentsKey])) {
                    $_SESSION['qa_comments'][$answerCommentsKey] = [];
                }
                
                // Add comment to session
                $_SESSION['qa_comments'][$answerCommentsKey][] = [
                    'id' => uniqid(),
                    'text' => $text,
                    'creator' => $CURRENT_USER,
                    'created' => date('Y-m-d H:i:s'),
                    'votes' => ['upvotes' => 0, 'downvotes' => 0]
                ];
                
                debugLog("Answer comment added", $_SESSION['qa_comments'][$answerCommentsKey]);
                $message = "Comment added to answer!";
                break;
                
            case 'add_answer':
                $text = trim($_POST['answer_text'] ?? '');
                if ($text === '') throw new Exception('Answer text required');
                if (strlen($text) > 3000) throw new Exception('Answer too long');
                
                $answersKey = getQuestionSessionKey($actualQuestionId, 'answers');
                if (!isset($_SESSION['qa_answers'][$answersKey])) {
                    $_SESSION['qa_answers'][$answersKey] = [];
                }
                
                // Generate a unique answer ID that's guaranteed to be unique
                $answerId = $actualQuestionId . '_' . time() . '_' . uniqid();
                
                // Create answer with immutable data
                $answerData = [
                    'answer_id' => $answerId,
                    'text' => $text,
                    'creator' => $CURRENT_USER,
                    'created' => date('Y-m-d H:i:s'),
                    'points' => 0,
                    'accepted' => false,
                    'upvotes' => 0,
                    'downvotes' => 0
                ];
                
                // Store answer with unique key
                $_SESSION['qa_answers'][$answersKey][$answerId] = $answerData;
                
                debugLog("Answer added", $answerData);
                $message = "Answer submitted successfully!";
                break;
                
            case 'accept_answer':
                $answerId = $_POST['answer_id'] ?? '';
                $answersKey = getQuestionSessionKey($actualQuestionId, 'answers');
                
                // Check if answer exists in session data
                $answerExists = isset($_SESSION['qa_answers'][$answersKey][$answerId]);
                
                if ($answerExists) {
                    // Unaccept all answers first
                    foreach ($_SESSION['qa_answers'][$answersKey] as &$ans) {
                        $ans['accepted'] = false;
                    }
                    $_SESSION['qa_answers'][$answersKey][$answerId]['accepted'] = true;
                    $message = "Answer accepted!";
                } else {
                    // Try API call for answers
                    $result = $api->updateAnswer($actualQuestionId, $answerId, ['accepted' => true]);
                    
                    if (isset($result['error'])) {
                        if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                            $message = "Answer accepted! (Simulated - no database)";
                        } else {
                            throw new Exception($result['error']);
                        }
                    } else {
                        $message = "Answer accepted!";
                    }
                }
                break;
                
            default:
                throw new Exception('Unknown action: ' . $action);
        }
    } catch (Exception $ex) {
        $message = 'Error: ' . $ex->getMessage();
        $messageType = 'error';
        debugLog("Exception caught", ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?questionName=" . urlencode($questionName) . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit;
}

// Show message from GET
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}

// Get question data (keep original API-based approach)
try {
    $questionResult = $api->getQuestion($questionName);
    
    debugLog("Question lookup", ['questionName' => $questionName, 'result' => $questionResult]);
    
    if (!$questionResult || isset($questionResult['error'])) {
        // Try searching for questions by title if direct ID lookup fails
        $searchResult = $api->searchQuestions(['limit' => 50]);
        debugLog("Search fallback", $searchResult);
        
        if (isset($searchResult['questions']) && is_array($searchResult['questions'])) {
            foreach ($searchResult['questions'] as $q) {
                if ($q['title'] === $questionName || $q['question_id'] === $questionName) {
                    $question = $q;
                    $actualQuestionId = $q['question_id'];
                    break;
                }
            }
        }
        
        if (!$question) {
            throw new Exception('Question not found. Question Name: ' . $questionName);
        }
    } else {
        $question = $questionResult;
        $actualQuestionId = $question['question_id'] ?? $questionName;
    }
    
    debugLog("Final question data", ['actualQuestionId' => $actualQuestionId, 'question' => $question]);
    
    // Track view for this question in this session
    if (!in_array($actualQuestionId, $_SESSION['viewed_questions'])) {
        $_SESSION['viewed_questions'][] = $actualQuestionId;
        
        // Initialize view count for this question if not exists
        if (!isset($_SESSION['question_views'][$actualQuestionId])) {
            $_SESSION['question_views'][$actualQuestionId] = 0;
        }
        
        // Increment view count
        $_SESSION['question_views'][$actualQuestionId]++;
        
        debugLog("View tracked for question", ['questionId' => $actualQuestionId, 'totalViews' => $_SESSION['question_views'][$actualQuestionId]]);
    }
    
    // Get API answers
    $answersResult = $api->getAnswers($actualQuestionId);
    debugLog("Answers lookup", $answersResult);
    
    $apiAnswers = [];
    if (!isset($answersResult['error'])) {
        $apiAnswers = $answersResult['answers'] ?? [];
    }
    
    // Merge API answers with session answers
    $answers = [];
    
    // Add API answers
    foreach ($apiAnswers as $answer) {
        $answerId = $answer['answer_id'];
        $answerKey = getQuestionSessionKey($actualQuestionId, 'answer_votes_' . $answerId);
        
        // Apply votes if they exist
        if (isset($_SESSION['qa_votes'][$answerKey])) {
            $votes = array_sum($_SESSION['qa_votes'][$answerKey]);
            $answer['upvotes'] = max(0, ($answer['upvotes'] ?? 0) + $votes);
        }
        $answers[] = $answer;
    }
    
    // Add session answers for this specific question
    $answersKey = getQuestionSessionKey($actualQuestionId, 'answers');
    if (isset($_SESSION['qa_answers'][$answersKey])) {
        foreach ($_SESSION['qa_answers'][$answersKey] as $sessionAnswer) {
            // Calculate points from session votes
            $answerId = $sessionAnswer['answer_id'];
            $answerKey = getQuestionSessionKey($actualQuestionId, 'answer_votes_' . $answerId);
            
            if (isset($_SESSION['qa_votes'][$answerKey])) {
                $sessionAnswer['points'] = array_sum($_SESSION['qa_votes'][$answerKey]);
                $sessionAnswer['upvotes'] = max(0, $sessionAnswer['points']);
            }
            $answers[] = $sessionAnswer;
        }
    }
    
    // Get question comments 
    $commentsResult = $api->getQuestionComments($actualQuestionId);
    debugLog("Question comments lookup", $commentsResult);
    
    $questionComments = [];
    if (!isset($commentsResult['error'])) {
        $questionComments = $commentsResult['comments'] ?? [];
    }
    
    // Add session comments for this specific question
    $questionCommentsKey = getQuestionSessionKey($actualQuestionId, 'question_comments');
    if (isset($_SESSION['qa_comments'][$questionCommentsKey])) {
        $questionComments = array_merge($questionComments, $_SESSION['qa_comments'][$questionCommentsKey]);
    }
    
    // Get answer comments for each answer
    foreach ($answers as &$answer) {
        $answerId = $answer['answer_id'];
        
        // Get API comments
        $answerCommentsResult = $api->getAnswerComments($actualQuestionId, $answerId);
        debugLog("Answer comments lookup for " . $answerId, $answerCommentsResult);
        
        $answer['comments'] = [];
        if (!isset($answerCommentsResult['error'])) {
            $answer['comments'] = $answerCommentsResult['comments'] ?? [];
        }
        
        // Add session comments for this specific answer
        $answerCommentsKey = getQuestionSessionKey($actualQuestionId, 'answer_comments_' . $answerId);
        if (isset($_SESSION['qa_comments'][$answerCommentsKey])) {
            $answer['comments'] = array_merge($answer['comments'], $_SESSION['qa_comments'][$answerCommentsKey]);
        }
    }
    
} catch (Exception $ex) {
    $message = 'Error loading question: ' . $ex->getMessage();
    $messageType = 'error';
    debugLog("Exception loading question", ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
}

// Helper functions
function formatDate($timestamp) {
    $timezone = new DateTimeZone('America/Chicago'); // Central Time Zone

    if (is_numeric($timestamp)) {
        if ($timestamp > 1000000000000) {
            $timestamp = $timestamp / 1000;
        }
        $date = new DateTime("@$timestamp"); // "@" treats it as a Unix timestamp
    } else {
        $date = new DateTime($timestamp);
    }

    $date->setTimezone($timezone);
    return $date->format('M j, Y g:i A');
}

function renderMarkdown($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`(.*?)`/', '<code class="bg-gray-600 px-1 rounded">$1</code>', $text);
    $text = preg_replace('/```(.*?)```/s', '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>', $text);
    $text = nl2br($text);
    return $text;
}

function canUserInteract($status) {
    return ($status === 'open' || !isset($status));
}

// Function to get comment votes
function getCommentVotes($comment) {
    if (isset($comment['votes'])) {
        return $comment['votes'];
    }
    return ['upvotes' => 0, 'downvotes' => 0];
}

// Calculate question points from session votes
$questionPoints = ($question['upvotes'] ?? 0);
$questionKey = getQuestionSessionKey($actualQuestionId, 'question_votes');
if (isset($_SESSION['qa_votes'][$questionKey])) {
    $sessionVotes = array_sum($_SESSION['qa_votes'][$questionKey]);
    $questionPoints += $sessionVotes;
}

// Calculate total views 
$totalViews = ($question['views'] ?? 0) + ($_SESSION['question_views'][$actualQuestionId] ?? 0);

// Sort answers: accepted first, then by points desc
if (!empty($answers)) {
    usort($answers, function($a, $b) {
        $aAccepted = $a['accepted'] ?? false;
        $bAccepted = $b['accepted'] ?? false;
        
        if ($aAccepted && !$bAccepted) return -1;
        if (!$aAccepted && $bAccepted) return 1;
        
        $aPoints = $a['upvotes'] ?? $a['points'] ?? 0;
        $bPoints = $b['upvotes'] ?? $b['points'] ?? 0;
        
        return $bPoints - $aPoints;
    });
}

if (!$question) {
    header("HTTP/1.0 404 Not Found");
    echo "Question not found";
    exit;
}

?>

<!DOCTYPE html>
<html lang="en" class="bg-gray-900 text-gray-300">
<head>
    
    <meta charset="UTF-8" />
    <title><?php echo htmlspecialchars($question['title'] ?? 'Question'); ?> - Q&A</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tab-button {
            @apply px-4 py-2 bg-gray-700 text-gray-300 border-b-2 border-transparent hover:bg-gray-600 transition-colors;
        }
        .tab-button.active {
            @apply bg-gray-600 border-blue-500 text-white;
        }
        .tab-content {
            @apply hidden;
        }
        .tab-content.active {
            @apply block;
        }
        .markdown-preview {
            @apply bg-gray-700 p-4 rounded border border-gray-600 min-h-32 max-h-64 overflow-y-auto;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-300 font-sans">
        <?php include '../../components/navBarLogIn.php'; ?>

    <div class="max-w-4xl mx-auto p-6">
<?php if ($message): ?>
    <div class="fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 <?php echo $messageType === 'success' ? 'bg-green-600' : 'bg-red-600'; ?> text-white" id="status-message">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <script>
        setTimeout(() => {
            const msg = document.getElementById('status-message');
            if (msg) {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.3s ease';
                setTimeout(() => { msg.style.display = 'none'; }, 300);
            }
        }, 3000);
    </script>
<?php endif; ?>

<section class="bg-gray-800 rounded-lg p-6 mb-10 shadow-lg">
    <div class="flex justify-between items-center mb-3">
        <div class="space-x-4 text-sm text-gray-400">
            <span>Asked: <time><?php echo formatDate($question['createdAt'] ?? time()); ?></time></span>
            <span>Views: <?php echo $totalViews; ?></span>
            <span>Points: <strong><?php echo $questionPoints; ?></strong></span>
        </div>
        <div>
            <strong><?php echo htmlspecialchars($question['creator'] ?? 'Unknown'); ?></strong>
        </div>
    </div>

    <h1 class="text-white text-3xl font-bold mb-5"><?php echo htmlspecialchars($question['title'] ?? 'Untitled Question'); ?></h1>

    <article class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-8 leading-relaxed text-gray-300 shadow-inner">
        <?php echo renderMarkdown($question['text'] ?? 'No question text available.'); ?>
    </article>

    <div class="flex space-x-4 mb-8">
        <form method="POST">
            <input type="hidden" name="action" value="vote_question">
            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
            <input type="hidden" name="operation" value="upvote">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white">▲ Upvote</button>
        </form>
        <form method="POST">
            <input type="hidden" name="action" value="vote_question">
            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
            <input type="hidden" name="operation" value="downvote">
            <button type="submit" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-white">▼ Downvote</button>
        </form>
    </div>

    <h2 class="text-lg font-semibold mb-3">Comments</h2>
    <ul class="mb-6">
        <?php if (!empty($questionComments)): ?>
            <?php foreach ($questionComments as $comment): ?>
                <?php 
                $commentId = $comment['comment_id'] ?? $comment['id'] ?? uniqid();
                $votes = getCommentVotes($comment);
                ?>
                <li class="border-l-2 border-gray-600 pl-3 py-2 mb-2 bg-gray-750 rounded-r">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <span><?php echo htmlspecialchars($comment['text'] ?? ''); ?></span> — <em><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></em>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li class="text-gray-500">No comments yet.</li>
        <?php endif; ?>
    </ul>

    <?php if (canUserInteract($question['status'] ?? null)): ?>
    <form method="POST" class="mb-6">
        <input type="hidden" name="action" value="add_question_comment">
        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
        <div class="relative">
            <input type="text" name="comment_text" maxlength="150" required placeholder="Add a comment..." class="w-full p-2 rounded bg-gray-700 text-gray-300 border border-gray-600 pr-12" oninput="updateCommentCounter(this, 'question-comment-counter')">
            <span id="question-comment-counter" class="absolute right-2 top-2 text-xs text-gray-500">150</span>
        </div>
        <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded text-white">Add Comment</button>
    </form>
    <?php endif; ?>
</section>

<section>
    <h2 class="text-white text-3xl font-bold mb-8">Answers (<?php echo count($answers); ?>)</h2>

    <?php if (count($answers) === 0): ?>
        <p class="text-gray-400 mb-8">No answers yet. Be the first to answer!</p>
    <?php endif; ?>

    <?php foreach ($answers as $answer): ?>
        <article class="bg-gray-800 rounded-lg p-6 mb-8 shadow-lg border <?php echo ($answer['accepted'] ?? false) ? 'border-green-600' : 'border-gray-600'; ?>">
            <?php if ($answer['accepted'] ?? false): ?>
                <div class="text-green-400 font-semibold mb-2">✓ Accepted Answer</div>
            <?php endif; ?>
            <div class="flex justify-between items-center mb-4">
                <div>Points: <strong><?php echo $answer['upvotes'] ?? $answer['points'] ?? 0; ?></strong></div>
                <div><strong><?php echo htmlspecialchars($answer['creator'] ?? 'Unknown'); ?></strong></div>
                <time class="text-gray-400 text-sm"><?php echo formatDate($answer['createdAt'] ?? $answer['created'] ?? time()); ?></time>
            </div>

            <div class="prose prose-invert mb-4"><?php echo renderMarkdown($answer['text'] ?? ''); ?></div>

            <div class="flex space-x-4 mb-4">
                <form method="POST">
                    <input type="hidden" name="action" value="vote_answer">
                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                    <input type="hidden" name="operation" value="upvote">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-white">▲ Upvote</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="vote_answer">
                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                    <input type="hidden" name="operation" value="downvote">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-white">▼ Downvote</button>
                </form>

                <?php if (($question['creator'] ?? '') === $CURRENT_USER && !($answer['accepted'] ?? false)): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="accept_answer">
                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-white">✓ Accept Answer</button>
                </form>
                <?php endif; ?>
            </div>

            <h3 class="font-semibold mb-2">Comments</h3>
            <ul class="mb-4">
                <?php if (!empty($answer['comments'])): ?>
                    <?php foreach ($answer['comments'] as $comment): ?>
                        <?php 
                        $commentId = $comment['comment_id'] ?? $comment['id'] ?? uniqid();
                        $votes = getCommentVotes($comment);
                        ?>
                        <li class="border-l-2 border-gray-600 pl-3 py-2 mb-2 bg-gray-750 rounded-r">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <span><?php echo htmlspecialchars($comment['text'] ?? ''); ?></span> — <em><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></em>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs text-gray-500"><?php echo $votes['upvotes'] - $votes['downvotes']; ?></span>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="vote_answer_comment">
                                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                        <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                                        <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                                        <input type="hidden" name="operation" value="upvote">
                                        <button type="submit" class="text-blue-400 hover:text-blue-300 text-xs">▲</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="vote_answer_comment">
                                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                        <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                                        <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                                        <input type="hidden" name="operation" value="downvote">
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-xs">▼</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-gray-500">No comments yet.</li>
                <?php endif; ?>
            </ul>

            <?php if (canUserInteract($question['status'] ?? null)): ?>
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="add_answer_comment">
                <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                <div class="relative">
                    <input type="text" name="comment_text" maxlength="150" required placeholder="Add a comment..." class="w-full p-2 rounded bg-gray-700 text-gray-300 border border-gray-600 pr-12" oninput="updateCommentCounter(this, 'answer-comment-counter-<?php echo htmlspecialchars($answer['answer_id']); ?>')">
                    <span id="answer-comment-counter-<?php echo htmlspecialchars($answer['answer_id']); ?>" class="absolute right-2 top-2 text-xs text-gray-500">150</span>
                </div>
                <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white">Add Comment</button>
            </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if (canUserInteract($question['status'] ?? null)): ?>
    <section class="bg-gray-800 rounded-lg p-6 shadow-lg">
        <h2 class="text-white text-xl font-bold mb-4">Your Answer</h2>
        
        <div class="mb-4">
            <div class="flex space-x-2 mb-2">
                <button class="tab-button active" onclick="switchTab('write')">Write</button>
                <button class="tab-button" onclick="switchTab('preview')">Preview</button>
            </div>
            
            <div id="write-tab" class="tab-content active">
                <form method="POST">
                    <input type="hidden" name="action" value="add_answer">
                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                    <div class="relative">
                        <textarea name="answer_text" id="answer-textarea" required maxlength="3000" placeholder="Write your answer here..." class="w-full h-64 p-4 rounded bg-gray-700 text-gray-300 border border-gray-600 resize-none" oninput="updateAnswerCounter(); updatePreview();"></textarea>
                        <span id="answer-counter" class="absolute bottom-2 right-2 text-xs text-gray-500">3000</span>
                    </div>
                    <div class="mt-4 flex justify-between items-center">
                        <div class="text-sm text-gray-400">
                            <strong>Formatting examples:</strong> Use **bold**, *italic*, `code`, or ```code blocks```
                        </div>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded text-white font-semibold">Post Your Answer</button>
                    </div>
                </form>
            </div>
            
            <div id="preview-tab" class="tab-content">
                <div id="preview-content" class="markdown-preview">
                    <em class="text-gray-500">Nothing to preview yet. Write something in the "Write" tab.</em>
                </div>
            </div>
        </div>
    </section>
    <?php else: ?>
    <div class="bg-gray-800 rounded-lg p-6 text-center">
        <p class="text-gray-400">This question is closed and no longer accepts new answers.</p>
    </div>
    <?php endif; ?>
</section>

<script>
function updateCommentCounter(input, counterId) {
    const counter = document.getElementById(counterId);
    if (counter) {
        const remaining = 150 - input.value.length;
        counter.textContent = remaining;
        counter.className = remaining < 10 ? 'absolute right-2 top-2 text-xs text-red-400' : 'absolute right-2 top-2 text-xs text-gray-500';
    }
}

function updateAnswerCounter() {
    const textarea = document.getElementById('answer-textarea');
    const counter = document.getElementById('answer-counter');
    if (textarea && counter) {
        const remaining = 3000 - textarea.value.length;
        counter.textContent = remaining;
        counter.className = remaining < 100 ? 'absolute bottom-2 right-2 text-xs text-red-400' : 'absolute bottom-2 right-2 text-xs text-gray-500';
    }
}

function switchTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`button[onclick="switchTab('${tab}')"]`).classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tab}-tab`).classList.add('active');
    
    // Update preview when switching to preview tab
    if (tab === 'preview') {
        updatePreview();
    }
}

function updatePreview() {
    const textarea = document.getElementById('answer-textarea');
    const preview = document.getElementById('preview-content');
    
    if (textarea && preview) {
        const text = textarea.value;
        if (text.trim() === '') {
            preview.innerHTML = '<em class="text-gray-500">Nothing to preview yet. Write something in the "Write" tab.</em>';
            return;
        }
        
        // Simple markdown rendering (matching PHP renderMarkdown function)
        let html = text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#x27;');
        
        // Apply markdown formatting
        html = html
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code class="bg-gray-600 px-1 rounded">$1</code>')
            .replace(/```(.*?)```/gs, '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>')
            .replace(/\n/g, '<br>');
        
        preview.innerHTML = html;
    }
}

// Initialize counters on page load
document.addEventListener('DOMContentLoaded', function() {
    updateAnswerCounter();
    
    // Initialize all comment counters
    document.querySelectorAll('input[name="comment_text"]').forEach(input => {
        const form = input.closest('form');
        if (form) {
            const action = form.querySelector('input[name="action"]').value;
            if (action === 'add_question_comment') {
                updateCommentCounter(input, 'question-comment-counter');
            } else if (action === 'add_answer_comment') {
                const answerId = form.querySelector('input[name="answer_id"]').value;
                updateCommentCounter(input, 'answer-comment-counter-' + answerId);
            }
        }
    });
});
</script>
</div>

</body>
</html>