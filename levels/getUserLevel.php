<?php
function getUserLevel($username) {
    //require_once '../../api/key.php';
    //require_once '../../api/api.php';

    $api = new qOverflowAPI(API_KEY);

    $userInfo = $api->getUser($username);
    if (!isset($userInfo['user']['points'])) {
        return ['level' => '?']; 
    }

    $points = $userInfo['user']['points'];

    if ($points >= 10000) {
        return ['level' => 7];
    } elseif ($points >= 3000) {
        return ['level' => 6];
    } elseif ($points >= 1000) {
        return ['level' => 5];
    } elseif ($points >= 125) {
        return ['level' => 4];
    } elseif ($points >= 50) {
        return ['level' => 3];
    } elseif ($points >= 15) {
        return ['level' => 2];
    } else {
        return ['level' => 1];
    }
}