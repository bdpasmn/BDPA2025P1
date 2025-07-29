
<?php
/*
file_put_contents('debug.txt', "Running rememberMe\n", FILE_APPEND);

function autoLoginFromCookie($api) {
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
        file_put_contents('debug.txt', "remember_me cookie found: {$_COOKIE['remember_me']}\n", FILE_APPEND);

        list($cookieUsername, $cookieToken) = explode('|', $_COOKIE['remember_me'], 2);
        $expectedToken = hash_hmac('sha256', $cookieUsername, API_KEY);

        file_put_contents('debug.txt', "Expected token: $expectedToken\nActual token: $cookieToken\n", FILE_APPEND);

        if (hash_equals($expectedToken, $cookieToken)) {
            file_put_contents('debug.txt', "Token match — fetching user\n", FILE_APPEND);
            $userResponse = $api->getUser($cookieUsername);
            if (isset($userResponse['user'])) {
                $_SESSION['username'] = $cookieUsername;
                $_SESSION['user_id'] = $userResponse['user']['id'];
                $_SESSION['points'] = $userResponse['user']['points'] ?? 0;

                file_put_contents('debug.txt', "Auto-login success, redirecting\n", FILE_APPEND);
                header("Location: /pages/buffet/buffet.php");
                exit;
            } else {
                file_put_contents('debug.txt', "User not found\n", FILE_APPEND);
            }
        } else {
            file_put_contents('debug.txt', "Token mismatch\n", FILE_APPEND);
        }
    } else {
        file_put_contents('debug.txt', "No cookie or session already set\n", FILE_APPEND);
    }
}
*/
function autoLoginFromCookie($api) {
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
        list($cookieUsername, $cookieToken) = explode('|', $_COOKIE['remember_me'], 2);
        $expectedToken = hash_hmac('sha256', $cookieUsername, API_KEY);

        if (hash_equals($expectedToken, $cookieToken)) {
            $userResponse = $api->getUser($cookieUsername);
            if (isset($userResponse['user'])) {
                $_SESSION['username'] = $cookieUsername;
                $_SESSION['user_id'] = $userResponse['user']['id'];
                $_SESSION['points'] = $userResponse['user']['points'] ?? 0;
                header("Location: /pages/buffet/buffet.php");
                exit;
            }
        }
    }
}

// require_once __DIR__ . '/../../rememberMe.php';

// autoLoginFromCookie($api);



