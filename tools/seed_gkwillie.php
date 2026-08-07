<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    echo "=== Seeding Plausible Data for GKwillie Club(s) ===\n\n";

    ensure_results_tables_exist($pdo);

    // Get all clubs matching GKwillie or Willie
    $stmt = $pdo->query("SELECT club_id, club_name FROM clubs WHERE club_name LIKE '%GKwillie%' OR club_name LIKE '%Willie%' OR slug = 'gkwillie'");
    $target_clubs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($target_clubs)) {
        $stmt = $pdo->prepare("INSERT INTO clubs (club_name, slug, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['GKwillie', 'gkwillie', 'Official GKwillie Board Gaming Club - Strategy, Fun & Tabletop Competition!']);
        $target_clubs = [['club_id' => (int)$pdo->lastInsertId(), 'club_name' => 'GKwillie']];
    }

    foreach ($target_clubs as $c) {
        $club_id = (int)$c['club_id'];
        echo "--- Processing Club ID {$club_id}: {$c['club_name']} ---\n";

        // 1. Completely purge old results, champions, teams, games, and members for this club to start 100% fresh & clean!
        $pdo->exec("DELETE FROM game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = $club_id)");
        $pdo->exec("DELETE FROM team_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = $club_id)");
        $pdo->exec("DELETE FROM cooperative_result_participants WHERE result_id IN (SELECT result_id FROM cooperative_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = $club_id))");
        $pdo->exec("DELETE FROM cooperative_game_results WHERE game_id IN (SELECT game_id FROM games WHERE club_id = $club_id)");
        $pdo->exec("DELETE FROM champions WHERE club_id = $club_id");
        try { $pdo->exec("DELETE FROM team_members WHERE team_id IN (SELECT team_id FROM teams WHERE club_id = $club_id)"); } catch (Throwable $e) {}
        $pdo->exec("DELETE FROM teams WHERE club_id = $club_id");
        $pdo->exec("DELETE FROM games WHERE club_id = $club_id");
        $pdo->exec("DELETE FROM members WHERE club_id = $club_id");

        // 2. Add 10 Members for the club
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
                $a_stmt = $pdo->query("SELECT admin_id FROM club_admins WHERE club_id = $club_id LIMIT 1");
                if ($a_stmt && ($val = $a_stmt->fetchColumn())) {
                    $admin_id = (int)$val;
                }
            } catch (Throwable $e) {}
        }

        $member_ids = [];
        foreach ($member_list as $m) {
            if ($has_admin_id) {
                $stmt = $pdo->prepare("INSERT INTO members (club_id, admin_id, member_name, nickname, email, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$club_id, $admin_id, $m[0], $m[1], $m[2]]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO members (club_id, member_name, nickname, email, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                $stmt->execute([$club_id, $m[0], $m[1], $m[2]]);
            }
            $member_ids[] = (int)$pdo->lastInsertId();
        }
        echo "Created " . count($member_ids) . " fresh members.\n";

        // 3. Add 5 Teams
        $teams_list = [
            ['Meeple Masters', [$member_ids[0], $member_ids[1]]],
            ['Dice Rollers', [$member_ids[2], $member_ids[3]]],
            ['Card Sharks', [$member_ids[4], $member_ids[5]]],
            ['Board Knights', [$member_ids[6], $member_ids[7]]],
            ['Shadow Alliance', [$member_ids[8], $member_ids[9]]]
        ];

        $team_ids = [];
        foreach ($teams_list as $t_info) {
            $team_name = $t_info[0];
            $m_ids = $t_info[1];
            try {
                $stmt = $pdo->prepare("INSERT INTO teams (club_id, team_name, member1_id, member2_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$club_id, $team_name, $m_ids[0], $m_ids[1]]);
            } catch (Throwable $e) {
                $stmt = $pdo->prepare("INSERT INTO teams (club_id, team_name, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$club_id, $team_name]);
            }
            $team_ids[] = (int)$pdo->lastInsertId();
        }
        echo "Created " . count($team_ids) . " teams.\n";

        // 4. Add 10 Games (8 Individual, 1 Cooperative, 1 Team)
        $games_list = [
            ['Catan', 'Settle the island of Catan through trading, building, and strategy.', 3, 4, 'individual'],
            ['Ticket to Ride', 'Cross-country train adventure board game.', 2, 5, 'individual'],
            ['Wingspan', 'Engine-building board game about bird enthusiasts.', 1, 5, 'individual'],
            ['Carcassonne', 'Tile-placement game building medieval French landscape.', 2, 5, 'individual'],
            ['Azul', 'Drafting beautiful mosaic tiles to decorate the Royal Palace of Evora.', 2, 4, 'individual'],
            ['7 Wonders', 'Lead an ancient city and construct architectural wonders.', 3, 7, 'individual'],
            ['Terraforming Mars', 'Corporate terraforming of Mars with resource management.', 1, 5, 'individual'],
            ['Splendor', 'Fast-paced gem-trading card game.', 2, 4, 'individual'],
            ['Pandemic', 'Cooperative game where players work together to cure global diseases.', 2, 4, 'cooperative'],
            ['Catan: Team Battles', '2v2 team variant of Catan.', 2, 4, 'team']
        ];

        $game_ids = [];
        foreach ($games_list as $g) {
            $stmt = $pdo->prepare("INSERT INTO games (club_id, game_name, min_players, max_players, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$club_id, $g[0], $g[2], $g[3]]);
            $game_ids[] = (int)$pdo->lastInsertId();
        }
        echo "Created " . count($game_ids) . " games.\n";

        // 5. Generate 14-16 plays per game (10-20 range per user requirement)
        // Ensure ALL 10 members get roughly the same total number of plays (~55-60 plays each)
        $member_play_counts = array_fill_keys($member_ids, 0);
        $now = time();
        $seconds_in_year = 365 * 86400;
        $results_count = 0;

        foreach ($game_ids as $index => $g_id) {
            $game_name = $games_list[$index][0];
            $game_type = $games_list[$index][4];
            $num_plays = rand(14, 16); // 10~20 plays per game

            for ($p = 0; $p < $num_plays; $p++) {
                $offset_seconds = (int)(($seconds_in_year / $num_plays) * ($num_plays - $p)) + rand(-43200, 43200);
                $play_time = date('Y-m-d H:i:s', $now - max(86400, $offset_seconds));
                $duration = rand(35, 90);
                $session_id = 'sess_' . $g_id . '_' . $p . '_' . uniqid();

                if ($game_type === 'individual') {
                    // Pick 4 players with the lowest play count to keep all members balanced!
                    $sorted_members = $member_ids;
                    usort($sorted_members, function($a, $b) use ($member_play_counts) {
                        $diff = $member_play_counts[$a] - $member_play_counts[$b];
                        return $diff !== 0 ? $diff : rand(-1, 1);
                    });
                    $participants = array_slice($sorted_members, 0, 4);
                    shuffle($participants); // Placed differently in each game

                    foreach ($participants as $p_id) {
                        $member_play_counts[$p_id]++;
                    }

                    $w1 = $participants[0];
                    $p2 = $participants[1];
                    $p3 = $participants[2];
                    $p4 = $participants[3];

                    $notes = "Match of $game_name";
                    $stmt = $pdo->prepare("
                        INSERT INTO game_results (game_id, session_id, member_id, position, played_at, duration, notes, num_players, winner, place_2, place_3, place_4)
                        VALUES (?, ?, ?, 1, ?, ?, ?, 4, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$g_id, $session_id, $w1, $play_time, $duration, $notes, $w1, $p2, $p3, $p4]);
                    $results_count++;

                } elseif ($game_type === 'team') {
                    // Pick 3 teams
                    $t_indices = array_rand($team_ids, 3);
                    $participating_teams = [$team_ids[$t_indices[0]], $team_ids[$t_indices[1]], $team_ids[$t_indices[2]]];
                    shuffle($participating_teams);

                    $w_team = $participating_teams[0];
                    $p2_team = $participating_teams[1];
                    $p3_team = $participating_teams[2];

                    foreach ($participating_teams as $t_id) {
                        $t_idx = array_search($t_id, $team_ids);
                        if ($t_idx !== false) {
                            foreach ($teams_list[$t_idx][1] as $m_id) {
                                $member_play_counts[$m_id]++;
                            }
                        }
                    }

                    $notes = "Team match of $game_name";
                    $stmt = $pdo->prepare("
                        INSERT INTO team_game_results (game_id, session_id, team_id, position, played_at, duration, notes, num_teams, winner, place_2, place_3)
                        VALUES (?, ?, ?, 1, ?, ?, ?, 3, ?, ?, ?)
                    ");
                    $stmt->execute([$g_id, $session_id, $w_team, $play_time, $duration, $notes, $w_team, $p2_team, $p3_team]);
                    $results_count++;

                } elseif ($game_type === 'cooperative') {
                    // Pick 4 players with lowest play count for co-op match
                    $sorted_members = $member_ids;
                    usort($sorted_members, function($a, $b) use ($member_play_counts) {
                        $diff = $member_play_counts[$a] - $member_play_counts[$b];
                        return $diff !== 0 ? $diff : rand(-1, 1);
                    });
                    $participants = array_slice($sorted_members, 0, 4);

                    foreach ($participants as $p_id) {
                        $member_play_counts[$p_id]++;
                    }

                    $outcome = (rand(0, 100) > 40) ? 'win' : 'loss';
                    $notes = "Co-op campaign of $game_name - " . strtoupper($outcome);

                    $stmt = $pdo->prepare("
                        INSERT INTO cooperative_game_results (game_id, session_id, outcome, score, difficulty, scenario, num_participants, played_at, duration, notes)
                        VALUES (?, ?, ?, ?, 'Normal', 'Global Pandemic', 4, ?, ?, ?)
                    ");
                    $stmt->execute([$g_id, $session_id, $outcome, rand(60, 98), $play_time, $duration, $notes]);
                    $cgr_id = (int)$pdo->lastInsertId();

                    foreach ($participants as $p_id) {
                        $p_stmt = $pdo->prepare("INSERT INTO cooperative_result_participants (result_id, participant_type, member_id) VALUES (?, 'member', ?)");
                        $p_stmt->execute([$cgr_id, $p_id]);
                    }
                    $results_count++;
                }
            }
        }
        echo "Generated $results_count game results (14-16 plays per game).\n";

        // 6. Add Various Championships across members
        $champ_cols = $pdo->query("DESCRIBE champions")->fetchAll(PDO::FETCH_ASSOC);
        $c_field_names = array_column($champ_cols, 'Field');
        $date_col = in_array('date', $c_field_names) ? 'date' : (in_array('start_date', $c_field_names) ? 'start_date' : 'created_at');

        $champions_list = [
            [$member_ids[0], date('Y-m-d', strtotime('-11 months')), 'GKwillie Club Inaugural Champion'],
            [$member_ids[0], date('Y-m-d', strtotime('-8 months')), 'Summer 2025 Catan Master'],
            [$member_ids[0], date('Y-m-d', strtotime('-1 months')), 'Summer 2026 Grand Tabletop Champion'],
            [$member_ids[1], date('Y-m-d', strtotime('-9 months')), 'Autumn 2025 Wingspan Champion'],
            [$member_ids[1], date('Y-m-d', strtotime('-3 months')), 'Spring 2026 Ticket to Ride Master'],
            [$member_ids[4], date('Y-m-d', strtotime('-10 months')), '7 Wonders Grand Architect'],
            [$member_ids[4], date('Y-m-d', strtotime('-4 months')), 'Terraforming Mars Tycoon'],
            [$member_ids[5], date('Y-m-d', strtotime('-7 months')), 'Azul Royal Tile Master'],
            [$member_ids[8], date('Y-m-d', strtotime('-5 months')), 'Splendor Gem League Winner'],
            [$member_ids[3], date('Y-m-d', strtotime('-6 months')), 'Carcassonne Castle Master'],
            [$member_ids[2], date('Y-m-d', strtotime('-2 months')), 'Pandemic Hero of the Year']
        ];

        foreach ($champions_list as $champ) {
            if (in_array('champ_comments', $c_field_names)) {
                $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, {$date_col}, champ_comments) VALUES (?, ?, ?, ?)");
                $stmt->execute([$club_id, $champ[0], $champ[1], $champ[2]]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO champions (club_id, member_id, {$date_col}) VALUES (?, ?, ?)");
                $stmt->execute([$club_id, $champ[0], $champ[1]]);
            }
        }
        echo "Inserted " . count($champions_list) . " championship titles.\n";

        // Summary for this club
        echo "Member Total Play Counts:\n";
        foreach ($member_ids as $idx => $m_id) {
            $m_name = $member_list[$idx][1];
            echo "  - {$m_name}: {$member_play_counts[$m_id]} total plays\n";
        }
    }

    echo "\n========================================\n";
    echo "SUCCESS: Seeding completed for GKwillie club(s)!\n";

} catch (Throwable $ex) {
    echo "ERROR: " . $ex->getMessage() . "\nFile: " . $ex->getFile() . " (Line " . $ex->getLine() . ")\n";
}
