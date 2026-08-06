<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    echo "=== Seeding Placeholder Data for GKwillie Club ===\n\n";

    ensure_results_tables_exist($pdo);

    // Diagnostic check of game results winner names for GKwillie
    $diag_stmt = $pdo->query("
        SELECT gr.result_id, gr.winner, m.member_name, m.nickname
        FROM game_results gr
        LEFT JOIN games g ON gr.game_id = g.game_id
        LEFT JOIN members m ON gr.winner = m.member_id
        WHERE g.club_id = (SELECT club_id FROM clubs WHERE slug = 'gkwillie' LIMIT 1)
        LIMIT 5
    ");
    $sample_results = $diag_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample Game Results Winners:\n";
    foreach ($sample_results as $sr) {
        echo "- Result ID {$sr['result_id']}: winner_id={$sr['winner']}, member_name='{$sr['member_name']}', nickname='{$sr['nickname']}'\n";
    }
    echo "\n";

    // 1. Get or Create GKwillie Club
    $stmt = $pdo->prepare("SELECT club_id, club_name FROM clubs WHERE club_name LIKE ? OR slug = ?");
    $stmt->execute(['%GKwillie%', 'gkwillie']);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$club) {
        $stmt = $pdo->prepare("INSERT INTO clubs (club_name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['GKwillie', 'gkwillie', 'Official GKwillie Board Gaming Club - Strategy, Fun & Tabletop Competition!']);
        $club_id = (int)$pdo->lastInsertId();
        echo "Created new club: GKwillie (ID: $club_id)\n";
    } else {
        $club_id = (int)$club['club_id'];
        echo "Found existing club: {$club['club_name']} (ID: $club_id)\n";
    }

    // 2. Add Members for GKwillie
    $member_list = [
        ['Willie G', 'GKwillie', 'willie@example.com'],
        ['Sarah Connor', 'Sarah', 'sarah@example.com'],
        ['Marcus Wright', 'Marcus', 'marcus@example.com'],
        ['Elena Fisher', 'Elena', 'elena@example.com'],
        ['Nathan Drake', 'Nate', 'nate@example.com'],
        ['Victor Sullivan', 'Sully', 'sully@example.com'],
        ['Chloe Frazer', 'Chloe', 'chloe@example.com'],
        ['Samuel Drake', 'Sam', 'sam@example.com'],
        ['Lara Croft', 'Lara', 'lara@example.com'],
        ['Bruce Wayne', 'Batman', 'bruce@example.com']
    ];

    // Check if admin_id is required in members table
    $has_admin_id = false;
    try {
        $m_cols = $pdo->query("SHOW COLUMNS FROM members LIKE 'admin_id'")->fetch();
        if ($m_cols) {
            $has_admin_id = true;
        }
    } catch (Throwable $e) {}

    $admin_id = 1;
    if ($has_admin_id) {
        try {
            $a_stmt = $pdo->query("SELECT admin_id FROM club_admins WHERE club_id = " . (int)$club_id . " LIMIT 1");
            if ($a_stmt && ($val = $a_stmt->fetchColumn())) {
                $admin_id = (int)$val;
            }
        } catch (Throwable $e) {}
    }

    $member_ids = [];
    foreach ($member_list as $m) {
        $stmt = $pdo->prepare("SELECT member_id FROM members WHERE club_id = ? AND (nickname = ? OR member_name = ?)");
        $stmt->execute([$club_id, $m[1], $m[0]]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $member_ids[] = (int)$existing;
        } else {
            if ($has_admin_id) {
                $stmt = $pdo->prepare("INSERT INTO members (club_id, admin_id, member_name, nickname, email, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$club_id, $admin_id, $m[0], $m[1], $m[2]]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO members (club_id, member_name, nickname, email, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$club_id, $m[0], $m[1], $m[2]]);
            }
            $member_ids[] = (int)$pdo->lastInsertId();
        }
    }
    echo "Ensured " . count($member_ids) . " members in club.\n";

    // 3. Add Teams for GKwillie and link member IDs
    $teams_list = [
        ['Meeple Masters', [$member_ids[0], $member_ids[1]]],
        ['Dice Rollers', [$member_ids[2], $member_ids[3]]],
        ['Card Sharks', [$member_ids[4], $member_ids[5]]],
        ['Board Knights', [$member_ids[6], $member_ids[7]]]
    ];

    $has_team_members_tbl = false;
    try {
        $pdo->query("SELECT 1 FROM team_members LIMIT 1");
        $has_team_members_tbl = true;
    } catch (Throwable $e) {}

    $team_ids = [];
    foreach ($teams_list as $t_info) {
        $team_name = $t_info[0];
        $m_ids = $t_info[1];
        
        $stmt = $pdo->prepare("SELECT team_id FROM teams WHERE club_id = ? AND team_name = ?");
        $stmt->execute([$club_id, $team_name]);
        $existing = $stmt->fetchColumn();
        
        if ($existing) {
            $t_id = (int)$existing;
            $team_ids[] = $t_id;
            // Update member columns on existing team
            try {
                $stmt = $pdo->prepare("UPDATE teams SET member1_id = ?, member2_id = ? WHERE team_id = ?");
                $stmt->execute([$m_ids[0], $m_ids[1], $t_id]);
            } catch (Throwable $e) {}
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO teams (club_id, team_name, member1_id, member2_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$club_id, $team_name, $m_ids[0], $m_ids[1]]);
            } catch (Throwable $e) {
                $stmt = $pdo->prepare("INSERT INTO teams (club_id, team_name, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$club_id, $team_name]);
            }
            $t_id = (int)$pdo->lastInsertId();
            $team_ids[] = $t_id;
        }

        // Ensure team_members rows exist
        if ($has_team_members_tbl && $t_id) {
            foreach ($m_ids as $m_id) {
                try {
                    $chk = $pdo->prepare("SELECT 1 FROM team_members WHERE team_id = ? AND member_id = ?");
                    $chk->execute([$t_id, $m_id]);
                    if (!$chk->fetchColumn()) {
                        $ins = $pdo->prepare("INSERT INTO team_members (team_id, member_id) VALUES (?, ?)");
                        $ins->execute([$t_id, $m_id]);
                    }
                } catch (Throwable $e) {}
            }
        }
    }
    echo "Ensured " . count($team_ids) . " fully linked teams in club.\n";

    // 4. Add 10 Games for GKwillie
    $games_list = [
        ['Catan', 'Settle the island of Catan through trading, building, and strategy.', 3, 4],
        ['Ticket to Ride', 'Cross-country train adventure board game.', 2, 5],
        ['Wingspan', 'Engine-building board game about bird enthusiasts.', 1, 5],
        ['Carcassonne', 'Tile-placement game building medieval French landscape.', 2, 5],
        ['Azul', 'Drafting beautiful mosaic tiles to decorate the Royal Palace of Evora.', 2, 4],
        ['Pandemic', 'Cooperative game where players work together to cure global diseases.', 2, 4],
        ['7 Wonders', 'Lead an ancient city and construct architectural wonders.', 3, 7],
        ['Terraforming Mars', 'Corporate terraforming of Mars with resource management.', 1, 5],
        ['Splendor', 'Fast-paced gem-trading card game.', 2, 4],
        ['Dominion', 'Deck-building strategy card game.', 2, 4]
    ];

    $game_ids = [];
    foreach ($games_list as $g) {
        $stmt = $pdo->prepare("SELECT game_id FROM games WHERE club_id = ? AND game_name = ?");
        $stmt->execute([$club_id, $g[0]]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            $game_ids[] = (int)$existing;
        } else {
            $stmt = $pdo->prepare("INSERT INTO games (club_id, game_name, min_players, max_players, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$club_id, $g[0], $g[2], $g[3]]);
            $game_ids[] = (int)$pdo->lastInsertId();
        }
    }
    echo "Ensured " . count($game_ids) . " games in club.\n";

    // 5. Generate 12+ Plays for each Game over the past year (365 days)
    $now = time();
    $seconds_in_year = 365 * 86400;
    $total_results_inserted = 0;

    foreach ($game_ids as $index => $g_id) {
        $num_plays = rand(12, 16);
        for ($p = 0; $p < $num_plays; $p++) {
            $offset_seconds = rand(86400, $seconds_in_year);
            $play_time = date('Y-m-d H:i:s', $now - $offset_seconds);
            $duration = rand(30, 120);

            $game_name = $games_list[$index][0];
            $type = 'individual';
            if ($game_name === 'Pandemic') {
                $type = rand(0, 1) === 0 ? 'cooperative' : 'individual';
            } elseif ($p % 4 === 0 && !empty($team_ids)) {
                $type = 'team';
            }

            if ($type === 'individual') {
                $winner_id = $member_ids[array_rand($member_ids)];
                $session_id = 'sess_' . uniqid();
                $notes = "Great play of $game_name!";

                $stmt = $pdo->prepare("INSERT INTO game_results (game_id, session_id, member_id, winner, played_at, duration, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$g_id, $session_id, $winner_id, $winner_id, $play_time, $duration, $notes]);
                $total_results_inserted++;

            } elseif ($type === 'team') {
                $winner_team_id = $team_ids[array_rand($team_ids)];
                $session_id = 'team_' . uniqid();
                $notes = "Exciting team match of $game_name!";

                $stmt = $pdo->prepare("INSERT INTO team_game_results (game_id, session_id, team_id, winner, position, played_at, duration, notes, num_teams) VALUES (?, ?, ?, ?, 1, ?, ?, ?, 2)");
                $stmt->execute([$g_id, $session_id, $winner_team_id, $winner_team_id, $play_time, $duration, $notes]);
                $total_results_inserted++;

            } elseif ($type === 'cooperative') {
                $outcome = rand(0, 2) > 0 ? 'win' : 'loss';
                $session_id = 'coop_' . uniqid();
                $notes = "Co-op campaign of $game_name - Outcome: " . strtoupper($outcome);

                $stmt = $pdo->prepare("INSERT INTO cooperative_game_results (game_id, session_id, outcome, score, difficulty, scenario, num_participants, played_at, duration, notes) VALUES (?, ?, ?, ?, 'Normal', 'Main Scenario', 4, ?, ?, ?)");
                $stmt->execute([$g_id, $session_id, $outcome, rand(50, 100), $play_time, $duration, $notes]);
                $total_results_inserted++;
            }
        }

        // Repair any orphaned/null winner records for this game
        $repair_stmt = $pdo->prepare("
            UPDATE game_results 
            SET winner = ?, member_id = ?
            WHERE game_id = ? AND (winner IS NULL OR winner NOT IN (SELECT member_id FROM members WHERE club_id = ?))
        ");
        $random_valid_winner = $member_ids[array_rand($member_ids)];
        $repair_stmt->execute([$random_valid_winner, $random_valid_winner, $g_id, $club_id]);
    }

    echo "Inserted $total_results_inserted total game results across 10 games.\n";

    // 6. Add Champions for GKwillie
    $champ_cols = $pdo->query("DESCRIBE champions")->fetchAll(PDO::FETCH_ASSOC);
    $c_field_names = array_column($champ_cols, 'Field');
    $date_col = in_array('date', $c_field_names) ? 'date' : (in_array('start_date', $c_field_names) ? 'start_date' : 'created_at');

    $champions_list = [
        [$member_ids[0], date('Y-m-d', strtotime('-11 months')), 'GKwillie Club Inaugural Champion'],
        [$member_ids[1], date('Y-m-d', strtotime('-9 months')), 'Autumn 2025 Catan Master'],
        [$member_ids[3], date('Y-m-d', strtotime('-6 months')), 'Winter 2025 Wingspan Champion'],
        [$member_ids[4], date('Y-m-d', strtotime('-4 months')), 'Spring 2026 Ticket to Ride Champion'],
        [$member_ids[0], date('Y-m-d', strtotime('-1 months')), 'Summer 2026 Grand Tabletop Champion']
    ];

    $champions_inserted = 0;
    foreach ($champions_list as $champ) {
        $stmt = $pdo->prepare("SELECT id FROM champions WHERE club_id = ? AND member_id = ?");
        $stmt->execute([$club_id, $champ[0]]);
        if (!$stmt->fetchColumn()) {
            if (in_array('champ_comments', $c_field_names)) {
                $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, {$date_col}, champ_comments) VALUES (?, ?, ?, ?)");
                $stmt->execute([$club_id, $champ[0], $champ[1], $champ[2]]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, {$date_col}) VALUES (?, ?, ?)");
                $stmt->execute([$club_id, $champ[0], $champ[1]]);
            }
            $champions_inserted++;
        }
    }
    $total_members = $pdo->query("SELECT COUNT(*) FROM members WHERE club_id = $club_id")->fetchColumn();
    $total_games = $pdo->query("SELECT COUNT(*) FROM games WHERE club_id = $club_id")->fetchColumn();
    
    $total_results = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM game_results gr JOIN games g ON gr.game_id = g.game_id WHERE g.club_id = $club_id) +
            (SELECT COUNT(*) FROM team_game_results tgr JOIN games g ON tgr.game_id = g.game_id WHERE g.club_id = $club_id) +
            (SELECT COUNT(*) FROM cooperative_game_results cgr JOIN games g ON cgr.game_id = g.game_id WHERE g.club_id = $club_id)
    ")->fetchColumn();

    $total_champions = $pdo->query("SELECT COUNT(*) FROM champions WHERE club_id = $club_id")->fetchColumn();

    echo "========================================\n";
    echo "GKwillie Club Data Summary:\n";
    echo "- Members: $total_members\n";
    echo "- Games: $total_games\n";
    echo "- Total Results Recorded: $total_results\n";
    echo "- Champions Entries: $total_champions\n";
    echo "========================================\n";
    echo "SUCCESS: Seeding completed for GKwillie club!\n";

} catch (Throwable $ex) {
    echo "ERROR: " . $ex->getMessage() . "\nFile: " . $ex->getFile() . " (Line " . $ex->getLine() . ")\n";
}

