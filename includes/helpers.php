<?php
/**
 * Helper Functions
 *
 * Common utility functions used throughout the application
 */

/**
 * Safely escape HTML output
 *
 * @param string $string The string to escape
 * @return string The escaped string
 */
function esc_html($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Display and clear a session message
 *
 * @param string $key Session key (e.g., 'success', 'error')
 * @param string $class CSS class for the message (default: uses key as class name)
 * @return void
 */
function display_session_message($key, $class = null) {
    if (isset($_SESSION[$key])) {
        $class = $class ?? $key; // Use key as class if not provided
        echo '<div class="message message--' . esc_html($class) . '">' . esc_html($_SESSION[$key]) . '</div>';
        unset($_SESSION[$key]);
    }
}

/**
 * Get and clear a session message (returns the message instead of echoing)
 *
 * @param string $key Session key
 * @return string|null The message or null if not set
 */
function get_session_message($key) {
    if (isset($_SESSION[$key])) {
        $message = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $message;
    }
    return null;
}

/**
 * Get full image URL for a game image
 *
 * @param string|null $image Game image filename or full URL
 * @param string $prefix Path prefix for local images (e.g., '' or '../')
 * @return string
 */
function get_demo_data($key = null) {
    static $demoData = null;
    if ($demoData === null) {
        $file = __DIR__ . '/../data/demo_data.json';
        $demoData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    }
    return $key ? ($demoData[$key] ?? []) : $demoData;
}

function get_game_image_url($image, $prefix = '') {
    if (empty($image)) {
        return '';
    }
    if (preg_match('~^https?://~i', $image)) {
        return $image;
    }
    return $prefix . 'images/game_images/' . $image;
}

/**
 * Ensures the 'game_image' column exists in the 'games' table
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_game_image_column_exists($pdo) {
    static $checked = false;
    if ($checked || !$pdo) {
        return;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM games LIKE 'game_image'");
        if ($stmt && $stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE games ADD COLUMN game_image VARCHAR(500) DEFAULT NULL");
        }
        $checked = true;
    } catch (Throwable $e) {
        // Silently fail if table issue or lacking permissions
    }
}

/**
 * Ensures the 'game_type' column exists in the 'games' table
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_game_type_column_exists($pdo) {
    static $checked = false;
    if ($checked || !$pdo) {
        return;
    }
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM games LIKE 'game_type'");
        if ($stmt && $stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE games ADD COLUMN game_type VARCHAR(50) NOT NULL DEFAULT 'winner_losers'");
        }
        $checked = true;
    } catch (Throwable $e) {
        // Silently fail if table issue or lacking permissions
    }
}

/**
 * Ensures all result-related tables exist in the database
 *
 * @param PDO $pdo
 * @return void
 */
function ensure_results_tables_exist($pdo) {
    static $checked = false;
    if ($checked || !$pdo || $pdo->inTransaction()) {
        return;
    }
    $checked = true;

    try {
        $pdo->query("SELECT 1 FROM game_result_losers LIMIT 1");
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS game_result_losers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                result_id INT NOT NULL,
                member_id INT NOT NULL,
                FOREIGN KEY (result_id) REFERENCES game_results(result_id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }

    try {
        $pdo->query("SELECT 1 FROM team_game_results LIMIT 1");
        try {
            $pdo->exec("ALTER TABLE team_game_results MODIFY COLUMN team_id INT DEFAULT NULL");
        } catch (Throwable $e3) {}
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS team_game_results (
                result_id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                session_id VARCHAR(255) DEFAULT NULL,
                team_id INT DEFAULT NULL,
                position INT DEFAULT NULL,
                played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                duration INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                num_teams INT DEFAULT NULL,
                winner INT DEFAULT NULL,
                place_2 INT DEFAULT NULL,
                place_3 INT DEFAULT NULL,
                place_4 INT DEFAULT NULL,
                place_5 INT DEFAULT NULL,
                place_6 INT DEFAULT NULL,
                place_7 INT DEFAULT NULL,
                place_8 INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }

    try {
        $pdo->query("SELECT 1 FROM cooperative_game_results LIMIT 1");
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cooperative_game_results (
                result_id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                session_id VARCHAR(255) NOT NULL,
                outcome ENUM('win','loss') NOT NULL,
                score INT DEFAULT NULL,
                difficulty VARCHAR(100) DEFAULT NULL,
                scenario VARCHAR(255) DEFAULT NULL,
                num_participants INT DEFAULT NULL,
                played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                duration INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(game_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS cooperative_result_participants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                result_id INT NOT NULL,
                participant_type ENUM('member','team') NOT NULL,
                member_id INT DEFAULT NULL,
                team_id INT DEFAULT NULL,
                FOREIGN KEY (result_id) REFERENCES cooperative_game_results(result_id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
                FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }

    try {
        $pdo->query("SELECT 1 FROM team_members LIMIT 1");
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS team_members (
                team_id INT NOT NULL,
                member_id INT NOT NULL,
                PRIMARY KEY (team_id, member_id),
                FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        } catch (Throwable $e2) {}
    }
}

/**
 * Saves team members to team_members junction table and updates member1_id..member4_id columns for legacy support.
 */
function save_team_members($pdo, int $team_id, array $member_ids): void {
    if (!$pdo || $team_id <= 0) return;
    ensure_results_tables_exist($pdo);

    $member_ids = array_values(array_unique(array_filter(array_map('intval', $member_ids))));

    $m1 = isset($member_ids[0]) ? (int)$member_ids[0] : null;
    $m2 = isset($member_ids[1]) ? (int)$member_ids[1] : null;
    $m3 = isset($member_ids[2]) ? (int)$member_ids[2] : null;
    $m4 = isset($member_ids[3]) ? (int)$member_ids[3] : null;

    try {
        $stmt = $pdo->prepare("UPDATE teams SET member1_id = ?, member2_id = ?, member3_id = ?, member4_id = ? WHERE team_id = ?");
        $stmt->execute([$m1, $m2, $m3, $m4, $team_id]);
    } catch (Throwable $e) {}

    try {
        $stmtDel = $pdo->prepare("DELETE FROM team_members WHERE team_id = ?");
        $stmtDel->execute([$team_id]);

        if (!empty($member_ids)) {
            $stmtIns = $pdo->prepare("INSERT IGNORE INTO team_members (team_id, member_id) VALUES (?, ?)");
            foreach ($member_ids as $mid) {
                if ($mid > 0) {
                    $stmtIns->execute([$team_id, $mid]);
                }
            }
        }
    } catch (Throwable $e) {}
}

/**
 * Gets array of member_ids for a team (supporting dynamic N members).
 */
function get_team_member_ids($pdo, int $team_id): array {
    if (!$pdo || $team_id <= 0) return [];
    ensure_results_tables_exist($pdo);

    try {
        $stmt = $pdo->prepare("SELECT member_id FROM team_members WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            return array_map('intval', $ids);
        }
    } catch (Throwable $e) {}

    try {
        $stmt = $pdo->prepare("SELECT member1_id, member2_id, member3_id, member4_id FROM teams WHERE team_id = ?");
        $stmt->execute([$team_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return array_values(array_filter(array_map('intval', [$row['member1_id'] ?? 0, $row['member2_id'] ?? 0, $row['member3_id'] ?? 0, $row['member4_id'] ?? 0])));
        }
    } catch (Throwable $e) {}

    return [];
}

/**
 * Gets a map of team_id => array of member objects for multiple teams.
 */
function get_team_members_map($pdo, array $teams): array {
    if (empty($teams) || !$pdo) return [];
    ensure_results_tables_exist($pdo);

    $teamIds = array_values(array_filter(array_map(function($t) {
        return (int)($t['team_id'] ?? 0);
    }, $teams)));

    if (empty($teamIds)) return [];

    $map = [];
    try {
        $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
        $sql = "
            SELECT tm.team_id, m.member_id, COALESCE(NULLIF(m.nickname, ''), m.member_name, 'Member') as nickname
            FROM team_members tm
            JOIN members m ON tm.member_id = m.member_id
            WHERE tm.team_id IN ($placeholders)
            ORDER BY m.nickname ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($teamIds);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $map[(int)$row['team_id']][] = [
                'member_id' => (int)$row['member_id'],
                'nickname' => $row['nickname']
            ];
        }
    } catch (Throwable $e) {}

    foreach ($teams as $t) {
        $tid = (int)($t['team_id'] ?? 0);
        if ($tid > 0 && empty($map[$tid])) {
            $mList = [];
            for ($i = 1; $i <= 4; $i++) {
                $nickKey = "member{$i}_nickname";
                $idKey = "member{$i}_id";
                if (!empty($t[$nickKey])) {
                    $mList[] = [
                        'member_id' => (int)($t[$idKey] ?? 0),
                        'nickname' => $t[$nickKey]
                    ];
                }
            }
            if (!empty($mList)) {
                $map[$tid] = $mList;
            }
        }
    }

    return $map;
}

/**
 * Verify logged in admin password for sensitive operations (e.g. bulk delete)
 *
 * @param string $password
 * @param PDO $pdo
 * @return bool
 */
function verify_admin_password($password, $pdo) {
    if (empty($password) || !$pdo) {
        return false;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['admin_id'])) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $hash = $stmt->fetchColumn();
        return $hash && password_verify($password, $hash);
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Safely delete a member and clean up dependent references.
 *
 * @param PDO $pdo
 * @param int $member_id
 * @param int|null $club_id
 * @return bool
 */
function delete_member_by_id($pdo, $member_id, $club_id = null) {
    $member_id = (int)$member_id;
    if ($member_id <= 0 || !$pdo) {
        return false;
    }

    try {
        if ($club_id !== null && (int)$club_id > 0) {
            $check = $pdo->prepare("SELECT club_id FROM members WHERE member_id = ? AND club_id = ?");
            $check->execute([$member_id, (int)$club_id]);
            if (!$check->fetch()) {
                return false;
            }
        }

        // Clean up references in child and linked tables
        try { $pdo->prepare("DELETE FROM champions WHERE member_id = ?")->execute([$member_id]); } catch (Throwable $e) {}
        try { $pdo->prepare("DELETE FROM team_members WHERE member_id = ?")->execute([$member_id]); } catch (Throwable $e) {}
        try { $pdo->prepare("DELETE FROM game_result_losers WHERE member_id = ?")->execute([$member_id]); } catch (Throwable $e) {}
        try { $pdo->prepare("DELETE FROM cooperative_result_participants WHERE member_id = ?")->execute([$member_id]); } catch (Throwable $e) {}

        try {
            $pdo->prepare("UPDATE teams SET member1_id = NULL WHERE member1_id = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE teams SET member2_id = NULL WHERE member2_id = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE teams SET member3_id = NULL WHERE member3_id = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE teams SET member4_id = NULL WHERE member4_id = ?")->execute([$member_id]);
        } catch (Throwable $e) {}

        try {
            $pdo->prepare("UPDATE game_results SET winner = NULL WHERE winner = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET member_id = NULL WHERE member_id = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_2 = NULL WHERE place_2 = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_3 = NULL WHERE place_3 = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_4 = NULL WHERE place_4 = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_5 = NULL WHERE place_5 = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_6 = NULL WHERE place_6 = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_7 = NULL WHERE place_7 = ?")->execute([$member_id]);
            $pdo->prepare("UPDATE game_results SET place_8 = NULL WHERE place_8 = ?")->execute([$member_id]);
        } catch (Throwable $e) {}

        $stmt = $pdo->prepare("DELETE FROM members WHERE member_id = ?");
        $stmt->execute([$member_id]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log("Failed to delete member {$member_id}: " . $e->getMessage());
        return false;
    }
}

