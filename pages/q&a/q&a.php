<?php

session_start();

require_once '../../api/key.php';
require_once '../../api/api.php';
require_once '../../levels/getUserLevel.php';
require_once '../../levels/updateUserPoints.php';

$api = new qOverflowAPI(API_KEY);

// Function to generate Gravatar URL
function getGravatarUrl($email, $size = 40, $default = 'identicon') {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}";
}

// Function to get user email for gravatar
function getUserEmail($username) {
    global $api;
    try {
        $userResult = $api->getUser($username);
        if (!isset($userResult['error']) && isset($userResult['user']['email'])) {
            return $userResult['user']['email'];
        }
        
        // Fallback: generate a consistent email based on username for consistent gravatar
        return strtolower($username) . '@example.com';
    } catch (Exception $e) {
        debugLog("Error getting user email", ['username' => $username, 'error' => $e->getMessage()]);
        return strtolower($username) . '@example.com';
    }
}

// User authentication check
$CURRENT_USER = $_SESSION['username'] ?? null;
$isGuest = !$CURRENT_USER;
$userLevel = 0;
$userPoints = 0;

if (!$isGuest) {
    // Get user points and level from API
    try {
        $userResult = $api->getUser($CURRENT_USER);
        if (!isset($userResult['error'])) {
            $userPoints = $userResult['user']['points'] ?? 0;
            $userLevelData = getUserLevel($CURRENT_USER);
            $userLevel = $userLevelData['level'];
        }
    } catch (Exception $e) {
        debugLog("Error getting user level/points from API", ['error' => $e->getMessage()]);
    }
}

$questionName = $_GET['questionName'] ?? '123';
$message = '';
$messageType = 'success';
$question = null;
$answers = [];
$questionComments = [];
$actualQuestionId = null;

// Helper function to get user level and points from API
function getUserLevelAndPoints($username) {
    global $api;
    try {
        $userResult = $api->getUser($username);
        if (!isset($userResult['error'])) {
            $points = $userResult['user']['points'] ?? 0;
            $levelData = getUserLevel($username);
            return ['level' => $levelData['level'], 'points' => $points];
        }
        
        return ['level' => 0, 'points' => 0];
    } catch (Exception $e) {
        debugLog("Error getting user data", ['username' => $username, 'error' => $e->getMessage()]);
        return ['level' => 0, 'points' => 0];
    }
}

// Helper function to check if user can perform action
function canPerformAction($action, $userLevel, $userPoints, $isGuest) {
    if ($isGuest) {
        return in_array($action, ['view']);
    }
    
    switch ($action) {
        case 'create_question': return !$isGuest;
        case 'create_answer': return $userLevel >= 1;
        case 'upvote': return $userLevel >= 2;
        case 'comment_any': return $userLevel >= 3;
        case 'downvote': return $userLevel >= 4;
        case 'view_vote_breakdown': return $userLevel >= 5;
        case 'protect_vote': return $userLevel >= 6;
        case 'close_reopen_vote': return $userLevel >= 7;
        default: return false;
    }
}

// Enhanced debugging function
function debugLog($message, $data = null) {
    $logMessage = "[DEBUG] " . $message;
    if ($data !== null) {
        $logMessage .= " | Data: " . json_encode($data);
    }
    error_log($logMessage);
}

// Function to increment question views
function incrementQuestionViews($questionId, $userId) {
    try {
        // Check if user has already viewed this question in this session
        $viewKey = 'viewed_question_' . $questionId;
        if (!isset($_SESSION[$viewKey])) {
            // Mark as viewed in session to prevent multiple increments per session
            $_SESSION[$viewKey] = true;
            
            // Increment view count via API
            global $api;
            $result = $api->updateQuestion($questionId, ['views' => 'increment']);
            
            debugLog("Question view incremented", ['questionId' => $questionId, 'result' => $result]);
        }
    } catch (Exception $e) {
        debugLog("Error incrementing views", ['error' => $e->getMessage()]);
    }
}

