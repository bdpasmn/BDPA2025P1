<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$user = $_SESSION['username'] ?? null;
if (!$user) {
    echo json_encode([
        'success' => false,
        'error' => 'Not logged in',
        'session' => $_SESSION,
        'cookies' => $_COOKIE,
        'post' => $_POST
    ]);
    exit;
}

$threadKey = $_POST['thread_key'] ?? '';
$action = $_POST['action'] ?? '';
if (!$threadKey || !in_array($action, ['pin', 'unpin'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid request',
        'session' => $_SESSION,
        'cookies' => $_COOKIE,
        'post' => $_POST
    ]);
    exit;
}

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass);
    if ($action === 'pin') {
        $stmt = $pdo->prepare("INSERT INTO pinned_threads (username, thread_key) VALUES (:username, :thread_key) ON CONFLICT DO NOTHING");
        $stmt->execute(['username' => $user, 'thread_key' => $threadKey]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM pinned_threads WHERE username = :username AND thread_key = :thread_key");
        $stmt->execute(['username' => $user, 'thread_key' => $threadKey]);
    }
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'session' => $_SESSION,
        'cookies' => $_COOKIE,
        'post' => $_POST
    ]);
} 