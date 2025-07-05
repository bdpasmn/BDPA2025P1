<?php

class qOverflowAPI {
    private $baseUrl;
    private $apiKey;

    public function __construct($apiKey, $version = 'v1') {
        $this->baseUrl = "https://drive.api.hscc.bdpa.org/$version";
        $this->apiKey = $apiKey;
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

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
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

    public function updateUserPoints($username, $operation, $amount) {
        return $this->request('PATCH', "/users/" . urlencode($username) . "/points", [
            'operation' => $operation,
            'amount' => $amount
        ]);
    }

    public function getUserQuestions($username, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/users/" . urlencode($username) . "/questions", null, $query);
    }

    public function getUserAnswers($username, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/users/" . urlencode($username) . "/answers", null, $query);
    }

    public function createQuestion($creator, $title, $text) {
        return $this->request('POST', "/questions", compact('creator', 'title', 'text'));
    }

    public function getQuestion($question_id) {
        return $this->request('GET', "/questions/" . urlencode($question_id));
    }

    public function updateQuestion($question_id, $fields) {
        return $this->request('PATCH', "/questions/" . urlencode($question_id), $fields);
    }

    public function deleteQuestion($question_id) {
        return $this->request('DELETE', "/questions/" . urlencode($question_id));
    }

    public function getAnswers($question_id, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/questions/$question_id/answers", null, $query);
    }

    public function createAnswer($question_id, $creator, $text) {
        return $this->request('POST', "/questions/$question_id/answers", compact('creator', 'text'));
    }

    public function updateAnswer($question_id, $answer_id, $fields) {
        return $this->request('PATCH', "/questions/$question_id/answers/$answer_id", $fields);
    }

    public function voteAnswer($question_id, $answer_id, $username, $operation, $target) {
        return $this->request('PATCH', "/questions/$question_id/answers/$answer_id/vote/$username", compact('operation', 'target'));
    }

    public function getQuestionComments($question_id, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/questions/$question_id/comments", null, $query);
    }

    public function createQuestionComment($question_id, $creator, $text) {
        return $this->request('POST', "/questions/$question_id/comments", compact('creator', 'text'));
    }

    public function deleteQuestionComment($question_id, $comment_id) {
        return $this->request('DELETE', "/questions/$question_id/comments/$comment_id");
    }

    public function voteQuestionComment($question_id, $comment_id, $username, $operation, $target) {
        return $this->request('PATCH', "/questions/$question_id/comments/$comment_id/vote/$username", compact('operation', 'target'));
    }

    public function getAnswerComments($question_id, $answer_id, $after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/questions/$question_id/answers/$answer_id/comments", null, $query);
    }

    public function createAnswerComment($question_id, $answer_id, $creator, $text) {
        return $this->request('POST', "/questions/$question_id/answers/$answer_id/comments", compact('creator', 'text'));
    }

    public function deleteAnswerComment($question_id, $answer_id, $comment_id) {
        return $this->request('DELETE', "/questions/$question_id/answers/$answer_id/comments/$comment_id");
    }

    public function voteAnswerComment($question_id, $answer_id, $comment_id, $username, $operation, $target) {
        return $this->request('PATCH', "/questions/$question_id/answers/$answer_id/comments/$comment_id/vote/$username", compact('operation', 'target'));
    }
}
?>
