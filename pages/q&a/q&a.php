<?php

session_start();

require_once '../../api/key.php';
require_once '../../api/api.php';
  require_once '../../db.php';

$api = new qOverflowAPI(API_KEY);



try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Function to generate Gravatar URL
function getGravatarUrl($email, $size = 40, $default = 'identicon') {
    $hash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d={$default}";
}

// Function to get user email for gravatar
function getUserEmail($pdo, $username) {
    try {
        // Get email from users table
        $stmt = $pdo->prepare("SELECT email FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['email']) {
            return $result['email'];
        }
        
        // Fallback: generate a consistent email based on username for consistent gravatar
        return strtolower($username) . '@example.com';
    } catch (PDOException $e) {
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
    // Get user points and level from users table
    try {
        $stmt = $pdo->prepare("SELECT level, points FROM users WHERE username = ?");
        $stmt->execute([$CURRENT_USER]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $userPoints = $result['points'] ?? 0;
            $userLevel = $result['level'] ?? 0;
        }
    } catch (PDOException $e) {
        debugLog("Error getting user level/points from database", ['error' => $e->getMessage()]);
        // Fallback to API if database query fails
        $userResult = $api->getUser($CURRENT_USER);
        if (!isset($userResult['error'])) {
            $userPoints = $userResult['points'] ?? 0;
            $userLevel = getUserLevelFromPoints($userPoints);
        }
    }
}

$questionName = $_GET['questionName'] ?? '123';
$message = '';
$messageType = 'success';
$question = null;
$answers = [];
$questionComments = [];
$actualQuestionId = null;

// Helper function to determine user level based on points (fallback only)
function getUserLevelFromPoints($points) {
    if ($points >= 10000) return 7;
    if ($points >= 3000) return 6;
    if ($points >= 1000) return 5;
    if ($points >= 125) return 4;
    if ($points >= 50) return 3;
    if ($points >= 15) return 2;
    if ($points >= 1) return 1;
    return 0;
}

// Helper function to get user level and points from database
function getUserLevelAndPoints($pdo, $username) {
    try {
        // Get level and points from users table
        $stmt = $pdo->prepare("SELECT level, points FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return ['level' => $result['level'] ?? 0, 'points' => $result['points'] ?? 0];
        }
        
        return ['level' => 0, 'points' => 0];
    } catch (PDOException $e) {
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
function incrementQuestionViews($pdo, $questionId, $userId) {
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

// Function to handle voting
function handleVote($api, $action, $postData, $currentUser) {
    $questionId = $postData['question_id'];
    $operation = $postData['operation']; // 'upvote' or 'downvote'
    
    switch ($action) {
        case 'vote_question':
            return $api->voteQuestionComment($questionId, '', $currentUser, $operation, 'question');
            
        case 'vote_answer':
            $answerId = $postData['answer_id'];
            return $api->voteAnswer($questionId, $answerId, $currentUser, $operation, 'answer');
            
        case 'vote_question_comment':
            $commentId = $postData['comment_id'];
            return $api->voteQuestionComment($questionId, $commentId, $currentUser, $operation, 'comment');
            
        case 'vote_answer_comment':
            $answerId = $postData['answer_id'];
            $commentId = $postData['comment_id'];
            return $api->voteAnswerComment($questionId, $answerId, $commentId, $currentUser, $operation, 'comment');
    }
    
    return ['error' => 'Invalid vote action'];
}

// Function to store comment in database
function storeCommentInDB($pdo, $questionId, $answerId, $username, $commentText) {
    try {
        $stmt = $pdo->prepare("INSERT INTO Comments (question_id, answer_id, Comment, username) VALUES (?, ?, ?, ?)");
        $stmt->execute([$questionId, $answerId, $commentText, $username]);
        debugLog("Comment stored in database", ['questionId' => $questionId, 'answerId' => $answerId, 'username' => $username]);
    } catch (PDOException $e) {
        debugLog("Error storing comment in database", ['error' => $e->getMessage()]);
    }
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
                    
                    // Store comment in database
                    storeCommentInDB($pdo, $actualQuestionId, null, $CURRENT_USER, $text);
                    
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
                    
                    // Store comment in database
                    storeCommentInDB($pdo, $actualQuestionId, $answerId, $CURRENT_USER, $text);
                    
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
    incrementQuestionViews($pdo, $actualQuestionId, $CURRENT_USER);
    
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
                        <img src="<?php echo getGravatarUrl(getUserEmail($pdo, $CURRENT_USER), 32); ?>" 
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
                    $creatorData = getUserLevelAndPoints($pdo, $question['creator'] ?? '');
                    $creatorLevel = $creatorData['level'];
                    $creatorEmail = getUserEmail($pdo, $question['creator'] ?? '');
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

            <!-- Question Voting --><?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
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
    </div>
<?php endif; ?>
            <!-- Question Comments -->
            <?php if (!empty($questionComments)): ?>
                <div class="border-t border-gray-600 pt-4 mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-white">Comments</h3>
                    <?php foreach ($questionComments as $comment): ?>
                        <div class="bg-gray-700 p-3 rounded mb-2 text-wrap">
                            <div class="flex justify-between items-start mb-2">
                                <div class="user-info">
                                    <?php 
                                    $commentorData = getUserLevelAndPoints($pdo, $comment['username'] ?? '');
                                    $commentorLevel = $commentorData['level'];
                                    $commentorEmail = getUserEmail($pdo, $comment['username'] ?? '');
                                    ?>
                                    <img src="<?php echo getGravatarUrl($commentorEmail, 24); ?>" 
                                        alt="<?php echo htmlspecialchars($comment['username'] ?? 'Unknown'); ?>" 
                                        class="gravatar w-6 h-6 rounded-full">
                                    <div>
                                        <strong class="text-wrap"><?php echo htmlspecialchars($comment['username'] ?? 'Unknown'); ?></strong>
                                        <span class="level-badge level-<?php echo $commentorLevel; ?>">L<?php echo $commentorLevel; ?></span>
                                    </div>
                                </div>
                                <span class="text-xs text-gray-400"><?php echo formatDate($comment['createdAt'] ?? time()); ?></span>
                            </div>
                            <p class="text-gray-300 text-wrap"><?php echo renderMarkdown($comment['text'] ?? $comment['Comment'] ?? ''); ?></p>
                            
                            <!-- Comment voting -->
                            <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                                <div class="flex items-center space-x-2 mt-2">
                                    <span class="text-sm text-gray-400">Points: <?php echo ($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0); ?></span>
                                    
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

            <!-- Add Comment to Question -->
            <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                <?php 
                $isOwnQuestion = ($question['creator'] ?? '') === $CURRENT_USER;
                $canCommentOnQuestion = $isOwnQuestion || canPerformAction('comment_any', $userLevel, $userPoints, $isGuest);
                ?>
                
                <?php if ($canCommentOnQuestion): ?>
                    <form method="POST" class="mb-6">
                        <input type="hidden" name="action" value="add_question_comment">
                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                        <div class="mb-3">
                            <label class="block text-sm font-medium mb-2">Add Comment (max 150 characters)</label>
                            <textarea name="comment_text" rows="2" maxlength="150" 
                                    class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 text-wrap" 
                                    placeholder="Enter your comment..." required></textarea>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                            Add Comment
                        </button>
                    </form>
                <?php else: ?>
                    <p class="text-gray-400 text-sm mb-6">You need level 3 to comment on others' questions.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Answers Section -->
        <section class="mb-10">
            <h2 class="text-2xl font-bold text-white mb-6">
                <?php echo count($answers); ?> Answer<?php echo count($answers) !== 1 ? 's' : ''; ?>
            </h2>

            <?php foreach ($answers as $answer): ?>
                <article class="bg-gray-800 rounded-lg p-6 mb-6 shadow-lg">
                    <?php if ($answer['accepted'] ?? false): ?>
                        <div class="bg-green-600 text-white px-3 py-1 rounded mb-4 inline-block font-semibold">
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
                            $answerCreatorData = getUserLevelAndPoints($pdo, $answer['creator'] ?? '');
                            $answerCreatorLevel = $answerCreatorData['level'];
                            $answerCreatorEmail = getUserEmail($pdo, $answer['creator'] ?? '');
                            ?>
                            <img src="<?php echo getGravatarUrl($answerCreatorEmail, 32); ?>" 
                                alt="<?php echo htmlspecialchars($answer['creator'] ?? 'Unknown'); ?>" 
                                class="gravatar w-8 h-8 rounded-full">
                            <div>
                                <strong class="text-wrap"><?php echo htmlspecialchars($answer['creator'] ?? 'Unknown'); ?></strong>
                                <span class="level-badge level-<?php echo $answerCreatorLevel; ?>">Level <?php echo $answerCreatorLevel; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-6 leading-relaxed text-gray-300 shadow-inner text-wrap code-wrap">
                        <?php echo renderMarkdown($answer['body'] ?? $answer['text'] ?? 'No content available'); ?>
                    </div>

                    <!-- Answer Actions -->
                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                        <div class="flex items-center space-x-4 mb-4 flex-wrap">
                            <!-- Vote buttons -->
                            <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="vote_answer">
                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                    <input type="hidden" name="operation" value="upvote">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition-colors">
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
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        ▼ Downvote
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Accept answer button (only for question creator) -->
                            <?php if (($question['creator'] ?? '') === $CURRENT_USER && !($answer['accepted'] ?? false)): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="accept_answer">
                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                        ✓ Accept Answer
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Answer Comments -->
                    <?php if (!empty($answer['comments'])): ?>
                        <div class="border-t border-gray-600 pt-4 mb-4">
                            <h4 class="text-sm font-semibold mb-2 text-gray-400">Comments</h4>
                            <?php foreach ($answer['comments'] as $comment): ?>
                                <div class="bg-gray-700 p-3 rounded mb-2 text-wrap">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="user-info">
                                            <?php 
                                            $commentorData = getUserLevelAndPoints($pdo, $comment['username'] ?? '');
                                            $commentorLevel = $commentorData['level'];
                                            $commentorEmail = getUserEmail($pdo, $comment['username'] ?? '');
                                            ?>
                                            <img src="<?php echo getGravatarUrl($commentorEmail, 20); ?>" 
                                                alt="<?php echo htmlspecialchars($comment['username'] ?? 'Unknown'); ?>" 
                                                class="gravatar w-5 h-5 rounded-full">
                                            <div>
                                                <strong class="text-sm text-wrap"><?php echo htmlspecialchars($comment['username'] ?? 'Unknown'); ?></strong>
                                                <span class="level-badge level-<?php echo $commentorLevel; ?> text-xs">L<?php echo $commentorLevel; ?></span>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-400"><?php echo formatDate($comment['createdAt'] ?? time()); ?></span>
                                    </div>
                                    <p class="text-sm text-gray-300 text-wrap"><?php echo renderMarkdown($comment['text'] ?? $comment['Comment'] ?? ''); ?></p>
                                    
                                    <!-- Comment voting -->
                                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                                        <div class="flex items-center space-x-2 mt-2">
                                            <span class="text-xs text-gray-400">Points: <?php echo ($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0); ?></span>
                                            
                                            <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="vote_answer_comment">
                                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['comment_id'] ?? ''); ?>">
                                                    <input type="hidden" name="operation" value="upvote">
                                                    <button type="submit" class="text-green-400 hover:text-green-300 text-xs">▲</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="vote_answer_comment">
                                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                                    <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                                    <input type="hidden" name="comment_id" value="<?php echo htmlspecialchars($comment['comment_id'] ?? ''); ?>">
                                                    <input type="hidden" name="operation" value="downvote">
                                                    <button type="submit" class="text-red-400 hover:text-red-300 text-xs">▼</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add Comment to Answer -->
                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                        <?php 
                        $isOwnAnswer = ($answer['creator'] ?? '') === $CURRENT_USER;
                        $isQuestionCreator = ($question['creator'] ?? '') === $CURRENT_USER;
                        $canCommentOnAnswer = $isOwnAnswer || $isQuestionCreator || canPerformAction('comment_any', $userLevel, $userPoints, $isGuest);
                        ?>
                        
                        <?php if ($canCommentOnAnswer): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_answer_comment">
                                <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                                <input type="hidden" name="answer_id" value="<?php echo htmlspecialchars($answer['answer_id'] ?? ''); ?>">
                                <div class="mb-3">
                                    <label class="block text-sm font-medium mb-2">Add Comment (max 150 characters)</label>
                                    <textarea name="comment_text" rows="2" maxlength="150" 
                                            class="w-full p-3 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 text-wrap" 
                                            placeholder="Enter your comment..." required></textarea>
                                </div>
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm transition-colors">
                                    Add Comment
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="text-gray-400 text-xs mt-2">You need level 3 to comment on others' content.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>

        <!-- Add Answer Section -->
        <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
            <?php if (canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)): ?>
                <section class="bg-gray-800 rounded-lg p-6 shadow-lg">
                    <h3 class="text-xl font-semibold text-white mb-4">Your Answer</h3>
                    
                    <div class="tab-container">
                        <div class="flex border-b border-gray-700">
                            <button class="tab-button active" onclick="switchTab('write')">Write</button>
                            <button class="tab-button" onclick="switchTab('preview')">Preview</button>
                        </div>
                        
                        <form method="POST" class="p-4">
                            <input type="hidden" name="action" value="add_answer">
                            <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($actualQuestionId); ?>">
                            
                            <div id="write-tab" class="tab-content active">
                                <textarea id="answer-textarea" name="answer_text" rows="10" maxlength="3000" 
                                        class="w-full p-4 bg-gray-700 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 text-wrap" 
                                        placeholder="Enter your answer here... (max 3000 characters)" 
                                        oninput="updatePreview()" required></textarea>
                                <p class="text-sm text-gray-400 mt-2">
                                    You can use basic Markdown: **bold**, *italic*, `code`, ```code blocks```
                                </p>
                            </div>
                            
                            <div id="preview-tab" class="tab-content">
                                <div id="answer-preview" class="markdown-preview text-wrap code-wrap">
                                    <em class="text-gray-400">Nothing to preview yet. Write something in the Write tab!</em>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                                    Post Your Answer
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            <?php else: ?>
                <div class="bg-gray-800 rounded-lg p-6 text-center">
                    <p class="text-gray-400">You need level 1 (1 point) to post answers.</p>
                </div>
            <?php endif; ?>
        <?php elseif ($isGuest): ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <p class="text-gray-400 mb-4">Want to answer this question?</p>
                <a href="/login" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors">
                    Log In to Answer
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Hide spinner and show content after page loads
        window.addEventListener('load', function() {
            document.getElementById('spinner').style.display = 'none';
            document.getElementById('main-content').classList.remove('hidden');
        });

        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab and activate button
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
            
            // Update preview if switching to preview tab
            if (tabName === 'preview') {
                updatePreview();
            }
        }

        function updatePreview() {
            const textarea = document.getElementById('answer-textarea');
            const preview = document.getElementById('answer-preview');
            const text = textarea.value.trim();
            
            if (text === '') {
                preview.innerHTML = '<em class="text-gray-400">Nothing to preview yet. Write something in the Write tab!</em>';
                return;
            }
            
            // Simple markdown rendering (matches PHP function)
            let html = text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;')
                .replace(/```([\s\S]*?)```/g, '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>')
                .replace(/`([^`]+)`/g, '<code class="bg-gray-600 px-1 rounded">$1</code>')
                .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
                .replace(/\*([^*]+)\*/g, '<em>$1</em>')
                .replace(/\n/g, '<br>');
                
            preview.innerHTML = html;
        }
    </script>
</body>
</html>