<?php
session_start();
require_once 'config/database.php';
require_once 'includes/NavigationHelper.php';
require_once 'includes/services/ClubService.php';

// Get club ID or Slug from URL parameter
$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

$demo = isset($_GET['demo']) || isset($_GET['preview']);

$clubService = new ClubService($pdo);
$club = $demo ? null : $clubService->getClubDetails($club_id, $slug);
$error = '';
$leaderboard = [];

if ($club) {
    $club_id = $club['club_id'];
    $leaderboard = $clubService->getLeaderboard($club_id, 5);
} else {
    $club = [
        'club_id' => 1,
        'club_name' => 'Meeple & Dice Club',
        'description' => 'Weekly board game enthusiasts gathering for strategy and fun.',
        'member_count' => 24,
        'game_count' => 18,
        'result_count' => 142
    ];
    $leaderboard = [
        ['full_name' => 'Alex Rivers', 'score' => 124, 'wins' => 28, 'games_played' => 45],
        ['full_name' => 'Sam Taylor', 'score' => 98, 'wins' => 22, 'games_played' => 38],
        ['full_name' => 'Jordan Lee', 'score' => 85, 'wins' => 19, 'games_played' => 32],
        ['full_name' => 'Casey Morgan', 'score' => 72, 'wins' => 15, 'games_played' => 29]
    ];
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

                <div class="dashboard-stats">
                    <a href="club_game_list.php?id=<?php echo $club_id; ?>" class="card stat-card stat-card--neutral stat-card--clickable">
                        <span class="stat-card__label">Games</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $club['game_count']; ?></span>
                            <span class="stat-card__meta">In the library</span>
                        </div>
                    </a>
                    <a href="club_game_results.php?id=<?php echo $club_id; ?>" class="card stat-card stat-card--emerald stat-card--clickable">
                        <span class="stat-card__label">Total Plays</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $club['play_count']; ?></span>
                            <span class="stat-card__meta">Games played</span>
                        </div>
                    </a>
                    <a href="#members-section" class="card stat-card stat-card--sky stat-card--clickable">
                        <span class="stat-card__label">Members</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $club['member_count']; ?></span>
                            <span class="stat-card__meta">Active members</span>
                        </div>
                    </a>
                    <a href="game_days.php?id=<?php echo $club_id; ?>" class="card stat-card stat-card--purple stat-card--clickable">
                        <span class="stat-card__label">Game Days</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $club['game_days_count']; ?></span>
                            <span class="stat-card__meta">Days of gaming</span>
                        </div>
                    </a>
                    <a href="club_champions.php?id=<?php echo $club_id; ?>" class="card stat-card stat-card--gold stat-card--clickable">
                        <span class="stat-card__label">Champions</span>
                        <div class="stat-card__body">
                            <span class="stat-card__value"><?php echo $club['champions_count']; ?></span>
                            <span class="stat-card__meta">Titles awarded</span>
                        </div>
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

                        </div>
                        <p class="champion-name"><?php echo htmlspecialchars($champion['nickname']); ?></p>
                        <p class="champion-date">Since: <?php echo date('F j, Y', strtotime($champion['date'])); ?></p>
                        <?php if ($champion['champ_comments']): ?>
                            <p class="champion-comments"><?php echo nl2br(htmlspecialchars($champion['champ_comments'])); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($leaderboard)): ?>
                <div class="leaderboard-section card">
                    <div class="leaderboard-header">
                        <h3>Top Players</h3>
                        <span class="leaderboard-subtitle">By wins</span>
                    </div>
                    <div class="leaderboard-list">
                        <?php foreach ($leaderboard as $index => $player): ?>
                        <a href="member_stathistory.php?id=<?php echo $player['member_id']; ?>" class="leaderboard-item">
                            <span class="leaderboard-rank"><?php echo $index + 1; ?></span>
                            <span class="leaderboard-name"><?php echo htmlspecialchars($player['nickname']); ?></span>
                            <span class="leaderboard-stats">
                                <span class="leaderboard-wins"><?php echo $player['wins']; ?> wins</span>
                                <span class="leaderboard-plays"><?php echo $player['total_plays']; ?> plays</span>
                            </span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Fetch members of the club
                $members_stmt = $pdo->prepare("SELECT member_id, nickname FROM members WHERE club_id = ? AND status = 'active' ORDER BY nickname");
                $members_stmt->execute([$club_id]);
                $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($members) > 0): ?>
                    <div class="members-section" id="members-section">
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
