<?php 
// Dependencies are included by parent files to avoid conflicts
// require_once __DIR__ . '/../Api/key.php';
// require_once __DIR__ . '/../Api/api.php';

function updateBadges($username) {
        global $pdo;
        
        $api = new qOverflowAPI(API_KEY);
        $userData = $api->getUser($username);

        $points = $userData['user']['points'];
        $questions = $api->getUserQuestions($username);
        $answers = $api->getUserAnswers($username);

        $existingBadges = getUserBadges($pdo, $username);
        $newBadges = [];

        // Question Badges
        foreach ($questions['questions'] as $q) {
            $votes = $q['upvotes'] - $q['downvotes'];

            if (!in_array('Nice Question', array_column($existingBadges, 'badge_name')) && $votes >= 10) {
                $newBadges[] = ['badge_name' => 'Nice Question', 'tier' => 'bronze'];
                break;
            }
    
            if (!in_array('Good Question', array_column($existingBadges, 'badge_name')) && $votes >= 25) {
                $newBadges[] = ['badge_name' => 'Good Question', 'tier' => 'silver'];
                break;
            }

            if (!in_array('Great Question', array_column($existingBadges, 'badge_name')) && $votes >= 100) {
                $newBadges[] = ['badge_name' => 'Great Question', 'tier' => 'gold'];
                break;
            }
        }

        // Answer Badges
        foreach ($answers['answers'] as $a) {
            $votes = ($a['upvotes'] ?? 0) - ($a['downvotes'] ?? 0);

            if (!in_array('Nice Answer', array_column($existingBadges, 'badge_name')) && $votes >= 10) {
                $newBadges[] = ['badge_name' => 'Nice Answer', 'tier' => 'bronze'];
                break;
            }

            if (!in_array('Good Answer', array_column($existingBadges, 'badge_name')) && $votes >= 25) {
                $newBadges[] = ['badge_name' => 'Good Answer', 'tier' => 'silver'];
                break;
            }

            if (!in_array('Great Answer', array_column($existingBadges, 'badge_name')) && $votes >= 100) {
                $newBadges[] = ['badge_name' => 'Great Answer', 'tier' => 'gold'];
                break;
            }
        }
        
        // Point Badges
        if (!in_array('Curious', array_column($existingBadges, 'badge_name')) && $points >= 100) {
            $newBadges[] = ['badge_name' => 'Curious', 'tier' => 'bronze'];
        }

        if (!in_array('Inquisitive', array_column($existingBadges, 'badge_name')) && $points >= 3000) {
            $newBadges[] = ['badge_name' => 'Inquisitive', 'tier' => 'silver'];
        }

        if (!in_array('Socratic', array_column($existingBadges, 'badge_name')) && $points >= 10000) {
            $newBadges[] = ['badge_name' => 'Socratic', 'tier' => 'gold'];
        }

        // Status Badges
        if (!in_array('Scholar', array_column($existingBadges, 'badge_name'))) {
            foreach ($questions['questions'] as $q) {
                if ($q['hasAcceptedAnswer']) {
                    $newBadges[] = ['badge_name' => 'Scholar', 'tier' => 'bronze'];
                    break;
                }
            }
        }

        if (!in_array('Protected', array_column($existingBadges, 'badge_name'))) {
            foreach ($questions['questions'] as $q) {
                if ($q['status'] == 'protected') {
                    $newBadges[] = ['badge_name' => 'Protected', 'tier' => 'silver'];
                    break;
                }
            }
        }        

        // Insert new badges into database
        foreach ($newBadges as $badge) {
            try {
                $insertStmt = $pdo->prepare("INSERT INTO user_badges (username, badge_name, tier) VALUES (?, ?, ?)");
                $insertStmt->execute([$username, $badge['badge_name'], $badge['tier']]);
            } catch (PDOException $e) {
                error_log("Error inserting badge: " . $e->getMessage());
            }
        }

        // Return updated badge list
        return getUserBadges($pdo, $username);
    }

    function getUserBadges(PDO $pdo, $username): array {
        try {
            $stmt = $pdo->prepare("
                SELECT badge_name, tier
                FROM user_badges
                WHERE username = ?
            ");
            $stmt->execute([$username]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching badges: " . $e->getMessage());
            return [];
        }
    }
?>