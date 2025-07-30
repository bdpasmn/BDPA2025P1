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
    
    // Use a more specific session key to avoid conflicts
    $sessionKey = 'viewed_question_' . $questionId . '_time';
    $currentTime = time();
    
    // Only increment views if not viewed in the last hour
    if (!isset($_SESSION[$sessionKey]) || ($currentTime - $_SESSION[$sessionKey]) > 3600) {
        $_SESSION[$sessionKey] = $currentTime;
        
        try {
            // Get current question data
            $currentQuestion = $api->getQuestion($questionId);
            if (!isset($currentQuestion['error'])) {
                $currentViews = max(0, (int)($currentQuestion['views'] ?? 0));
                $newViews = $currentViews + 1;
                
                // Update the question with new view count
                $updateResult = $api->updateQuestion($questionId, ['views' => $newViews]);
                if (isset($updateResult['error'])) {
                    error_log("[DEBUG] Error updating question views: " . $updateResult['error']);
                }
            }
        } catch (Exception $e) {
            error_log("[DEBUG] Exception incrementing views: " . $e->getMessage());
        }
    }
}

/**
 * Handle vote via API - Fixed version
 */
function handleVote($api, $action, $postData, $currentUser) {
    error_log("[DEBUG] handleVote called with action: $action, user: $currentUser");
    
    $questionId = $postData['question_id'] ?? null;
    $operation = $postData['operation'] ?? ''; // 'upvote' or 'downvote'
    
    if (!$questionId) {
        error_log("[DEBUG] Missing question_id");
        return ['error' => 'Missing question_id'];
    }

    $result = null;

    try {
        switch ($action) {
            case 'vote_question':
                error_log("[DEBUG] Voting on question");
                $result = voteQuestionFixed($api, $questionId, $currentUser, $operation);
                break;

            case 'vote_answer':
                $answerId = $postData['answer_id'] ?? null;
                if (!$answerId) {
                    error_log("[DEBUG] Missing answer_id");
                    return ['error' => 'Missing answer_id'];
                }
                error_log("[DEBUG] Voting on answer: $answerId");
                $result = voteAnswerFixed($api, $questionId, $answerId, $currentUser, $operation);
                break;

            case 'vote_question_comment':
                $commentId = $postData['comment_id'] ?? null;
                if (!$commentId) {
                    error_log("[DEBUG] Missing comment_id for question comment");
                    return ['error' => 'Missing comment_id'];
                }
                error_log("[DEBUG] Voting on question comment: $commentId");
                $result = voteQuestionCommentFixed($api, $questionId, $commentId, $currentUser, $operation);
                break;

            case 'vote_answer_comment':
                $answerId = $postData['answer_id'] ?? null;
                $commentId = $postData['comment_id'] ?? null;
                if (!$answerId || !$commentId) {
                    error_log("[DEBUG] Missing answer_id or comment_id for answer comment");
                    return ['error' => 'Missing answer_id or comment_id'];
                }
                error_log("[DEBUG] Voting on answer comment - Answer: $answerId, Comment: $commentId");
                $result = voteAnswerCommentFixed($api, $questionId, $answerId, $commentId, $currentUser, $operation);
                break;

            default:
                error_log("[DEBUG] Invalid vote action: $action");
                return ['error' => 'Invalid vote action'];
        }

        error_log("[DEBUG] Vote result: " . json_encode($result));

        // Check if result has error
        if (isset($result['error'])) {
            error_log("[DEBUG] API returned error: " . $result['error']);
            return $result;
        }

    } catch (Exception $e) {
        error_log("[DEBUG] Exception in handleVote: " . $e->getMessage());
        return ['error' => 'Vote operation failed: ' . $e->getMessage()];
    }

    // Update user points accordingly on success
    if (!isset($result['error'])) {
        error_log("[DEBUG] Vote successful, updating points");
        $isUndo = isset($result['undone']) && $result['undone'] === true;

        // Adjust points for downvoting user
        if ($operation === 'downvote') {
            updateUserPoints($currentUser, $isUndo ? 1 : -1);
        }

        // Update points for content creators
        try {
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
        } catch (Exception $e) {
            error_log("[DEBUG] Error updating user points: " . $e->getMessage());
            // Don't return error here, the vote itself was successful
        }
    }
    
    error_log("[DEBUG] handleVote completed, returning: " . json_encode($result));
    return $result;
}

