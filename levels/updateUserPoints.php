<?php 
function updateUserPoints($username, $pointChange) { 
    //require_once '../../api/key.php';
    //require_once '../../api/api.php';

    $api = new qOverflowAPI(API_KEY);

    $userInfo = $api->getUser($username);

    $currentPoints = $userInfo['user']['points'];
    $newPoints = max(0, $currentPoints + $pointChange);

    $api->updateUser($username, ['points' => $newPoints]);

    if (isset($_SESSION['username']) && $_SESSION['username'] == $username) {
        $_SESSION['points'] = $newPoints;
    }
}
?>