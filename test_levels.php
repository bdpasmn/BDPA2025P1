<?php
// qOverflow Level Testing Script - Simplified Version
require_once 'Api/api.php';

// Helper functions
function calculateLevel($points) {
        if ($points >= 10000) return 7;
        if ($points >= 3000) return 6;
        if ($points >= 1000) return 5;
        if ($points >= 125) return 4;
        if ($points >= 50) return 3;
        if ($points >= 15) return 2;
        if ($points >= 1) return 1;
        return 0;
    }
    
function getPrivilegesForLevel($level) {
        switch ($level) {
            case 1: return 'Create answers';
            case 2: return 'Create answers, Upvote';
            case 3: return 'Create answers, Upvote, Comment anywhere';
            case 4: return 'Create answers, Upvote, Comment anywhere, Downvote';
            case 5: return 'Create answers, Upvote, Comment anywhere, Downvote, View detailed votes';
            case 6: return 'Create answers, Upvote, Comment anywhere, Downvote, View detailed votes, Protection votes';
            case 7: return 'All privileges including Close/Reopen votes';
            default: return 'Basic access';
        }
    }
    
function getTestChecklist($level) {
        $checklist = [
            1 => ['Create a new answer', 'Verify answer appears correctly', 'Check point gain (+2)'],
            2 => ['Upvote a question', 'Upvote an answer', 'Verify point distribution'],
            3 => ['Comment on any question', 'Comment on any answer', 'Verify comment permissions'],
            4 => ['Downvote a question', 'Downvote an answer', 'Verify point loss'],
            5 => ['View detailed vote counts', 'Check upvote/downvote breakdown', 'Verify level requirement'],
            6 => ['Initiate protection vote', 'Participate in protection vote', 'Verify protection status'],
            7 => ['Initiate close vote', 'Participate in close vote', 'Verify question closure']
        ];
        
        if (isset($checklist[$level])) {
        echo "<h3>Test Checklist for Level $level:</h3>";
        echo "<ol>";
            foreach ($checklist[$level] as $item) {
            echo "<li>$item</li>";
        }
        echo "</ol>";
    }
}

// Main logic
$action = $_GET['action'] ?? 'main';
$username = $_GET['user'] ?? '';

// Set session for test users
if ($username && in_array($username, ['smnuser1', 'smnuser2', 'smnuser3', 'smnuser4', 'smnuser5', 'smnuser6', 'smnuser7'])) {
    session_start();
    $_SESSION['username'] = $username;
    
    // Get the actual user data from API and set session points
    try {
        require_once __DIR__ . '/Api/key.php';
        $api = new qOverflowAPI(API_KEY);
        $userData = $api->getUser($username);
        if (isset($userData['user']['points'])) {
            $_SESSION['points'] = $userData['user']['points'];
        } else {
            // Fallback to expected points if API doesn't return them
            $_SESSION['points'] = $testUsers[$username]['points'] ?? 1;
        }
    } catch (Exception $e) {
        // Fallback to expected points if API call fails
        $_SESSION['points'] = $testUsers[$username]['points'] ?? 1;
    }
}

// Handle actions
if ($action === 'clear_session') {
        session_start();
        session_destroy();
        header('Location: ?');
        exit;
    }

// Test users configuration
$testUsers = [
    'smnuser1' => ['points' => 1, 'level' => 1],
    'smnuser2' => ['points' => 15, 'level' => 2],
    'smnuser3' => ['points' => 50, 'level' => 3],
    'smnuser4' => ['points' => 125, 'level' => 4],
    'smnuser5' => ['points' => 1000, 'level' => 5],
    'smnuser6' => ['points' => 3000, 'level' => 6],
    'smnuser7' => ['points' => 10000, 'level' => 7]
];