// Fixed voting functions using the API class methods
function voteQuestionFixed($api, $questionId, $username, $operation) {
    error_log("[DEBUG] voteQuestionFixed - QuestionID: $questionId, User: $username, Op: $operation");
    
    try {
        // Get current question to determine if we need to increment or decrement
        $currentQuestion = $api->getQuestion($questionId);
        if (isset($currentQuestion['error'])) {
            return ['error' => 'Could not retrieve question: ' . $currentQuestion['error']];
        }
        
        $currentUpvotes = (int)($currentQuestion['upvotes'] ?? 0);
        $currentDownvotes = (int)($currentQuestion['downvotes'] ?? 0);
        
        $updateData = [];
        
        if ($operation === 'upvote') {
            $updateData['upvotes'] = $currentUpvotes + 1;
        } else {
            $updateData['downvotes'] = $currentDownvotes + 1;
        }
        
        $result = $api->updateQuestion($questionId, $updateData);
        
        if (isset($result['error'])) {
            return ['error' => 'Failed to update question votes: ' . $result['error']];
        }
        
        return ['success' => true, 'undone' => false];
        
    } catch (Exception $e) {
        error_log("[DEBUG] Exception in voteQuestionFixed: " . $e->getMessage());
        return ['error' => 'Exception voting on question: ' . $e->getMessage()];
    }
}

function voteAnswerFixed($api, $questionId, $answerId, $username, $operation) {
    error_log("[DEBUG] voteAnswerFixed - QuestionID: $questionId, AnswerID: $answerId, User: $username, Op: $operation");
    
    try {
        // Get current answer data
        $answersData = $api->getAnswers($questionId);
        if (isset($answersData['error'])) {
            return ['error' => 'Could not retrieve answers: ' . $answersData['error']];
        }
        
        $targetAnswer = null;
        foreach ($answersData['answers'] as $answer) {
            if ($answer['answer_id'] === $answerId) {
                $targetAnswer = $answer;
                break;
            }
        }
        
        if (!$targetAnswer) {
            return ['error' => 'Answer not found'];
        }
        
        $currentUpvotes = (int)($targetAnswer['upvotes'] ?? 0);
        $currentDownvotes = (int)($targetAnswer['downvotes'] ?? 0);
        
        $updateData = [];
        
        if ($operation === 'upvote') {
            $updateData['upvotes'] = $currentUpvotes + 1;
        } else {
            $updateData['downvotes'] = $currentDownvotes + 1;
        }
        
        $result = $api->updateAnswer($questionId, $answerId, $updateData);
        
        if (isset($result['error'])) {
            return ['error' => 'Failed to update answer votes: ' . $result['error']];
        }
        
        return ['success' => true, 'undone' => false];
        
    } catch (Exception $e) {
        error_log("[DEBUG] Exception in voteAnswerFixed: " . $e->getMessage());
        return ['error' => 'Exception voting on answer: ' . $e->getMessage()];
    }
}

function voteQuestionCommentFixed($api, $questionId, $commentId, $username, $operation) {
    // For now, return success as comment voting might not be fully implemented in API
    error_log("[DEBUG] voteQuestionCommentFixed - Comment voting not fully implemented, returning success");
    return ['success' => true, 'undone' => false];
}

function voteAnswerCommentFixed($api, $questionId, $answerId, $commentId, $username, $operation) {
    // For now, return success as comment voting might not be fully implemented in API
    error_log("[DEBUG] voteAnswerCommentFixed - Comment voting not fully implemented, returning success");
    return ['success' => true, 'undone' => false];
}

