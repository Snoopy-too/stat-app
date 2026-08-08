<?php
require_once 'config/database.php';
require_once 'includes/helpers.php';
require_once 'includes/NavigationHelper.php';

ensure_results_tables_exist($pdo);
ensure_game_image_column_exists($pdo);

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if ($club_id <= 0 && empty($slug)) {
    $stmt = $pdo->query("SELECT club_id FROM clubs ORDER BY club_id ASC LIMIT 1");
    $first = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if ($first) {
        $club_id = (int)$first['club_id'];
    }
}

// Get club details
$club = false;
if ($club_id > 0 || !empty($slug)) {
    $sql = "SELECT club_id, club_name, slug FROM clubs WHERE ";
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
        $club_id = (int)$club['club_id'];
    }
}

if (!$club) {
    header("Location: index.php");
    exit();
}

// Check game_image column in games table
$gColStmt = $pdo->query("SHOW COLUMNS FROM games");
$gameCols = $gColStmt ? $gColStmt->fetchAll(PDO::FETCH_COLUMN) : [];
$hasGameImage = in_array('game_image', $gameCols);
$gameImageSelect = $hasGameImage ? "CONVERT(g.game_image USING utf8mb4)" : "NULL";

// Generate base URL for sorting links
$base_url_param = !empty($club['slug']) ? 'slug=' . urlencode($club['slug']) : 'id=' . $club_id;
$club_url = !empty($club['slug']) ? $club['slug'] : 'club_stats.php?id=' . $club_id;

$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'played_at';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
// Updated allowed columns for sorting combined results
$allowed_columns = ['played_at', 'game_name', 'winner_identifier', 'participants', 'game_type'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'played_at';
}

// Pagination settings
$results_per_page = 25; // Show 25 results at a time
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $results_per_page;

// Get total count of results first
$count_sql = "
    SELECT COUNT(*) as total FROM (
        SELECT gr.result_id
        FROM game_results gr
        JOIN games g ON gr.game_id = g.game_id
        WHERE g.club_id = :club_id_individual

        UNION ALL

        SELECT tgr.result_id
        FROM team_game_results tgr
        JOIN games g ON tgr.game_id = g.game_id
        WHERE g.club_id = :club_id_team

        UNION ALL

        SELECT cgr.result_id
        FROM cooperative_game_results cgr
        JOIN games g ON cgr.game_id = g.game_id
        WHERE g.club_id = :club_id_coop
    ) as all_results
";

try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(['club_id_individual' => $club_id, 'club_id_team' => $club_id, 'club_id_coop' => $club_id]);
    $total_results = (int)$count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_results / $results_per_page);
    $has_more = $page < $total_pages;
} catch (PDOException $e) {
    error_log("Database count query failed: " . $e->getMessage());
    $total_results = 0;
    $total_pages = 1;
    $has_more = false;
}

// Get combined game results (individual, team, and cooperative) for the club with LIMIT
$sql = "
    -- Individual Games
    SELECT
        gr.played_at,
        CONVERT(g.game_name USING utf8mb4) as game_name,
        {$gameImageSelect} as game_image,
        CONVERT(COALESCE(NULLIF(m.nickname, ''), 'Unknown Member') USING utf8mb4) as winner_identifier,
        gr.num_players as participants,
        gr.game_id,
        CONVERT('Individual' USING utf8mb4) as game_type,
        gr.result_id as record_id
    FROM game_results gr
    JOIN games g ON gr.game_id = g.game_id
    LEFT JOIN members m ON COALESCE(gr.winner, gr.member_id) = m.member_id
    WHERE g.club_id = :club_id_individual

    UNION ALL

    -- Team Games
    SELECT
        tgr.played_at,
        CONVERT(g.game_name USING utf8mb4) as game_name,
        {$gameImageSelect} as game_image,
        CONVERT(COALESCE(t.team_name, 'Unknown Team') USING utf8mb4) as winner_identifier,
        tgr.num_teams as participants,
        tgr.game_id,
        CONVERT('Team' USING utf8mb4) as game_type,
        tgr.result_id as record_id
    FROM team_game_results tgr
    JOIN games g ON tgr.game_id = g.game_id
    LEFT JOIN teams t ON tgr.winner = t.team_id
    WHERE g.club_id = :club_id_team

    UNION ALL

    -- Cooperative Games
    SELECT
        cgr.played_at,
        CONVERT(g.game_name USING utf8mb4) as game_name,
        {$gameImageSelect} as game_image,
        CONVERT(CONCAT(UPPER(cgr.outcome), ' - Co-op') USING utf8mb4) as winner_identifier,
        cgr.num_participants as participants,
        cgr.game_id,
        CONVERT('Cooperative' USING utf8mb4) as game_type,
        cgr.result_id as record_id
    FROM cooperative_game_results cgr
    JOIN games g ON cgr.game_id = g.game_id
    WHERE g.club_id = :club_id_coop

    ORDER BY $sort_column $order
    LIMIT :limit OFFSET :offset
