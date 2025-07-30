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
        return strtolower($username) . '@example.com';
    } catch (Exception $e) {
        error_log("[DEBUG] Error getting user email for {$username}: " . $e->getMessage());
        return strtolower($username) . '@example.com';
    }
}

// User authentication check
$CURRENT_USER = $_SESSION['username'] ?? null;
$isGuest = !$CURRENT_USER;
$userLevel = 0;
$userPoints = 0;

if (!$isGuest) {
    try {
        $userResult = $api->getUser($CURRENT_USER);
        if (!isset($userResult['error'])) {
            $userPoints = $userResult['user']['points'] ?? 0;
            $userLevelData = getUserLevel($CURRENT_USER);
            $userLevel = $userLevelData['level'];
        }
    } catch (Exception $e) {
        error_log("[DEBUG] Error getting user level/points: " . $e->getMessage());
    }
}

$questionName = $_GET['questionName'] ?? '123';
$message = '';
$messageType = 'success';
$question = null;
$answers = [];
$questionComments = [];
$answerComments = [];
$actualQuestionId = null;

// --- Helper functions ---

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
        error_log("[DEBUG] Error getting user level & points for {$username}: " . $e->getMessage());
        return ['level' => 0, 'points' => 0];
    }
}

function canPerformAction($action, $userLevel, $userPoints, $isGuest) {
    if ($isGuest) return in_array($action, ['view']);

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

function formatDate($timestamp) {
    $timezone = new DateTimeZone('America/Chicago');

    if (is_numeric($timestamp)) {
        if ($timestamp > 1000000000000) {
            $timestamp = (int)($timestamp / 1000);
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
    $text = preg_replace('/```([\s\S]*?)```/', '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>', $text);
    $text = preg_replace('/`([^`\n]+)`/', '<code class="bg-gray-600 px-1 rounded">$1</code>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
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

function incrementQuestionViews($questionId) {
    global $api;

    if (!isset($_SESSION['viewed_question_' . $questionId])) {
        $_SESSION['viewed_question_' . $questionId] = true;
        // Use updateQuestion to increment views by fetching current and adding 1
        $curr = $api->getQuestion($questionId);
        if (!isset($curr['error']) && isset($curr['views'])) {
            $newViews = max(0, (int)$curr['views']) + 1;
            $api->updateQuestion($questionId, ['views' => $newViews]);
        }
    }
}

/**
 * Handle vote via API
 */
function handleVote($api, $action, $postData, $currentUser) {
    $questionId = $postData['question_id'] ?? null;
    $operation = $postData['operation'] ?? ''; // 'upvote' or 'downvote'
    $apiOperation = ($operation === 'upvote') ? 'increment' : 'decrement';

    if (!$questionId) {
        return ['error' => 'Missing question_id'];
    }

    $result = null;

    switch ($action) {
        case 'vote_question':
            // voteQuestionViaAPI adapts operation name for API GET call
            $result = voteQuestionViaAPI($questionId, $currentUser, $operation);
            break;

        case 'vote_answer':
            $answerId = $postData['answer_id'] ?? null;
            if (!$answerId) return ['error' => 'Missing answer_id'];
            $result = $api->voteAnswer($questionId, $answerId, $currentUser, $apiOperation, 'answer');
            break;

        case 'vote_question_comment':
            $commentId = $postData['comment_id'] ?? null;
            if (!$commentId) return ['error' => 'Missing comment_id'];
            $result = $api->voteQuestionComment($questionId, $commentId, $currentUser, $apiOperation, 'comment');
            break;

        case 'vote_answer_comment':
            $answerId = $postData['answer_id'] ?? null;
            $commentId = $postData['comment_id'] ?? null;
            if (!$answerId || !$commentId) return ['error' => 'Missing answer_id or comment_id'];
            $result = $api->voteAnswerComment($questionId, $answerId, $commentId, $currentUser, $apiOperation, 'comment');
            break;

        default:
            return ['error' => 'Invalid vote action'];
    }

    // Update user points accordingly on success
    if (!isset($result['error'])) {
        $isUndo = isset($result['undone']) && $result['undone'] === true;

        // Adjust points for downvoting user
        if ($operation === 'downvote') {
            updateUserPoints($currentUser, $isUndo ? 1 : -1);
        }

        if ($action === 'vote_question') {
            $questionData = $api->getQuestion($questionId);
            if (!isset($questionData['error'])) {
                $creator = $questionData['creator'] ?? '';
                if ($creator !== $currentUser) {
                    if ($operation === 'upvote') {
                        updateUserPoints($creator, $isUndo ? -5 : 5);
                    } else {
                        updateUserPoints($creator, $isUndo ? 1 : -1);
                    }
                }
            }
        } elseif ($action === 'vote_answer') {
            $answersData = $api->getAnswers($questionId);
            if (!isset($answersData['error'])) {
                $answerId = $postData['answer_id'];
                foreach ($answersData['answers'] as $answer) {
                    if ($answer['answer_id'] === $answerId) {
                        $creator = $answer['creator'] ?? '';
                        if ($creator !== $currentUser) {
                            if ($operation === 'upvote') {
                                updateUserPoints($creator, $isUndo ? -10 : 10);
                            } else {
                                updateUserPoints($creator, $isUndo ? 5 : -5);
                            }
                        }
                        break;
                    }
                }
            }
        } elseif ($action === 'vote_question_comment') {
            $commentId = $postData['comment_id'];
            $comments = $api->getQuestionComments($questionId);
            if (!isset($comments['error'])) {
                foreach ($comments['comments'] as $comment) {
                    if ($comment['comment_id'] === $commentId) {
                        $creator = $comment['creator'] ?? '';
                        if ($creator !== $currentUser) {
                            if ($operation === 'upvote') {
                                updateUserPoints($creator, $isUndo ? -2 : 2);
                            } else {
                                updateUserPoints($creator, $isUndo ? 1 : -1);
                            }
                        }
                        break;
                    }
                }
            }
        } elseif ($action === 'vote_answer_comment') {
            $answerId = $postData['answer_id'];
            $commentId = $postData['comment_id'];
            $commentsData = $api->getAnswerComments($questionId, $answerId);
            if (!isset($commentsData['error'])) {
                foreach ($commentsData['comments'] as $comment) {
                    if ($comment['comment_id'] === $commentId) {
                        $creator = $comment['creator'] ?? '';
                        if ($creator !== $currentUser) {
                            if ($operation === 'upvote') {
                                updateUserPoints($creator, $isUndo ? -2 : 2);
                            } else {
                                updateUserPoints($creator, $isUndo ? 1 : -1);
                            }
                        }
                        break;
                    }
                }
            }
        }
    }
    return $result;
}

/**
 * voteQuestionViaAPI implementation - direct GET vote call due to private API method
 */
function voteQuestionViaAPI($questionId, $username, $operation) {
    // Map upvote/downvote to increment/decrement
    $opStr = ($operation === 'upvote') ? 'increment' : 'decrement';

$apiBase = 'https://qoverflow.api.hscc.bdpa.org/$version';

    $url = rtrim($apiBase, '/') . "/questions/{$questionId}/vote/{$username}?operation={$opStr}&key=" . urlencode(API_KEY);

    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Accept: application/json\r\n"
        ]
    ];
    $context = stream_context_create($opts);

    try {
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            return ['error' => 'Failed to contact vote API'];
        }
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Invalid JSON from vote API'];
        }
        return $result;
    } catch (Exception $e) {
        return ['error' => 'Exception contacting vote API: ' . $e->getMessage()];
    }
}

// --- AJAX Handling for voting and views ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    try {
        if ($isGuest) {
            throw new Exception("You must be logged in to perform this action.");
        }

        $action = $_POST['action'] ?? '';
        $questionId = $_POST['question_id'] ?? null;
        if (!$questionId) throw new Exception("Missing question_id.");

        switch ($action) {
            case 'update_view':
                incrementQuestionViews($questionId);
                $questionData = $api->getQuestion($questionId);
                echo json_encode([
                    'success' => true,
                    'views' => $questionData['views'] ?? 0,
                ]);
                exit;

            case 'vote_question':
            case 'vote_answer':
            case 'vote_question_comment':
            case 'vote_answer_comment':
                $operation = $_POST['operation'] ?? '';
                if (!in_array($operation, ['upvote', 'downvote'])) throw new Exception("Invalid vote operation.");

                $canVote = ($operation === 'upvote' && canPerformAction('upvote', $userLevel, $userPoints, $isGuest)) ||
                           ($operation === 'downvote' && canPerformAction('downvote', $userLevel, $userPoints, $isGuest));
                if (!$canVote) throw new Exception("You do not have permission to $operation.");

                $result = handleVote($api, $action, $_POST, $CURRENT_USER);
                if (isset($result['error'])) throw new Exception($result['error']);

                // Fetch updated votes for the related entity
                $newVotes = 0;
                switch ($action) {
                    case 'vote_question':
                        $qData = $api->getQuestion($questionId);
                        $newVotes = (($qData['upvotes'] ?? 0) - ($qData['downvotes'] ?? 0));
                        break;
                    case 'vote_answer':
                        $answerId = $_POST['answer_id'];
                        $answersData = $api->getAnswers($questionId);
                        if (!isset($answersData['error'])) {
                            foreach ($answersData['answers'] as $answer) {
                                if ($answer['answer_id'] === $answerId) {
                                    $newVotes = (($answer['upvotes'] ?? 0) - ($answer['downvotes'] ?? 0));
                                    break;
                                }
                            }
                        }
                        break;
                    case 'vote_question_comment':
                        $commentId = $_POST['comment_id'];
                        $comments = $api->getQuestionComments($questionId);
                        if (!isset($comments['error'])) {
                            foreach ($comments['comments'] as $comment) {
                                if ($comment['comment_id'] === $commentId) {
                                    $newVotes = (($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0));
                                    break;
                                }
                            }
                        }
                        break;
                    case 'vote_answer_comment':
                        $answerId = $_POST['answer_id'];
                        $commentId = $_POST['comment_id'];
                        $commentData = $api->getAnswerComments($questionId, $answerId);
                        if (!isset($commentData['error'])) {
                            foreach ($commentData['comments'] as $comment) {
                                if ($comment['comment_id'] === $commentId) {
                                    $newVotes = (($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0));
                                    break;
                                }
                            }
                        }
                        break;
                }

                echo json_encode([
                    'success' => true,
                    'votes' => $newVotes,
                    'operation' => $operation,
                    'undone' => $result['undone'] ?? false,
                ]);
                exit;

            case 'protect_question':
            case 'close_question':
            case 'reopen_question':
                $requiredLevel = ($action === 'protect_question') ? 6 : 7;
                if ($userLevel < $requiredLevel) {
                    throw new Exception("You need level $requiredLevel to perform this action");
                }

                $newStatus = null;
                if ($action === 'protect_question') $newStatus = 'protected';
                elseif ($action === 'close_question') $newStatus = 'closed';
                elseif ($action === 'reopen_question') $newStatus = 'open';

                $updateResult = $api->updateQuestion($questionId, ['status' => $newStatus]);
                if (isset($updateResult['error'])) {
                    throw new Exception("Failed to update question status: " . $updateResult['error']);
                }

                echo json_encode([
                    'success' => true,
                    'new_status' => $newStatus,
                    'message' => ucfirst($newStatus) . " status set for question!",
                ]);
                exit;

            default:
                throw new Exception("Invalid AJAX action");
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// --- Handle non-AJAX POST form submissions (redirect after processing) ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    if ($isGuest) {
        $message = 'You must be logged in to perform this action.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            $actualQuestionId = $_POST['question_id'] ?? $questionName;
            switch ($action) {
                case 'add_question_comment':
                    $text = trim($_POST['comment_text'] ?? '');
                    if ($text === '') throw new Exception('Comment text required');
                    if (strlen($text) > 150) throw new Exception('Comment too long (max 150 characters)');
                    $questionResult = $api->getQuestion($actualQuestionId);
                    $isOwnQuestion = !isset($questionResult['error']) && ($questionResult['creator'] ?? '') === $CURRENT_USER;
                    if (!$isOwnQuestion && !canPerformAction('comment_any', $userLevel, $userPoints, $isGuest)) {
                        throw new Exception('You lack permission to comment on this question');
                    }
                    $result = $api->createQuestionComment($actualQuestionId, $CURRENT_USER, $text);
                    if (isset($result['error'])) throw new Exception($result['error']);
                    $message = "Comment added to question!";
                    break;

                case 'add_answer_comment':
                    $answerId = $_POST['answer_id'] ?? '';
                    $text = trim($_POST['comment_text'] ?? '');
                    if ($text === '') throw new Exception('Comment text required');
                    if (strlen($text) > 150) throw new Exception('Comment too long (max 150 characters)');
                    $questionResult = $api->getQuestion($actualQuestionId);
                    $answersResult = $api->getAnswers($actualQuestionId);
                    $canComment = false;
                    if (!isset($questionResult['error']) && ($questionResult['creator'] ?? '') === $CURRENT_USER) {
                        $canComment = true;
                    } elseif (!isset($answersResult['error'])) {
                        foreach ($answersResult['answers'] ?? [] as $answer) {
                            if ($answer['answer_id'] === $answerId && ($answer['creator'] ?? '') === $CURRENT_USER) {
                                $canComment = true;
                                break;
                            }
                        }
                    }
                    if (!$canComment && !canPerformAction('comment_any', $userLevel, $userPoints, $isGuest)) {
                        throw new Exception('You need level 3 to comment on others\' content');
                    }
                    $result = $api->createAnswerComment($actualQuestionId, $answerId, $CURRENT_USER, $text);
                    if (isset($result['error'])) throw new Exception($result['error']);
                    $message = "Comment added to answer!";
                    break;

                case 'add_answer':
                    $text = trim($_POST['answer_text'] ?? '');
                    if ($text === '') throw new Exception('Answer text required');
                    if (strlen($text) > 3000) throw new Exception('Answer too long (max 3000 characters)');

                    if (!canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)) {
                        throw new Exception('You need level 1 (1 point) to create answers');
                    }
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
                    if (isset($result['error'])) throw new Exception($result['error']);
                    updateUserPoints($CURRENT_USER, 2);
                    $message = "Answer submitted successfully!";
                    break;

                case 'accept_answer':
                    $answerId = $_POST['answer_id'] ?? '';
                    $questionResult = $api->getQuestion($actualQuestionId);
                    if (isset($questionResult['error']) || ($questionResult['creator'] ?? '') !== $CURRENT_USER) {
                        throw new Exception('Only the question creator can accept answers');
                    }
                    $result = $api->updateAnswer($actualQuestionId, $answerId, ['accepted' => true]);
                    if (isset($result['error'])) throw new Exception($result['error']);
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
                    // Actually update the question status
                    $newStatus = null;
                    if ($action === 'protect_question') $newStatus = 'protected';
                    elseif ($action === 'close_question') $newStatus = 'closed';
                    elseif ($action === 'reopen_question') $newStatus = 'open';

                    if ($newStatus !== null) {
                        $updateResult = $api->updateQuestion($actualQuestionId, ['status' => $newStatus]);
                        if (isset($updateResult['error'])) {
                            throw new Exception("Failed to update question status: " . $updateResult['error']);
                        }
                        $message = ucfirst($newStatus) . " status set for question!";
                    } else {
                        throw new Exception("Invalid protect/close/reopen action");
                    }
                    break;

                default:
                    throw new Exception('Unknown action: ' . $action);
            }
        } catch (Exception $ex) {
            $message = 'Error: ' . $ex->getMessage();
            $messageType = 'error';
        }
    }

    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?questionName=" . urlencode($questionName) . "&msg=" . urlencode($message) . "&type=" . urlencode($messageType));
    exit;
}