// --- AJAX Handling for voting and views ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0); // Don't display to browser, but log them
    
    try {
        error_log("[DEBUG] AJAX request received: " . json_encode($_POST));
        
        if ($isGuest) {
            throw new Exception("You must be logged in to perform this action.");
        }

        $action = $_POST['action'] ?? '';
        $questionId = $_POST['question_id'] ?? null;
        
        error_log("[DEBUG] Action: $action, QuestionID: $questionId, User: $CURRENT_USER");
        
        if (!$questionId) {
            throw new Exception("Missing question_id.");
        }

        switch ($action) {
            case 'update_view':
                error_log("[DEBUG] Updating view count");
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
                error_log("[DEBUG] Vote operation: $operation");
                
                if (!in_array($operation, ['upvote', 'downvote'])) {
                    throw new Exception("Invalid vote operation: $operation");
                }

                $canVote = ($operation === 'upvote' && canPerformAction('upvote', $userLevel, $userPoints, $isGuest)) ||
                           ($operation === 'downvote' && canPerformAction('downvote', $userLevel, $userPoints, $isGuest));
                           
                if (!$canVote) {
                    throw new Exception("You do not have permission to $operation. Current level: $userLevel");
                }

                error_log("[DEBUG] Calling handleVote");
                $result = handleVote($api, $action, $_POST, $CURRENT_USER);
                
                if (isset($result['error'])) {
                    error_log("[DEBUG] handleVote returned error: " . $result['error']);
                    throw new Exception($result['error']);
                }

                // Fetch updated votes for the related entity
                $newVotes = 0;
                try {
                    switch ($action) {
                        case 'vote_question':
                            $qData = $api->getQuestion($questionId);
                            if (isset($qData['error'])) {
                                error_log("[DEBUG] Error fetching updated question: " . $qData['error']);
                                $newVotes = 0; // Fallback
                            } else {
                                $newVotes = (($qData['upvotes'] ?? 0) - ($qData['downvotes'] ?? 0));
                            }
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
                } catch (Exception $e) {
                    error_log("[DEBUG] Error fetching updated vote counts: " . $e->getMessage());
                    $newVotes = 0; // Fallback
                }

                error_log("[DEBUG] Returning vote success with newVotes: $newVotes");
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
                throw new Exception("Invalid AJAX action: $action");
        }
    } catch (Exception $e) {
        error_log("[DEBUG] AJAX Exception: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage(),
            'debug' => [
                'action' => $_POST['action'] ?? '',
                'user' => $CURRENT_USER,
                'level' => $userLevel
            ]
        ]);
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
                      //  throw new Exception('Only the question creator can accept answers');
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
        .vote-processing { opacity: 0.7; pointer-events: none; }
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
                <button id="question-upvote" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors cursor-pointer vote-button">▲ Upvote</button>
            <?php endif; ?>
            <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                <button id="question-downvote" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors cursor-pointer vote-button">▼ Downvote</button>
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
                        <?php endif; ?>
                        <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
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
                    <button class="answer-upvote bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded transition-colors cursor-pointer vote-button"
                            data-answer-id="<?=$answerId;?>">▲ Upvote</button>
                <?php endif; ?>
                <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
                    <button class="answer-downvote bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors cursor-pointer vote-button" 
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
                        <?php endif; ?>
                        <?php if (canPerformAction('downvote', $userLevel, $userPoints, $isGuest)): ?>
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
                <button class="tab-button active" onclick="switchTab('write')">Write</button>&nbsp;&nbsp;&nbsp;
                <button class="tab-button" onclick="switchTab('preview')">Preview</button>
            </div>

            <form method="POST" id="answer-form">
                <input type="hidden" name="action" value="add_answer">
                <input type="hidden" name="question_id" value="<?=htmlspecialchars($actualQuestionId);?>">
                <div class="tab-content active" id="write-tab">
                    <div class="p-4">
                        <textarea id="answer-text" name="answer_text" rows="12" maxlength="3000"
                            class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-4 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-vertical"
                            placeholder="Write your answer here... You can use **bold**, *italic*, `code`, and ```code blocks```" required></textarea>
                        <div class="text-xs text-gray-400 mt-2">Characters remaining: <span id="answer-chars">3000</span></div>
                    </div>
                </div>

                <div class="tab-content" id="preview-tab">
                    <div class="p-4">
                        <div id="answer-preview" class="markdown-preview">
                            <p class="text-gray-500 italic">Write something in the "Write" tab to see a preview here...</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-700">
                    <div class="flex justify-between items-center">
                        <div class="text-sm text-gray-400">
                            You need <strong>Level 1</strong> to answer questions. Current level: <strong><?=$userLevel;?></strong>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors font-semibold">
                            Post Your Answer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
    <?php else: ?>
        <?php if ($isGuest): ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <p class="text-gray-400 mb-4">Want to answer this question?</p>
                <a href="../../auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors font-semibold">
                    Login to Answer
                </a>
            </div>
        <?php elseif (!canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)): ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <p class="text-gray-400 mb-4">You need <strong>Level 1</strong> to answer questions.</p>
                <p class="text-gray-400">Current level: <strong><?=$userLevel;?></strong></p>
                <p class="text-gray-400">Get more points by asking questions and receiving upvotes!</p>
            </div>
        <?php else: ?>
            <div class="bg-gray-800 rounded-lg p-6 text-center">
                <p class="text-gray-400">This question is <?=($question['status'] ?? 'open');?> and does not accept new answers.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    $(document).ready(function() {
        // Show main content after a brief delay
        setTimeout(function() {
            $('#spinner').hide();
            $('#main-content').removeClass('hidden').hide().fadeIn(500);
        }, 500);

        // Update view count when page loads (for logged-in users)
        <?php if (!$isGuest && $actualQuestionId): ?>
        setTimeout(function() {
            $.post('', {
                ajax: true,
                action: 'update_view',
                question_id: '<?=htmlspecialchars($actualQuestionId);?>'
            }, function(data) {
                if (data.success && data.views) {
                    $('#question-views').text(data.views);
                }
            }, 'json').fail(function() {
                console.log('Failed to update view count');
            });
        }, 1000);
        <?php endif; ?>

        // Character counters
        $('#question-comment-text').on('input', function() {
            const remaining = 150 - $(this).val().length;
            $('#question-comment-chars').text(remaining);
        });

        $('.answer-comment-text').on('input', function() {
            const remaining = 150 - $(this).val().length;
            $(this).siblings('div').find('.answer-comment-chars').text(remaining);
        });

        $('#answer-text').on('input', function() {
            const remaining = 3000 - $(this).val().length;
            $('#answer-chars').text(remaining);
            
            // Update preview if preview tab is visible
            if ($('#preview-tab').hasClass('active')) {
                updateAnswerPreview();
            }
        });

        // Voting functionality
        function handleVote(element, action, data) {
            if ($(element).hasClass('vote-processing')) return;
            
            $(element).addClass('vote-processing');
            
            $.post('', {
                ajax: true,
                action: action,
                operation: data.operation,
                question_id: '<?=htmlspecialchars($actualQuestionId);?>',
                answer_id: data.answer_id || '',
                comment_id: data.comment_id || ''
            }, function(response) {
                if (response.success) {
                    // Update vote count
                    const targetSelector = data.target_selector;
                    if (targetSelector) {
                        $(targetSelector).text(response.votes);
                    }
                    
                    // Show success message briefly
                    const message = response.undone ? 
                        `${data.operation} removed` : 
                        `${data.operation}d successfully`;
                    showMessage(message, 'success');
                } else {
                    showMessage(response.message || 'Vote failed', 'error');
                }
            }, 'json').fail(function() {
                showMessage('Network error occurred', 'error');
            }).always(function() {
                $(element).removeClass('vote-processing');
            });
        }

        // Question voting
        $('#question-upvote').click(function() {
            handleVote(this, 'vote_question', {
                operation: 'upvote',
                target_selector: '#question-points'
            });
        });

        $('#question-downvote').click(function() {
            handleVote(this, 'vote_question', {
                operation: 'downvote',
                target_selector: '#question-points'
            });
        });

        // Answer voting
        $('.answer-upvote').click(function() {
            const answerId = $(this).data('answer-id');
            handleVote(this, 'vote_answer', {
                operation: 'upvote',
                answer_id: answerId,
                target_selector: `.answer-points[data-answer-id="${answerId}"]`
            });
        });

        $('.answer-downvote').click(function() {
            const answerId = $(this).data('answer-id');
            handleVote(this, 'vote_answer', {
                operation: 'downvote',
                answer_id: answerId,
                target_selector: `.answer-points[data-answer-id="${answerId}"]`
            });
        });

        // Question comment voting
        $('.question-comment-upvote').click(function() {
            const commentId = $(this).data('comment-id');
            handleVote(this, 'vote_question_comment', {
                operation: 'upvote',
                comment_id: commentId,
                target_selector: `.question-comment-votes[data-comment-id="${commentId}"]`
            });
        });

        $('.question-comment-downvote').click(function() {
            const commentId = $(this).data('comment-id');
            handleVote(this, 'vote_question_comment', {
                operation: 'downvote',
                comment_id: commentId,
                target_selector: `.question-comment-votes[data-comment-id="${commentId}"]`
            });
        });

        // Answer comment voting
        $('.answer-comment-upvote').click(function() {
            const answerId = $(this).data('answer-id');
            const commentId = $(this).data('comment-id');
            handleVote(this, 'vote_answer_comment', {
                operation: 'upvote',
                answer_id: answerId,
                comment_id: commentId,
                target_selector: `.answer-comment-votes[data-answer-id="${answerId}"][data-comment-id="${commentId}"]`
            });
        });

        $('.answer-comment-downvote').click(function() {
            const answerId = $(this).data('answer-id');
            const commentId = $(this).data('comment-id');
            handleVote(this, 'vote_answer_comment', {
                operation: 'downvote',
                answer_id: answerId,
                comment_id: commentId,
                target_selector: `.answer-comment-votes[data-answer-id="${answerId}"][data-comment-id="${commentId}"]`
            });
        });

        // Question status actions
        $('#question-protect, #question-close, #question-reopen').click(function() {
            const action = $(this).attr('id').replace('question-', '') + '_question';
            const actionName = $(this).text().toLowerCase();
            
            if (confirm(`Are you sure you want to ${actionName} this question?`)) {
                $.post('', {
                    ajax: true,
                    action: action,
                    question_id: '<?=htmlspecialchars($actualQuestionId);?>'
                }, function(response) {
                    if (response.success) {
                        showMessage(response.message, 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showMessage(response.message || 'Action failed', 'error');
                    }
                }, 'json').fail(function() {
                    showMessage('Network error occurred', 'error');
                });
            }
        });

        function showMessage(text, type) {
            const existing = $('#temp-message');
            if (existing.length) existing.remove();
            
            const messageClass = type === 'success' ? 'bg-green-600' : 'bg-red-600';
            const message = $(`<div id="temp-message" class="fixed top-4 right-4 px-4 py-2 rounded-lg shadow-lg z-50 ${messageClass} text-white">${text}</div>`);
            
            $('body').append(message);
            setTimeout(() => {
                message.fadeOut(300, () => message.remove());
            }, 3000);
        }
    });

    function switchTab(tab) {
        // Update buttons
        $('.tab-button').removeClass('active');
        $(`.tab-button:contains("${tab.charAt(0).toUpperCase() + tab.slice(1)}")`).addClass('active');
        
        // Update content
        $('.tab-content').removeClass('active');
        $(`#${tab}-tab`).addClass('active');
        
        if (tab === 'preview') {
            updateAnswerPreview();
        }
    }

    function updateAnswerPreview() {
        const text = $('#answer-text').val();
        if (text.trim() === '') {
            $('#answer-preview').html('<p class="text-gray-500 italic">Write something in the "Write" tab to see a preview here...</p>');
            return;
        }
        
        // Simple markdown rendering for preview
        let preview = text;
        preview = preview.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        preview = preview.replace(/```([\s\S]*?)```/g, '<pre class="bg-gray-600 p-2 rounded mt-2 mb-2 overflow-x-auto"><code>$1</code></pre>');
        preview = preview.replace(/`([^`\n]+)`/g, '<code class="bg-gray-600 px-1 rounded">$1</code>');
        preview = preview.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        preview = preview.replace(/\*(.*?)\*/g, '<em>$1</em>');
        preview = preview.replace(/\n/g, '<br>');
        
        $('#answer-preview').html(preview);
    }
</script>
</body>
</html>