";

// Prepare and execute the statement
$game_results = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':club_id_individual', $club_id, PDO::PARAM_INT);
    $stmt->bindValue(':club_id_team', $club_id, PDO::PARAM_INT);
    $stmt->bindValue(':club_id_coop', $club_id, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $results_per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $game_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database query failed: " . $e->getMessage());
}

// Calculate Analytics & Trends for Results
$game_play_counts = [];
$indiv_winners = [];
$ranked_winners = [];
$team_winners = [];
$coop_outcomes = ['Victory' => 0, 'Defeat' => 0];

foreach ($game_results as $res) {
    // Most Played Games (Include ALL games)
    $gName = !empty($res['game_name']) ? $res['game_name'] : 'Unknown Game';
    $game_play_counts[$gName] = ($game_play_counts[$gName] ?? 0) + 1;

    // Win rates per game type
    $t = strtolower($res['game_type'] ?? 'winner_losers');
    $w = $res['winner_identifier'] ?: 'Unknown';

    if (strpos($t, 'coop') !== false) {
        if (stripos($w, 'WIN') !== false || stripos($w, 'VICTORY') !== false) {
            $coop_outcomes['Victory']++;
        } else {
            $coop_outcomes['Defeat']++;
        }
    } elseif (strpos($t, 'team') !== false) {
        $cleanTeam = str_replace(' (Team)', '', $w);
        $team_winners[$cleanTeam] = ($team_winners[$cleanTeam] ?? 0) + 1;
    } elseif (strpos($t, 'rank') !== false) {
        $ranked_winners[$w] = ($ranked_winners[$w] ?? 0) + 1;
    } else {
        $indiv_winners[$w] = ($indiv_winners[$w] ?? 0) + 1;
    }
}

arsort($game_play_counts);
$all_games_labels = array_keys($game_play_counts);
$all_games_data = array_values($game_play_counts);

arsort($indiv_winners);
$indiv_labels = array_slice(array_keys($indiv_winners), 0, 6);
$indiv_data = array_slice(array_values($indiv_winners), 0, 6);

arsort($ranked_winners);
$ranked_labels = array_slice(array_keys($ranked_winners), 0, 6);
$ranked_data = array_slice(array_values($ranked_winners), 0, 6);

arsort($team_winners);
$team_labels = array_slice(array_keys($team_winners), 0, 6);
$team_data = array_slice(array_values($team_winners), 0, 6);

$filtered_coop = array_filter($coop_outcomes, function($v) { return $v > 0; });
$coop_labels = array_keys($filtered_coop);
$coop_data = array_values($filtered_coop);

?>

