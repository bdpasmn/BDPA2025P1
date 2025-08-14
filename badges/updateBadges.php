<?php 
    function updateBadges($username) {
        $api = new qOverflowAPI(API_KEY);
        $userData = $api->getUser($username);

        $points = $userData['user']['points'];
        $questions = $api->getUserQuestions($username);
        $answers = $api->getUserAnswers($username);

        global $pdo;

        $existingBadges = getUserBadges($pdo, $username);
        $newBadges = [];
        $badgesToRestore = [];

        // Question Badges - Check if they should exist and restore if missing
        foreach ($questions['questions'] as $q) {
            $votes = $q['upvotes'] - $q['downvotes'];

            if ($votes >= 10 && !in_array('Nice Question', $existingBadges)) {
                $badgesToRestore[] = ['badge_name' => 'Nice Question', 'tier' => 'bronze'];
            }
    
            if ($votes >= 25 && !in_array('Good Question', $existingBadges)) {
                $badgesToRestore[] = ['badge_name' => 'Good Question', 'tier' => 'silver'];
            }

            if ($votes >= 100 && !in_array('Great Question', $existingBadges)) {
                $badgesToRestore[] = ['badge_name' => 'Great Question', 'tier' => 'gold'];
            }
        }

        // Answer Badges - Check if they should exist and restore if missing
        foreach ($answers['answers'] as $a) {
            $votes = ($a['upvotes'] ?? 0) - ($a['downvotes'] ?? 0);

            if ($votes >= 10 && !in_array('Nice Answer', $existingBadges)) {
                $badgesToRestore[] = ['badge_name' => 'Nice Answer', 'tier' => 'bronze'];
            }

            if ($votes >= 25 && !in_array('Good Answer', $existingBadges)) {
                $badgesToRestore[] = ['badge_name' => 'Good Answer', 'tier' => 'silver'];
            }

            if ($votes >= 100 && !in_array('Great Answer', $existingBadges)) {
                $badgesToRestore[] = ['badge_name' => 'Great Answer', 'tier' => 'gold'];
            }
        }
        
        // Point Badges - Check if they should exist and restore if missing
        if ($points >= 100 && !in_array('Curious', $existingBadges)) {
            $badgesToRestore[] = ['badge_name' => 'Curious', 'tier' => 'bronze'];
        }

        if ($points >= 3000 && !in_array('Inquisitive', $existingBadges)) {
            $badgesToRestore[] = ['badge_name' => 'Inquisitive', 'tier' => 'silver'];
        }

        if ($points >= 10000 && !in_array('Socratic', $existingBadges)) {
            $badgesToRestore[] = ['badge_name' => 'Socratic', 'tier' => 'gold'];
        }

        // Status Badges - Check if they should exist and restore if missing
        if (!in_array('Scholar', $existingBadges)) {
            foreach ($questions['questions'] as $q) {
                if ($q['hasAcceptedAnswer']) {
                    $badgesToRestore[] = ['badge_name' => 'Scholar', 'tier' => 'bronze'];
                    break;
                }
            }
        }

        if (!in_array('Protected', $existingBadges)) {
            foreach ($questions['questions'] as $q) {
                if ($q['status'] == 'protected') {
                    $badgesToRestore[] = ['badge_name' => 'Protected', 'tier' => 'silver'];
                    break;
                }
            }
        }        

        // Insert all missing badges (both new and restored)
        if (!empty($badgesToRestore)) {
            $insertStmt = $pdo->prepare("INSERT INTO user_badges (username, badge_name, tier) VALUES (?, ?, ?)");
            foreach ($badgesToRestore as $badge) {
                try {
                    $insertStmt->execute([$username, $badge['badge_name'], $badge['tier']]);
                } catch (PDOException $e) {
                    // Ignore duplicate key errors (badge already exists)
                    if ($e->getCode() != '23505') { // PostgreSQL duplicate key error code
                        error_log("Error inserting badge: " . $e->getMessage());
                    }
                }
            }
        }

        return $badgesToRestore;
    }

    function getUserBadges(PDO $pdo, $username): array {
        $stmt = $pdo->prepare("
            SELECT badge_name
            FROM user_badges
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
?>