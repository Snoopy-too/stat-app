<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';

// Get club ID or Slug from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

// Fetch club details
$club = null;
$error = '';

if ($club_id > 0 || !empty($slug)) {
    // First, fetch the club to get the club_id
    $sql = "SELECT * FROM clubs WHERE ";
    $params = [];

    if ($club_id > 0) {
        $sql .= "club_id = ?";
        $params[] = $club_id;
    } else {
        $sql .= "slug = ?";
        $params[] = $slug;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($club) {
        $club_id = $club['club_id'];

        // Now fetch the counts using the club_id
        $count_stmt = $pdo->prepare("
            SELECT
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
        ");
        $count_stmt->execute([$club_id, $club_id, $club_id, $club_id]);
        $counts = $count_stmt->fetch(PDO::FETCH_ASSOC);

        // Merge counts into club array
        $club = array_merge($club, $counts);
    } else {
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
    <script src="js/dark-mode.js"></script>
</head>
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    if ($club) {
        NavigationHelper::renderSidebar('club_stats', $club_id, $club['club_name'], $club['logo_image'] ?? null);
    } else {
        NavigationHelper::renderSidebar('club_stats');
    }
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($club ? $club['club_name'] : 'Club Stats'); ?>
    </div>

    <div class="container">
        <?php if ($error): ?>
            <div class="message message--error"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($club): ?>
            <div class="club-profile card">
                <div class="club-header">
                    <?php if ($club['logo_image']): ?>
                        <img src="images/club_logos/<?php echo htmlspecialchars($club['logo_image']); ?>" alt="<?php echo htmlspecialchars($club['club_name']); ?> logo" class="club-logo" loading="lazy">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($club['club_name']); ?></h2>
                </div>

                <div class="stats-grid">
                    <a href="club_game_list.php?id=<?php echo $club_id; ?>" class="card-link card-link--stat">
                        <h3>Games</h3>
                        <div class="stat-number"><?php echo $club['game_count']; ?></div>
                    </a>
                    <a href="club_game_results.php?id=<?php echo $club_id; ?>" class="card-link card-link--stat">
                        <h3>Total Plays</h3>
                        <div class="stat-number"><?php echo $club['play_count']; ?></div>
                    </a>
                    <a href="game_days.php?id=<?php echo $club_id; ?>" class="card-link card-link--stat">
                        <h3>Game Days</h3>
                        <div class="stat-number">&#128197;</div>
                    </a>
                    <a href="club_champions.php?id=<?php echo $club_id; ?>" class="card-link card-link--stat">
                        <h3>Champions</h3>
                        <div class="stat-number">&#127942;</div>
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
                                <img src="<?php echo htmlspecialchars($club['champ_image']); ?>" alt="Championship Trophy" class="trophy-thumbnail" loading="lazy">
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
                            <?php foreach ($members as $member): ?>
                                <div class="member-item">
                                    <span class="member-nickname"><?php echo htmlspecialchars($member['nickname']); ?></span>
                                    <a href="member_stathistory.php?id=<?php echo urlencode($member['member_id']); ?>" class="btn btn--subtle btn--small">View</a>
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
    <script src="js/sidebar.js"></script>
    <script src="js/form-loading.js"></script>
    <script src="js/confirmations.js"></script>
    <script src="js/form-validation.js"></script>
    <script src="js/empty-states.js"></script>
</body>
</html>