// Show message from GET params
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'success';
}

// Load question data
try {
    $questionResult = $api->getQuestion($questionName);

    if (!$questionResult || isset($questionResult['error'])) {
        $searchResult = $api->searchQuestions(['limit' => 50]);
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
            header("Location: q&aError.php");
            exit; 
        }
    } else {
        $question = $questionResult;
        $actualQuestionId = $question['question_id'] ?? $questionName;
    }
    incrementQuestionViews($actualQuestionId);
} catch (Exception $ex) {
    $message = 'Error loading question: ' . $ex->getMessage();
    $messageType = 'error';
}

// Load answers & comments
if ($question && $actualQuestionId) {
    try {
        $answersResult = $api->getAnswers($actualQuestionId);
        if (!isset($answersResult['error'])) {
            $answers = $answersResult['answers'] ?? [];
        }
        $commentsResult = $api->getQuestionComments($actualQuestionId);
        if (!isset($commentsResult['error'])) {
            $questionComments = $commentsResult['comments'] ?? [];
        }
        $answerComments = [];
        foreach ($answers as $answer) {
            $answerId = $answer['answer_id'] ?? '';
            $answerCommentsResult = $api->getAnswerComments($actualQuestionId, $answerId);
            if (!isset($answerCommentsResult['error'])) {
                $answerComments[$answerId] = $answerCommentsResult['comments'] ?? [];
            } else {
                $answerComments[$answerId] = [];
            }
        }
    } catch (Exception $ex) {
        error_log("[DEBUG] Error loading question data: " . $ex->getMessage());
    }
}

