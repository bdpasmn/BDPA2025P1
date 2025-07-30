<?php
class qOverflowAPI {
    private $baseUrl;
    private $apiKey;
    private $maxRetries;

    public function __construct($apiKey, $version = 'v1') {
        $this->baseUrl = "https://qoverflow.api.hscc.bdpa.org/$version";
        $this->apiKey = $apiKey;
        $this->maxRetries = 5;
    }

    private function request($method, $endpoint, $data = null, $query = []) {
        $url = $this->baseUrl . $endpoint;

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->apiKey}"
        ];

        $retry = $this->maxRetries;
        while ($retry > 0) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

          
            // Handle cURL errors
            if ($curlError) {
                error_log("qOverflow API - cURL Error: " . $curlError);
                $this->redirectToError(503);
                return false;
            }

            // Retry logic for server errors (5xx)
            if ($httpCode >= 429 && $httpCode < 600) {
                $retry--;
                if ($retry > 0) {
                    error_log("qOverflow API - Retrying request. Attempts remaining: " . $retry);
                    sleep(1);
                } else {
                    error_log("qOverflow API - All retry attempts exhausted for server error");
                    
                    $this->redirectToError(503);
                    return false;
                }
            } else {
                // Handle successful responses (2xx)
                if ($httpCode >= 200 && $httpCode < 405) {
                    return json_decode($response, true);
                } else if ($httpCode >= 405 && $httpCode < 500) {
                    // DEBUG: Log all responses
                        error_log("qOverflow API Debug - URL: $url");
                        error_log("qOverflow API Debug - HTTP Code: $httpCode");
                        error_log("qOverflow API Debug - Response: " . substr($response, 0, 200));

                    // Redirect for critical client errors that should show error page
                    if (in_array($httpCode, [401, 403, 404])) {
                        $this->redirectToError($httpCode);
                        return false;
                    }
                    
                    // For other client errors, return the response data
                    error_log("qOverflow API Debug - Not redirecting, returning response data for code: " . $httpCode);
                    $responseData = json_decode($response, true);
                    if ($responseData === null) {
                        $responseData = ['error' => true, 'message' => 'Client error', 'code' => $httpCode];
                    }
                    $responseData['_httpCode'] = $httpCode;
                    return $responseData;
                } else {
                    // Handle other unexpected errors
                    error_log("qOverflow API Unexpected Error - Code: " . $httpCode . " - Response: " . $response);
                    $this->redirectToError($httpCode);
                    return false;
                }
            }
        }

        return false;
    }

    private function redirectToError($httpCode) {
        error_log("qOverflow API Debug - redirectToError called with code: " . $httpCode);
        
        // Check if headers have already been sent
        if (headers_sent($file, $line)) {
            error_log("qOverflow API Debug - Headers already sent at $file:$line - cannot redirect");
            // If headers are already sent, we can't redirect
            // You might want to throw an exception or handle this differently
            echo "<script>window.location.href='/Api/error.php?code=$httpCode';</script>";
            exit();
        }
        
        $errorUrl = '/Api/error.php?code=' . $httpCode;
        error_log("qOverflow API Debug - Redirecting to: " . $errorUrl);
        
        header('Location: ' . $errorUrl);
        exit();
    }

    // All your existing methods remain the same
    public function sendMail($sender, $receiver, $subject, $text) {
        return $this->request('POST', '/mail', compact('sender', 'receiver', 'subject', 'text'));
    }

    public function getMail($username, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/mail/" . urlencode($username), null, $query);
    }

    public function createUser($username, $email, $salt, $key) {
        return $this->request('POST', "/users", compact('username', 'email', 'salt', 'key'));
    }

    public function getUser($username) {
        return $this->request('GET', "/users/" . urlencode($username));
    }

    public function updateUser($username, $fields) {
        return $this->request('PATCH', "/users/" . urlencode($username), $fields);
    }

    public function deleteUser($username) {
        return $this->request('DELETE', "/users/" . urlencode($username));
    }

    public function authenticateUser($username, $key) {
        return $this->request('POST', "/users/" . urlencode($username) . "/auth", ['key' => $key]);
    }

    public function listUsers($after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/users", null, $query);
    }

    public function getUserQuestions($username, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/users/" . urlencode($username) . "/questions", null, $query);
    }

    public function getUserAnswers($username, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/users/" . urlencode($username) . "/answers", null, $query);
    }

    public function searchQuestions(array $params = []) {
        return $this->request('GET', '/questions/search', null, $params);
    }    

    public function createQuestion($creator, $title, $text) {
        if (empty($creator) || empty($title) || empty($text)) {
            throw new InvalidArgumentException('Creator, title, and text are required');
        }
        return $this->request('POST', "/questions", compact('creator', 'title', 'text'));
    }

    public function getQuestion($question_id) {
        if (empty($question_id)) {
            throw new InvalidArgumentException('Question ID is required');
        }
        return $this->request('GET', "/questions/" . urlencode($question_id));
    }

    public function updateQuestion($question_id, $fields) {
        if (empty($question_id)) {
            throw new InvalidArgumentException('Question ID is required');
        }
        return $this->request('PATCH', "/questions/" . urlencode($question_id), $fields);
    }

    public function deleteQuestion($question_id) {
        if (empty($question_id)) {
            throw new InvalidArgumentException('Question ID is required');
        }
        return $this->request('DELETE', "/questions/" . urlencode($question_id));
    }

    public function getAnswers($question_id, $after = null) {
        if (empty($question_id)) {
            throw new InvalidArgumentException('Question ID is required');
        }
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/questions/$question_id/answers", null, $query);
    }

    public function createAnswer($question_id, $creator, $text) {
        if (empty($question_id) || empty($creator) || empty($text)) {
            throw new InvalidArgumentException('Question ID, creator, and text are required');
        }
        return $this->request('POST', "/questions/$question_id/answers", compact('creator', 'text'));
    }

    public function updateAnswer($question_id, $answer_id, $fields) {
        if (empty($question_id) || empty($answer_id)) {
            throw new InvalidArgumentException('Question ID and Answer ID are required');
        }
        return $this->request('PATCH', "/questions/$question_id/answers/$answer_id", $fields);
    }

    public function voteAnswer($question_id, $answer_id, $username, $operation, $target) {
        if (empty($question_id) || empty($answer_id) || empty($username)) {
            throw new InvalidArgumentException('Question ID, Answer ID, and username are required');
        }
        if (!in_array($operation, ['increment', 'decrement', 'unvote'])) {
            throw new InvalidArgumentException('Operation must be increment, decrement, or unvote');
        }
        return $this->request('PATCH', "/questions/$question_id/answers/$answer_id/vote/$username", compact('operation', 'target'));
    }

    public function getQuestionComments($question_id, $after = null) {
        if (empty($question_id)) {
            throw new InvalidArgumentException('Question ID is required');
        }
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/questions/$question_id/comments", null, $query);
    }

    public function createQuestionComment($question_id, $creator, $text) {
        if (empty($question_id) || empty($creator) || empty($text)) {
            throw new InvalidArgumentException('Question ID, creator, and text are required');
        }
        return $this->request('POST', "/questions/$question_id/comments", compact('creator', 'text'));
    }

    public function deleteQuestionComment($question_id, $comment_id) {
        if (empty($question_id) || empty($comment_id)) {
            throw new InvalidArgumentException('Question ID and Comment ID are required');
        }
        return $this->request('DELETE', "/questions/$question_id/comments/$comment_id");
    }

    public function voteQuestionComment($question_id, $comment_id, $username, $operation, $target) {
        if (empty($question_id) || empty($comment_id) || empty($username)) {
            throw new InvalidArgumentException('Question ID, Comment ID, and username are required');
        }
        if (!in_array($operation, ['increment', 'decrement', 'unvote'])) {
            throw new InvalidArgumentException('Operation must be increment, decrement, or unvote');
        
        }
        return $this->request('PATCH', "/questions/$question_id/comments/$comment_id/vote/$username", compact('operation', 'target'));
    }

    public function getAnswerComments($question_id, $answer_id, $after = null) {
        if (empty($question_id) || empty($answer_id)) {
            throw new InvalidArgumentException('Question ID and Answer ID are required');
        }
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/questions/$question_id/answers/$answer_id/comments", null, $query);
    }

    public function createAnswerComment($question_id, $answer_id, $creator, $text) {
        if (empty($question_id) || empty($answer_id) || empty($creator) || empty($text)) {
            throw new InvalidArgumentException('Question ID, Answer ID, creator, and text are required');
        }
        return $this->request('POST', "/questions/$question_id/answers/$answer_id/comments", compact('creator', 'text'));
    }

    public function deleteAnswerComment($question_id, $answer_id, $comment_id) {
        if (empty($question_id) || empty($answer_id) || empty($comment_id)) {
            throw new InvalidArgumentException('Question ID, Answer ID, and Comment ID are required');
        }
        return $this->request('DELETE', "/questions/$question_id/answers/$answer_id/comments/$comment_id");
    }

    public function voteAnswerComment($question_id, $answer_id, $comment_id, $username, $operation, $target) {
        if (empty($question_id) || empty($answer_id) || empty($comment_id) || empty($username)) {
            throw new InvalidArgumentException('Question ID, Answer ID, Comment ID, and username are required');
        }
        if (!in_array($operation, ['increment', 'decrement', 'unvote'])) {
            throw new InvalidArgumentException('Operation must be increment, decrement, or unvote');
        }
        return $this->request('PATCH', "/questions/$question_id/answers/$answer_id/comments/$comment_id/vote/$username", compact('operation', 'target'));
    }
}
?>