<?php
session_start();
require_once '../config/database.php';
require_once '../includes/SecurityUtils.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

$security = new SecurityUtils($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$security->verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid security token.";
        header("Location: manage_clubs.php");
        exit();
    }

    $club_id = isset($_POST['club_id']) ? (int)$_POST['club_id'] : 0;
    
    // Verify club ownership
    $stmt = $pdo->prepare("SELECT club_id FROM clubs WHERE club_id = ? AND admin_id = ?");
    $stmt->execute([$club_id, $_SESSION['admin_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Club not found or permission denied.";
        header("Location: manage_clubs.php");
        exit();
    }

    try {
        // 1. Fetch club details
        $stmt = $pdo->prepare("SELECT c.*, 
            (SELECT COUNT(*) FROM members WHERE club_id = ? AND status = 'active') as member_count,
            (SELECT COUNT(*) FROM games WHERE club_id = ?) as game_count,
            (SELECT COUNT(*) FROM (
                SELECT g.game_id FROM games g 
                INNER JOIN game_results gr ON g.game_id = gr.game_id 
                WHERE g.club_id = ?
                UNION ALL
                SELECT g.game_id FROM games g
                INNER JOIN team_game_results tgr ON g.game_id = tgr.game_id
                WHERE g.club_id = ?
            ) as all_plays) as play_count
            FROM clubs c 
            WHERE c.club_id = ?");

        $stmt->execute([$club_id, $club_id, $club_id, $club_id, $club_id]);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. Fetch current champion
        $champ_stmt = $pdo->prepare("SELECT m.nickname, c.champ_comments, c.date 
            FROM champions c 
            INNER JOIN members m ON c.member_id = m.member_id 
            WHERE c.club_id = ? 
            ORDER BY c.date DESC LIMIT 1");
        $champ_stmt->execute([$club_id]);
        $champion = $champ_stmt->fetch(PDO::FETCH_ASSOC);

        // 3. Fetch members
        $members_stmt = $pdo->prepare("SELECT member_id, nickname FROM members WHERE club_id = ? AND status = 'active' ORDER BY nickname");
        $members_stmt->execute([$club_id]);
        $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Fetch teams
        $teams_stmt = $pdo->prepare("SELECT * FROM teams WHERE club_id = ? ORDER BY team_name");
        $teams_stmt->execute([$club_id]);
        $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Process teams to include member names
        $processed_teams = [];
        if (count($teams) > 0) {
            foreach ($teams as $team) {
                $member_ids = array_filter([
                    $team['member1_id'],
                    $team['member2_id'],
                    $team['member3_id'],
                    $team['member4_id']
                ]);
                
                $team_members = [];
                if (count($member_ids) > 0) {
                    $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
                    $members_query = $pdo->prepare("SELECT member_id, nickname FROM members WHERE member_id IN ($placeholders)");
                    $members_query->execute($member_ids);
                    $tmembers = $members_query->fetchAll(PDO::FETCH_ASSOC);
                    
                    $tmap = [];
                    foreach ($tmembers as $tm) {
                        $tmap[$tm['member_id']] = $tm['nickname'];
                    }
                    
                    foreach ($member_ids as $mid) {
                        if (isset($tmap[$mid])) {
                            $team_members[] = [
                                'id' => $mid,
                                'name' => $tmap[$mid]
                            ];
                        } else {
                            $team_members[] = [
                                'id' => $mid,
                                'name' => 'Unknown Member'
                            ];
                        }
                    }
                }

                $processed_teams[] = [
                    'team_id' => $team['team_id'],
                    'team_name' => $team['team_name'],
                    'members' => $team_members
                ];
            }
        }

        // 5. Fetch games
        $games_stmt = $pdo->prepare("SELECT * FROM games WHERE club_id = ? ORDER BY game_name");
        $games_stmt->execute([$club_id]);
        $games = $games_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Fetch game results (history)
        $results_stmt = $pdo->prepare("
            -- Individual Games
            SELECT
                gr.played_at,
                g.game_name,
                m.nickname as winner_identifier,
                gr.num_players as participants,
                gr.game_id,
                'Individual' as game_type,
                gr.result_id as record_id
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            JOIN members m ON gr.winner = m.member_id
            WHERE m.club_id = ?

            UNION ALL

            -- Team Games
            SELECT
                tgr.played_at,
                g.game_name,
                t.team_name as winner_identifier,
                tgr.num_teams as participants,
                tgr.game_id,
                'Team' as game_type,
                tgr.result_id as record_id
            FROM team_game_results tgr
            JOIN games g ON tgr.game_id = g.game_id
            JOIN teams t ON tgr.winner = t.team_id
            WHERE t.club_id = ?

            ORDER BY played_at DESC
        ");
        $results_stmt->execute([$club_id, $club_id]);
        $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Construct final response
        $response = [
            'club' => $club,
            'champion' => $champion ? $champion : null,
            'members' => $members,
            'teams' => $processed_teams,
            'games' => $games,
            'results' => $results
        ];

        // Ensure api directory exists
        if (!is_dir('../api')) {
            mkdir('../api', 0755, true);
        }

        // Clean up old files for this club
        $files = glob("../api/club_{$club_id}_*.json");
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        // Generate filename
        $random_string = bin2hex(random_bytes(8));
        $filename = "club_{$club_id}_{$random_string}.json";
        $filepath = "../api/" . $filename;

        // Write to file
        if (file_put_contents($filepath, json_encode($response, JSON_PRETTY_PRINT))) {
            $full_url = "api/" . $filename;
            $_SESSION['success'] = "API generated successfully!";
            $_SESSION['api_file'] = $full_url;
        } else {
            $_SESSION['error'] = "Failed to write API file.";
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
    }

    header("Location: manage_clubs.php");
    exit();
} else {
    header("Location: manage_clubs.php");
    exit();
}
