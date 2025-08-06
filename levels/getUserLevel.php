<?php
function getUserLevel($username) {
    $api = new qOverflowAPI(API_KEY);

    $userInfo = $api->getUser($username);
    if (!isset($userInfo['user']['points'])) {
        return ['level' => '?']; 
    }

    $points = $userInfo['user']['points'];

    if ($points >= 10000) {
        return ['level' => 9]; 
    } elseif ($points >= 3000) {
        return ['level' => 8]; 
    } elseif ($points >= 2000) {
        return ['level' => 7];
    } elseif ($points >= 1000) {
        return ['level' => 6]; 
    } elseif ($points >= 125) {
        return ['level' => 5];
    } elseif ($points >= 75) {
        return ['level' => 4]; 
    } elseif ($points >= 50) {
        return ['level' => 3];
    } elseif ($points >= 15) {
        return ['level' => 2];
    } else {
        return ['level' => 1];
    }
}
