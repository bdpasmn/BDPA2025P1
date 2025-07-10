<?php
session_start();
require_once '../../api/key.php';
require_once '../../api/api.php';

$api = new qOverflowAPI(API_KEY);

// Set current user first line should be removed once windows database works 
$CURRENT_USER = 'test_user';
$_SESSION['username'] = $CURRENT_USER;
$questionName = $_GET['questionName'] ?? '123';

// Initialize data structures
if (!isset($_SESSION['qa_votes'])) {
    $_SESSION['qa_votes'] = [
        'question' => [],
        'answers' => [],   
    ];
}

if (!isset($_SESSION['qa_answers'])) {
    $_SESSION['qa_answers'] = []; 
}
 // array of comment objects
if (!isset($_SESSION['qa_comments'])) {
    $_SESSION['qa_comments'] = [
        'question' => [], 
        'answers' => [],   
    ];
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
                
                // Store vote
                $_SESSION['qa_votes']['question'][$CURRENT_USER] = ($operation === 'upvote') ? 1 : -1;
                
                debugLog("Question vote stored", $_SESSION['qa_votes']['question']);
                $message = "Question " . $operation . " successful!";
                break;
                
            case 'vote_answer':
                $answerId = $_POST['answer_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                if (!in_array($operation, ['upvote', 'downvote'])) {
                    throw new Exception('Invalid vote operation');
                }
                
                // Initialize answer votes if not exists
                if (!isset($_SESSION['qa_votes']['answers'][$answerId])) {
                    $_SESSION['qa_votes']['answers'][$answerId] = [];
                }
                
                // Store vote
                $_SESSION['qa_votes']['answers'][$answerId][$CURRENT_USER] = ($operation === 'upvote') ? 1 : -1;
                
                debugLog("Answer vote stored", $_SESSION['qa_votes']['answers'][$answerId]);
                $message = "Answer " . $operation . " successful!";
                break;

            case 'vote_question_comment':
                $commentId = $_POST['comment_id'] ?? '';
                $operation = $_POST['operation'] ?? '';
                
                // Find and update comment
                foreach ($_SESSION['qa_comments']['question'] as &$comment) {
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
                
                // Find and update comment 
                if (isset($_SESSION['qa_comments']['answers'][$answerId])) {
                    foreach ($_SESSION['qa_comments']['answers'][$answerId] as &$comment) {
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
                }
                
                $message = "Answer comment " . $operation . " successful!";
                break;
                
            case 'add_question_comment':
                $text = trim($_POST['comment_text'] ?? '');
                if ($text === '') throw new Exception('Comment text required');
                if (strlen($text) > 150) throw new Exception('Comment too long');
                
                // Add comment 
                $_SESSION['qa_comments']['question'][] = [
                    'id' => uniqid(),
                    'text' => $text,
                    'creator' => $CURRENT_USER,
                    'created' => date('Y-m-d H:i:s'),
                    'votes' => ['upvotes' => 0, 'downvotes' => 0]
                ];
                
                debugLog("Question comment added", $_SESSION['qa_comments']['question']);
                $message = "Comment added to question!";
                break;
                
            case 'add_answer_comment':
                $answerId = $_POST['answer_id'] ?? '';
                $text = trim($_POST['comment_text'] ?? '');
                if ($text === '') throw new Exception('Comment text required');
                if (strlen($text) > 150) throw new Exception('Comment too long');
                
                // Initialize answer comments if not exists
                if (!isset($_SESSION['qa_comments']['answers'][$answerId])) {
                    $_SESSION['qa_comments']['answers'][$answerId] = [];
                }
                
                // Add comment to session
                $_SESSION['qa_comments']['answers'][$answerId][] = [
                    'id' => uniqid(),
                    'text' => $text,
                    'creator' => $CURRENT_USER,
                    'created' => date('Y-m-d H:i:s'),
                    'votes' => ['upvotes' => 0, 'downvotes' => 0]
                ];
                
                debugLog("Answer comment added", $_SESSION['qa_comments']['answers'][$answerId]);
                $message = "Comment added to answer!";
                break;
                
            case 'add_answer':
                $text = trim($_POST['answer_text'] ?? '');
                if ($text === '') throw new Exception('Answer text required');
                if (strlen($text) > 3000) throw new Exception('Answer too long');
                
                // Add answer to session
                $answerId = $_SESSION['next_answer_id']++;
                $_SESSION['qa_answers'][$answerId] = [
                    'answer_id' => $answerId,
                    'text' => $text,
                    'creator' => $CURRENT_USER,
                    'created' => date('Y-m-d H:i:s'),
                    'points' => 0,
                    'accepted' => false,
                    'upvotes' => 0,
                    'downvotes' => 0
                ];
                
                debugLog("Answer added", $_SESSION['qa_answers'][$answerId]);
                $message = "Answer submitted!";
                $reloadData = true;
                break;
                
            case 'accept_answer':
                $answerId = $_POST['answer_id'] ?? '';
                
                // Check if answer exists in API data
                $answerExists = isset($_SESSION['qa_answers'][$answerId]);
                
                if ($answerExists) {
                    // Unaccept all answers first
                    foreach ($_SESSION['qa_answers'] as &$ans) {
                        $ans['accepted'] = false;
                    }
                    $_SESSION['qa_answers'][$answerId]['accepted'] = true;
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
    
    // Merge API answers
    $answers = [];
    
    // Add API answers
    foreach ($apiAnswers as $answer) {
        $answerId = $answer['answer_id'];
        // Apply votes if they exist
        if (isset($_SESSION['qa_votes']['answers'][$answerId])) {
            $votes = array_sum($_SESSION['qa_votes']['answers'][$answerId]);
            $answer['upvotes'] = max(0, ($answer['upvotes'] ?? 0) + $votes);
        }
        $answers[] = $answer;
    }
    
    // Add answers
    foreach ($_SESSION['qa_answers'] as $sessionAnswer) {
        // Calculate points from session votes
        $answerId = $sessionAnswer['answer_id'];
        if (isset($_SESSION['qa_votes']['answers'][$answerId])) {
            $sessionAnswer['points'] = array_sum($_SESSION['qa_votes']['answers'][$answerId]);
            $sessionAnswer['upvotes'] = max(0, $sessionAnswer['points']);
        }
        $answers[] = $sessionAnswer;
    }
    
    // Get question comments 
    $commentsResult = $api->getQuestionComments($actualQuestionId);
    debugLog("Question comments lookup", $commentsResult);
    
    $questionComments = [];
    if (!isset($commentsResult['error'])) {
        $questionComments = $commentsResult['comments'] ?? [];
    }
    
    // Add comments
    $questionComments = array_merge($questionComments, $_SESSION['qa_comments']['question']);
    
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
        
        // Add comments
        if (isset($_SESSION['qa_comments']['answers'][$answerId])) {
            $answer['comments'] = array_merge($answer['comments'], $_SESSION['qa_comments']['answers'][$answerId]);
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

// Function to get comment votes
function getCommentVotes($comment) {
    if (isset($comment['votes'])) {
        return $comment['votes'];
    }
    return ['upvotes' => 0, 'downvotes' => 0];
}

// Calculate question points from session votes
$questionPoints = ($question['upvotes'] ?? 0);
if (isset($_SESSION['qa_votes']['question'])) {
    $sessionVotes = array_sum($_SESSION['qa_votes']['question']);
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
                                    <span class="text-gray-400"><?php echo htmlspecialchars($comment['text'] ?? ''); ?></span> — <em><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></em>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                   <?php else: ?>
                    <li class="text-gray-500">No comments yet.</li>
                <?php endif; ?>
            </ul>

            <?php if (canUserInteract($question['status'] ?? null)): ?>
            <form method="POST">
                <input type="hidden" name="action" value="add_answer_comment">
                <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id']); ?>">
                <input type="text" name="comment_text" maxlength="150" required placeholder="Add a comment..." class="w-full p-2 rounded bg-gray-700 text-gray-300 border border-gray-600">
                <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-white">Add Comment</button>
            </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<?php if (canUserInteract($question['status'] ?? null)): ?>
<section class="bg-gray-800 rounded-lg p-6 mt-8 shadow-lg">
    <h2 class="text-white text-2xl font-bold mb-4">Your Answer</h2>
    <form method="POST">
        <input type="hidden" name="action" value="add_answer">
        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
        <textarea name="answer_text" maxlength="3000" required placeholder="Write your answer here..." class="w-full p-4 rounded bg-gray-700 text-gray-300 border border-gray-600 h-40 resize-vertical"></textarea>
        <div class="flex justify-between items-center mt-4">
            <span class="text-gray-400 text-sm">Maximum 3000 characters</span>
            <button type="submit" class="bg-green-600 hover:bg-green-700 px-6 py-2 rounded text-white font-semibold">Submit Answer</button>
        </div>
    </form>
</section>
<?php endif; ?>


</body>
</html>