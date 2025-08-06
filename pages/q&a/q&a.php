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

// Storage for edit votes (stored locally in session as requested)
if (!isset($_SESSION['edit_votes'])) {
    $_SESSION['edit_votes'] = [];
}

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
        case 'edit_vote': return $userLevel >= 7; // Level 7 (2,000 points) for edit voting
        case 'close_reopen_vote': return $userLevel >= 8; // Bumped up by 1
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
    
    // Code blocks (triple backticks)
    $text = preg_replace('/```([\s\S]*?)```/', '<pre class="bg-gray-600 p-3 rounded-lg mt-3 mb-3 overflow-x-auto border border-gray-500"><code class="text-gray-100">$1</code></pre>', $text);
    
    // Inline code
    $text = preg_replace('/`([^`\n]+)`/', '<code class="bg-gray-600 px-2 py-1 rounded text-gray-100 text-sm border border-gray-500">$1</code>', $text);
    
    // Images - ![alt text](url)
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg shadow-lg my-4 border border-gray-600" loading="lazy" />', $text);
    
    // Links - [text](url)
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" class="text-blue-400 hover:text-blue-300 underline" target="_blank" rel="noopener noreferrer">$1</a>', $text);
    
    // Bold text
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-bold text-white">$1</strong>', $text);
    
    // Italic text
    $text = preg_replace('/\*(.*?)\*/', '<em class="italic text-gray-200">$1</em>', $text);
    
    // Headers
    $text = preg_replace('/^### (.+)$/m', '<h3 class="text-xl font-bold text-white mt-6 mb-3">$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m', '<h2 class="text-2xl font-bold text-white mt-6 mb-4">$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m', '<h1 class="text-3xl font-bold text-white mt-6 mb-4">$1</h1>', $text);
    
    // Lists - simple bullet points
    $text = preg_replace('/^- (.+)$/m', '<li class="ml-4 mb-1">• $1</li>', $text);
    $text = preg_replace('/^(\d+)\. (.+)$/m', '<li class="ml-4 mb-1">$1. $2</li>', $text);
    
    // Line breaks
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

// Edit vote management functions (stored locally in session)
function getEditVoteKey($type, $id, $answerId = null) {
    if ($type === 'answer') {
        return "edit_vote_{$type}_{$id}_{$answerId}";
    }
    return "edit_vote_{$type}_{$id}";
}

function createEditVote($type, $id, $newContent, $newTitle = null, $answerId = null) {
    global $CURRENT_USER;
    
    $voteKey = getEditVoteKey($type, $id, $answerId);
    $editVote = [
        'type' => $type,
        'id' => $id,
        'answer_id' => $answerId,
        'new_content' => $newContent,
        'new_title' => $newTitle,
        'initiator' => $CURRENT_USER,
        'votes' => [$CURRENT_USER => 'yes'],
        'created_at' => time(),
        'expires_at' => time() + (24 * 60 * 60) // 24 hours
    ];
    
    $_SESSION['edit_votes'][$voteKey] = $editVote;
    return $voteKey;
}

function voteOnEdit($voteKey, $vote) {
    global $CURRENT_USER;
    
    if (!isset($_SESSION['edit_votes'][$voteKey])) {
        return false;
    }
    
    $editVote = &$_SESSION['edit_votes'][$voteKey];
    
    // Check if vote hasn't expired
    if (time() > $editVote['expires_at']) {
        unset($_SESSION['edit_votes'][$voteKey]);
        return false;
    }
    
    $editVote['votes'][$CURRENT_USER] = $vote;
    return true;
}

