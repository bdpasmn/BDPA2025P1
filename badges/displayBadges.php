<?php
session_start();

if (!isset($_SESSION['username'])) {
    die("Not logged in.");
}

require_once 'updateBadges.php';  // Your functions file
require_once '../Api/key.php';
require_once '../Api/api.php';
require_once '../db.php';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$username = $_SESSION['username'];
$api = new qOverflowAPI(API_KEY);

// Make $pdo available inside updateBadges()
global $pdo;

// Call updateBadges and get the result
$badges = updateBadges($username);
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Badges</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-8">

<div class="max-w-4xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">User: <?= htmlspecialchars($username) ?></h1>

    <h2 class="text-2xl font-semibold mb-4">Badges Earned</h2>
    <?php if (empty($badges)): ?>
        <p class="text-gray-400">No badges earned yet.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php 
            $tierColors = [
                'gold' => 'bg-yellow-500',
                'silver' => 'bg-gray-400', 
                'bronze' => 'bg-yellow-800'
            ];
            $tierEmojis = [
                'gold' => '🥇',
                'silver' => '🥈',
                'bronze' => '🥉'
            ];
            
            foreach ($badges as $badge): ?>
                <div class="bg-gray-800 p-4 rounded-lg border border-gray-700">
                    <div class="flex items-center space-x-2">
                        <span class="text-2xl"><?= $tierEmojis[strtolower($badge['tier'])] ?? '🏅' ?></span>
                        <span class="<?= $tierColors[strtolower($badge['tier'])] ?? 'bg-gray-600' ?> px-2 py-1 rounded text-xs font-semibold text-black">
                            <?= ucfirst(htmlspecialchars($badge['tier'])) ?>
                        </span>
                    </div>
                    <p class="mt-2 font-medium"><?= htmlspecialchars($badge['badge_name']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
