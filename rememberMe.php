<?php
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    list($cookieUsername, $cookieToken) = explode('|', $_COOKIE['remember_me'], 2);
    $expectedToken = hash_hmac('sha256', $cookieUsername, API_KEY);
    if (hash_equals($expectedToken, $cookieToken)) {
        $userResponse = $api->getUser($cookieUsername);
        if (isset($userResponse['user'])) {
            $_SESSION['username'] = $cookieUsername;
            $_SESSION['user_id'] = $userResponse['user']['id'];
            $_SESSION['points'] = $userResponse['user']['points'] ?? 0;
        }
    }
}