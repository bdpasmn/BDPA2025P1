<?php

class BdpaDriveAPI {

    private $baseUrl;
    private $apiKey;
    private $version;

    public function __construct($apiKey, $version = 'v1') {
        $this->baseUrl = "https://drive.api.hscc.bdpa.org/$version";
        $this->apiKey = $apiKey;
        $this->version = $version;
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

    public function getUserByUsername($username) {
        return $this->request('GET', "/users/" . urlencode($username));
    }

    public function updateUser($username, $fields) {
        return $this->request('PUT', "/users/" . urlencode($username), $fields);
    }

    public function deleteUser($username) {
        return $this->request('DELETE', "/users/" . urlencode($username));
    }

    public function authenticateUser($username, $key) {
        return $this->request('POST', "/users/" . urlencode($username) . "/auth", compact('key'));
    }

    public function listUsers($after = null) {
        $query = $after ? ['after' => $after] : [];
        return $this->request('GET', "/users", null, $query);
    }


    public function searchNodes($username, $match = null, $regexMatch = null, $after = null) {
        $query = [];
        if ($match) $query['match'] = urlencode(json_encode($match));
        if ($regexMatch) $query['regexMatch'] = urlencode(json_encode($regexMatch));
        if ($after) $query['after'] = $after;
        return $this->request('GET', "/filesystem/" . urlencode($username) . "/search", null, $query);
    }

    public function createNode($username, $data) {
        return $this->request('POST', "/filesystem/" . urlencode($username), $data);
    }

    public function getNodes($username, ...$nodeIds) {
        $path = implode("/", array_map('urlencode', $nodeIds));
        return $this->request('GET', "/filesystem/" . urlencode($username) . "/$path");
    }

    public function updateNode($username, $nodeId, $data) {
        return $this->request('PUT', "/filesystem/" . urlencode($username) . "/" . urlencode($nodeId), $data);
    }

    public function deleteNodes($username, ...$nodeIds) {
        $path = implode("/", array_map('urlencode', $nodeIds));
        return $this->request('DELETE', "/filesystem/" . urlencode($username) . "/$path");
    }
}
?>
