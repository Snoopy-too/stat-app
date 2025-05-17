<?php
session_start();
require_once 'config/database.php';

// Get club ID from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch club details
$club = null;
$error = '';

if ($club_id > 0) {
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
        $error = 'Club not found';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Club Statistics - Board Game StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/club-logo.css">
</head>
<body>
    <div class="header">
        <h1>Board Game Club StatApp</h1>
        <a href="index.php" class="button">Back to Home</a>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($club): ?>
            <div class="club-profile card">
                <div class="club-header">
                    <?php if ($club['logo_image']): ?>
                        <img src="images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="<?php echo htmlspecialchars($club['club_name']); ?> logo" class="club-logo">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($club['club_name']); ?></h2>
                </div>

                <div class="stats-grid">
                    <a href="club_game_list.php?id=<?php echo $club_id; ?>" class="stat-card button" style="width: auto; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <h3>Games</h3>
                        <div class="stat-number"><?php echo $club['game_count']; ?></div>
                    </a>
                    <a href="club_game_results.php?id=<?php echo $club_id; ?>" class="stat-card button" style="width: auto; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <h3>Total Plays</h3>
                        <div class="stat-number"><?php echo $club['play_count']; ?></div>
                    </a>
                    <a href="game_days.php?id=<?php echo $club_id; ?>" class="stat-card button" style="width: auto; margin: 0; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <h3>Game Days</h3>
                        <div class="stat-number">&#128197;</div>
                    </a>
                </div>

                <?php
                // Fetch current champion if exists
                $champ_stmt = $pdo->prepare("SELECT m.nickname, c.champ_comments, c.date 
                    FROM champions c 
                    INNER JOIN members m ON c.member_id = m.member_id 
                    WHERE c.club_id = ? 
                    ORDER BY c.date DESC LIMIT 1");
                $champ_stmt->execute([$club_id]);
                $champion = $champ_stmt->fetch(PDO::FETCH_ASSOC);
                ?>
                
                <?php if ($champion): ?>
                    <div class="champion-section">
                        <div class="champion-header">
                            <h3>Current Champion</h3>
                            <?php if ($club['champ_image']): ?>
                                <img src="<?php echo htmlspecialchars($club['champ_image']); ?>" alt="Championship Trophy" class="trophy-thumbnail">
                            <?php endif; ?>
                        </div>
                        <p class="champion-name"><?php echo htmlspecialchars($champion['nickname']); ?></p>
                        <p class="champion-date">Since: <?php echo date('F j, Y', strtotime($champion['date'])); ?></p>
                        <?php if ($champion['champ_comments']): ?>
                            <p class="champion-comments"><?php echo nl2br(htmlspecialchars($champion['champ_comments'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php
                // Fetch members of the club
                $members_stmt = $pdo->prepare("SELECT member_id, nickname FROM members WHERE club_id = ? AND status = 'active' ORDER BY nickname");
                $members_stmt->execute([$club_id]);
                $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($members) > 0): ?>
                    <div class="members-section">
                        <h3>Club Members</h3>
                        <div class="members-list">
                                                        <style>
                                .members-list {
                                    display: grid;
                                    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                                    gap: 15px;
                                }
                                .member-item {
                                    display: flex;
                                    align-items: center;
                                    justify-content: space-between;
                                    padding: 10px;
                                    background-color: #f8f9fa;
                                    border-radius: 5px;
                                }
                                .view-btn {
                                    padding: 5px 15px;
                                    background-color: #3498db;
                                    color: white;
                                    text-decoration: none;
                                    border-radius: 3px;
                                    font-size: 0.9em;
                                }
                                .view-btn:hover {
                                    background-color: #2980b9;
                                }
                            </style>
                            <?php foreach ($members as $member): ?>
                                <div class="member-item">
                                    <span class="member-nickname"><?php echo htmlspecialchars($member['nickname']); ?></span>
                                    <a href="member_stathistory.php?id=<?php echo urlencode($member['member_id']); ?>" class="view-btn">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <!-- Club Teams Section -->
                <?php
                // Fetch teams for this club
                $teams_stmt = $pdo->prepare("SELECT * FROM teams WHERE club_id = ? ORDER BY team_name");
                $teams_stmt->execute([$club_id]);
                $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($teams) > 0): ?>
                    <div class="teams-section">
                        <h3>Club Teams</h3>
                        <div class="teams-list">
                            <style>
                                .teams-list {
                                    margin-top: 20px;
                                }
                                .team-block {
                                    margin-bottom: 25px;
                                    background-color: #f5f6fa;
                                    border: 1px solid #e1e8ef;
                                    border-radius: 6px;
                                    padding: 16px;
                                }
                                .team-title {
                                    font-weight: bold;
                                    font-size: 1.12em;
                                    margin-bottom: 8px;
                                }
                                .team-members {
                                    margin-left: 15px;
                                    display: flex;
                                    flex-wrap: wrap;
                                    gap: 10px;
                                }
                                .team-member-item {
                                    padding: 5px 10px;
                                    background-color: #eef3fa;
                                    border-radius: 4px;
                                }
                                .team-empty-msg {
                                    color: #888;
                                    margin-left: 5px;
                                    font-style: italic;
                                }
                            </style>
                            <?php foreach ($teams as $team): ?>
                                <div class="team-block">
                                    <div class="team-title">
                                        <?php echo htmlspecialchars($team['team_name']); ?>
                                    </div>
                                    <div class="team-members">
                                        <?php
                                        $member_ids = array_filter([
                                            $team['member1_id'],
                                            $team['member2_id'],
                                            $team['member3_id'],
                                            $team['member4_id']
                                        ]);
                                        if (count($member_ids) > 0):
                                            // fetch member details for all member_ids in this team
                                            $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
                                            $members_query = $pdo->prepare("SELECT member_id, nickname FROM members WHERE member_id IN ($placeholders)");
                                            $members_query->execute($member_ids);
                                            $tmembers = $members_query->fetchAll(PDO::FETCH_ASSOC);
                                            // Index by member_id for easy output in correct order
                                            $tmap = [];
                                            foreach ($tmembers as $tm) {
                                                $tmap[$tm['member_id']] = $tm['nickname'];
                                            }
                                            foreach ($member_ids as $mid) {
                                                if (isset($tmap[$mid])) {
                                                    echo '<span class="team-member-item">'.htmlspecialchars($tmap[$mid]).'</span>';
                                                } else {
                                                    echo '<span class="team-member-item team-empty-msg">Unknown Member</span>';
                                                }
                                            }
                                        else:
                                            echo '<span class="team-empty-msg">No members assigned.</span>';
                                        endif;
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>