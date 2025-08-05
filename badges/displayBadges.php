<?php
session_start();

if (!isset($_SESSION['username'])) {
    die("Not logged in.");
}

require_once 'updateBadges.php';  // Your functions file
require_once '../api/key.php';
require_once '../api/api.php';
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

$data = updateBadges($username);
?>

<!DOCTYPE html>
<html>
<head>
</head>
<body>

<h1>User: <?= htmlspecialchars($username) ?></h1>

<h2>Badges Earned</h2>
<?php if (empty($data['badges'])): ?>
    <p>No badges earned yet.</p>
<?php else: ?>
    <ul>
        <?php foreach ($data['badges'] as $badge): ?>
            <li><?= htmlspecialchars($badge) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

</body>
</html>
