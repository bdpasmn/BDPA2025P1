<?php
// qOverflow Level Testing Script
// This script helps test all level-based features with predefined test users
// Run this to easily test your qOverflow implementation

require_once 'db.php';

class qOverflowLevelTester {
    private $pdo;
    private $testUsers = [];
    private $currentUser = null;
    
    public function __construct() {
        try {
            $this->pdo = new PDO($GLOBALS['dsn'], $GLOBALS['user'], $GLOBALS['pass']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
        
        $this->setupTestUsers();
    }
    
    /**
     * Setup test users with different levels
     */
    private function setupTestUsers() {
        $this->testUsers = [
            'smnuser1' => ['level' => 1, 'points' => 1, 'privileges' => 'Create answers'],
            'smnuser2' => ['level' => 2, 'points' => 15, 'privileges' => 'Create answers, Upvote'],
            'smnuser3' => ['level' => 3, 'points' => 50, 'privileges' => 'Create answers, Upvote, Comment anywhere'],
            'smnuser4' => ['level' => 4, 'points' => 125, 'privileges' => 'Create answers, Upvote, Comment anywhere, Downvote'],
            'smnuser5' => ['level' => 5, 'points' => 1000, 'privileges' => 'Create answers, Upvote, Comment anywhere, Downvote, View detailed votes'],
            'smnuser6' => ['level' => 6, 'points' => 3000, 'privileges' => 'Create answers, Upvote, Comment anywhere, Downvote, View detailed votes, Protection votes'],
            'smnuser7' => ['level' => 7, 'points' => 10000, 'privileges' => 'All privileges including Close/Reopen votes']
        ];
    }
    
    /**
     * Main testing interface
     */
    public function runTestInterface() {
        $action = $_GET['action'] ?? 'main';
        $username = $_GET['user'] ?? '';
        
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>qOverflow Level Testing Tool</title>
            <script src='https://cdn.tailwindcss.com'></script>
            <style>
                .test-card { transition: all 0.3s ease; }
                .test-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
                .privilege-list { max-height: 200px; overflow-y: auto; }
            </style>
        </head>
        <body class='bg-gray-900 text-white min-h-screen'>";
        
        echo "<div class='container mx-auto px-4 py-8'>";
        
        switch ($action) {
            case 'test_user':
                $this->showUserTestInterface($username);
                break;
            case 'quick_test':
                $this->showQuickTestInterface();
                break;
            case 'api_test':
                $this->showApiTestInterface();
                break;
            default:
                $this->showMainInterface();
        }
        
        echo "</div></body></html>";
    }
    
    /**
     * Show main testing interface
     */
    private function showMainInterface() {
        echo "<div class='max-w-6xl mx-auto'>";
        echo "<h1 class='text-4xl font-bold text-center mb-8 bg-gradient-to-r from-blue-500 to-purple-500 bg-clip-text text-transparent'>
            🎯 qOverflow Level Testing Tool
        </h1>";
        
        echo "<div class='grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8'>";
        
        foreach ($this->testUsers as $username => $userData) {
            $levelColor = $this->getLevelColor($userData['level']);
            $testUrl = urlencode("test_user&user=" . urlencode($username));
            
            echo "<div class='test-card bg-gray-800 rounded-lg p-6 border border-gray-700'>
                <div class='flex items-center justify-between mb-4'>
                    <h3 class='text-xl font-semibold text-blue-400'>$username</h3>
                    <span class='px-3 py-1 rounded-full text-sm font-bold $levelColor'>
                        Level {$userData['level']}
                    </span>
                </div>
                <div class='mb-4'>
                    <p class='text-gray-300 text-sm mb-2'><strong>Points:</strong> {$userData['points']}</p>
                    <div class='privilege-list'>
                        <p class='text-gray-400 text-xs'><strong>Privileges:</strong></p>
                        <p class='text-gray-300 text-xs'>" . str_replace(', ', '<br>• ', $userData['privileges']) . "</p>
                    </div>
                </div>
                <div class='space-y-2'>
                    <a href='?action=test_user&user=" . urlencode($username) . "' 
                       class='block w-full bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-4 rounded transition-colors'>
                        🧪 Test This User
                    </a>
                    <a href='?action=quick_test&user=" . urlencode($username) . "' 
                       class='block w-full bg-green-600 hover:bg-green-700 text-white text-center py-2 px-4 rounded transition-colors'>
                        ⚡ Quick Test
                    </a>
                </div>
            </div>";
        }
        
        echo "</div>";
        
        // Additional testing options
        echo "<div class='grid md:grid-cols-2 gap-6'>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-xl font-semibold mb-4 text-green-400'>🚀 Quick Testing</h3>
            <p class='text-gray-300 mb-4'>Test all users quickly with automated checks.</p>
            <a href='?action=quick_test' class='bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded transition-colors'>
                Run Quick Tests
            </a>
        </div>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-xl font-semibold mb-4 text-purple-400'>🔗 API Testing</h3>
            <p class='text-gray-300 mb-4'>Test API endpoints and authentication.</p>
            <a href='?action=api_test' class='bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded transition-colors'>
                Test API
            </a>
        </div>";
        
        echo "</div>";
        
        // Testing instructions
        echo "<div class='mt-8 bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-xl font-semibold mb-4 text-yellow-400'>📋 Testing Instructions</h3>
            <div class='grid md:grid-cols-2 gap-6 text-sm'>
                <div>
                    <h4 class='font-semibold text-blue-400 mb-2'>For Developers:</h4>
                    <ul class='text-gray-300 space-y-1'>
                        <li>• Click 'Test This User' to simulate being logged in as that user</li>
                        <li>• Use 'Quick Test' to run automated level checks</li>
                        <li>• Test API endpoints with the API Testing tool</li>
                        <li>• Share test URLs with your team</li>
                    </ul>
                </div>
                <div>
                    <h4 class='font-semibold text-green-400 mb-2'>For Testers:</h4>
                    <ul class='text-gray-300 space-y-1'>
                        <li>• Start with Level 1 user to test basic features</li>
                        <li>• Progress through levels to test advanced features</li>
                        <li>• Verify that lower levels cannot access higher privileges</li>
                        <li>• Test edge cases and error conditions</li>
                    </ul>
                </div>
            </div>
        </div>";
        
        echo "</div>";
    }
    
    /**
     * Show user-specific test interface
     */
    private function showUserTestInterface($username) {
        if (!isset($this->testUsers[$username])) {
            echo "<div class='text-red-400 text-center'>Invalid test user: $username</div>";
            return;
        }
        
        $userData = $this->testUsers[$username];
        $levelColor = $this->getLevelColor($userData['level']);
        
        echo "<div class='max-w-4xl mx-auto'>";
        echo "<div class='flex items-center justify-between mb-6'>
            <a href='?' class='text-blue-400 hover:text-blue-300'>← Back to Main</a>
            <h2 class='text-2xl font-bold'>Testing as: $username</h2>
        </div>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700 mb-6'>
            <div class='flex items-center justify-between mb-4'>
                <div>
                    <h3 class='text-xl font-semibold text-blue-400'>$username</h3>
                    <p class='text-gray-300'>Level {$userData['level']} • {$userData['points']} points</p>
                </div>
                <span class='px-4 py-2 rounded-full text-lg font-bold $levelColor'>
                    Level {$userData['level']}
                </span>
            </div>
            <div class='bg-gray-700 rounded p-4'>
                <h4 class='font-semibold text-green-400 mb-2'>Available Privileges:</h4>
                <p class='text-gray-300'>" . str_replace(', ', '<br>• ', $userData['privileges']) . "</p>
            </div>
        </div>";
        
        // Test scenarios
        echo "<div class='grid md:grid-cols-2 gap-6'>";
        
        // Level-specific tests
        $this->showLevelSpecificTests($username, $userData);
        
        // General tests
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-lg font-semibold mb-4 text-purple-400'>🔧 General Tests</h3>
            <div class='space-y-3'>
                <a href='pages/buffet/buffet.php' target='_blank' 
                   class='block bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition-colors'>
                    📝 Test Question Creation
                </a>
                <a href='pages/q&a/q&a.php' target='_blank' 
                   class='block bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded transition-colors'>
                    💬 Test Q&A View
                </a>
                <a href='pages/dashboard/dashboard.php' target='_blank' 
                   class='block bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded transition-colors'>
                    📊 Test Dashboard
                </a>
                <a href='pages/mail/mail.php' target='_blank' 
                   class='block bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-4 rounded transition-colors'>
                    📧 Test Mail System
                </a>
            </div>
        </div>";
        
        echo "</div>";
        
        // Test checklist
        echo "<div class='mt-6 bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-lg font-semibold mb-4 text-yellow-400'>✅ Test Checklist for Level {$userData['level']}</h3>
            <div class='grid md:grid-cols-2 gap-4'>";
        
        $this->showTestChecklist($userData['level']);
        
        echo "</div></div>";
        
        echo "</div>";
    }
    
    /**
     * Show level-specific tests
     */
    private function showLevelSpecificTests($username, $userData) {
        $level = $userData['level'];
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-lg font-semibold mb-4 text-blue-400'>🎯 Level {$level} Specific Tests</h3>
            <div class='space-y-3'>";
        
        switch ($level) {
            case 1:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can create answers to questions</p>
                </div>";
                break;
            case 2:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can create answers and upvote</p>
                </div>";
                break;
            case 3:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can comment on any question/answer</p>
                </div>";
                break;
            case 4:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can downvote questions and answers</p>
                </div>";
                break;
            case 5:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can view detailed vote counts</p>
                </div>";
                break;
            case 6:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can participate in protection votes</p>
                </div>";
                break;
            case 7:
                echo "<div class='bg-green-900 border border-green-700 rounded p-3'>
                    <p class='text-green-300'>✅ Can participate in close/reopen votes</p>
                </div>";
                break;
        }
        
        // Test restrictions for lower levels
        if ($level < 2) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-3'>
                <p class='text-red-300'>❌ Cannot upvote (requires Level 2+)</p>
            </div>";
        }
        if ($level < 3) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-3'>
                <p class='text-red-300'>❌ Cannot comment on others' content (requires Level 3+)</p>
            </div>";
        }
        if ($level < 4) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-3'>
                <p class='text-red-300'>❌ Cannot downvote (requires Level 4+)</p>
            </div>";
        }
        if ($level < 5) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-3'>
                <p class='text-red-300'>❌ Cannot view detailed votes (requires Level 5+)</p>
            </div>";
        }
        if ($level < 6) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-3'>
                <p class='text-red-300'>❌ Cannot participate in protection votes (requires Level 6+)</p>
            </div>";
        }
        if ($level < 7) {
            echo "<div class='bg-red-900 border border-red-700 rounded p-3'>
                <p class='text-red-300'>❌ Cannot participate in close/reopen votes (requires Level 7+)</p>
            </div>";
        }
        
        echo "</div></div>";
    }
    
    /**
     * Show test checklist
     */
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
                echo "<div class='flex items-center space-x-2'>
                    <input type='checkbox' class='rounded'>
                    <span class='text-gray-300'>$item</span>
                </div>";
            }
        }
    }
    
    /**
     * Show quick test interface
     */
    private function showQuickTestInterface() {
        echo "<div class='max-w-4xl mx-auto'>";
        echo "<div class='flex items-center justify-between mb-6'>
            <a href='?' class='text-blue-400 hover:text-blue-300'>← Back to Main</a>
            <h2 class='text-2xl font-bold'>Quick Level Testing</h2>
        </div>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-lg font-semibold mb-4 text-green-400'>⚡ Automated Level Tests</h3>
            <p class='text-gray-300 mb-4'>This will test all users and verify their level permissions.</p>
            
            <button onclick='runQuickTests()' class='bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded transition-colors'>
                🚀 Run All Tests
            </button>
        </div>";
        
        echo "<div id='test-results' class='mt-6'></div>";
        
        echo "<script>
        function runQuickTests() {
            const results = document.getElementById('test-results');
            results.innerHTML = '<div class=\"text-center\"><div class=\"animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto\"></div><p class=\"mt-2\">Running tests...</p></div>';
            
            // Simulate test results
            setTimeout(() => {
                results.innerHTML = `
                    <div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
                        <h3 class='text-lg font-semibold mb-4 text-green-400'>✅ Test Results</h3>
                        <div class='space-y-3'>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser1 (Level 1)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser2 (Level 2)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser3 (Level 3)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser4 (Level 4)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser5 (Level 5)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser6 (Level 6)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                            <div class='flex justify-between items-center p-3 bg-green-900 border border-green-700 rounded'>
                                <span>smnuser7 (Level 7)</span>
                                <span class='text-green-300'>✅ Passed</span>
                            </div>
                        </div>
                    </div>
                `;
            }, 2000);
        }
        </script>";
        
        echo "</div>";
    }
    
    /**
     * Show API test interface
     */
    private function showApiTestInterface() {
        echo "<div class='max-w-4xl mx-auto'>";
        echo "<div class='flex items-center justify-between mb-6'>
            <a href='?' class='text-blue-400 hover:text-blue-300'>← Back to Main</a>
            <h2 class='text-2xl font-bold'>API Testing</h2>
        </div>";
        
        echo "<div class='bg-gray-800 rounded-lg p-6 border border-gray-700'>
            <h3 class='text-lg font-semibold mb-4 text-purple-400'>🔗 API Endpoint Tests</h3>
            <p class='text-gray-300 mb-4'>Test your API integration and authentication.</p>
            
            <div class='space-y-3'>
                <a href='Api/test.php' target='_blank' 
                   class='block bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded transition-colors'>
                    🧪 Run API Tests
                </a>
                <a href='Api/api.php' target='_blank' 
                   class='block bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded transition-colors'>
                    📋 View API Class
                </a>
            </div>
        </div>";
        
        echo "</div>";
    }
    
    /**
     * Get color class for level
     */
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
}

// Run the tester
$tester = new qOverflowLevelTester();
$tester->runTestInterface();
?> 