<!DOCTYPE html>
<!-- Rest of your HTML code remains the same -->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Results - <?php echo htmlspecialchars($club['club_name']); ?></title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/dark-mode.js"></script>
    <style>
        .game-thumbnail {
            width: 48px !important;
            height: 48px !important;
            min-width: 48px !important;
            flex-shrink: 0;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-border);
            display: block;
            background: var(--color-surface-muted);
            overflow: hidden;
            position: relative;
            max-width: none !important;
        }
        .col-image {
            width: 48px;
            padding-right: 0 !important;
        }
        .game-thumbnail--skeleton::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, 
                rgba(17, 24, 39, 0.03) 25%, 
                rgba(17, 24, 39, 0.06) 37%, 
                rgba(17, 24, 39, 0.03) 63%);
            background-size: 400% 100%;
            animation: skeleton-loading 2s ease infinite;
        }
        @keyframes skeleton-loading {
            0% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
    </style>
</head>
<body class="has-sidebar">
    <?php
    // Render sidebar navigation
    NavigationHelper::renderSidebar('results', $club_id, $club['club_name']);
    ?>

    <div class="header header--compact">
        <?php NavigationHelper::renderSidebarToggle(); ?>
        <?php NavigationHelper::renderCompactHeader($club['club_name'] . ' Game Results (' . count($game_results) . ')'); ?>
    </div>

    <div class="container container--wide">
        <?php if (!empty($all_games_labels)): ?>
        <details class="card" style="margin-bottom: 1.5rem;" id="results-analytics-accordion">
            <summary style="cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none; padding: 0.25rem 0;">
                <h2 style="margin: 0; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;">
                    <span class="material-symbols-outlined" style="font-size: 1.35rem;">monitoring</span>
                    <span>Analytics & Trends</span>
                </h2>
                <span class="material-symbols-outlined accordion-icon" style="transition: transform 0.2s ease;">expand_more</span>
            </summary>
            <div style="margin-top: 1rem; border-top: 1px solid var(--color-border); padding-top: 1rem;">
                <div style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Most Played Games (All Games)</h3>
                    <div style="position: relative; height: 260px;">
                        <canvas id="mostPlayedGamesChart"></canvas>
                    </div>
                </div>

                <div>
                    <h3 style="font-size: 1rem; margin-bottom: 1rem; text-align: center;">Win Rates by Game Type</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap: 1.25rem;">
                        <?php if (!empty($indiv_labels)): ?>
                        <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                            <h4 style="font-size: 0.9rem; margin: 0 0 0.5rem 0; text-align: center;">Winner/Losers Games</h4>
                            <div style="position: relative; height: 210px;">
                                <canvas id="indivWinChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($ranked_labels)): ?>
                        <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                            <h4 style="font-size: 0.9rem; margin: 0 0 0.5rem 0; text-align: center;">Ranked Games (1st Place)</h4>
                            <div style="position: relative; height: 210px;">
                                <canvas id="rankedWinChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($team_labels)): ?>
                        <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                            <h4 style="font-size: 0.9rem; margin: 0 0 0.5rem 0; text-align: center;">Team Games</h4>
                            <div style="position: relative; height: 210px;">
                                <canvas id="teamWinChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($coop_labels)): ?>
                        <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                            <h4 style="font-size: 0.9rem; margin: 0 0 0.5rem 0; text-align: center;">Cooperative Games</h4>
                            <div style="position: relative; height: 210px;">
                                <canvas id="coopWinChart"></canvas>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </details>
        <?php endif; ?>

        <div class="card-toolbar" style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
            <div style="min-width: 200px; max-width: 320px; width: 100%;">
                <input type="text" id="gameHistorySearch" class="form-control" placeholder="Search history..." aria-label="Search game history">
            </div>
        </div>

            <?php if (!empty($game_results)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-image"></th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=game_name&order=<?php echo ($sort_column === 'game_name' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Game</span><?php if ($sort_column === 'game_name'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=played_at&order=<?php echo ($sort_column === 'played_at' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Date Played</span><?php if ($sort_column === 'played_at'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=game_type&order=<?php echo ($sort_column === 'game_type' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Type</span><?php if ($sort_column === 'game_type'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=winner_identifier&order=<?php echo ($sort_column === 'winner_identifier' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Winner / Team</span><?php if ($sort_column === 'winner_identifier'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                            <th><a href="?<?php echo $base_url_param; ?>&sort=participants&order=<?php echo ($sort_column === 'participants' && $order === 'DESC') ? 'asc' : 'desc'; ?>" class="table-sort-link sort-link" onclick="saveScroll()"><span>Participants</span><?php if ($sort_column === 'participants'): ?><span class="table-sort-link__icon"><?php echo $order === 'ASC' ? '▲' : '▼'; ?></span><?php endif; ?></a></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="noSearchMatch" style="display: none;">
                            <td colspan="6" class="text-center text-muted" style="padding: 1.5rem;">No results match your search.</td>
                        </tr>
                        <?php foreach ($game_results as $result): ?>
                        <?php
                        $detail_url = 'game_play_details.php';
                        ?>
                        <tr onclick="window.location='<?php echo $detail_url; ?>?result_id=<?php echo urlencode($result['record_id']); ?>'" class="table-row--link">
                            <td class="col-image">
                                <?php if ($result['game_image']): ?>
                                    <img src="<?php echo htmlspecialchars(get_game_image_url($result['game_image'])); ?>" alt="" class="game-thumbnail" loading="lazy">
                                <?php else: ?>
                                    <div class="game-thumbnail game-thumbnail--skeleton" title="No image uploaded"></div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Game"><?php echo htmlspecialchars($result['game_name']); ?></td>
                            <td data-label="Date Played"><?php echo date('Y/m/d', strtotime($result['played_at'])); ?></td>
                            <td data-label="Type"><?php echo htmlspecialchars($result['game_type']); ?></td>
                            <td data-label="Winner / Team">
                                <?php if ($result['game_type'] === 'Cooperative'): ?>
                                    <?php
                                    $outcome = strtolower(explode(' - ', $result['winner_identifier'])[0]);
                                    ?>
                                    <span class="outcome-badge outcome-<?php echo $outcome; ?>"><?php echo htmlspecialchars($result['winner_identifier']); ?></span>
                                <?php else: ?>
                                    <span class="position-badge position-1"><?php echo htmlspecialchars($result['winner_identifier']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Participants"><?php echo htmlspecialchars($result['participants']); ?> <?php echo ($result['game_type'] === 'Individual') ? 'Players' : (($result['game_type'] === 'Team') ? 'Teams' : 'Players'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Info and Load More Button -->
            <?php if ($total_results > 0): ?>
                <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-secondary); border-radius: var(--border-radius); text-align: center;">
                    <p style="color: var(--text-light); margin-bottom: 1rem;">
                        Showing <?php echo min($offset + 1, $total_results); ?> - <?php echo min($offset + count($game_results), $total_results); ?> of <?php echo $total_results; ?> results
                    </p>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center; align-items: center; flex-wrap: wrap;">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo $base_url_param; ?>&sort=<?php echo urlencode($sort_column); ?>&order=<?php echo urlencode($order); ?>&page=<?php echo $page - 1; ?>" 
                               class="btn btn--ghost">
                                ← Show Previous Results
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($has_more): ?>
                            <a href="?<?php echo $base_url_param; ?>&sort=<?php echo urlencode($sort_column); ?>&order=<?php echo urlencode($order); ?>&page=<?php echo $page + 1; ?>" 
                               class="btn btn--secondary">
                                Load More Results →
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($has_more): ?>
                        <p style="color: var(--text-light); font-size: 0.875rem; margin-top: 0.75rem;">
                            <?php echo $total_results - ($offset + count($game_results)); ?> more results available
                        </p>
                    <?php elseif ($page > 1): ?>
                        <p style="color: var(--text-light); font-style: italic; margin-top: 0.75rem;">
                            All results loaded • Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php else: ?>
                <p>No game results available for this club.</p>
            <?php endif; ?>
    </div>
    <script src="js/sidebar.js"></script>
    <script src="js/form-loading.js"></script>
    <script src="js/empty-states.js"></script>
</body>
</html>
<script>
function saveScroll() {
    sessionStorage.setItem('scrollPos', window.scrollY);
}
window.addEventListener('DOMContentLoaded', function() {
    var scrollPos = sessionStorage.getItem('scrollPos');
    if (scrollPos !== null) {
        window.scrollTo(0, parseInt(scrollPos));
        sessionStorage.removeItem('scrollPos');
    }

    document.getElementById('gameHistorySearch')?.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('.data-table tbody tr:not(#noSearchMatch)');
        let visibleCount = 0;
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        const noMatch = document.getElementById('noSearchMatch');
        if (noMatch) {
            noMatch.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
        }
    });

    // Initialize Analytics & Trends Charts for Results
    (function() {
        function getStyleVal(prop) {
            return getComputedStyle(document.documentElement).getPropertyValue(prop).trim();
        }

        function getThemeColors() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark' || 
                           (!document.documentElement.hasAttribute('data-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
            return {
                primary: getStyleVal('--color-primary') || '#6366f1',
                accent: getStyleVal('--color-accent') || '#8b5cf6',
                text: getStyleVal('--color-text') || (isDark ? '#f1f5f9' : '#1e293b'),
                border: getStyleVal('--color-border') || (isDark ? '#334155' : '#e2e8f0'),
                grid: getStyleVal('--color-border') || (isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.06)')
            };
        }

        const colors = getThemeColors();
        const palette = [
            colors.primary,
            colors.accent,
            getStyleVal('--color-success-border') || '#10b981',
            getStyleVal('--color-warning-text') || '#f59e0b',
            '#ec4899',
            '#3b82f6'
        ];

        const gamesLabels = <?php echo json_encode($all_games_labels); ?>;
        const gamesData = <?php echo json_encode($all_games_data); ?>;

        let charts = [];

        const ctxGames = document.getElementById('mostPlayedGamesChart');
        if (ctxGames && gamesLabels.length > 0) {
            charts.push(new Chart(ctxGames, {
                type: 'bar',
                data: {
                    labels: gamesLabels,
                    datasets: [{
                        label: 'Plays',
                        data: gamesData,
                        backgroundColor: colors.primary,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: colors.text }, grid: { color: colors.grid } },
                        y: { ticks: { color: colors.text, stepSize: 1 }, grid: { color: colors.grid }, beginAtZero: true }
                    }
                }
            }));
        }

        function makePieChart(id, labels, data) {
            const ctx = document.getElementById(id);
            if (ctx && labels.length > 0) {
                charts.push(new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: palette.slice(0, labels.length),
                            borderWidth: 2,
                            borderColor: colors.border
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: { color: colors.text, font: { size: 11 } }
                            }
                        }
                    }
                }));
            }
        }

        makePieChart('indivWinChart', <?php echo json_encode($indiv_labels); ?>, <?php echo json_encode($indiv_data); ?>);
        makePieChart('rankedWinChart', <?php echo json_encode($ranked_labels); ?>, <?php echo json_encode($ranked_data); ?>);
        makePieChart('teamWinChart', <?php echo json_encode($team_labels); ?>, <?php echo json_encode($team_data); ?>);
        makePieChart('coopWinChart', <?php echo json_encode($coop_labels); ?>, <?php echo json_encode($coop_data); ?>);

        const accordion = document.getElementById('results-analytics-accordion');
        if (accordion) {
            const savedState = sessionStorage.getItem('results_analytics_open');
            if (savedState === 'true') {
                accordion.open = true;
                setTimeout(() => {
                    charts.forEach(c => c && c.resize());
                }, 50);
            } else if (savedState === 'false') {
                accordion.open = false;
            }

            accordion.addEventListener('toggle', function() {
                sessionStorage.setItem('results_analytics_open', this.open ? 'true' : 'false');
                if (this.open) {
                    setTimeout(() => {
                        charts.forEach(c => c && c.resize());
                    }, 50);
                }
            });
        }
    })();
});
</script>
