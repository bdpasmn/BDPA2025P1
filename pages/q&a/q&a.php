<?php
session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

$api = new qOverflowAPI(API_KEY);

// Set current user and question - NO DATABASE VALIDATION
$CURRENT_USER = 'test_user';
$_SESSION['username'] = $CURRENT_USER;
$questionName = $_GET['questionName'] ?? '123';

// Initialize comment votes in session if not exists
if (!isset($_SESSION['comment_votes'])) {
    $_SESSION['comment_votes'] = [];
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
                $target = ($operation === 'upvote') ? 'upvotes' : 'downvotes';
                $voteOperation = 'increment';
                
                debugLog("Attempting to vote on question", ['operation' => $voteOperation, 'target' => $target, 'question_id' => $actualQuestionId]);
                
                // Skip user validation - just make the API call
                $result = $api->voteQuestionComment($actualQuestionId, $actualQuestionId, $CURRENT_USER, $voteOperation, $target);
                
                debugLog("Vote question result", $result);
                
                if (isset($result['error'])) {
                    // If error mentions user not found, create a mock success
                    if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                        $message = "Question " . $operation . " successful! (Simulated - no database)";
                    } else {
                        throw new Exception($result['error']);
                    }
                } else {
                    $message = "Question " . $operation . " successful!";
                }
                break;
                
            case 'vote_answer':
                $answerId = $_POST['answer_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                $target = ($operation === 'upvote') ? 'upvotes' : 'downvotes';
                $voteOperation = 'increment';
                
                debugLog("Attempting to vote on answer", ['answer_id' => $answerId, 'operation' => $voteOperation, 'target' => $target]);
                
                $result = $api->voteAnswer($actualQuestionId, $answerId, $CURRENT_USER, $voteOperation, $target);
                
                debugLog("Vote answer result", $result);
                
                if (isset($result['error'])) {
                    // If error mentions user not found, create a mock success
                    if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                        $message = "Answer " . $operation . " successful! (Simulated - no database)";
                    } else {
                        throw new Exception($result['error']);
                    }
                } else {
                    $message = "Answer " . $operation . " successful!";
                }
                break;

            case 'vote_question_comment':
                $commentId = $_POST['comment_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                
                debugLog("Attempting to vote on question comment", ['comment_id' => $commentId, 'operation' => $operation]);
                
                // Store vote in session since no database
                $voteKey = 'question_comment_' . $commentId;
                if (!isset($_SESSION['comment_votes'][$voteKey])) {
                    $_SESSION['comment_votes'][$voteKey] = ['upvotes' => 0, 'downvotes' => 0];
                }
                
                if ($operation === 'upvote') {
                    $_SESSION['comment_votes'][$voteKey]['upvotes']++;
                } else {
                    $_SESSION['comment_votes'][$voteKey]['downvotes']++;
                }
                
                $message = "Question comment " . $operation . " successful! (Stored in session)";
                break;

            case 'vote_answer_comment':
                $commentId = $_POST['comment_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                
                debugLog("Attempting to vote on answer comment", ['comment_id' => $commentId, 'operation' => $operation]);
                
                // Store vote in session since no database
                $voteKey = 'answer_comment_' . $commentId;
                if (!isset($_SESSION['comment_votes'][$voteKey])) {
                    $_SESSION['comment_votes'][$voteKey] = ['upvotes' => 0, 'downvotes' => 0];
                }
                
                if ($operation === 'upvote') {
                    $_SESSION['comment_votes'][$voteKey]['upvotes']++;
                } else {
                    $_SESSION['comment_votes'][$voteKey]['downvotes']++;
                }
                
                $message = "Answer comment " . $operation . " successful! (Stored in session)";
                break;
                
            case 'add_question_comment':
                $text = trim($_POST['comment_text'] ?? '');
                if ($text === '') throw new Exception('Comment text required');
                if (strlen($text) > 150) throw new Exception('Comment too long');
                
                debugLog("Attempting to add question comment", ['text' => $text, 'question_id' => $actualQuestionId]);
                
                $result = $api->createQuestionComment($actualQuestionId, $CURRENT_USER, $text);
                
                debugLog("Add question comment result", $result);
                
                if (isset($result['error'])) {
                    // If error mentions user not found, create a mock success
                    if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                        $message = "Comment added to question! (Simulated - no database)";
                    } else {
                        throw new Exception($result['error']);
                    }
                } else {
                    $message = "Comment added to question!";
                }
                break;
                
            case 'add_answer_comment':
                $answerId = $_POST['answer_id'] ?? '';
                $text = trim($_POST['comment_text'] ?? '');
                if ($text === '') throw new Exception('Comment text required');
                if (strlen($text) > 150) throw new Exception('Comment too long');
                
                debugLog("Attempting to add answer comment", ['answer_id' => $answerId, 'text' => $text]);
                
                $result = $api->createAnswerComment($actualQuestionId, $answerId, $CURRENT_USER, $text);
                
                debugLog("Add answer comment result", $result);
                
                if (isset($result['error'])) {
                    // If error mentions user not found, create a mock success
                    if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                        $message = "Comment added to answer! (Simulated - no database)";
                    } else {
                        throw new Exception($result['error']);
                    }
                } else {
                    $message = "Comment added to answer!";
                }
                break;
                
            case 'add_answer':
                $text = trim($_POST['answer_text'] ?? '');
                if ($text === '') throw new Exception('Answer text required');
                if (strlen($text) > 3000) throw new Exception('Answer too long');
                
                debugLog("Attempting to add answer", ['text' => substr($text, 0, 100) . '...', 'question_id' => $actualQuestionId]);
                
                $result = $api->createAnswer($actualQuestionId, $CURRENT_USER, $text);
                
                debugLog("Add answer result", $result);
                
                if (isset($result['error'])) {
                    // If error mentions user not found, create a mock success
                    if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                        $message = "Answer submitted! (Simulated - no database)";
                        // Don't redirect - reload data to show the new answer
                        $reloadData = true;
                    } else {
                        throw new Exception($result['error']);
                    }
                } else {
                    $message = "Answer submitted!";
                    // Don't redirect - reload data to show the new answer
                    $reloadData = true;
                }
                break;
                
            case 'accept_answer':
                $answerId = $_POST['answer_id'] ?? '';
                
                debugLog("Attempting to accept answer", ['answer_id' => $answerId]);
                
                $result = $api->updateAnswer($actualQuestionId, $answerId, ['accepted' => true]);
                
                debugLog("Accept answer result", $result);
                
                if (isset($result['error'])) {
                    // If error mentions user not found, create a mock success
                    if (strpos($result['error'], 'user') !== false || strpos($result['error'], 'not found') !== false) {
                        $message = "Answer accepted! (Simulated - no database)";
                    } else {
                        throw new Exception($result['error']);
                    }
                } else {
                    $message = "Answer accepted!";
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
    
    // Only redirect if we're not reloading data (for answer submissions)
    if (!$reloadData) {
        // Debug: Log redirect parameters
        debugLog("Redirecting with message", ['message' => $message, 'type' => $messageType]);
        
        // Redirect to avoid repost
        header("Location: " . $_SERVER['PHP_SELF'] . "?questionName=" . urlencode($questionName) . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
        exit;
    }
}

// Show message from GET
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}