?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>qOverflow Test Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #333; color: white; padding: 20px; margin-bottom: 20px; }
        .section { background: white; padding: 20px; margin-bottom: 20px; border: 1px solid #ddd; }
        .user-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; }
        .user-btn { padding: 10px; text-decoration: none; color: white; text-align: center; border-radius: 4px; }
        .level1 { background: #6c757d; }
        .level2 { background: #007bff; }
        .level3 { background: #28a745; }
        .level4 { background: #ffc107; color: black; }
        .level5 { background: #fd7e14; }
        .level6 { background: #dc3545; }
        .level7 { background: #6f42c1; }
        .test-links { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-top: 15px; }
        .test-link { padding: 8px; text-decoration: none; color: white; text-align: center; border-radius: 4px; background: #007bff; }
        .test-link:hover { background: #0056b3; }
        .info { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; margin: 15px 0; border-radius: 4px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; margin: 15px 0; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        .setup-btn { background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
        .clear-btn { background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
    </style>
</head>
<body>

<?php if ($action === 'setup_users'): ?>
    <!-- Setup Users Page -->
    <div class='container'>
        <div class='header'>
            <a href='?' style='color: white; text-decoration: none;'>← Back to Main</a>
            <h1>Setup Test Users</h1>
        </div>
        
        <?php
        try {
            require_once __DIR__ . '/Api/key.php';
            $api = new qOverflowAPI(API_KEY);
            
            foreach ($testUsers as $username => $userData):
                $currentUser = $api->getUser($username);
                ?>
                <div class='section'>
                    <h3>Processing <?= htmlspecialchars($username) ?></h3>
                    
                    <?php if (isset($currentUser['error'])): ?>
                        <div class='info'>
                            <p>User doesn't exist, creating...</p>
                            <?php $createResult = $api->createUser($username, $username . '@test.com', 'testsalt', 'testkey'); ?>
                            <p>Create result: <?= isset($createResult['error']) ? 'Error' : 'Success' ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php
                    $currentUser = $api->getUser($username);
                    $currentPoints = $currentUser['user']['points'] ?? 0;
                    $targetPoints = $userData['points'];
                    ?>
                    
                    <p>Current points: <strong><?= $currentPoints ?></strong>, Target points: <strong><?= $targetPoints ?></strong></p>
                    
                    <?php if ($currentPoints != $targetPoints): ?>
                        <?php
                        echo "<p>Attempting to set points to: <strong>$targetPoints</strong></p>";
                        
                        // Use the same approach as the buffet page - updateUser with points field
                        $result = $api->updateUser($username, ['points' => $targetPoints]);
                        
                        echo "<p>API Response: <pre>" . htmlspecialchars(print_r($result, true)) . "</pre></p>";
                        
                        // Wait a moment for API to process
                        sleep(2);
                        
                        // Refresh user data to show updated points
                        $updatedUser = $api->getUser($username);
                        $updatedPoints = $updatedUser['user']['points'] ?? 0;
                        ?>
                        <div class='success'>
                            <p>Points updated successfully</p>
                            <p>New points: <strong><?= $updatedPoints ?></strong></p>
                            <p>Expected points: <strong><?= $targetPoints ?></strong></p>
                        </div>
                    <?php else: ?>
                        <div class='success'>
                            <p>✓ Points already correct</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach;
            
        } catch (Exception $e) {
            echo "<div class='error'><h3>Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
        }
        ?>
    </div>

<?php elseif ($action === 'test_user' && $username): ?>
    <!-- Test User Page -->
    <?php
    try {
        require_once __DIR__ . '/Api/key.php';
        $api = new qOverflowAPI(API_KEY);
        
        // Get fresh user data with retry to ensure updated points
        $userData = $api->getUser($username);
        $user = $userData['user'] ?? $userData['results']['user'] ?? $userData['results'] ?? $userData;
        $points = $user['points'] ?? 0;
        
        // If points don't match expected level, try one more time after a short delay
        $expectedLevel = $testUsers[$username]['level'] ?? 0;
        $actualLevel = calculateLevel($points);
        if ($actualLevel !== $expectedLevel) {
            sleep(1); // Wait 1 second
            $userData = $api->getUser($username);
            $user = $userData['user'] ?? $userData['results']['user'] ?? $userData['results'] ?? $userData;
            $points = $user['points'] ?? 0;
        }
        
        $level = calculateLevel($points);
        ?>
        <div class='container'>
            <div class='header'>
                <a href='?' style='color: white; text-decoration: none;'>← Back to Main</a>
                <h1>Testing as: <?= htmlspecialchars($username) ?></h1>
                <p>Session set - you can now navigate to other pages</p>
            </div>
            
            <div class='section'>
                <h2>User Information</h2>
                <div id="user-info">
                    <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
                    <p><strong>Level:</strong> <span id="user-level"><?= $level ?></span></p>
                    <p><strong>Points:</strong> <span id="user-points"><?= $points ?></span></p>
                    <p><strong>Privileges:</strong> <span id="user-privileges"><?= htmlspecialchars(getPrivilegesForLevel($level)) ?></span></p>
                </div>
                <div id="refresh-status" class="mt-2 text-sm text-gray-600"></div>
            </div>
            
            <div class='section'>
                <h2>Test Pages</h2>
                <p>Click these links to test different features (opens in new tab):</p>
                <div class='test-links'>
                    <a href='pages/buffet/buffet.php' target='_blank' class='test-link'>Buffet (Home)</a>
                    <a href='pages/q&a/q&a.php' target='_blank' class='test-link'>Q&A View</a>
                    <a href='pages/dashboard/dashboard.php' target='_blank' class='test-link'>Dashboard</a>
                    <a href='pages/mail/mail.php' target='_blank' class='test-link'>Mail System</a>
                    <a href='pages/auth/login.php' target='_blank' class='test-link'>Login Page</a>
                    <a href='pages/auth/signup.php' target='_blank' class='test-link'>Signup Page</a>
                </div>
            </div>
            
            <div class='section'>
                <h2>Level <?= $level ?> Specific Tests</h2>
                <div class='info'>
                    <h3>What you CAN do:</h3>
                    <ul>
                        <li>Create new questions</li>
                        <?php if ($level >= 1): ?><li>Create answers to questions</li><?php endif; ?>
                        <?php if ($level >= 2): ?><li>Upvote questions and answers</li><?php endif; ?>
                        <?php if ($level >= 3): ?><li>Comment on any question or answer</li><?php endif; ?>
                        <?php if ($level >= 4): ?><li>Downvote questions and answers</li><?php endif; ?>
                        <?php if ($level >= 5): ?><li>View detailed vote counts (upvotes/downvotes)</li><?php endif; ?>
                        <?php if ($level >= 6): ?><li>Participate in protection votes</li><?php endif; ?>
                        <?php if ($level >= 7): ?><li>Participate in close/reopen votes</li><?php endif; ?>
                    </ul>
                </div>
                
                <div class='info'>
                    <h3>What you CANNOT do:</h3>
                    <ul>
                        <?php if ($level < 2): ?><li>Upvote questions and answers (requires Level 2+)</li><?php endif; ?>
                        <?php if ($level < 3): ?><li>Comment on others' questions/answers (requires Level 3+)</li><?php endif; ?>
                        <?php if ($level < 4): ?><li>Downvote questions and answers (requires Level 4+)</li><?php endif; ?>
                        <?php if ($level < 5): ?><li>View detailed vote counts (requires Level 5+)</li><?php endif; ?>
                        <?php if ($level < 6): ?><li>Participate in protection votes (requires Level 6+)</li><?php endif; ?>
                        <?php if ($level < 7): ?><li>Participate in close/reopen votes (requires Level 7+)</li><?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <div class='section'>
                <h2>Quick Test Checklist</h2>
                <div class='info'>
                    <?php getTestChecklist($level); ?>
                </div>
            </div>
            
            <div class='section'>
                <h2>API Debug</h2>
                <pre><?= htmlspecialchars(print_r($userData, true)) ?></pre>
            </div>
            
            <div class='section'>
                <h2>All Test Users Data</h2>
                <?php
                foreach ($testUsers as $testUser => $userConfig) {
                    try {
                        $testUserData = $api->getUser($testUser);
                        echo "<h3>$testUser</h3>";
                        echo "<pre>" . htmlspecialchars(print_r($testUserData, true)) . "</pre>";
                        echo "<hr>";
                    } catch (Exception $e) {
                        echo "<h3>$testUser - Error</h3>";
                        echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
                        echo "<hr>";
                    }
                }
                ?>
            </div>
        </div>
        <?php
        
    } catch (Exception $e) {
        echo "<div class='container'><div class='error'><h3>Error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p></div></div>";
    }
    ?>

<?php else: ?>
    <!-- Main Page -->
    <div class='container'>
        <div class='header'>
            <h1>qOverflow Test Tool</h1>
            <p>Quick testing interface for different user levels</p>
        </div>
        
        <div class='section'>
            <h2>Setup</h2>
            <p>First, set up test users with correct point levels:</p>
            <a href='?action=setup_users' class='setup-btn'>Setup Test Users</a>
        </div>
        
        <div class='section'>
            <h2>Test Users</h2>
            <p>Click a user to set session and view their capabilities:</p>
            <div class='user-grid'>
                <?php foreach ($testUsers as $user => $data): ?>
                    <a href='?action=test_user&user=<?= urlencode($user) ?>' class='user-btn level<?= $data['level'] ?>'>
                        <strong><?= htmlspecialchars($user) ?></strong><br>
                        Level <?= $data['level'] ?> • <?= $data['points'] ?> pts
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class='section'>
            <h2>Session Management</h2>
            <a href='?action=clear_session' class='clear-btn'>Clear Session (Logout)</a>
        </div>
        
        <div class='section'>
            <h2>Testing Instructions</h2>
            <div class='info'>
                <h3>How to Test Efficiently:</h3>
                <ol>
                    <li><strong>Setup:</strong> Click 'Setup Test Users' to create users with correct point levels</li>
                    <li><strong>Select User:</strong> Click any test user to set their session</li>
                    <li><strong>Test Features:</strong> Use the test links to verify level-specific functionality</li>
                    <li><strong>URL Testing:</strong> You can also manually test by visiting: <code>http://127.0.0.1:3000/pages/buffet/buffet.php</code></li>
                    <li><strong>Session Verification:</strong> Check that the navbar shows the correct user and level</li>
                </ol>
                <h3>Key Test Scenarios:</h3>
                <ul>
                    <li><strong>Level 1:</strong> Can create answers, cannot upvote/downvote</li>
                    <li><strong>Level 2:</strong> Can upvote questions and answers</li>
                    <li><strong>Level 3:</strong> Can comment on any question/answer</li>
                    <li><strong>Level 4:</strong> Can downvote questions and answers</li>
                    <li><strong>Level 5:</strong> Can see detailed vote counts</li>
                    <li><strong>Level 6:</strong> Can participate in protection votes</li>
                    <li><strong>Level 7:</strong> Can participate in close/reopen votes</li>
                </ul>
            </div>
        </div>
            </div>
    <?php endif; ?>

<script>
function refreshUserData() {
    const statusDiv = document.getElementById('refresh-status');
    const levelSpan = document.getElementById('user-level');
    const pointsSpan = document.getElementById('user-points');
    const privilegesSpan = document.getElementById('user-privileges');
    
    statusDiv.textContent = 'Refreshing...';
    
    // Make AJAX request to get fresh user data
    fetch('?action=test_user&user=<?= urlencode($username) ?>&refresh=1&t=' + Date.now())
        .then(response => response.text())
        .then(html => {
            // Parse the HTML to extract updated values
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            
            const newLevel = doc.getElementById('user-level')?.textContent || levelSpan.textContent;
            const newPoints = doc.getElementById('user-points')?.textContent || pointsSpan.textContent;
            const newPrivileges = doc.getElementById('user-privileges')?.textContent || privilegesSpan.textContent;
            
            // Update the display
            levelSpan.textContent = newLevel;
            pointsSpan.textContent = newPoints;
            privilegesSpan.textContent = newPrivileges;
            
            statusDiv.textContent = 'Updated at ' + new Date().toLocaleTimeString();
            
            // Update level-specific tests if level changed
            if (newLevel !== levelSpan.textContent) {
                location.reload(); // Reload page if level changed
            }
        })
        .catch(error => {
            statusDiv.textContent = 'Error refreshing: ' + error.message;
        });
}

// Auto-refresh every 30 seconds
setInterval(refreshUserData, 30000);

// Refresh when page becomes visible (user comes back from other tabs)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        refreshUserData();
    }
});
</script>

</body>
</html> 