// Sort answers: accepted first, then by points descending
if (!empty($answers)) {
    usort($answers, function ($a, $b) {
        $aAccepted = $a['accepted'] ?? false;
        $bAccepted = $b['accepted'] ?? false;
        if ($aAccepted && !$bAccepted) return -1;
        if (!$aAccepted && $bAccepted) return 1;
        $aPoints = ($a['upvotes'] ?? 0) - ($a['downvotes'] ?? 0);
        $bPoints = ($b['upvotes'] ?? 0) - ($b['downvotes'] ?? 0);
        return $bPoints - $aPoints;
    });
}

if (!$question) {
 header("Location: q&aError.php");    
 echo "Question not found";
    exit;
}

?><!DOCTYPE html>
<html lang="en" class="bg-gray-900 text-gray-300">
<head>
    <meta charset="UTF-8" />
    <title><?=htmlspecialchars($question['title'] ?? 'Question');?> - Q&A</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        .text-wrap {
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            hyphens: auto;
        }
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
        .vote-button { cursor: pointer; font-weight: bold; }
        .disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body class="bg-gray-900 text-gray-300 font-sans">
<?php 
if ($isGuest) {
    include '../../components/navBarLogOut.php';
} else {
    include '../../components/navBarLogIn.php';
}
?>