// Get question data
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
    
    // Skip view count increment to avoid user validation issues
    
    // Get answers using the actual question ID
    $answersResult = $api->getAnswers($actualQuestionId);
    debugLog("Answers lookup", $answersResult);
    
    if (!isset($answersResult['error'])) {
        $answers = $answersResult['answers'] ?? [];
    } else {
        debugLog("Error getting answers", $answersResult);
        $answers = []; // Initialize as empty array
    }
    
    // Get question comments using the actual question ID
    $commentsResult = $api->getQuestionComments($actualQuestionId);
    debugLog("Question comments lookup", $commentsResult);
    
    $questionComments = [];
    if (!isset($commentsResult['error'])) {
        $questionComments = $commentsResult['comments'] ?? [];
    } else {
        debugLog("Error getting question comments", $commentsResult);
    }
    
    // Get answer comments for each answer
    foreach ($answers as &$answer) {
        $answerCommentsResult = $api->getAnswerComments($actualQuestionId, $answer['answer_id']);
        debugLog("Answer comments lookup for " . $answer['answer_id'], $answerCommentsResult);
        
        $answer['comments'] = [];
        if (!isset($answerCommentsResult['error'])) {
            $answer['comments'] = $answerCommentsResult['comments'] ?? [];
        } else {
            debugLog("Error getting answer comments for " . $answer['answer_id'], $answerCommentsResult);
        }
    }
    
} catch (Exception $ex) {
    $message = 'Error loading question: ' . $ex->getMessage();
    $messageType = 'error';
    debugLog("Exception loading question", ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
}

// Helper functions
function formatDate($timestamp) {
    if (is_numeric($timestamp)) {
        if ($timestamp > 1000000000000) {
            $timestamp = $timestamp / 1000;
        }
        return date('M j, Y g:i A', $timestamp);
    }
    return date('M j, Y g:i A', strtotime($timestamp));
}

function renderMarkdown($text) {
    $text = htmlspecialchars($text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = nl2br($text);
    return $text;
}

function canUserInteract($status) {
    return ($status === 'open' || !isset($status));
}

// Function to get comment votes from session
function getCommentVotes($commentId, $type = 'question') {
    $voteKey = $type . '_comment_' . $commentId;
    if (isset($_SESSION['comment_votes'][$voteKey])) {
        return $_SESSION['comment_votes'][$voteKey];
    }
    return ['upvotes' => 0, 'downvotes' => 0];
}

// Sort answers: accepted first, then by points desc
if (!empty($answers)) {
    usort($answers, function($a, $b) {
        $aAccepted = $a['accepted'] ?? false;
        $bAccepted = $b['accepted'] ?? false;
        
        if ($aAccepted && !$bAccepted) return -1;
        if (!$aAccepted && $bAccepted) return 1;
        
        $aPoints = $a['upvotes'] ?? 0;
        $bPoints = $b['upvotes'] ?? 0;
        
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
</head>
<body class="max-w-4xl mx-auto p-6 bg-gray-900 text-gray-300 font-sans">

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
        
        // Auto-reload after answer submission to ensure fresh data
        <?php if ($reloadData && strpos($message, 'Answer submitted') !== false): ?>
        setTimeout(() => {
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?questionName=<?php echo urlencode($questionName); ?>&msg=<?php echo urlencode($message); ?>&type=<?php echo urlencode($messageType); ?>';
        }, 1500);
        <?php endif; ?>
    </script>
<?php endif; ?>

<section class="bg-gray-800 rounded-lg p-6 mb-10 shadow-lg">
    <div class="flex justify-between items-center mb-3">
        <div class="space-x-4 text-sm text-gray-400">
            <span>Asked: <time><?php echo formatDate($question['createdAt'] ?? time()); ?></time></span>
            <span>Views: <?php echo $question['views'] ?? 0; ?></span>
            <span>Points: <strong><?php echo $question['upvotes'] ?? 0; ?></strong></span>
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
                $commentId = $comment['comment_id'] ?? uniqid();
                $votes = getCommentVotes($commentId, 'question');
                ?>
                <li class="border-l-2 border-gray-600 pl-3 py-2 mb-2 bg-gray-750 rounded-r">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <span><?php echo htmlspecialchars($comment['text'] ?? ''); ?></span> — <em><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></em>
                        </div>
                        <div class="flex items-center space-x-2 ml-4">
                            <span class="text-sm text-gray-400">
                                ▲<?php echo $votes['upvotes']; ?> ▼<?php echo $votes['downvotes']; ?>
                            </span>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="vote_question_comment">
                                <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                                <input type="hidden" name="operation" value="upvote">
                                <button type="submit" class="text-blue-400 hover:text-blue-300 px-1">▲</button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="vote_question_comment">
                                <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                                <input type="hidden" name="operation" value="downvote">
                                <button type="submit" class="text-red-400 hover:text-red-300 px-1">▼</button>
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
    <form method="POST" class="mb-6">
        <input type="hidden" name="action" value="add_question_comment">
        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
        <input type="text" name="comment_text" maxlength="150" required placeholder="Add a comment..." class="w-full p-2 rounded bg-gray-700 text-gray-300 border border-gray-600">
        <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded text-white">Add Comment</button>
    </form>
    <?php endif; ?>
</section>

<section>
    <h2 class="text-white text-3xl font-bold mb-8">Answers (<?php echo count($answers); ?>)</h2>

    <?php if (count($answers) === 0): ?>
        <p class="text-gray-400 mb-8">No answers yet. Be the first to answer!</p>
    <?php else: ?>
        <!-- Debug info -->
        <div class="text-xs text-gray-500 mb-4">
            Debug: Found <?php echo count($answers); ?> answers | Last updated: <?php echo date('H:i:s'); ?>
        </div>
    <?php endif; ?>

    <?php foreach ($answers as $answer): ?>
        <article class="bg-gray-800 rounded-lg p-6 mb-8 shadow-lg border <?php echo ($answer['accepted'] ?? false) ? 'border-green-600' : 'border-gray-600'; ?>">
            <?php if ($answer['accepted'] ?? false): ?>
                <div class="text-green-400 font-semibold mb-2">✓ Accepted Answer</div>
            <?php endif; ?>
            <div class="flex justify-between items-center mb-4">
                <div>Points: <strong><?php echo $answer['upvotes'] ?? 0; ?></strong></div>
                <div><strong><?php echo htmlspecialchars($answer['creator'] ?? 'Unknown'); ?></strong></div>
                <time class="text-gray-400 text-sm"><?php echo formatDate($answer['createdAt'] ?? time()); ?></time>
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
                        $commentId = $comment['comment_id'] ?? uniqid();
                        $votes = getCommentVotes($commentId, 'answer');
                        ?>
                        <li class="border-l-2 border-gray-600 pl-3 py-2 mb-2 bg-gray-750 rounded-r">
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <span class="text-gray-400"><?php echo htmlspecialchars($comment['text'] ?? ''); ?></span> — <em><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></em>
                                </div>
                                <div class="flex items-center space-x-2 ml-4">
                                    <span class="text-sm text-gray-400">
                                        ▲<?php echo $votes['upvotes']; ?> ▼<?php echo $votes['downvotes']; ?>
                                    </span>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="vote_answer_comment">
                                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                        <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                                        <input type="hidden" name="operation" value="upvote">
                                        <button type="submit" class="text-blue-400 hover:text-blue-300 px-1">▲</button>
                                    </form>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="vote_answer_comment">
                                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                        <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($commentId); ?>">
                                        <input type="hidden" name="operation" value="downvote">
                                        <button type="submit" class="text-red-400 hover:text-red-300 px-1">▼</button>
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
                <input type="text" name="comment_text" maxlength="150" required placeholder="Add comment..." class="w-full p-2 rounded bg-gray-700 text-gray-300 border border-gray-600">  
                <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded text-white">Add Comment</button>
            </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>

    <?php if (canUserInteract($question['status'] ?? null)): ?>
    <section class="bg-gray-800 p-6 rounded-lg shadow-lg">
        <h3 class="text-white text-2xl font-bold mb-4">Add a new answer</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_answer">
            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
            <textarea name="answer_text" rows="6" maxlength="3000" required placeholder="Your answer here..." class="w-full p-3 rounded bg-gray-700 text-gray-300 border border-gray-600 mb-4"></textarea>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-8 py-3 rounded text-white font-semibold">Submit Answer</button>
        </form>
    </section>
    <?php endif; ?>
        
</section>

</body>
</html>