function checkEditVoteResult($voteKey) {
    global $api;
    
    if (!isset($_SESSION['edit_votes'][$voteKey])) {
        return null;
    }
    
    $editVote = $_SESSION['edit_votes'][$voteKey];
    
    // Check if vote has expired
    if (time() > $editVote['expires_at']) {
        unset($_SESSION['edit_votes'][$voteKey]);
        return 'expired';
    }
    
    $yesVotes = array_count_values($editVote['votes'])['yes'] ?? 0;
    $noVotes = array_count_values($editVote['votes'])['no'] ?? 0;
    
    // Need at least 3 yes votes and more yes than no votes
    if ($yesVotes >= 3 && $yesVotes > $noVotes) {
        // Apply the edit
        $success = false;
        
        try {
            if ($editVote['type'] === 'question') {
                $updateData = ['body' => $editVote['new_content']];
                if ($editVote['new_title'] !== null) {
                    $updateData['title'] = $editVote['new_title'];
                }
                $result = $api->updateQuestion($editVote['id'], $updateData);
                $success = !isset($result['error']);
            } elseif ($editVote['type'] === 'answer') {
                $result = $api->updateAnswer($editVote['id'], $editVote['answer_id'], ['text' => $editVote['new_content']]);
                $success = !isset($result['error']);
            }
        } catch (Exception $e) {
            error_log("[DEBUG] Error applying edit: " . $e->getMessage());
        }
        
        unset($_SESSION['edit_votes'][$voteKey]);
        return $success ? 'approved' : 'failed';
    } elseif ($noVotes > $yesVotes && ($yesVotes + $noVotes) >= 3) {
        // Edit rejected
        unset($_SESSION['edit_votes'][$voteKey]);
        return 'rejected';
    }
    
    return 'pending';
}

function getActiveEditVotes($questionId) {
    $activeVotes = [];
    
    foreach ($_SESSION['edit_votes'] as $voteKey => $editVote) {
        if (time() > $editVote['expires_at']) {
            unset($_SESSION['edit_votes'][$voteKey]);
            continue;
        }
        
        if ($editVote['id'] === $questionId) {
            $activeVotes[$voteKey] = $editVote;
        }
    }
    
    return $activeVotes;
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

// --- AJAX Handling for voting, views, and edit votes ---

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

            case 'initiate_question_edit':
                if (!canPerformAction('edit_vote', $userLevel, $userPoints, $isGuest)) {
                    throw new Exception("You need level 7 (2,000 points) to initiate edit votes");
                }

                $newTitle = trim($_POST['new_title'] ?? '');
                $newBody = trim($_POST['new_body'] ?? '');
                
                if (empty($newTitle) && empty($newBody)) {
                    throw new Exception("Must provide new title or body content");
                }

                $voteKey = createEditVote('question', $questionId, $newBody, $newTitle);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Edit vote initiated. Other users can now vote on your proposed changes.',
                    'vote_key' => $voteKey
                ]);
                exit;

            case 'initiate_answer_edit':
                if (!canPerformAction('edit_vote', $userLevel, $userPoints, $isGuest)) {
                    throw new Exception("You need level 7 (2,000 points) to initiate edit votes");
                }

                $answerId = $_POST['answer_id'] ?? null;
                $newBody = trim($_POST['new_body'] ?? '');
                
                if (!$answerId) {
                    throw new Exception("Missing answer_id");
                }
                
                if (empty($newBody)) {
                    throw new Exception("Must provide new body content");
                }

                $voteKey = createEditVote('answer', $questionId, $newBody, null, $answerId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Answer edit vote initiated. Other users can now vote on your proposed changes.',
                    'vote_key' => $voteKey
                ]);
                exit;

            case 'vote_on_edit':
                if (!canPerformAction('edit_vote', $userLevel, $userPoints, $isGuest)) {
                    throw new Exception("You need level 7 (2,000 points) to vote on edits");
                }

                $voteKey = $_POST['vote_key'] ?? '';
                $vote = $_POST['vote'] ?? ''; // 'yes' or 'no'
                
                if (!in_array($vote, ['yes', 'no'])) {
                    throw new Exception("Invalid vote value");
                }

                $success = voteOnEdit($voteKey, $vote);
                if (!$success) {
                    throw new Exception("Could not record vote (vote may have expired)");
                }

                // Check if vote is complete
                $result = checkEditVoteResult($voteKey);
                
                $response = ['success' => true, 'vote_recorded' => true];
                
                if ($result === 'approved') {
                    $response['edit_applied'] = true;
                    $response['message'] = 'Edit approved and applied!';
                } elseif ($result === 'rejected') {
                    $response['edit_rejected'] = true;
                    $response['message'] = 'Edit rejected by community vote.';
                } elseif ($result === 'failed') {
                    $response['edit_failed'] = true;
                    $response['message'] = 'Edit was approved but failed to apply.';
                }
                
                echo json_encode($response);
                exit;

            case 'protect_question':
            case 'close_question':
            case 'reopen_question':
                $requiredLevel = ($action === 'protect_question') ? 6 : 8; // Updated level requirement
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
                        throw new Exception('Only the question author can accept answers');
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
                    $requiredLevel = ($action === 'protect_question') ? 6 : 8; // Updated level requirement
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

