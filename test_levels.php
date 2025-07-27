<?php
// qOverflow Level Testing Script - Simplified Version
require_once 'db.php';
require_once 'Api/api.php';

class qOverflowLevelTester {
    private $pdo;
    private $testUsers = [];
    
    public function __construct() {
        try {
            $this->pdo = new PDO($GLOBALS['dsn'], $GLOBALS['user'], $GLOBALS['pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function runTestInterface() {
        $action = $_GET['action'] ?? 'main';
        $username = $_GET['user'] ?? '';
        
        if ($username && in_array($username, ['smnuser1', 'smnuser2', 'smnuser3', 'smnuser4', 'smnuser5', 'smnuser6', 'smnuser7'])) {
            session_start();
            $_SESSION['username'] = $username;
        }
        
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>qOverflow Test Tool</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <style>
                body { font-family: 'Inter', sans-serif; background: radial-gradient(ellipse at top, #0f172a, #0b1120); }
            </style>
        </head>
        <body class='text-white min-h-screen'>";
        
        switch ($action) {
            case 'test_user':
                $this->showUserTest($username);
                break;
            case 'setup_users':
                $this->setupUsers();
                break;
            case 'clear_session':
                $this->clearSession();
                break;
            default:
                $this->showMain();
        }
        
        echo "</body></html>";
    }
    
    private function showMain() {
        echo "<div class='container mx-auto px-4 py-8'>";
        echo "<h1 class='text-4xl font-bold text-center mb-8 bg-gradient-to-r from-blue-500 to-purple-500 bg-clip-text text-transparent'>qOverflow Test Tool</h1>";
        
        echo "<div class='max-w-2xl mx-auto space-y-4'>";
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>";
        echo "<h2 class='text-xl font-semibold mb-4 text-blue-400'>Setup</h2>";
        echo "<a href='?action=setup_users' class='bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded transition-colors'>Setup Test Users</a>";
        echo "</div>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>";
        echo "<h2 class='text-xl font-semibold mb-4 text-green-400'>Test Users</h2>";
        $testUsers = ['smnuser1', 'smnuser2', 'smnuser3', 'smnuser4', 'smnuser5', 'smnuser6', 'smnuser7'];
        foreach ($testUsers as $user) {
            echo "<a href='?action=test_user&user=$user' class='block bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded mb-2 transition-colors'>Test as $user</a>";
        }
        echo "</div>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>";
        echo "<h2 class='text-xl font-semibold mb-4 text-red-400'>Session</h2>";
        echo "<a href='?action=clear_session' class='bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded transition-colors'>Clear Session</a>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
    
    private function showUserTest($username) {
        echo "<div class='container mx-auto px-4 py-8'>";
        echo "<div class='max-w-4xl mx-auto'>";
        echo "<div class='flex items-center justify-between mb-6'>";
        echo "<a href='?' class='text-blue-400 hover:text-blue-300'>← Back to Main</a>";
        echo "<h2 class='text-2xl font-bold'>Testing as: $username</h2>";
        echo "<div class='text-green-400 text-sm'>Session set - you can now navigate to other pages</div>";
        echo "</div>";
        
        try {
            require_once __DIR__ . '/Api/key.php';
            $api = new qOverflowAPI(API_KEY);
            
            $userData = $api->getUser($username);
            echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700 mb-4'>";
            echo "<h3 class='text-lg font-semibold mb-4 text-blue-400'>Raw API Response:</h3>";
            echo "<pre class='bg-gray-900 p-4 rounded text-sm overflow-x-auto'>" . print_r($userData, true) . "</pre>";
            echo "</div>";
            
            // Extract user data - handle different possible response structures
            $user = null;
            if (isset($userData['user'])) {
                $user = $userData['user'];
            } elseif (isset($userData['results']) && isset($userData['results']['user'])) {
                $user = $userData['results']['user'];
            } elseif (isset($userData['results'])) {
                $user = $userData['results'];
            } else {
                $user = $userData;
            }
            
            $points = $user['points'] ?? 0;
            $level = $this->calculateLevel($points);
            
            echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700 mb-6'>";
            echo "<div class='flex items-center justify-between mb-4'>";
            echo "<div>";
            echo "<h3 class='text-xl font-semibold text-blue-400'>$username</h3>";
            echo "<p class='text-gray-300'>Level $level • $points points</p>";
            echo "</div>";
            echo "<span class='px-4 py-2 rounded-full text-lg font-bold " . $this->getLevelColor($level) . "'>Level $level</span>";
            echo "</div>";
            echo "<div class='bg-gray-700 rounded p-4'>";
            echo "<h4 class='font-semibold text-green-400 mb-2'>Available Privileges:</h4>";
            echo "<p class='text-gray-300'>" . $this->getPrivilegesForLevel($level) . "</p>";
            echo "</div>";
            echo "</div>";
            
            // Test scenarios
            echo "<div class='grid md:grid-cols-2 gap-6 mb-6'>";
            
            // Level-specific tests
            echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>";
            echo "<h3 class='text-lg font-semibold mb-4 text-blue-400'>Level $level Specific Tests</h3>";
            echo "<div class='space-y-3'>";
            
            // Show what this level can do
            $this->showLevelCapabilities($level);
            
            // Show what this level cannot do
            $this->showLevelRestrictions($level);
            
            echo "</div></div>";
            
            // General tests
            echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>";
            echo "<h3 class='text-lg font-semibold mb-4 text-purple-400'>Test Pages</h3>";
            echo "<div class='space-y-3'>";
            echo "<a href='pages/buffet/buffet.php' target='_blank' class='block bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition-colors'>Test Question Creation</a>";
            echo "<a href='pages/q&a/q&a.php' target='_blank' class='block bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded transition-colors'>Test Q&A View</a>";
            echo "<a href='pages/dashboard/dashboard.php' target='_blank' class='block bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded transition-colors'>Test Dashboard</a>";
            echo "<a href='pages/mail/mail.php' target='_blank' class='block bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded transition-colors'>Test Mail System</a>";
            echo "<a href='?' onclick='clearSession()' class='block bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded transition-colors'>Clear Session (Logout)</a>";
            echo "</div></div>";
            
            echo "</div>";
            
            // Test checklist
            echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>";
            echo "<h3 class='text-lg font-semibold mb-4 text-yellow-400'>Test Checklist for Level $level</h3>";
            echo "<div class='grid md:grid-cols-2 gap-4'>";
            $this->showTestChecklist($level);
            echo "</div></div>";
            
            // Add JavaScript for clearing session
            echo "<script>
            function clearSession() {
                fetch('?action=clear_session', { method: 'POST' })
                    .then(() => {
                        window.location.href = '?';
                    });
            }
            </script>";
            
        } catch (Exception $e) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-4'>";
            echo "<p class='text-red-300'>Error: " . $e->getMessage() . "</p>";
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    private function setupUsers() {
        echo "<div class='container mx-auto px-4 py-8'>";
        echo "<div class='max-w-4xl mx-auto'>";
        echo "<div class='flex items-center justify-between mb-6'>";
        echo "<a href='?' class='text-blue-400 hover:text-blue-300'>← Back to Main</a>";
        echo "<h2 class='text-2xl font-bold'>Setup Test Users</h2>";
        echo "</div>";
        
        try {
            require_once __DIR__ . '/Api/key.php';
            $api = new qOverflowAPI(API_KEY);
            
            $testUsers = [
                'smnuser1' => ['points' => 1, 'level' => 1],
                'smnuser2' => ['points' => 15, 'level' => 2],
                'smnuser3' => ['points' => 50, 'level' => 3],
                'smnuser4' => ['points' => 125, 'level' => 4],
                'smnuser5' => ['points' => 1000, 'level' => 5],
                'smnuser6' => ['points' => 3000, 'level' => 6],
                'smnuser7' => ['points' => 10000, 'level' => 7]
            ];
            
            foreach ($testUsers as $username => $userData) {
                echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700 mb-6'>";
                echo "<h3 class='text-lg font-semibold mb-4 text-blue-400'>Processing $username</h3>";
                
                // Get current user data
                $currentUser = $api->getUser($username);
                echo "<div class='mb-4'>";
                echo "<h4 class='text-sm font-semibold mb-2 text-gray-300'>Current user data:</h4>";
                echo "<pre class='bg-gray-900 p-4 rounded text-sm overflow-x-auto'>" . print_r($currentUser, true) . "</pre>";
                echo "</div>";
                
                if (isset($currentUser['error'])) {
                    echo "<div class='mb-4'>";
                    echo "<p class='text-yellow-300 mb-2'>User doesn't exist, creating...</p>";
                    $createResult = $api->createUser($username, $username . '@test.com', 'testsalt', 'testkey');
                    echo "<h4 class='text-sm font-semibold mb-2 text-gray-300'>Create result:</h4>";
                    echo "<pre class='bg-gray-900 p-4 rounded text-sm overflow-x-auto'>" . print_r($createResult, true) . "</pre>";
        echo "</div>";
    }
    
                // Get user data again (in case we just created them)
                $currentUser = $api->getUser($username);
                $currentPoints = $currentUser['user']['points'] ?? 0;
                $targetPoints = $userData['points'];
                
                echo "<div class='mb-4'>";
                echo "<p class='text-gray-300'>Current points: <span class='font-semibold'>$currentPoints</span>, Target points: <span class='font-semibold'>$targetPoints</span></p>";
                
                if ($currentPoints != $targetPoints) {
                    $difference = $targetPoints - $currentPoints;
                    echo "<p class='text-blue-300'>Need to adjust by: <span class='font-semibold'>$difference</span></p>";
                    
                    if ($difference > 0) {
                        $pointsResult = $api->updateUserPoints($username, 'increment', $difference);
                    } else {
                        $pointsResult = $api->updateUserPoints($username, 'decrement', abs($difference));
                    }
                    
                    echo "<h4 class='text-sm font-semibold mb-2 text-gray-300'>Points update result:</h4>";
                    echo "<pre class='bg-gray-900 p-4 rounded text-sm overflow-x-auto'>" . print_r($pointsResult, true) . "</pre>";
                } else {
                    echo "<p class='text-green-300'>✓ Points already correct</p>";
                }
                echo "</div>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-4'>";
            echo "<p class='text-red-300'>Error: " . $e->getMessage() . "</p>";
            echo "</div>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    private function calculateLevel($points) {
        if ($points >= 10000) return 7;
        if ($points >= 3000) return 6;
        if ($points >= 1000) return 5;
        if ($points >= 125) return 4;
        if ($points >= 50) return 3;
        if ($points >= 15) return 2;
        if ($points >= 1) return 1;
        return 0;
    }
    
    private function getPrivilegesForLevel($level) {
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
    
    private function getLevelColor($level) {
        switch ($level) {
            case 1: return 'bg-gray-600 text-white';
            case 2: return 'bg-blue-600 text-white';
            case 3: return 'bg-green-600 text-white';
            case 4: return 'bg-yellow-600 text-black';
            case 5: return 'bg-orange-600 text-white';
            case 6: return 'bg-red-600 text-white';
            case 7: return 'bg-purple-600 text-white';
            default: return 'bg-gray-600 text-white';
        }
    }
    
    private function showLevelCapabilities($level) {
        echo "<div class='bg-green-900 border border-green-700 rounded p-4'>";
        echo "<h4 class='font-semibold text-green-300 mb-2'>Features You CAN Use:</h4>";
        echo "<ul class='space-y-1'>";
        
        // All levels can create questions
        echo "<li class='text-green-300'>Create new questions</li>";
        
        // Level 1+ can create answers
        if ($level >= 1) {
            echo "<li class='text-green-300'>Create answers to questions</li>";
        }
        
        // Level 2+ can upvote
        if ($level >= 2) {
            echo "<li class='text-green-300'>Upvote questions and answers</li>";
        }
        
        // Level 3+ can comment anywhere
        if ($level >= 3) {
            echo "<li class='text-green-300'>Comment on any question or answer</li>";
        }
        
        // Level 4+ can downvote
        if ($level >= 4) {
            echo "<li class='text-green-300'>Downvote questions and answers</li>";
        }
        
        // Level 5+ can see detailed votes
        if ($level >= 5) {
            echo "<li class='text-green-300'>View detailed vote counts (upvotes/downvotes)</li>";
        }
        
        // Level 6+ can protection vote
        if ($level >= 6) {
            echo "<li class='text-green-300'>Participate in protection votes</li>";
        }
        
        // Level 7+ can close/reopen
        if ($level >= 7) {
            echo "<li class='text-green-300'>Participate in close/reopen votes</li>";
        }
        
        echo "</ul>";
        echo "</div>";
    }
    
    private function showLevelRestrictions($level) {
        echo "<div class='bg-red-900 border border-red-700 rounded p-4'>";
        echo "<h4 class='font-semibold text-red-300 mb-2'>Features You CANNOT Use:</h4>";
        echo "<ul class='space-y-1'>";
        
        if ($level < 2) {
            echo "<li class='text-red-300'>Upvote questions and answers (requires Level 2+)</li>";
        }
        if ($level < 3) {
            echo "<li class='text-red-300'>Comment on others' questions/answers (requires Level 3+)</li>";
        }
        if ($level < 4) {
            echo "<li class='text-red-300'>Downvote questions and answers (requires Level 4+)</li>";
        }
        if ($level < 5) {
            echo "<li class='text-red-300'>View detailed vote counts (requires Level 5+)</li>";
        }
        if ($level < 6) {
            echo "<li class='text-red-300'>Participate in protection votes (requires Level 6+)</li>";
        }
        if ($level < 7) {
            echo "<li class='text-red-300'>Participate in close/reopen votes (requires Level 7+)</li>";
        }
        
        echo "</ul>";
        echo "</div>";
    }
    
    private function showTestChecklist($level) {
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
            foreach ($checklist[$level] as $item) {
                echo "<div class='flex items-center space-x-2'>";
                echo "<input type='checkbox' class='rounded'>";
                echo "<span class='text-gray-300'>$item</span>";
                echo "</div>";
            }
        }
    }
    
    private function clearSession() {
        session_start();
        session_destroy();
        header('Location: ?');
        exit;
    }
}

$tester = new qOverflowLevelTester();
$tester->runTestInterface();
?> 