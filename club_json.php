<?php
header('Content-Type: application/json');
session_start();
require_once 'config/database.php';

// Get club ID from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($club_id <= 0) {
    echo json_encode(['error' => 'Invalid Club ID']);
    exit;
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

    if (!$club) {
        echo json_encode(['error' => 'Club not found']);
        exit;
    }

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

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