// Check if there's an accepted answer
$hasAcceptedAnswer = false;
foreach ($answers as $answer) {
    if ($answer['accepted'] ?? false) {
        $hasAcceptedAnswer = true;
        break;
    }
}

// Get active edit votes for this question
$activeEditVotes = getActiveEditVotes($actualQuestionId);

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
            @apply px-6 py-3 bg-gray-800 text-gray-300 border border-gray-600 hover:bg-gray-700 transition-all duration-200 font-medium;
        }
        .tab-button:first-child {
            @apply rounded-l-lg border-r-0;
        }
        .tab-button:last-child {
            @apply rounded-r-lg border-l-0;
        }
        .tab-button.active {
            @apply bg-blue-600 border-blue-500 text-white shadow-lg;
        }
        .tab-content {
            @apply hidden;
        }
        .tab-content.active {
            @apply block;
        }
        .markdown-preview {
            @apply bg-gray-700 p-4 rounded-lg border border-gray-600 min-h-32 max-h-64 overflow-y-auto;
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
        .level-8 { @apply bg-pink-600 text-white; }
        .level-9 { @apply bg-indigo-600 text-white; }
        .gravatar {
            @apply rounded-full border-2 border-gray-600;
        }
        .user-info {
            @apply flex items-center space-x-2;
        }
        .tab-container {
            @apply bg-gray-800 border border-gray-600 rounded-lg overflow-hidden;
        }
        .tab-header {
            @apply bg-gray-900 border-b border-gray-600 p-1;
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
        
        .edit-vote-container {
            @apply bg-gradient-to-r from-yellow-900 to-orange-900 border border-yellow-600 rounded-lg p-4 mb-4;
        }
        
        .edit-vote-pending {
            @apply bg-blue-900 border-blue-600;
        }
        
        .edit-vote-approved {
            @apply bg-green-900 border-green-600;
        }
        
        .edit-vote-rejected {
            @apply bg-red-900 border-red-600;
        }
        
        /* Remove focus outline from interactive elements */
        .tab-button:focus,
        input:focus,
        textarea:focus,
        button:focus {
            outline: none !important;
            box-shadow: none !important;
        }
        
        .tab-button:focus {
            @apply ring-2 ring-gray-500 ring-opacity-50;
        }
        
        input:focus,
        textarea:focus {
            @apply ring-2 ring-blue-500 ring-opacity-50 border-blue-500;
        }
        
        button:focus {
            @apply ring-2 ring-blue-500 ring-opacity-50;
        }
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

    <!-- Active Edit Votes Section -->
    <?php if (!empty($activeEditVotes)): ?>
    <section class="mb-6">
        <h2 class="text-xl font-bold text-white mb-4">🗳️ Active Edit Votes</h2>
        <?php foreach ($activeEditVotes as $voteKey => $editVote): ?>
            <?php 
            $yesVotes = array_count_values($editVote['votes'])['yes'] ?? 0;
            $noVotes = array_count_values($editVote['votes'])['no'] ?? 0;
            $totalVotes = $yesVotes + $noVotes;
            $userVoted = isset($editVote['votes'][$CURRENT_USER ?? '']);
            $userVote = $editVote['votes'][$CURRENT_USER ?? ''] ?? null;
            $timeLeft = max(0, $editVote['expires_at'] - time());
            $hoursLeft = floor($timeLeft / 3600);
            $minutesLeft = floor(($timeLeft % 3600) / 60);
            ?>
            <div class="edit-vote-container edit-vote-pending" id="edit-vote-<?=$voteKey;?>">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h3 class="text-lg font-semibold text-yellow-200">
                            <?= $editVote['type'] === 'question' ? 'Question Edit' : 'Answer Edit'; ?> 
                            by <?=htmlspecialchars($editVote['initiator']);?>
                        </h3>
                        <p class="text-sm text-gray-300">
                            Expires in <?=$hoursLeft;?>h <?=$minutesLeft;?>m | 
                            Votes: <?=$yesVotes;?> Yes, <?=$noVotes;?> No (need 3+ yes votes to pass)
                        </p>
                    </div>
                    <?php if (!$userVoted && canPerformAction('edit_vote', $userLevel, $userPoints, $isGuest)): ?>
                    <div class="flex space-x-2">
                        <button class="vote-on-edit bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm" 
                                data-vote-key="<?=$voteKey;?>" data-vote="yes">✓ Yes</button>
                        <button class="vote-on-edit bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm" 
                                data-vote-key="<?=$voteKey;?>" data-vote="no">✗ No</button>
                    </div>
                    <?php elseif ($userVoted): ?>
                    <div class="text-sm text-gray-300">
                        You voted: <span class="<?= $userVote === 'yes' ? 'text-green-400' : 'text-red-400'; ?>">
                            <?= $userVote === 'yes' ? '✓ Yes' : '✗ No'; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="bg-gray-800 rounded p-3">
                    <?php if ($editVote['type'] === 'question'): ?>
                        <?php if ($editVote['new_title']): ?>
                        <div class="mb-2">
                            <h4 class="text-sm font-semibold text-gray-400">Proposed Title:</h4>
                            <p class="text-white"><?=htmlspecialchars($editVote['new_title']);?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($editVote['new_content']): ?>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-400">Proposed Body:</h4>
                            <div class="text-gray-300 max-h-32 overflow-y-auto">
                                <?=renderMarkdown($editVote['new_content']);?>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div>
                            <h4 class="text-sm font-semibold text-gray-400">Proposed Answer Content:</h4>
                            <div class="text-gray-300 max-h-32 overflow-y-auto">
                                <?=renderMarkdown($editVote['new_content']);?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
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
        
        <!-- Share Question Button -->
        <div class="mb-4">
            <button id="share-question" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors cursor-pointer" 
                    data-question-id="<?=htmlspecialchars($actualQuestionId);?>"
                    title="Share this question">
                📤 Share Question
            </button>
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
            <?php if (canPerformAction('edit_vote', $userLevel, $userPoints, $isGuest)): ?>
                <button id="question-edit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition-colors cursor-pointer">✏️ Propose Edit</button>
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
                        class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-3 resize-none"
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
            
            <!-- Share Answer Button -->
            <div class="mb-4">
                <button class="share-answer bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm transition-colors cursor-pointer" 
                        data-question-id="<?=htmlspecialchars($actualQuestionId);?>"
                        data-answer-id="<?=htmlspecialchars($answerId);?>"
                        title="Share this answer">
                    📤 Share Answer
                </button>
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
                <?php if (canPerformAction('edit_vote', $userLevel, $userPoints, $isGuest)): ?>
                    <button class="answer-edit bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded transition-colors cursor-pointer"
                            data-answer-id="<?=$answerId;?>">✏️ Propose Edit</button>
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
                    <div class="mb-4">
                        <textarea name="comment_text" rows="2" maxlength="150"
                            class="w-full bg-gray-800 text-gray-300 border border-gray-600 rounded-lg p-3 resize-none answer-comment-textarea"
                            placeholder="Add a comment (max 150 characters)..." required></textarea>
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

    <!-- Create Answer Section -->
    <?php if (!$isGuest && canUserInteract($question['status'] ?? 'open', $userLevel) && canPerformAction('create_answer', $userLevel, $userPoints, $isGuest) && !$hasAcceptedAnswer): ?>
    <section class="bg-gray-800 rounded-lg p-6 shadow-lg">
        <h2 class="text-2xl font-bold text-white mb-6">Your Answer</h2>
        
        <form method="POST" id="answer-form">
            <input type="hidden" name="action" value="add_answer">
            <input type="hidden" name="question_id" value="<?=htmlspecialchars($actualQuestionId);?>">
            
            <div class="tab-container mb-6">
                <div class="tab-header">
                    <div class="flex">
                        <button type="button" class="tab-button active" data-tab="write-tab">Write</button>
                        <button type="button" class="tab-button" data-tab="preview-tab">Preview</button>
                    </div>
                </div>
                
                <div class="p-4">
                    <div id="write-tab" class="tab-content active">
                        <textarea id="answer-text" name="answer_text" rows="12" maxlength="3000"
                            class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-lg p-4 resize-vertical"
                            placeholder="Write your answer here... You can use markdown formatting:

                            **bold text**
                            *italic text*
                            `inline code`
                            ```
                            code blocks
                            ```
                            ![alt text](image-url)
                            [link text](url)
                            # Headers
                            - List items

                            Images and links are supported!" required></textarea>
                        <div class="text-xs text-gray-400 mt-2">
                            Characters remaining: <span id="answer-chars">3000</span> | 
                            <span class="text-blue-400">Markdown supported</span>
                        </div>
                    </div>
                    
                    <div id="preview-tab" class="tab-content">
                        <div class="markdown-preview" id="answer-preview">
                            <p class="text-gray-500 italic">Nothing to preview yet...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-400">
                    <?php if (!canPerformAction('create_answer', $userLevel, $userPoints, $isGuest)): ?>
                        <span class="text-red-400">You need Level 1 (1 point) to create answers.</span>
                    <?php else: ?>
                        <span class="text-green-400">✓ You can create answers</span>
                    <?php endif; ?>
                </div>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-lg transition-colors font-semibold">
                    Post Your Answer
                </button>
            </div>
        </form>
    </section>
    <?php elseif ($hasAcceptedAnswer): ?>
    <section class="bg-gray-700 rounded-lg p-6 shadow-lg text-center">
        <div class="text-green-400 text-6xl mb-4">✓</div>
        <h2 class="text-2xl font-bold text-white mb-2">Question Solved!</h2>
        <p class="text-gray-300">This question has an accepted answer and is no longer accepting new responses.</p>
    </section>
    <?php elseif ($isGuest): ?>
    <section class="bg-gray-700 rounded-lg p-6 shadow-lg text-center">
        <h2 class="text-2xl font-bold text-white mb-4">Want to Answer?</h2>
        <p class="text-gray-300 mb-4">Please log in to share your knowledge and help the community.</p>
        <a href="../../auth/login.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors font-semibold">
            Login to Answer
        </a>
    </section>
    <?php elseif (!canUserInteract($question['status'] ?? 'open', $userLevel)): ?>
    <section class="bg-gray-700 rounded-lg p-6 shadow-lg text-center">
        <h2 class="text-2xl font-bold text-white mb-4">Cannot Answer</h2>
        <p class="text-gray-300">
            <?php if (($question['status'] ?? 'open') === 'closed'): ?>
                This question is closed and no longer accepts answers.
            <?php elseif (($question['status'] ?? 'open') === 'protected'): ?>
                This question is protected. You need Level 5 to answer.
            <?php endif; ?>
        </p>
    </section>
    <?php endif; ?>
</div>

<!-- Edit Modal for Questions -->
<div id="question-edit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-white">Propose Question Edit</h3>
                <button id="close-question-edit" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            
            <form id="question-edit-form">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Title (optional)</label>
                    <input type="text" id="edit-question-title" 
                           class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-lg p-3"
                           placeholder="Leave empty to keep current title"
                           value="<?=htmlspecialchars($question['title'] ?? '');?>">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Body Content (optional)</label>
                    <textarea id="edit-question-body" rows="10"
                              class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-lg p-3 resize-vertical"
                              placeholder="Leave empty to keep current body"><?=htmlspecialchars($question['body'] ?? $question['text'] ?? '');?></textarea>
                    <div class="text-xs text-gray-400 mt-1">Markdown supported</div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-question-edit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        Cancel
                    </button>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                        Propose Edit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal for Answers -->
<div id="answer-edit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-gray-800 rounded-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-white">Propose Answer Edit</h3>
                <button id="close-answer-edit" class="text-gray-400 hover:text-white text-2xl">&times;</button>
            </div>
            
            <form id="answer-edit-form">
                <input type="hidden" id="edit-answer-id" value="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Answer Content</label>
                    <textarea id="edit-answer-body" rows="10" required
                              class="w-full bg-gray-700 text-gray-300 border border-gray-600 rounded-lg p-3 resize-vertical"
                              placeholder="Enter the new answer content"></textarea>
                    <div class="text-xs text-gray-400 mt-1">Markdown supported</div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="cancel-answer-edit" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        Cancel
                    </button>
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                        Propose Edit
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Hide spinner and show content
    setTimeout(function() {
        $('#spinner').addClass('hidden');
        $('#main-content').removeClass('hidden');
    }, 500);

    // Character counters
    $('#question-comment-text').on('input', function() {
        const remaining = 150 - $(this).val().length;
        $('#question-comment-chars').text(remaining);
    });

    $('.answer-comment-textarea').on('input', function() {
        const remaining = 150 - $(this).val().length;
        $(this).siblings().find('.answer-comment-chars').text(remaining);
    });

    $('#answer-text').on('input', function() {
        const remaining = 3000 - $(this).val().length;
        $('#answer-chars').text(remaining);
    });

    // Enhanced markdown rendering function
    function renderMarkdown(text) {
        if (!text || text.trim() === '') {
            return '<p class="text-gray-500 italic">Nothing to preview yet...</p>';
        }
        
        // Escape HTML first
        text = $('<div>').text(text).html();
        
        // Code blocks (triple backticks) - must be processed first
        text = text.replace(/```([\s\S]*?)```/g, '<pre class="bg-gray-600 p-3 rounded-lg mt-3 mb-3 overflow-x-auto border border-gray-500"><code class="text-gray-100">$1</code></pre>');
        
        // Inline code
        text = text.replace(/`([^`\n]+)`/g, '<code class="bg-gray-600 px-2 py-1 rounded text-gray-100 text-sm border border-gray-500">$1</code>');
        
        // Images - ![alt text](url)
        text = text.replace(/!\[([^\]]*)\]\(([^)]+)\)/g, '<img src="$2" alt="$1" class="max-w-full h-auto rounded-lg shadow-lg my-4 border border-gray-600" loading="lazy" />');
        
        // Links - [text](url)
        text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="text-blue-400 hover:text-blue-300 underline" target="_blank" rel="noopener noreferrer">$1</a>');
        
        // Bold text
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold text-white">$1</strong>');
        
        // Italic text
        text = text.replace(/\*(.*?)\*/g, '<em class="italic text-gray-200">$1</em>');
        
        // Headers
        text = text.replace(/^### (.+)$/gm, '<h3 class="text-xl font-bold text-white mt-6 mb-3">$1</h3>');
        text = text.replace(/^## (.+)$/gm, '<h2 class="text-2xl font-bold text-white mt-6 mb-4">$1</h2>');
        text = text.replace(/^# (.+)$/gm, '<h1 class="text-3xl font-bold text-white mt-6 mb-4">$1</h1>');
        
        // Lists - simple bullet points
        text = text.replace(/^- (.+)$/gm, '<li class="ml-4 mb-1">• $1</li>');
        text = text.replace(/^(\d+)\. (.+)$/gm, '<li class="ml-4 mb-1">$1. $2</li>');
        
        // Line breaks
        text = text.replace(/\n/g, '<br>');
        
        return text;
    }

    // Tab functionality with better styling
    $('.tab-button').on('click', function(e) {
        e.preventDefault();
        
        const targetTab = $(this).data('tab');
        const container = $(this).closest('.tab-container');
        
        // Remove active class from all tabs and contents in this container
        container.find('.tab-button').removeClass('active');
        container.find('.tab-content').removeClass('active');
        
        // Add active class to clicked tab and corresponding content
        $(this).addClass('active');
        container.find('#' + targetTab).addClass('active');
        
        // Update preview if switching to preview tab
        if (targetTab === 'preview-tab') {
            const answerText = $('#answer-text').val();
            const previewHtml = renderMarkdown(answerText);
            $('#answer-preview').html(previewHtml);
        }
    });

    // Auto-update preview when typing (debounced)
    let previewTimeout;
    $('#answer-text').on('input', function() {
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(function() {
            if ($('#preview-tab').hasClass('active')) {
                const answerText = $('#answer-text').val();
                const previewHtml = renderMarkdown(answerText);
                $('#answer-preview').html(previewHtml);
            }
        }, 500);
    });

    // Edit modal functionality
    $('#question-edit').on('click', function() {
        $('#question-edit-modal').removeClass('hidden');
    });

    $('#close-question-edit, #cancel-question-edit').on('click', function() {
        $('#question-edit-modal').addClass('hidden');
    });

    $('.answer-edit').on('click', function() {
        const answerId = $(this).data('answer-id');
        const answerText = $(this).closest('article').find('.prose').text().trim();
        
        $('#edit-answer-id').val(answerId);
        $('#edit-answer-body').val(answerText);
        $('#answer-edit-modal').removeClass('hidden');
    });

    $('#close-answer-edit, #cancel-answer-edit').on('click', function() {
        $('#answer-edit-modal').addClass('hidden');
    });

    // Handle edit form submissions
    $('#question-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        const newTitle = $('#edit-question-title').val().trim();
        const newBody = $('#edit-question-body').val().trim();
        
        if (!newTitle && !newBody) {
            alert('Please provide either a new title or new body content.');
            return;
        }

        const button = $(this).find('button[type="submit"]');
        const originalText = button.text();
        button.text('Processing...').prop('disabled', true);

        $.post(window.location.href, {
            ajax: true,
            action: 'initiate_question_edit',
            question_id: '<?=htmlspecialchars($actualQuestionId);?>',
            new_title: newTitle !== '<?=htmlspecialchars($question['title'] ?? '');?>' ? newTitle : '',
            new_body: newBody !== '<?=htmlspecialchars($question['body'] ?? $question['text'] ?? '');?>' ? newBody : ''
        })
        .done(function(response) {
            if (response.success) {
                $('#question-edit-modal').addClass('hidden');
                alert(response.message);
                location.reload(); // Reload to show the new edit vote
            } else {
                alert('Error: ' + (response.message || 'Failed to initiate edit vote'));
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            button.text(originalText).prop('disabled', false);
        });
    });

    $('#answer-edit-form').on('submit', function(e) {
        e.preventDefault();
        
        const answerId = $('#edit-answer-id').val();
        const newBody = $('#edit-answer-body').val().trim();
        
        if (!newBody) {
            alert('Please provide new answer content.');
            return;
        }

        const button = $(this).find('button[type="submit"]');
        const originalText = button.text();
        button.text('Processing...').prop('disabled', true);

        $.post(window.location.href, {
            ajax: true,
            action: 'initiate_answer_edit',
            question_id: '<?=htmlspecialchars($actualQuestionId);?>',
            answer_id: answerId,
            new_body: newBody
        })
        .done(function(response) {
            if (response.success) {
                $('#answer-edit-modal').addClass('hidden');
                alert(response.message);
                location.reload(); // Reload to show the new edit vote
            } else {
                alert('Error: ' + (response.message || 'Failed to initiate edit vote'));
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            button.text(originalText).prop('disabled', false);
        });
    });

    // Handle edit vote buttons
    $('.vote-on-edit').on('click', function() {
        const voteKey = $(this).data('vote-key');
        const vote = $(this).data('vote');
        const button = $(this);
        const originalText = button.text();
        
        button.text('Voting...').prop('disabled', true);

        $.post(window.location.href, {
            ajax: true,
            action: 'vote_on_edit',
            vote_key: voteKey,
            vote: vote
        })
        .done(function(response) {
            if (response.success) {
                if (response.edit_applied) {
                    alert(response.message);
                    location.reload();
                } else if (response.edit_rejected) {
                    alert(response.message);
                    location.reload();
                } else if (response.edit_failed) {
                    alert(response.message);
                    location.reload();
                } else {
                    alert('Vote recorded successfully!');
                    location.reload();
                }
            } else {
                alert('Error: ' + (response.message || 'Failed to record vote'));
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            button.text(originalText).prop('disabled', false);
        });
    });

    // Voting functionality
    function handleVoteClick(element, action, extraData = {}) {
        if ($(element).hasClass('vote-processing')) return;
        
        $(element).addClass('vote-processing');
        
        const operation = $(element).hasClass('upvote') || $(element).attr('id').includes('upvote') ? 'upvote' : 'downvote';
        
        const postData = {
            ajax: true,
            action: action,
            operation: operation,
            question_id: '<?=htmlspecialchars($actualQuestionId);?>',
            ...extraData
        };

        $.post(window.location.href, postData)
        .done(function(response) {
            if (response.success) {
                // Update vote count based on action
                if (action === 'vote_question') {
                    $('#question-points').text(response.votes);
                } else if (action === 'vote_answer') {
                    $(`.answer-points[data-answer-id="${extraData.answer_id}"]`).text(response.votes);
                } else if (action === 'vote_question_comment') {
                    $(`.question-comment-votes[data-comment-id="${extraData.comment_id}"]`).text(response.votes);
                } else if (action === 'vote_answer_comment') {
                    $(`.answer-comment-votes[data-answer-id="${extraData.answer_id}"][data-comment-id="${extraData.comment_id}"]`).text(response.votes);
                }
            } else {
                alert('Error: ' + (response.message || 'Vote failed'));
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            $(element).removeClass('vote-processing');
        });
    }

    // Question voting
    $('#question-upvote').on('click', function() {
        handleVoteClick(this, 'vote_question');
    });

    $('#question-downvote').on('click', function() {
        handleVoteClick(this, 'vote_question');
    });

    // Answer voting
    $('.answer-upvote').on('click', function() {
        const answerId = $(this).data('answer-id');
        handleVoteClick(this, 'vote_answer', { answer_id: answerId });
    });

    $('.answer-downvote').on('click', function() {
        const answerId = $(this).data('answer-id');
        handleVoteClick(this, 'vote_answer', { answer_id: answerId });
    });

    // Question status buttons
    $('#question-protect, #question-close, #question-reopen').on('click', function() {
        const action = $(this).attr('id').replace('question-', '') + '_question';
        const button = $(this);
        const originalText = button.html();
        
        button.addClass('vote-processing').html('Processing...');
        
        $.post(window.location.href, {
            ajax: true,
            action: action,
            question_id: '<?=htmlspecialchars($actualQuestionId);?>'
        })
        .done(function(response) {
            if (response.success) {
                location.reload(); // Reload to show new status
            } else {
                button.removeClass('vote-processing').html(originalText);
                alert('Error: ' + (response.message || 'Action failed'));
            }
        })
        .fail(function() {
            button.removeClass('vote-processing').html(originalText);
            alert('Network error. Please try again.');
        });
    });

    // Update view count on page load (only once)
    if (!sessionStorage.getItem('viewed_<?=htmlspecialchars($actualQuestionId);?>')) {
        $.post(window.location.href, {
            ajax: true,
            action: 'update_view',
            question_id: '<?=htmlspecialchars($actualQuestionId);?>'
        })
        .done(function(response) {
            if (response.success) {
                $('#question-views').text(response.views);
                sessionStorage.setItem('viewed_<?=htmlspecialchars($actualQuestionId);?>', 'true');
            }
        });
    }
});
// Simple share functionality
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Link copied to clipboard!');
        }).catch(function() {
            // Fallback
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Link copied to clipboard!');
        });
    }

    // Share buttons
    $('#share-question').on('click', function() {
        const questionId = $(this).data('question-id');
        const shareUrl = window.location.origin + '/pages/q&a/q&a.php?questionName=' + encodeURIComponent(questionId);
        copyToClipboard(shareUrl);
    });

    $('.share-answer').on('click', function() {
        const questionId = $(this).data('question-id');
        const answerId = $(this).data('answer-id');
        const shareUrl = window.location.origin + '/pages/q&a/q&a.php?questionName=' + encodeURIComponent(questionId) + '#answer-' + answerId;
        copyToClipboard(shareUrl);
    });
</script>

</body>
</html>