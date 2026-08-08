<?php
session_start();
require_once 'config/database.php';

$demo = isset($_GET['demo']) || isset($_GET['preview']) || !isset($_SESSION['logged_in']) || !$_SESSION['logged_in'];

if ($demo) {
    $_SESSION['club_name'] = $_SESSION['club_name'] ?? 'Meeple & Dice Club';
    $members = [
        ['member_id' => 1, 'nickname' => 'Alex', 'username' => '@arivers'],
        ['member_id' => 2, 'nickname' => 'Sam', 'username' => '@staylor'],
        ['member_id' => 3, 'nickname' => 'Jordan', 'username' => '@jlee'],
        ['member_id' => 4, 'nickname' => 'Casey', 'username' => '@cmorgan']
    ];
} else {
    // Fetch existing members for this club
    $stmt = $pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY nickname");
    $stmt->execute([$_SESSION['club_id']]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Aggregate member stats (individual wins & team wins) for multi-layered stacked bar chart
$chart_member_names = [];
$chart_individual_wins = [];
$chart_team_wins = [];
$club_id = $_SESSION['club_id'] ?? 0;

if (!$demo && $club_id) {
    try {
        $stmt_stats = $pdo->prepare("
            SELECT m.member_id,
                   COALESCE(NULLIF(m.nickname, ''), 'Member') as name,
                   (
                       SELECT COUNT(*)
                       FROM game_results gr
                       WHERE COALESCE(gr.winner, gr.member_id) = m.member_id
                    ) as ind_wins,
                    (
                        SELECT COUNT(*)
                        FROM team_game_results tgr
                        JOIN teams t ON tgr.winner = t.team_id
                        WHERE (t.member1_id = m.member_id OR t.member2_id = m.member_id OR t.member3_id = m.member_id OR t.member4_id = m.member_id)
                    ) as tm_wins
            FROM members m
            WHERE m.club_id = ? AND (m.status IS NULL OR m.status != 'inactive')
            ORDER BY (ind_wins + tm_wins) DESC, name ASC
            LIMIT 12
        ");
        $stmt_stats->execute([$club_id]);
        $rows = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $chart_member_names[] = $row['name'];
            $chart_individual_wins[] = (int)$row['ind_wins'];
            $chart_team_wins[] = (int)$row['tm_wins'];
        }
    } catch (Exception $e) {
        $chart_member_names = [];
    }
}

if ($demo && empty($chart_member_names)) {
    foreach ($members as $idx => $m) {
        $name = !empty($m['nickname']) ? $m['nickname'] : 'Member ' . ($idx + 1);
        $chart_member_names[] = $name;
        $chart_individual_wins[] = max(1, 8 - ($idx * 2));
        $chart_team_wins[] = max(0, 4 - $idx);
    }
}

// Wins Over Time Per Member (multi-line chart)
$wot_labels = [];
$wot_datasets = [];

if (!$demo && $club_id) {
    try {
        $stmt_months = $pdo->prepare("
            SELECT DISTINCT DATE_FORMAT(gr.played_at, '%Y-%m') as month_key
            FROM game_results gr
            JOIN games g ON gr.game_id = g.game_id
            WHERE g.club_id = ?
            ORDER BY month_key ASC
            LIMIT 12
        ");
        $stmt_months->execute([$club_id]);
        $all_months = array_column($stmt_months->fetchAll(PDO::FETCH_ASSOC), 'month_key');

        if (!empty($all_months)) {
            $wot_labels = array_map(fn($m) => date('M Y', strtotime($m . '-01')), $all_months);

            $stmt_mwins = $pdo->prepare("
                SELECT COALESCE(NULLIF(m.nickname, ''), 'Member') as name,
                       DATE_FORMAT(gr.played_at, '%Y-%m') as month_key,
                       COUNT(*) as wins
                FROM game_results gr
                JOIN members m ON m.member_id = gr.member_id
                JOIN games g ON gr.game_id = g.game_id
                WHERE g.club_id = ?
                  AND COALESCE(gr.winner, gr.member_id) = m.member_id
                  AND (m.status IS NULL OR m.status != 'inactive')
                GROUP BY m.member_id, month_key
                ORDER BY name, month_key
            ");
            $stmt_mwins->execute([$club_id]);
            $raw = $stmt_mwins->fetchAll(PDO::FETCH_ASSOC);

            $lookup = [];
            foreach ($raw as $row) {
                $lookup[$row['name']][$row['month_key']] = (int)$row['wins'];
            }

            foreach ($lookup as $name => $month_wins) {
                $series = [];
                $cumulative = 0;
                foreach ($all_months as $mk) {
                    $cumulative += $month_wins[$mk] ?? 0;
                    $series[] = $cumulative;
                }
                $wot_datasets[] = ['name' => $name, 'data' => $series];
            }
        }
    } catch (Exception $e) {
        $wot_labels = [];
        $wot_datasets = [];
    }
}

if ($demo && empty($wot_labels)) {
    $wot_labels = ['Apr', 'May', 'Jun', 'Jul', 'Aug'];
    $demo_wins = [[1,2,2,3,4],[0,1,2,2,3],[0,0,1,2,2],[0,1,1,1,2]];
    foreach ($members as $idx => $m) {
        $name = !empty($m['nickname']) ? $m['nickname'] : 'Member ' . ($idx + 1);
        $wot_datasets[] = ['name' => $name, 'data' => $demo_wins[$idx] ?? array_fill(0, 5, 0)];
        if ($idx >= 3) break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Board Game Club StatApp</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Member Management</h1>
        <h2><?php echo htmlspecialchars($_SESSION['club_name']); ?></h2>
    </div>

    <div class="container">
        <?php if (!empty($chart_member_names)): ?>
        <details class="card" style="margin-bottom: 1.5rem;" id="analytics-accordion">
            <summary style="cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none; padding: 0.25rem 0;">
                <h2 style="margin: 0; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;">
                    <span class="material-symbols-outlined" style="font-size: 1.35rem;">monitoring</span>
                    <span>Analytics & Trends</span>
                </h2>
                <span class="material-symbols-outlined accordion-icon" style="transition: transform 0.2s ease;">expand_more</span>
            </summary>
            <div style="margin-top: 1rem; border-top: 1px solid var(--color-border); padding-top: 1rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                    <div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Wins for Members and Their Teams</h3>
                        <div style="position: relative; height: 260px;">
                            <canvas id="memberWinsChart"></canvas>
                        </div>
                    </div>
                    <?php if (!empty($wot_labels) && !empty($wot_datasets)): ?>
                    <div>
                        <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Cumulative Wins Over Time</h3>
                        <div style="position: relative; height: 260px;">
                            <canvas id="winRatesChart"></canvas>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </details>
        <?php endif; ?>

        <div class="member-list">
            <?php foreach ($members as $member): ?>
            <div class="member-item">
                <div class="member-info">
                    <strong><?php echo htmlspecialchars($member['nickname'] ?? 'Member'); ?></strong>
                    <?php if (!empty($member['username'])): ?>
                        <br><small><?php echo htmlspecialchars($member['username']); ?></small>
                    <?php endif; ?>
                </div>
                <div class="member-actions">
                    <a href="edit_member.php?id=<?php echo $member['member_id']; ?>" class="btn">View/Edit</a>
                    <a href="view_stats.php?id=<?php echo $member['member_id']; ?>" class="btn">Stats</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <a href="add_member.php" class="btn">Add New Member</a>
        <a href="dashboard.php" class="btn">Back to Dashboard</a>
    </div>

    <script>
        // Active Member Multi-Layered Stacked Bar Chart
        (function() {
            const memberNames = <?php echo json_encode($chart_member_names); ?>;
            const indWins = <?php echo json_encode($chart_individual_wins); ?>;
            const teamWins = <?php echo json_encode($chart_team_wins); ?>;
            let memberWinsChart = null;
            
            function getThemeColors() {
                const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                let text = isDark ? '#f1f5f9' : '#1e293b';
                let border = isDark ? 'rgba(255, 255, 255, 0.15)' : 'rgba(0, 0, 0, 0.08)';
                const style = getComputedStyle(document.body);
                const cssText = style.getPropertyValue('--color-text').trim();
                const cssBorder = style.getPropertyValue('--color-border').trim();
                if (cssText) text = cssText;
                if (cssBorder) border = cssBorder;
                return { text, border };
            }

            const colors = getThemeColors();

            const winsCtx = document.getElementById('memberWinsChart');
            if (winsCtx && memberNames.length > 0) {
                memberWinsChart = new Chart(winsCtx, {
                    type: 'bar',
                    data: {
                        labels: memberNames,
                        datasets: [
                            {
                                label: 'Individual Wins',
                                data: indWins,
                                backgroundColor: '#3b82f6',
                                borderRadius: 4
                            },
                            {
                                label: 'Team Wins',
                                data: teamWins,
                                backgroundColor: '#8b5cf6',
                                borderRadius: 4
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    color: colors.text,
                                    font: { family: 'inherit', size: 13, weight: '500' },
                                    padding: 14
                                }
                            }
                        },
                        scales: {
                            x: {
                                stacked: true,
                                ticks: { color: colors.text, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                ticks: { color: colors.text, stepSize: 1, precision: 0, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            }
                        }
                    }
                });
            }

            // Wins Over Time Per Member (multi-line)
            const wotLabels = <?php echo json_encode($wot_labels); ?>;
            const wotDatasets = <?php echo json_encode($wot_datasets); ?>;
            let winRatesChart = null;

            const lineColors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f43f5e','#6366f1'];

            const winrateCtx = document.getElementById('winRatesChart');
            if (winrateCtx && wotLabels.length > 0 && wotDatasets.length > 0) {
                winRatesChart = new Chart(winrateCtx, {
                    type: 'line',
                    data: {
                        labels: wotLabels,
                        datasets: wotDatasets.map((ds, i) => ({
                            label: ds.name,
                            data: ds.data,
                            borderColor: lineColors[i % lineColors.length],
                            backgroundColor: 'transparent',
                            tension: 0.3,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            borderWidth: 2
                        }))
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom',
                                labels: {
                                    color: colors.text,
                                    font: { family: 'inherit', size: 12 },
                                    padding: 12,
                                    boxWidth: 12
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: colors.text, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { color: colors.text, stepSize: 1, precision: 0, font: { family: 'inherit', size: 12 } },
                                grid: { color: colors.border }
                            }
                        }
                    }
                });
            }

            function updateChartTheme() {
                const c = getThemeColors();
                if (memberWinsChart && memberWinsChart.options.scales) {
                    if (memberWinsChart.options.plugins && memberWinsChart.options.plugins.legend) {
                        memberWinsChart.options.plugins.legend.labels.color = c.text;
                    }
                    memberWinsChart.options.scales.x.ticks.color = c.text;
                    memberWinsChart.options.scales.x.grid.color = c.border;
                    memberWinsChart.options.scales.y.ticks.color = c.text;
                    memberWinsChart.options.scales.y.grid.color = c.border;
                    memberWinsChart.update();
                }
                if (winRatesChart && winRatesChart.options.scales) {
                    winRatesChart.options.scales.x.ticks.color = c.text;
                    winRatesChart.options.scales.x.grid.color = c.border;
                    winRatesChart.options.scales.y.ticks.color = c.text;
                    winRatesChart.options.scales.y.grid.color = c.border;
                    winRatesChart.update();
                }
            }

            const analyticsAccordion = document.getElementById('analytics-accordion');
            if (analyticsAccordion) {
                analyticsAccordion.addEventListener('toggle', function() {
                    if (this.open) {
                        setTimeout(() => {
                            if (typeof memberWinsChart !== 'undefined' && memberWinsChart) memberWinsChart.resize();
                            if (typeof winRatesChart !== 'undefined' && winRatesChart) winRatesChart.resize();
                        }, 50);
                    }
                });
            }

            window.addEventListener('themechange', updateChartTheme);
            const observer = new MutationObserver(updateChartTheme);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        })();
    </script>
</body>
</html>