// Function to handle voting with point updates
function handleVote($api, $action, $postData, $currentUser) {
    $questionId = $postData['question_id'];
    $operation = $postData['operation']; // 'upvote' or 'downvote'
    
    $result = null;
    switch ($action) {
        case 'vote_question':
            $result = $api->voteQuestionComment($questionId, '', $currentUser, $operation, 'question');
            break;
            
        case 'vote_answer':
            $answerId = $postData['answer_id'];
            $result = $api->voteAnswer($questionId, $answerId, $currentUser, $operation, 'answer');
            break;
            
        case 'vote_question_comment':
            $commentId = $postData['comment_id'];
            $result = $api->voteQuestionComment($questionId, $commentId, $currentUser, $operation, 'comment');
            break;
            
        case 'vote_answer_comment':
            $answerId = $postData['answer_id'];
            $commentId = $postData['comment_id'];
            $result = $api->voteAnswerComment($questionId, $answerId, $commentId, $currentUser, $operation, 'comment');
            break;
            
        default:
            return ['error' => 'Invalid vote action'];
    }
    
    // Update points based on voting action
    if (!isset($result['error'])) {
        // Downvoting costs the voter 1 point
        if ($operation === 'downvote') {
            updateUserPoints($currentUser, -1);
        }
        
        // Award points to the content creator
        if ($action === 'vote_question') {
            // Get question creator and award/deduct points
            $questionData = $api->getQuestion($questionId);
            if (!isset($questionData['error'])) {
                $creator = $questionData['creator'];
                if ($operation === 'upvote') {
                    updateUserPoints($creator, 5); // Question upvote: +5 points
                } else {
                    updateUserPoints($creator, -1); // Question downvote: -1 point
                }
            }
        } elseif ($action === 'vote_answer') {
            // Get answer creator and award/deduct points
            $answersData = $api->getAnswers($questionId);
            if (!isset($answersData['error'])) {
                $answerId = $postData['answer_id'];
                foreach ($answersData['answers'] as $answer) {
                    if ($answer['answer_id'] === $answerId) {
                        $creator = $answer['creator'];
                        if ($operation === 'upvote') {
                            updateUserPoints($creator, 10); // Answer upvote: +10 points
                        } else {
                            updateUserPoints($creator, -5); // Answer downvote: -5 points
                        }
                        break;
                    }
                }
            }
        }
    }
    
    return $result;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isGuest) {
        $message = 'You must be logged in to perform this action.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        
        debugLog("POST Request received", $_POST);
        
        try {
            $actualQuestionId = $_POST['question_id'] ?? $questionName;
            debugLog("Processing action: $action with question_id: $actualQuestionId");
            
            switch ($action) {
                case 'vote_question':
                case 'vote_answer':
                case 'vote_question_comment':
                case 'vote_answer_comment':
                    $operation = $_POST['operation'] ?? '';
                    if (!in_array($operation, ['upvote', 'downvote'])) {
                        throw new Exception('Invalid vote operation');
                    }
                    
                    // Check permissions
                    $canVote = ($operation === 'upvote' && canPerformAction('upvote', $userLevel, $userPoints, $isGuest)) ||
                              ($operation === 'downvote' && canPerformAction('downvote', $userLevel, $userPoints, $isGuest));
                    
                    if (!$canVote) {
                        throw new Exception('You do not have permission to ' . $operation);
                    }
                    
                    $result = handleVote($api, $action, $_POST, $CURRENT_USER);
                    
                    if (isset($result['error'])) {
                        throw new Exception($result['error']);
                    }
                    
                    $message = ucfirst($operation) . " successful!";
                    break;
                    
                case 'add_question_comment':
                    $text = trim($_POST['comment_text'] ?? '');
                    if ($text === '') throw new Exception('Comment text required');
                    if (strlen($text) > 150) throw new Exception('Comment too long (max 150 characters)');
                    
                    // Check if user can comment (level 3+ or own question)
                    $questionResult = $api->getQuestion($actualQuestionId);
                    $isOwnQuestion = !isset($questionResult['error']) && 
                                    ($questionResult['creator'] ?? '') === $CURRENT_USER;
                    
                    // Allow commenting on own question at any level
                    if (!$isOwnQuestion && !canPerformAction('comment_any', $userLevel, $userPoints, $isGuest)) {
                        throw new Exception('You need level 3 to comment on others\' questions');
                    }
                    
                    $result = $api->createQuestionComment($actualQuestionId, $CURRENT_USER, $text);
                    
                    if (isset($result['error'])) {
                        throw new Exception($result['error']);
                    }
                    
                    $message = "Comment added to question!";
                    break;
                    
                case 'add_answer_comment':
                    $answerId = $_POST['answer_id'] ?? '';
                    $text = trim($_POST['comment_text'] ?? '');
                    if ($text === '') throw new Exception('Comment text required');
                    if (strlen($text) > 150) throw new Exception('Comment too long (max 150 characters)');
                    
                    // Check if user can comment (level 3+, own answer, or answer to own question)
                    $questionResult = $api->getQuestion($actualQuestionId);
                    $answersResult = $api->getAnswers($actualQuestionId);
                    
                    $canComment = false;
                    // Allow commenting on own question's answers at any level
                    if (!isset($questionResult['error']) && ($questionResult['creator'] ?? '') === $CURRENT_USER) {
                        $canComment = true; // Own question
                    } elseif (!isset($answersResult['error'])) {
                        foreach ($answersResult['answers'] ?? [] as $answer) {
                            if ($answer['answer_id'] === $answerId && ($answer['creator'] ?? '') === $CURRENT_USER) {
                                $canComment = true; // Own answer
                                break;
                            }
                        }
                    }
                    
                    if (!$canComment && !canPerformAction('comment_any', $userLevel, $userPoints, $isGuest)) {
                        throw new Exception('You need level 3 to comment on others\' content');
                    }
                    
                    $result = $api->createAnswerComment($actualQuestionId, $answerId, $CURRENT_USER, $text);
                    
                    if (isset($result['error'])) {
                        throw new Exception($result['error']);
                    }
                    
                    $message = "Comment added to answer!";
                    break;
                    
                case 'add_answer':
                    $text = trim($_POST['answer_text'] ?? '');
                    if ($text === '') throw new Exception('Answer text required');
                    if (strlen($text) > 3000) throw new Exception('Answer too long (max 3000 characters)');
                    
                    if (!canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)) {
                        throw new Exception('You need level 1 (1 point) to create answers');
                    }
                    
                    // Check if question is open for answers
                    $questionResult = $api->getQuestion($actualQuestionId);
                    if (!isset($questionResult['error'])) {
                        $status = $questionResult['status'] ?? 'open';
                        if ($status === 'closed') {
                            throw new Exception('This question is closed and no longer accepts answers');
                        }
                        if ($status === 'protected' && $userLevel < 5) {
                            throw new Exception('This question is protected. You need level 5 to answer');
                        }
                    }
                    
                    $result = $api->createAnswer($actualQuestionId, $CURRENT_USER, $text);
                    
                    if (isset($result['error'])) {
                        throw new Exception($result['error']);
                    }
                    
                    // Award points for creating an answer (2 points)
                    updateUserPoints($CURRENT_USER, 2);
                    
                    $message = "Answer submitted successfully!";
                    break;
                    
                case 'accept_answer':
                    $answerId = $_POST['answer_id'] ?? '';
                    
                    // Check if user is the question creator
                    $questionResult = $api->getQuestion($actualQuestionId);
                    if (isset($questionResult['error']) || ($questionResult['creator'] ?? '') !== $CURRENT_USER) {
                        throw new Exception('Only the question creator can accept answers');
                    }
                    
                    $result = $api->updateAnswer($actualQuestionId, $answerId, ['accepted' => true]);
                    
                    if (isset($result['error'])) {
                        throw new Exception($result['error']);
                    }
                    
                    // Award points to answer creator for accepted answer (15 points)
                    $answersResult = $api->getAnswers($actualQuestionId);
                    if (!isset($answersResult['error'])) {
                        foreach ($answersResult['answers'] ?? [] as $answer) {
                            if ($answer['answer_id'] === $answerId) {
                                updateUserPoints($answer['creator'], 15);
                                break;
                            }
                        }
                    }
                    
                    $message = "Answer accepted!";
                    break;
                    
                case 'protect_question':
                case 'close_question':
                case 'reopen_question':
                    $requiredLevel = ($action === 'protect_question') ? 6 : 7;
                    if ($userLevel < $requiredLevel) {
                        throw new Exception("You need level $requiredLevel to perform this action");
                    }
                    
                    // Implementation would involve tracking votes in database
                    // For now, simulate the action
                    $message = "Vote registered. Need 2 more votes from qualified users.";
                    break;
                    
                default:
                    throw new Exception('Unknown action: ' . $action);
            }
        } catch (Exception $ex) {
            $message = 'Error: ' . $ex->getMessage();
            $messageType = 'error';
            debugLog("Exception caught", ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
        }
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
    
    // Increment view count
    incrementQuestionViews($actualQuestionId, $CURRENT_USER);
    
    // Get answers
    $answersResult = $api->getAnswers($actualQuestionId);
    debugLog("Answers lookup", $answersResult);
    
    $answers = [];
    if (!isset($answersResult['error'])) {
        $answers = $answersResult['answers'] ?? [];
    }
    
    // Get question comments
    $commentsResult = $api->getQuestionComments($actualQuestionId);
    debugLog("Question comments lookup", $commentsResult);
    
    $questionComments = [];
    if (!isset($commentsResult['error'])) {
        $questionComments = $commentsResult['comments'] ?? [];
    }
    
    // Get answer comments for each answer
    foreach ($answers as &$answer) {
        $answerId = $answer['answer_id'];
        
        $answerCommentsResult = $api->getAnswerComments($actualQuestionId, $answerId);
        debugLog("Answer comments lookup for " . $answerId, $answerCommentsResult);
        
        $answer['comments'] = [];
        if (!isset($answerCommentsResult['error'])) {
            $answer['comments'] = $answerCommentsResult['comments'] ?? [];
        }
    }
    
} catch (Exception $ex) {
    $message = 'Error loading question: ' . $ex->getMessage();
    $messageType = 'error';
    debugLog("Exception loading question", ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()]);
}

