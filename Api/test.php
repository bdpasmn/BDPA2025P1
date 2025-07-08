<?php
require_once './api.php';
require_once './key.php';

$api = new qOverflowAPI(API_KEY);
$response = $api->listUsers();

if (!empty($response['users'])) {
    foreach ($response['users'] as $user) {
        echo $user['username'] . "\n";
    }
} else {
    echo "No users found.\n";
}
?>