<div id="spinner" class="flex justify-center items-center py-20">
    <div class="w-10 h-10 border-4 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
</div>

<div id="main-content" class="max-w-4xl mx-auto p-6 hidden">
    <?php if ($message): ?>
        <div class="fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 <?= ($messageType === 'success') ? 'bg-green-600' : 'bg-red-600'; ?> text-white" id="status-message">
            <?=htmlspecialchars($message);?>
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
                <img src="<?=getGravatarUrl(getUserEmail($CURRENT_USER), 32);?>" alt="<?=htmlspecialchars($CURRENT_USER);?>" class="gravatar w-8 h-8 rounded-full" />
                <div>
                    <span class="text-gray-400">Logged in as:</span> 
                    <strong class="text-wrap"><?=htmlspecialchars($CURRENT_USER);?></strong>
                    <span class="level-badge level-<?=$userLevel;?>">Level <?=$userLevel;?></span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="bg-gray-800 rounded-lg p-6 mb-10 shadow-lg">
        <?php 
        $statusInfo = getStatusDisplay($question['status'] ?? 'open');
        if ($statusInfo): ?>
            <div class="<?= $statusInfo['class']; ?> text-white px-4 py-2 rounded mb-4 font-semibold text-wrap">
                <?= $statusInfo['text']; ?>: <?= $statusInfo['description']; ?>
            </div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-3 flex-wrap">
            <div class="space-x-4 text-sm text-gray-400">
                <span>Asked: <time><?=formatDate($question['createdAt'] ?? time());?></time></span>
                <span>Views: <span id="question-views"><?=intval($question['views'] ?? 0);?></span></span>
                <span>Votes: <strong id="question-points"><?=intval(($question['upvotes'] ?? 0) - ($question['downvotes'] ?? 0));?></strong></span>
                <?php if (canPerformAction('view_vote_breakdown', $userLevel, $userPoints, $isGuest)): ?>
                    <span class="text-green-400">▲<?=intval($question['upvotes'] ?? 0);?></span>
                    <span class="text-red-400">▼<?=intval($question['downvotes'] ?? 0);?></span>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <?php 
                $creatorData = getUserLevelAndPoints($question['creator'] ?? '');
                $creatorLevel = $creatorData['level'];
                $creatorEmail = getUserEmail($question['creator'] ?? '');
                ?>
                <img src="<?=getGravatarUrl($creatorEmail, 32);?>" alt="<?=htmlspecialchars($question['creator'] ?? 'Unknown');?>" class="gravatar w-8 h-8 rounded-full" />
                <div>
                    <strong class="text-wrap"><?=htmlspecialchars($question['creator'] ?? 'Unknown');?></strong>
                    <span class="level-badge level-<?=$creatorLevel;?>">Level <?=$creatorLevel;?></span>
                </div>
            </div>
        </div>

        <h1 class="text-white text-3xl font-bold mb-5 text-wrap"><?=htmlspecialchars($question['title'] ?? 'Untitled Question');?></h1>

        <article class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-8 leading-relaxed text-gray-300 shadow-inner text-wrap code-wrap">
            <?=renderMarkdown($question['body'] ?? $question['text'] ?? 'No content available');?>
        </article>

        <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
        <div class="flex items-center space-x-4 mb-6 flex-wrap">
            <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                <button id="question-upvote" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors cursor-pointer">▲ Upvote</button>
            <?php endif; ?>
            <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                <button id="question-downvote" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors cursor-pointer">▼ Downvote</button>
            <?php endif; ?>
            <?php if (canPerformAction('protect_vote', $userLevel, $userPoints, $isGuest)): ?>
                <button id="question-protect" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition-colors cursor-pointer">Protect</button>
            <?php endif; ?>
            <?php if (canPerformAction('close_reopen_vote', $userLevel, $userPoints, $isGuest) && ($question['status'] ?? 'open') !== 'closed'): ?>
                <button id="question-close" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition-colors cursor-pointer">Close</button>
            <?php endif; ?>
            <?php if (canPerformAction('close_reopen_vote', $userLevel, $userPoints, $isGuest) && ($question['status'] ?? 'open') === 'closed'): ?>
                <button id="question-reopen" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors cursor-pointer">Reopen</button>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <div class="text-gray-500 mb-6">Login and have enough reputation to vote or change question status.</div>
        <?php endif; ?>

        <!-- Question Comments -->
        <?php if (!empty($questionComments)): ?>
        <div class="mt-6 bg-gray-700 rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-4 text-white">Comments</h3>
            <?php foreach ($questionComments as $comment): ?>
                <?php 
                $cid = $comment['comment_id'] ?? '';
                $commenterData = getUserLevelAndPoints($comment['creator'] ?? '');
                $commenterLevel = $commenterData['level'];
                $commenterEmail = getUserEmail($comment['creator'] ?? '');
                ?>
                <div id="question-comment-<?=$cid;?>" class="mb-4 pb-4 border-b border-gray-600 last:border-b-0">
                    <div class="flex justify-between items-start mb-2">
                        <div class="user-info">
                            <img src="<?=getGravatarUrl($commenterEmail, 24);?>" alt="<?=htmlspecialchars($comment['creator'] ?? 'Unknown');?>" class="gravatar w-6 h-6 rounded-full" />
                            <div>
                                <strong class="text-wrap"><?=htmlspecialchars($comment['creator'] ?? 'Unknown');?></strong>
                                <span class="level-badge level-<?=$commenterLevel;?>">Level <?=$commenterLevel;?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-400"><?=formatDate($comment['createdAt'] ?? time());?></span>
                            <span class="text-xs text-gray-400">Points: <span class="question-comment-votes" data-comment-id="<?=$cid;?>"><?=intval(($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0));?></span></span>
                        </div>
                    </div>
                    <p class="text-gray-300 text-wrap"><?=renderMarkdown($comment['text'] ?? '');?></p>
                    <?php if(!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                    <div class="flex items-center space-x-2 mt-2">
                        <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                            <button class="vote-button question-comment-upvote text-green-400 hover:text-green-300 text-sm" data-comment-id="<?=$cid;?>">▲</button>
                        <?php endif; ?>
                        <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                            <button class="vote-button question-comment-downvote text-red-400 hover:text-red-300 text-sm" data-comment-id="<?=$cid;?>">▼</button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Add Question Comment Form -->
        <?php 
        $canCommentQuestion = canPerformAction('comment_any', $userLevel, $userPoints, $isGuest) || 
            (($question['creator'] ?? '') === $CURRENT_USER);
        if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel) && $canCommentQuestion): ?>
        <div class="mt-6 bg-gray-700 rounded-lg p-4">
            <h3 class="text-lg font-semibold mb-4 text-white">Add Comment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_question_comment">
                <input type="hidden" name="question_id" value="<?=htmlspecialchars($actualQuestionId);?>">
                <div class="mb-4">
                    <textarea id="question-comment-text" name="comment_text" rows="3" maxlength="150"
                        class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="Add a comment (max 150 characters)..." required></textarea>
                    <div class="text-xs text-gray-400 mt-1">Characters remaining: <span id="question-comment-chars">150</span></div>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                    Post Comment
                </button>
            </form>
        </div>
        <?php endif; ?>
    </section>

    <!-- Answers Section -->
    <section class="mb-10">
        <h2 class="text-2xl font-bold text-white mb-6">
            <?= count($answers); ?> Answer<?= count($answers) !== 1 ? 's' : ''; ?>
        </h2>

        <?php foreach ($answers as $answer):
            $answerId = $answer['answer_id'] ?? '';
            $answererData = getUserLevelAndPoints($answer['creator'] ?? '');
            $answererLevel = $answererData['level'];
            $answererEmail = getUserEmail($answer['creator'] ?? '');
            $answerVotes = intval(($answer['upvotes'] ?? 0) - ($answer['downvotes'] ?? 0));
            $accepted = $answer['accepted'] ?? false;
            $comments = $answerComments[$answerId] ?? [];
        ?>
        <article id="answer-<?=$answerId;?>" class="bg-gray-800 rounded-lg p-6 mb-6 shadow-lg <?= $accepted ? 'border-l-4 border-green-500' : ''; ?>">
            <?php if ($accepted): ?>
                <div class="bg-green-600 text-white px-4 py-2 rounded mb-4 font-semibold">
                    ✓ ACCEPTED ANSWER
                </div>
            <?php endif; ?>

            <div class="flex justify-between items-center mb-4 flex-wrap">
                <div class="space-x-4 text-sm text-gray-400">
                    <span>Answered: <time><?=formatDate($answer['createdAt'] ?? time());?></time></span>
                    <span>Votes: <strong class="answer-points" data-answer-id="<?=$answerId;?>"><?=$answerVotes;?></strong></span>
                    <?php if (canPerformAction('view_vote_breakdown', $userLevel, $userPoints, $isGuest)): ?>
                        <span class="text-green-400">▲<?=intval($answer['upvotes'] ?? 0);?></span>
                        <span class="text-red-400">▼<?=intval($answer['downvotes'] ?? 0);?></span>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <img src="<?=getGravatarUrl($answererEmail, 32);?>" alt="<?=htmlspecialchars($answer['creator'] ?? 'Unknown');?>" class="gravatar w-8 h-8 rounded-full" />
                    <div>
                        <strong class="text-wrap"><?=htmlspecialchars($answer['creator'] ?? 'Unknown');?></strong>
                        <span class="level-badge level-<?=$answererLevel;?>">Level <?=$answererLevel;?></span>
                    </div>
                </div>
            </div>

            <div class="prose prose-invert bg-gray-700 p-6 rounded-lg mb-6 leading-relaxed text-gray-300 shadow-inner text-wrap code-wrap">
                <?=renderMarkdown($answer['text'] ?? $answer['body'] ?? 'No content available');?>
            </div>

            <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
            <div class="flex items-center space-x-4 mb-4 flex-wrap">
                <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                    <button class="answer-upvote bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors cursor-pointer"
                            data-answer-id="<?=$answerId;?>">▲ Upvote</button>
                <?php endif; ?>
                <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                    <button class="answer-downvote bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors cursor-pointer" 
                            data-answer-id="<?=$answerId;?>">▼ Downvote</button>
                <?php endif; ?>

                <?php if (($question['creator'] ?? '') === $CURRENT_USER && !$accepted): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="action" value="accept_answer">
                        <input type="hidden" name="question_id" value="<?=htmlspecialchars($actualQuestionId);?>">
                        <input type="hidden" name="answer_id" value="<?=htmlspecialchars($answerId);?>">
                        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded transition-colors">
                            ✓ Accept Answer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Answer Comments -->
            <?php if (!empty($comments)): ?>
            <div class="bg-gray-700 rounded-lg p-4 mb-4">
                <h4 class="text-md font-semibold mb-3 text-white">Comments</h4>
                <?php foreach ($comments as $comment):
                    $cid = $comment['comment_id'] ?? '';
                    $commenterData = getUserLevelAndPoints($comment['creator'] ?? '');
                    $commenterLevel = $commenterData['level'];
                    $commenterEmail = getUserEmail($comment['creator'] ?? '');
                    $voteCount = intval(($comment['upvotes'] ?? 0) - ($comment['downvotes'] ?? 0));
                ?>
                <div id="answer-comment-<?=$answerId;?>-<?=$cid;?>" class="mb-3 pb-3 border-b border-gray-600 last:border-b-0">
                    <div class="flex justify-between items-start mb-2">
                        <div class="user-info">
                            <img src="<?=getGravatarUrl($commenterEmail, 20);?>" alt="<?=htmlspecialchars($comment['creator'] ?? 'Unknown');?>" class="gravatar w-5 h-5 rounded-full" />
                            <div>
                                <strong class="text-sm text-wrap"><?=htmlspecialchars($comment['creator'] ?? 'Unknown');?></strong>
                                <span class="level-badge level-<?=$commenterLevel;?> text-xs">Level <?=$commenterLevel;?></span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-400"><?=formatDate($comment['createdAt'] ?? time());?></span>
                            <span class="text-xs text-gray-400">Points: <span class="answer-comment-votes" data-answer-id="<?=$answerId;?>" data-comment-id="<?=$cid;?>"><?=$voteCount;?></span></span>
                        </div>
                    </div>
                    <p class="text-sm text-gray-300 text-wrap"><?=renderMarkdown($comment['text'] ?? '');?></p>

                    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
                    <div class="flex items-center space-x-2 mt-2">
                        <?php if (canPerformAction('upvote', $userLevel, $userPoints, $isGuest)): ?>
                            <button class="vote-button answer-comment-upvote text-green-400 hover:text-green-300 text-sm" data-answer-id="<?=$answerId;?>" data-comment-id="<?=$cid;?>">▲</button>
                        <?php endif; ?>
                        <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                            <button class="vote-button answer-comment-downvote text-red-400 hover:text-red-300 text-sm" data-answer-id="<?=$answerId;?>" data-comment-id="<?=$cid;?>">▼</button>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Add Answer Comment Form -->
            <?php
            $canCommentAnswer = canPerformAction('comment_any', $userLevel, $userPoints, $isGuest) || 
                          (($answer['creator'] ?? '') === $CURRENT_USER) ||
                          (($question['creator'] ?? '') === $CURRENT_USER);
            if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel) && $canCommentAnswer): ?>
            <div class="bg-gray-700 rounded-lg p-4">
                <h4 class="text-md font-semibold mb-3 text-white">Add Comment</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_answer_comment">
                    <input type="hidden" name="question_id" value="<?=htmlspecialchars($actualQuestionId);?>">
                    <input type="hidden" name="answer_id" value="<?=htmlspecialchars($answerId);?>">
                    <div class="mb-3">
                        <textarea class="answer-comment-text w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none" 
                                  name="comment_text" rows="2" maxlength="150" placeholder="Add a comment (max 150 characters)..." required></textarea>
                        <div class="text-xs text-gray-400 mt-1">Characters remaining: <span class="answer-comment-chars">150</span></div>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        Post Comment
                    </button>
                </form>
            </div>
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

            <form method="POST" id="answer-form">
                <input type="hidden" name="action" value="add_answer">
                <input type="hidden" name="question_id" value="<?=htmlspecialchars($actualQuestionId);?>">

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
        <a href="../auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">Log In</a>
    </section>
    <?php elseif (!canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)): ?>
    <section class="bg-gray-800 rounded-lg p-6 shadow-lg text-center">
        <h2 class="text-xl font-bold text-white mb-4">Answer Restricted</h2>
        <p class="text-gray-400 mb-4">You need Level 1 (1 point) to post answers. Current level: <?=$userLevel;?></p>
    </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Hide spinner and show content
    document.getElementById('spinner').style.display = 'none';
    document.getElementById('main-content').classList.remove('hidden');

    // Character countdowns
    const answerTextarea = document.getElementById('answer-textarea');
    const answerCharsSpan = document.getElementById('answer-chars');
    if (answerTextarea && answerCharsSpan) {
        answerTextarea.addEventListener('input', function () {
            let remaining = 3000 - this.value.length;
            answerCharsSpan.textContent = remaining;
            if (remaining < 100) answerCharsSpan.className = 'text-red-400';
            else if (remaining < 300) answerCharsSpan.className = 'text-yellow-400';
            else answerCharsSpan.className = 'text-gray-400';
        });
    }

    // Question comment chars
    const questionCommentTextarea = document.getElementById('question-comment-text');
    const questionCommentChars = document.getElementById('question-comment-chars');
    if (questionCommentTextarea && questionCommentChars) {
        questionCommentTextarea.addEventListener('input', function () {
            const remaining = 150 - this.value.length;
            questionCommentChars.textContent = remaining;
            if (remaining < 20) questionCommentChars.className = 'text-red-400';
            else if (remaining < 50) questionCommentChars.className = 'text-yellow-400';
            else questionCommentChars.className = 'text-gray-400';
        });
    }

    // Answer comment char counts
    document.querySelectorAll('.answer-comment-text').forEach(function(textarea){
        textarea.addEventListener('input', function(){
            const remaining = 150 - this.value.length;
            const span = this.parentNode.querySelector('.answer-comment-chars');
            if(span) {
                span.textContent = remaining;
                if(remaining < 20) span.className = 'answer-comment-chars text-red-400';
                else if(remaining < 50) span.className = 'answer-comment-chars text-yellow-400';
                else span.className = 'answer-comment-chars text-gray-400';
            }
        });
    });

    // Markdown preview for answer
    answerTextarea && answerTextarea.addEventListener('input', function () {
        if (document.getElementById('preview-tab').classList.contains('active')) {
            updatePreview();
        }
    });

    // Tab switching function
    window.switchTab = function (tabName) {
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
        document.getElementById(`${tabName}-tab`).classList.add('active');
        if (tabName === 'preview') {
            updatePreview();
        }
    }

    window.updatePreview = function () {
        let textarea = document.getElementById('answer-textarea');
        let preview = document.getElementById('answer-preview');
        let text = textarea.value.trim();
        if (!text) {
            preview.innerHTML = '<em class="text-gray-500">Nothing to preview yet...</em>';
            return;
        }
        let html = text
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;')
            .replace(/```([\s\S]*?)```/g, '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>')
            .replace(/`([^`\n]+)`/g, '<code class="bg-gray-600 px-1 rounded">$1</code>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
        preview.innerHTML = html;
    };

    // AJAX helper
    function ajaxPost(data, successCallback, errorCallback) {
        $.post('', $.extend({ajax: 1}, data), function (res) {
            if (res.success) {
                successCallback(res);
            } else {
                alert('Error: ' + res.message);
                if (errorCallback) errorCallback(res);
            }
        }).fail(function () {
            alert('Server error, please try again later.');
            if (errorCallback) errorCallback();
        });
    }

    // Increment views asynchronously
    ajaxPost({action: 'update_view', question_id: <?=json_encode($actualQuestionId);?>}, function (res) {
        $('#question-views').text(res.views);
    });

    // Question vote buttons
    $('#question-upvote').click(function () {
        ajaxPost({
            action: 'vote_question',
            question_id: <?=json_encode($actualQuestionId);?>,
            operation: 'upvote',
        }, function (res) {
            $('#question-points').text(res.votes);
        });
    });

    $('#question-downvote').click(function () {
        ajaxPost({
            action: 'vote_question',
            question_id: <?=json_encode($actualQuestionId);?>,
            operation: 'downvote',
        }, function (res) {
            $('#question-points').text(res.votes);
        });
    });

    // Protect, Close, Reopen buttons
    $('#question-protect').click(function () {
        ajaxPost({
            action: 'protect_question',
            question_id: <?=json_encode($actualQuestionId);?>,
        }, function (res) {
            alert(res.message);
            if(res.success) location.reload();
        });
    });

    $('#question-close').click(function () {
        ajaxPost({
            action: 'close_question',
            question_id: <?=json_encode($actualQuestionId);?>,
        }, function (res) {
            alert(res.message);
            if(res.success) location.reload();
        });
    });

    $('#question-reopen').click(function () {
        ajaxPost({
            action: 'reopen_question',
            question_id: <?=json_encode($actualQuestionId);?>,
        }, function (res) {
            alert(res.message);
            if(res.success) location.reload();
        });
    });

    // Question comment votes
    $('.question-comment-upvote').click(function () {
        let commentId = $(this).data('comment-id');
        ajaxPost({
            action: 'vote_question_comment',
            question_id: <?=json_encode($actualQuestionId);?>,
            comment_id: commentId,
            operation: 'upvote',
        }, function (res) {
            $(`.question-comment-votes[data-comment-id='${commentId}']`).text(res.votes);
        });
    });

    $('.question-comment-downvote').click(function () {
        let commentId = $(this).data('comment-id');
        ajaxPost({
            action: 'vote_question_comment',
            question_id: <?=json_encode($actualQuestionId);?>,
            comment_id: commentId,
            operation: 'downvote',
        }, function (res) {
            $(`.question-comment-votes[data-comment-id='${commentId}']`).text(res.votes);
        });
    });

    // Answer upvote/downvote
    $('.answer-upvote').click(function () {
        let answerId = $(this).data('answer-id');
        ajaxPost({
            action: 'vote_answer',
            question_id: <?=json_encode($actualQuestionId);?>,
            answer_id: answerId,
            operation: 'upvote',
        }, function (res) {
            $(`.answer-points[data-answer-id='${answerId}']`).text(res.votes);
        });
    });

    $('.answer-downvote').click(function () {
        let answerId = $(this).data('answer-id');
        ajaxPost({
            action: 'vote_answer',
            question_id: <?=json_encode($actualQuestionId);?>,
            answer_id: answerId,
            operation: 'downvote',
        }, function (res) {
            $(`.answer-points[data-answer-id='${answerId}']`).text(res.votes);
        });
    });

    // Answer comment upvote/downvote
    $('.answer-comment-upvote').click(function () {
        let answerId = $(this).data('answer-id');
        let commentId = $(this).data('comment-id');
        ajaxPost({
            action: 'vote_answer_comment',
            question_id: <?=json_encode($actualQuestionId);?>,
            answer_id: answerId,
            comment_id: commentId,
            operation: 'upvote',
        }, function (res) {
            $(`.answer-comment-votes[data-answer-id='${answerId}'][data-comment-id='${commentId}']`).text(res.votes);
        });
    });

    $('.answer-comment-downvote').click(function () {
        let answerId = $(this).data('answer-id');
        let commentId = $(this).data('comment-id');
        ajaxPost({
            action: 'vote_answer_comment',
            question_id: <?=json_encode($actualQuestionId);?>,
            answer_id: answerId,
            comment_id: commentId,
            operation: 'downvote',
        }, function (res) {
            $(`.answer-comment-votes[data-answer-id='${answerId}'][data-comment-id='${commentId}']`).text(res.votes);
        });
    });
});
</script>
</body>
</html>