// Helper functions
function formatDate($timestamp) {
    $timezone = new DateTimeZone('America/Chicago');

    if (is_numeric($timestamp)) {
        if ($timestamp > 1000000000000) {
            $timestamp = $timestamp / 1000;
        }
        $date = new DateTime("@$timestamp");
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

function canUserInteract($status, $userLevel) {
    if ($status === 'closed') return false;
    if ($status === 'protected' && $userLevel < 5) return false;
    return true;
}

function getStatusDisplay($status) {
    switch ($status) {
        case 'closed':
            return ['text' => 'CLOSED', 'class' => 'bg-red-600', 'description' => 'This question is closed and no longer accepts answers or comments.'];
        case 'protected':
            return ['text' => 'PROTECTED', 'class' => 'bg-yellow-600', 'description' => 'This question is protected. Only users with level 5+ can answer or comment.'];
        default:
            return null;
    }
}

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
            @apply px-4 py-2 bg-gray-900 text-gray-300 border-b-2 border-transparent hover:bg-gray-800 transition-colors;
        }
        .tab-button.active {
            @apply bg-gray-800 border-gray-600 text-white;
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
        .level-badge {
            @apply inline-block px-2 py-1 text-xs font-semibold rounded;
        }
        .level-1 { @apply bg-gray-600 text-white; }
        .level-2 { @apply bg-green-600 text-white; }
        .level-3 { @apply bg-blue-600 text-white; }
        .level-4 { @apply bg-purple-600 text-white; }
        .level-5 { @apply bg-orange-600 text-white; }
        .level-6 { @apply bg-red-600 text-white; }
        .level-7 { @apply bg-yellow-600 text-black; }
        .gravatar {
            @apply rounded-full border-2 border-gray-600;
        }
        .user-info {
            @apply flex items-center space-x-2;
        }
        .tab-container {
            @apply bg-gray-900 border border-gray-700 rounded-lg;
        }
        
        /* Text wrap utilities */
        .text-wrap {
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
        }
        
        /* Ensure code blocks also wrap */
        .code-wrap code {
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .code-wrap pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-300 font-sans">
    <?php 
    // Use the appropriate navigation bar based on user authentication status
    if ($isGuest) {
        include '../../components/navBarLogOut.php';
    } else {
        include '../../components/navBarLogIn.php';
    }
    ?>

    <!-- Spinner -->
    <div id="spinner" class="flex justify-center items-center py-20">
        <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <!-- Main Content -->
    <div id="main-content" class="max-w-4xl mx-auto p-6 hidden">
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

        <?php if (!$isGuest): ?>
            <div class="bg-gray-800 rounded-lg p-4 mb-6">
                <div class="flex justify-between items-center">
                    <div class="user-info">
                        <img src="<?php echo getGravatarUrl(getUserEmail($CURRENT_USER), 32); ?>" 
                             alt="<?php echo htmlspecialchars($CURRENT_USER); ?>" 
                             class="gravatar w-8 h-8 rounded-full">
                             
                        <div>
                            <span class="text-gray-400">Logged in as:</span> 
                            <strong class="text-wrap"><?php echo htmlspecialchars($CURRENT_USER); ?></strong>
                            <span class="level-badge level-<?php echo $userLevel; ?>">Level <?php echo $userLevel; ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-400">Points:</span> 
                        <strong><?php echo $userPoints; ?></strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="bg-gray-800 rounded-lg p-6 mb-10 shadow-lg">
            <?php 
            $statusInfo = getStatusDisplay($question['status'] ?? 'open');
            if ($statusInfo): ?>
                <div class="<?php echo $statusInfo['class']; ?> text-white px-4 py-2 rounded mb-4 font-semibold text-wrap">
                    <?php echo $statusInfo['text']; ?>: <?php echo $statusInfo['description']; ?>
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center mb-3">
                <div class="space-x-4 text-sm text-gray-400">
                    <span>Asked: <time><?php echo formatDate($question['createdAt'] ?? time()); ?></time></span>
                    <span>Views: <?php echo ($question['views'] ?? 0); ?></span>
                    <span>Points: <strong><?php echo ($question['upvotes'] ?? 0) - ($question['downvotes'] ?? 0); ?></strong></span>
                    <?php if (canPerformAction('view_vote_breakdown', $userLevel, $userPoints, $isGuest)): ?>
                        <span class="text-green-400">▲<?php echo ($question['upvotes'] ?? 0); ?></span>
                        <span class="text-red-400">▼<?php echo ($question['downvotes'] ?? 0); ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <?php 
                    $creatorData = getUserLevelAndPoints($question['creator'] ?? '');
                    $creatorLevel = $creatorData['level'];
                    $creatorEmail = getUserEmail($question['creator'] ?? '');
                    ?>
                    <img src="<?php echo getGravatarUrl($creatorEmail, 32); ?>" 
                        alt="<?php echo htmlspecialchars($question['creator'] ?? 'Unknown'); ?>" 
                        class="gravatar w-8 h-8 rounded-full">
                    <div>
                        <strong class="text-wrap"><?php echo htmlspecialchars($question['creator'] ?? 'Unknown'); ?></strong>
                        <span class="level-badge level-<?php echo $creatorLevel; ?>">Level <?php echo $creatorLevel; ?></span>
                    </div>
                </div>
            </div>

            <h1 class="text-white text-3xl font-bold mb-5 text-wrap"><?php echo htmlspecialchars($question['title'] ?? 'Untitled Question'); ?></h1>

            <article class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-8 leading-relaxed text-gray-300 shadow-inner text-wrap code-wrap">
                <?php echo renderMarkdown($question['body'] ?? $question['text'] ?? 'No content available'); ?>
            </article>

            <!-- Question Voting -->
            <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                <div class="flex items-center space-x-4 mb-6 flex-wrap">
                    <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="vote_question">
                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                            <input type="hidden" name="operation" value="upvote">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors">
                                ▲ Upvote
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="vote_question">
                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                            <input type="hidden" name="operation" value="downvote">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors">
                                ▼ Downvote
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($userLevel >= 6): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="protect_question">
                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition-colors">
                                Protect
                            </button>
                        </form>
                    <?php endif; ?>

                    <?php if ($userLevel >= 7): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="action" value="close_question">
                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors">
                                Close
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Question Comments -->
            <?php if (!empty($questionComments)): ?>
                <div class="mt-6 bg-gray-700 rounded-lg p-4">
                    <h3 class="text-lg font-semibold mb-4 text-white">Comments</h3>
                    <?php foreach ($questionComments as $comment): ?>
                        <div class="mb-4 pb-4 border-b border-gray-600 last:border-b-0">
                            <div class="flex justify-between items-start mb-2">
                                <div class="user-info">
                                    <?php 
                                    $commenterData = getUserLevelAndPoints($comment['creator'] ?? '');
                                    $commenterLevel = $commenterData['level'];
                                    $commenterEmail = getUserEmail($comment['creator'] ?? '');
                                    ?>
                                    <img src="<?php echo getGravatarUrl($commenterEmail, 24); ?>" 
                                         alt="<?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?>" 
                                         class="gravatar w-6 h-6 rounded-full">
                                    <div>
                                        <strong class="text-wrap"><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></strong>
                                        <span class="level-badge level-<?php echo $commenterLevel; ?>">Level <?php echo $commenterLevel; ?></span>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs text-gray-400"><?php echo formatDate($comment['createdAt'] ?? time()); ?></span>
                                    <span class="text-xs text-gray-400">Points: <?php echo ($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0); ?></span>
                                </div>
                            </div>
                            <p class="text-gray-300 text-wrap"><?php echo renderMarkdown($comment['text'] ?? ''); ?></p>
                            
                            <!-- Comment Voting -->
                            <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                                <div class="flex items-center space-x-2 mt-2">
                                    <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="vote_question_comment">
                                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['comment_id'] ?? ''); ?>">
                                            <input type="hidden" name="operation" value="upvote">
                                            <button type="submit" class="text-green-400 hover:text-green-300 text-sm">▲</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="vote_question_comment">
                                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                            <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['comment_id'] ?? ''); ?>">
                                            <input type="hidden" name="operation" value="downvote">
                                            <button type="submit" class="text-red-400 hover:text-red-300 text-sm">▼</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Add Question Comment Form -->
            <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                <?php 
                $canCommentQuestion = canPerformAction('comment_any', $userLevel, $userPoints, $isGuest) || 
                                    (($question['creator'] ?? '') === $CURRENT_USER);
                ?>
                <?php if ($canCommentQuestion): ?>
                    <div class="mt-6 bg-gray-700 rounded-lg p-4">
                        <h3 class="text-lg font-semibold mb-4 text-white">Add Comment</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_question_comment">
                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                            <div class="mb-4">
                                <textarea name="comment_text" rows="3" maxlength="150" 
                                          class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none" 
                                          placeholder="Add a comment (max 150 characters)..." required></textarea>
                                <div class="text-xs text-gray-400 mt-1">Characters remaining: <span id="comment-chars">150</span></div>
                            </div>
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                Post Comment
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Answers Section -->
        <section class="mb-10">
            <h2 class="text-2xl font-bold text-white mb-6">
                <?php echo count($answers); ?> Answer<?php echo count($answers) !== 1 ? 's' : ''; ?>
            </h2>

            <?php foreach ($answers as $answer): ?>
                <article class="bg-gray-800 rounded-lg p-6 mb-6 shadow-lg <?php echo ($answer['accepted'] ?? false) ? 'border-l-4 border-green-500' : ''; ?>">
                    <?php if ($answer['accepted'] ?? false): ?>
                        <div class="bg-green-600 text-white px-4 py-2 rounded mb-4 font-semibold">
                            ✓ ACCEPTED ANSWER
                        </div>
                    <?php endif; ?>

                    <div class="flex justify-between items-center mb-4">
                        <div class="space-x-4 text-sm text-gray-400">
                            <span>Answered: <time><?php echo formatDate($answer['createdAt'] ?? time()); ?></time></span>
                            <span>Points: <strong><?php echo ($answer['upvotes'] ?? 0) - ($answer['downvotes'] ?? 0); ?></strong></span>
                            <?php if (canPerformAction('view_vote_breakdown', $userLevel, $userPoints, $isGuest)): ?>
                                <span class="text-green-400">▲<?php echo ($answer['upvotes'] ?? 0); ?></span>
                                <span class="text-red-400">▼<?php echo ($answer['downvotes'] ?? 0); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <?php 
                            $answererData = getUserLevelAndPoints($answer['creator'] ?? '');
                            $answererLevel = $answererData['level'];
                            $answererEmail = getUserEmail($answer['creator'] ?? '');
                            ?>
                            <img src="<?php echo getGravatarUrl($answererEmail, 32); ?>" 
                                 alt="<?php echo htmlspecialchars($answer['creator'] ?? 'Unknown'); ?>" 
                                 class="gravatar w-8 h-8 rounded-full">
                            <div>
                                <strong class="text-wrap"><?php echo htmlspecialchars($answer['creator'] ?? 'Unknown'); ?></strong>
                                <span class="level-badge level-<?php echo $answererLevel; ?>">Level <?php echo $answererLevel; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-6 leading-relaxed text-gray-300 shadow-inner text-wrap code-wrap">
                        <?php echo renderMarkdown($answer['text'] ?? $answer['body'] ?? 'No content available'); ?>
                    </div>

                    <!-- Answer Actions -->
                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                        <div class="flex items-center space-x-4 mb-4 flex-wrap">
                            <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="vote_answer">
                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                    <input type="hidden" name="operation" value="upvote">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors">
                                        ▲ Upvote
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="vote_answer">
                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                    <input type="hidden" name="operation" value="downvote">
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors">
                                        ▼ Downvote
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (($question['creator'] ?? '') === $CURRENT_USER && !($answer['accepted'] ?? false)): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="accept_answer">
                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition-colors">
                                        ✓ Accept Answer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Answer Comments -->
                    <?php if (!empty($answer['comments'])): ?>
                        <div class="bg-gray-700 rounded-lg p-4 mb-4">
                            <h4 class="text-md font-semibold mb-3 text-white">Comments</h4>
                            <?php foreach ($answer['comments'] as $comment): ?>
                                <div class="mb-3 pb-3 border-b border-gray-600 last:border-b-0">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="user-info">
                                            <?php 
                                            $commenterData = getUserLevelAndPoints($comment['creator'] ?? '');
                                            $commenterLevel = $commenterData['level'];
                                            $commenterEmail = getUserEmail($comment['creator'] ?? '');
                                            ?>
                                            <img src="<?php echo getGravatarUrl($commenterEmail, 20); ?>" 
                                                 alt="<?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?>" 
                                                 class="gravatar w-5 h-5 rounded-full">
                                            <div>
                                                <strong class="text-sm text-wrap"><?php echo htmlspecialchars($comment['creator'] ?? 'Unknown'); ?></strong>
                                                <span class="level-badge level-<?php echo $commenterLevel; ?> text-xs">Level <?php echo $commenterLevel; ?></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs text-gray-400"><?php echo formatDate($comment['createdAt'] ?? time()); ?></span>
                                            <span class="text-xs text-gray-400">Points: <?php echo ($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0); ?></span>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-300 text-wrap"><?php echo renderMarkdown($comment['text'] ?? ''); ?></p>
                                    
                                    <!-- Answer Comment Voting -->
                                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                                        <div class="flex items-center space-x-2 mt-2">
                                            <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="vote_answer_comment">
                                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['comment_id'] ?? ''); ?>">
                                                    <input type="hidden" name="operation" value="upvote">
                                                    <button type="submit" class="text-green-400 hover:text-green-300 text-sm">▲</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="vote_answer_comment">
                                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['comment_id'] ?? ''); ?>">
                                                    <input type="hidden" name="operation" value="downvote">
                                                    <button type="submit" class="text-red-400 hover:text-red-300 text-sm">▼</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add Answer Comment Form -->
                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                        <?php 
                        $canCommentAnswer = canPerformAction('comment_any', $userLevel, $userPoints, $isGuest) || 
                                          (($answer['creator'] ?? '') === $CURRENT_USER) ||
                                          (($question['creator'] ?? '') === $CURRENT_USER);
                        ?>
                        <?php if ($canCommentAnswer): ?>
                            <div class="bg-gray-700 rounded-lg p-4">
                                <h4 class="text-md font-semibold mb-3 text-white">Add Comment</h4>
                                <form method="POST">
                                    <input type="hidden" name="action" value="add_answer_comment">
                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                    <div class="mb-3">
                                        <textarea name="comment_text" rows="2" maxlength="150" 
                                                  class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none" 
                                                  placeholder="Add a comment (max 150 characters)..." required></textarea>
                                        <div class="text-xs text-gray-400 mt-1">Characters remaining: <span class="answer-comment-chars">150</span></div>
                                    </div>
                                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        Post Comment
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <!-- Add Answer Form -->
        <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel) && canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)): ?>
            <section class="bg-gray-800 rounded-lg p-6 shadow-lg">
                <h2 class="text-2xl font-bold text-white mb-6">Your Answer</h2>
                
                <div class="tab-container">
                    <div class="flex border-b border-gray-700">
                        <button class="tab-button active" onclick="switchTab('write')">Write</button>
                        <button class="tab-button" onclick="switchTab('preview')">Preview</button>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_answer">
                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                        
                        <div id="write-tab" class="tab-content active p-4">
                            <textarea name="answer_text" id="answer-textarea" rows="12" maxlength="3000" 
                                      class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-vertical" 
                                      placeholder="Write your answer here... (Markdown supported)" required></textarea>
                            <div class="text-xs text-gray-400 mt-2">
                                Characters remaining: <span id="answer-chars">3000</span> | 
                                Supports: **bold**, *italic*, `code`, ```code blocks```
                            </div>
                        </div>
                        
                        <div id="preview-tab" class="tab-content p-4">
                            <div id="answer-preview" class="markdown-preview">
                                <em class="text-gray-500">Nothing to preview yet...</em>
                            </div>
                        </div>
                        
                        <div class="p-4 bg-gray-900 rounded-b-lg">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg font-semibold transition-colors">
                                Post Your Answer
                            </button>
                            <span class="ml-4 text-sm text-gray-400">+2 points for answering</span>
                        </div>
                    </form>
                </div>
            </section>
        <?php elseif ($isGuest): ?>
            <section class="bg-gray-800 rounded-lg p-6 shadow-lg text-center">
                <h2 class="text-xl font-bold text-white mb-4">Want to Answer?</h2>
                <p class="text-gray-400 mb-4">You need to be logged in to post answers.</p>
                <a href="../auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Log In
                </a>
            </section>
        <?php elseif (!canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)): ?>
            <section class="bg-gray-800 rounded-lg p-6 shadow-lg text-center">
                <h2 class="text-xl font-bold text-white mb-4">Answer Restricted</h2>
                <p class="text-gray-400 mb-4">You need Level 1 (1 point) to post answers. Current level: <?php echo $userLevel; ?></p>
            </section>
        <?php endif; ?>
    </div>

    <script>
        // Hide spinner and show content when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('spinner').style.display = 'none';
            document.getElementById('main-content').classList.remove('hidden');
        });

        // Tab switching functionality
        function switchTab(tabName) {
            // Remove active class from all tabs and buttons
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // Add active class to selected tab and button
            document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');
            
            // Update preview if switching to preview tab
            if (tabName === 'preview') {
                updatePreview();
            }
        }

        // Character counting for answer textarea
        document.addEventListener('DOMContentLoaded', function() {
            const answerTextarea = document.getElementById('answer-textarea');
            const answerCharsSpan = document.getElementById('answer-chars');
            
            if (answerTextarea && answerCharsSpan) {
                answerTextarea.addEventListener('input', function() {
                    const remaining = 3000 - this.value.length;
                    answerCharsSpan.textContent = remaining;
                    answerCharsSpan.className = remaining < 100 ? 'text-red-400' : remaining < 300 ? 'text-yellow-400' : 'text-gray-400';
                });
            }

            // Character counting for comment textareas
            document.querySelectorAll('textarea[name="comment_text"]').forEach(textarea => {
                textarea.addEventListener('input', function() {
                    const remaining = 150 - this.value.length;
                    const span = this.parentNode.querySelector('[id$="comment-chars"], [class*="comment-chars"]');
                    if (span) {
                        span.textContent = remaining;
                        span.className = remaining < 20 ? 'text-red-400' : remaining < 50 ? 'text-yellow-400' : 'text-gray-400';
                    }
                });
            });
        });

        // Preview functionality
        function updatePreview() {
            const textarea = document.getElementById('answer-textarea');
            const preview = document.getElementById('answer-preview');
            
            if (textarea && preview) {
                const text = textarea.value.trim();
                if (text === '') {
                    preview.innerHTML = '<em class="text-gray-500">Nothing to preview yet...</em>';
                    return;
                }
                
                // Simple markdown rendering (matches PHP renderMarkdown function)
                let html = text
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/`(.*?)`/g, '<code class="bg-gray-600 px-1 rounded">$1</code>')
                    .replace(/```(.*?)```/gs, '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>')
                    .replace(/\n/g, '<br>');
                
                preview.innerHTML = html;
            }
        }

        // Auto-update preview when typing
        document.addEventListener('DOMContentLoaded', function() {
            const answerTextarea = document.getElementById('answer-textarea');
            if (answerTextarea) {
                answerTextarea.addEventListener('input', function() {
                    if (document.getElementById('preview-tab').classList.contains('active')) {
                        updatePreview();
                    }
                });
            }
        });
    </script>
</body>
</html>