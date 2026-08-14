<?php
declare(strict_types=1);

/**
 * Table & View Helper
 * Shared table, header & analytics renderer for Public and Admin pages.
 */
class TableHelper {

    /**
     * Render Members Analytics Accordion & Charts
     */
    public static function renderMembersAnalytics(array $chart_member_names, array $chart_individual_wins, array $chart_team_wins, array $wot_labels = [], array $wot_datasets = [], array $options = []): void {
        if (empty($chart_member_names)) {
            return;
        }
        ?>
        <details class="card" style="margin-bottom: 1.5rem;" id="analytics-accordion" <?php echo (!isset($options['open']) || $options['open']) ? 'open' : ''; ?>>
            <summary style="cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none; padding: 0.25rem 0;">
                <h2 style="margin: 0; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;">
                    <span class="material-symbols-outlined" style="font-size: 1.35rem;">monitoring</span>
                    <span>Analytics & Trends</span>
                </h2>
                <span class="material-symbols-outlined accordion-icon" style="transition: transform 0.2s ease;">expand_more</span>
            </summary>
            <div style="margin-top: 1rem; border-top: 1px solid var(--color-border); padding-top: 1rem;">
                <div>
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Wins for Members and Their Teams</h3>
                    <div style="position: relative; height: 260px;">
                        <canvas id="memberWinsChart"></canvas>
                    </div>
                </div>
                <?php if (!empty($wot_labels) && !empty($wot_datasets)): ?>
                <div style="margin-top: 2rem;">
                    <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Cumulative Wins Over Time</h3>
                    <div style="position: relative; height: 260px;">
                        <canvas id="winRatesChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </details>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
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
            const memberNames = <?php echo json_encode($chart_member_names); ?>;
            const indWins = <?php echo json_encode($chart_individual_wins); ?>;
            const teamWins = <?php echo json_encode($chart_team_wins); ?>;

            let barChart = null;
            let lineChart = null;

            const ctxWins = document.getElementById('memberWinsChart');
            if (ctxWins && memberNames.length > 0) {
                barChart = new Chart(ctxWins, {
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

            const wotLabels = <?php echo json_encode($wot_labels); ?>;
            const wotRawData = <?php echo json_encode($wot_datasets); ?>;
            const lineColors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#f43f5e','#6366f1'];

            const ctxWot = document.getElementById('winRatesChart');
            if (ctxWot && wotLabels.length > 0 && wotRawData.length > 0) {
                lineChart = new Chart(ctxWot, {
                    type: 'line',
                    data: {
                        labels: wotLabels,
                        datasets: wotRawData.map((ds, i) => ({
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
                                labels: { color: colors.text, font: { size: 12 } }
                            }
                        },
                        scales: {
                            x: { ticks: { color: colors.text }, grid: { color: colors.border } },
                            y: { ticks: { color: colors.text, stepSize: 1 }, grid: { color: colors.border }, beginAtZero: true }
                        }
                    }
                });
            }

            const accordion = document.getElementById('analytics-accordion');
            if (accordion) {
                const savedState = sessionStorage.getItem('member_analytics_open');
                if (savedState === 'true') {
                    accordion.open = true;
                    setTimeout(() => {
                        barChart && barChart.resize();
                        lineChart && lineChart.resize();
                    }, 50);
                } else if (savedState === 'false') {
                    accordion.open = false;
                }

                accordion.addEventListener('toggle', function() {
                    sessionStorage.setItem('member_analytics_open', this.open ? 'true' : 'false');
                    if (this.open) {
                        setTimeout(() => {
                            barChart && barChart.resize();
                            lineChart && lineChart.resize();
                        }, 50);
                    }
                });
            }
        })();
        </script>
        <?php
    }

    /**
     * Render Results Analytics Accordion & Charts
     */
    public static function renderResultsAnalytics(array $results, array $all_games, $game_id = null, array $options = []): void {
        $results = array_values(array_filter($results, function($r) {
            $w = trim(str_replace(' (Team)', '', $r['winner_name'] ?? ''));
            return !empty($w) && !in_array($w, ['Unknown', 'Unknown Member', 'Unknown Team', 'Member'], true);
        }));

        $game_play_counts = [];
        $indiv_winners = [];
        $ranked_winners = [];
        $team_winners = [];
        $coop_outcomes = ['Victory' => 0, 'Defeat' => 0];
        $selected_game_labels = [];
        $selected_game_data = [];
        $selected_game_title = "";

        $selected_game = null;
        if ($game_id) {
            foreach ($all_games as $g) {
                if ($g['game_id'] == $game_id) {
                    $selected_game = $g;
                    break;
                }
            }
        }

        if ($game_id && !empty($selected_game)) {
            $gType = strtolower($selected_game['game_type'] ?? 'winner_losers');
            $selected_game_title = "Win Rate for " . $selected_game['game_name'];

            if ($gType === 'cooperative') {
                $cWins = 0; $cLosses = 0;
                foreach ($results as $res) {
                    if (stripos($res['winner_name'], 'WIN') !== false || stripos($res['winner_name'], 'VICTORY') !== false) {
                        $cWins++;
                    } else {
                        $cLosses++;
                    }
                }
                $selected_game_labels = ['Victory', 'Defeat'];
                $selected_game_data = [$cWins, $cLosses];
            } else {
                $wCounts = [];
                foreach ($results as $res) {
                    if (($res['member_status'] ?? 'active') === 'inactive') continue;
                    $wName = str_replace(' (Team)', '', $res['winner_name'] ?? '');
                    if (empty($wName) || in_array($wName, ['Unknown', 'Unknown Member', 'Unknown Team', 'Member'], true)) continue;
                    $wCounts[$wName] = ($wCounts[$wName] ?? 0) + 1;
                }
                arsort($wCounts);
                $selected_game_labels = array_slice(array_keys($wCounts), 0, 6);
                $selected_game_data = array_slice(array_values($wCounts), 0, 6);
            }
        } else {
            foreach ($results as $res) {
                $gName = !empty($res['game_name']) ? $res['game_name'] : 'Unknown Game';
                $game_play_counts[$gName] = ($game_play_counts[$gName] ?? 0) + 1;

                $t = strtolower($res['game_type'] ?? 'winner_losers');
                $w = trim($res['winner_name'] ?? '');
                $isUnknown = empty($w) || in_array($w, ['Unknown', 'Unknown Member', 'Unknown Team', 'Member'], true);

                if (strpos($t, 'coop') !== false) {
                    if (stripos($w, 'WIN') !== false || stripos($w, 'VICTORY') !== false) {
                        $coop_outcomes['Victory']++;
                    } else {
                        $coop_outcomes['Defeat']++;
                    }
                } elseif (strpos($t, 'team') !== false) {
                    $cleanTeam = str_replace(' (Team)', '', $w);
                    $isUnknownTeam = empty($cleanTeam) || in_array($cleanTeam, ['Unknown', 'Unknown Member', 'Unknown Team', 'Member'], true);
                    if (!$isUnknownTeam) {
                        $team_winners[$cleanTeam] = ($team_winners[$cleanTeam] ?? 0) + 1;
                    }
                } elseif (strpos($t, 'rank') !== false) {
                    if (($res['member_status'] ?? 'active') !== 'inactive' && !$isUnknown) {
                        $ranked_winners[$w] = ($ranked_winners[$w] ?? 0) + 1;
                    }
                } else {
                    if (($res['member_status'] ?? 'active') !== 'inactive' && !$isUnknown) {
                        $indiv_winners[$w] = ($indiv_winners[$w] ?? 0) + 1;
                    }
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
        }

        if (empty($game_id) && empty($all_games_labels)) return;
        ?>
        <details class="card" style="margin-bottom: 1.5rem;" id="results-analytics-accordion" <?php echo (!isset($options['open']) || $options['open']) ? 'open' : ''; ?>>
            <summary style="cursor: pointer; list-style: none; display: flex; align-items: center; justify-content: space-between; user-select: none; padding: 0.25rem 0;">
                <h2 style="margin: 0; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;">
                    <span class="material-symbols-outlined" style="font-size: 1.35rem;">monitoring</span>
                    <span>Analytics & Trends</span>
                </h2>
                <span class="material-symbols-outlined accordion-icon" style="transition: transform 0.2s ease;">expand_more</span>
            </summary>
            <div style="margin-top: 1rem; border-top: 1px solid var(--color-border); padding-top: 1rem;">
                <?php if ($game_id && !empty($selected_game)): ?>
                    <div class="results-pie-grid">
                        <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                            <h3 style="font-size: 0.95rem; margin: 0 0 0.5rem 0; text-align: center;"><?php echo htmlspecialchars($selected_game_title); ?></h3>
                            <div style="position: relative; height: 220px;">
                                <canvas id="selectedGameWinChart"></canvas>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 2rem;">
                        <h3 style="font-size: 1rem; margin-bottom: 0.75rem; text-align: center;">Most Played Games (All Games)</h3>
                        <div style="position: relative; height: 260px;">
                            <canvas id="mostPlayedGamesChart"></canvas>
                        </div>
                    </div>

                    <div>
                        <h3 style="font-size: 1rem; margin-bottom: 1rem; text-align: center;">Win Rates by Game Type</h3>
                        <div class="results-pie-grid">
                            <?php if (!empty($indiv_labels)): ?>
                            <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                                <h4 style="font-size: 0.95rem; margin: 0 0 0.5rem 0; text-align: center;">Winner/Losers Games</h4>
                                <div style="position: relative; height: 220px;">
                                    <canvas id="indivWinChart"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($ranked_labels)): ?>
                            <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                                <h4 style="font-size: 0.95rem; margin: 0 0 0.5rem 0; text-align: center;">Ranked Games (1st Place)</h4>
                                <div style="position: relative; height: 220px;">
                                    <canvas id="rankedWinChart"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($team_labels)): ?>
                            <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                                <h4 style="font-size: 0.95rem; margin: 0 0 0.5rem 0; text-align: center;">Team Games</h4>
                                <div style="position: relative; height: 220px;">
                                    <canvas id="teamWinChart"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($coop_labels)): ?>
                            <div style="background: var(--color-surface-muted, rgba(255,255,255,0.04)); border: 1px solid var(--color-border); border-radius: 12px; padding: 1rem;">
                                <h4 style="font-size: 0.95rem; margin: 0 0 0.5rem 0; text-align: center;">Cooperative Games</h4>
                                <div style="position: relative; height: 220px;">
                                    <canvas id="coopWinChart"></canvas>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </details>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
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
                '#2563eb', // Blue
                '#f97316', // Orange
                '#16a34a', // Green
                '#9333ea', // Purple
                '#dc2626', // Red
                '#06b6d4', // Cyan
                '#eab308', // Yellow
                '#ec4899', // Pink
                '#84cc16', // Lime
                '#b45309', // Brown
                '#6366f1', // Indigo
                '#0d9488'  // Teal
            ];

            const gamesLabels = <?php echo json_encode($all_games_labels ?? []); ?>;
            const gamesData = <?php echo json_encode($all_games_data ?? []); ?>;

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

            makePieChart('selectedGameWinChart', <?php echo json_encode($selected_game_labels ?? []); ?>, <?php echo json_encode($selected_game_data ?? []); ?>);
            makePieChart('indivWinChart', <?php echo json_encode($indiv_labels ?? []); ?>, <?php echo json_encode($indiv_data ?? []); ?>);
            makePieChart('rankedWinChart', <?php echo json_encode($ranked_labels ?? []); ?>, <?php echo json_encode($ranked_data ?? []); ?>);
            makePieChart('teamWinChart', <?php echo json_encode($team_labels ?? []); ?>, <?php echo json_encode($team_data ?? []); ?>);
            makePieChart('coopWinChart', <?php echo json_encode($coop_labels ?? []); ?>, <?php echo json_encode($coop_data ?? []); ?>);

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
        </script>
        <?php
    }

    /**
     * Render Members Table
     */
    public static function renderMembersTable(array $members, array $options = []): void {
        $isAdmin = !empty($options['is_admin']);
        $baseUrl = $options['base_url'] ?? '';
        $sort = $options['sort'] ?? 'nickname';
        $order = strtolower($options['order'] ?? 'asc');
        $search = $options['search'] ?? '';
        $statusFilter = $options['status_filter'] ?? 'all';
        $clubId = (int)($options['club_id'] ?? 0);

        $oppositeOrder = ($order === 'asc') ? 'desc' : 'asc';
        ?>
        <div class="card-toolbar" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn--primary" id="add-member-btn" onclick="toggleAddMemberForm()" style="white-space: nowrap; flex-shrink: 0; <?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? 'visibility:hidden;' : ''; ?>">
                    Add a Member
                </button>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-left: auto;">
                <form method="GET" class="toolbar-group" id="filter-form" action="<?php echo htmlspecialchars(parse_url($baseUrl, PHP_URL_PATH)); ?>" style="display: flex; gap: 0.5rem; margin: 0;">
                    <?php
                    $urlParts = parse_url($baseUrl);
                    if (!empty($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        foreach ($queryParams as $k => $v) {
                            if (!in_array($k, ['sort', 'order', 'search', 'status'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
                            }
                        }
                    }
                    ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                    <div class="input-group" style="display: flex; gap: 0;">
                        <input type="text" name="search" placeholder="Search members..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="width: 220px;" oninput="filterTableRows(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();filterTableRows(this);}" <?php echo $search !== '' ? 'autofocus onfocus="this.setSelectionRange(this.value.length, this.value.length)"' : ''; ?>>
                        <select name="status" id="status-filter" class="form-control form-control--sm" onchange="this.form.submit()" style="width: 120px;">
                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </form>

                <?php if ($isAdmin): ?>
                    <form method="POST" id="bulk-action-form" style="display: inline-block; margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($options['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="club_id" value="<?php echo $clubId; ?>">
                        <select name="bulk_action" id="bulk-action-select" class="form-control form-control--sm" onchange="executeBulkAction(this)" style="width: 140px;">
                            <option value="">Bulk Actions</option>
                            <option value="bulk_activate">Activate Selected</option>
                            <option value="bulk_deactivate">Deactivate Selected</option>
                            <option value="bulk_email">Send Email to</option>
                            <option value="bulk_delete">Delete Selected</option>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table members-table">
                <thead>
                    <tr>
                        <?php if ($isAdmin): ?>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="select-all" class="form-check-input"></th>
                            <th>
                                <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'member_name', 'order' => ($sort === 'member_name' ? $oppositeOrder : 'asc'), 'search' => $search, 'status' => $statusFilter]); ?>" class="table-sort-link sort-link">
                                    <span>Name</span>
                                    <?php if ($sort === 'member_name'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endif; ?>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'nickname', 'order' => ($sort === 'nickname' ? $oppositeOrder : 'asc'), 'search' => $search, 'status' => $statusFilter]); ?>" class="table-sort-link sort-link">
                                <span>Nickname</span>
                                <?php if ($sort === 'nickname'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Teams</th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'total_wins', 'order' => ($sort === 'total_wins' ? $oppositeOrder : 'desc'), 'search' => $search, 'status' => $statusFilter]); ?>" class="table-sort-link sort-link">
                                <span>Total Wins</span>
                                <?php if ($sort === 'total_wins'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'championships_count', 'order' => ($sort === 'championships_count' ? $oppositeOrder : 'desc'), 'search' => $search, 'status' => $statusFilter]); ?>" class="table-sort-link sort-link">
                                <span>Trophies</span>
                                <?php if ($sort === 'championships_count'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? 8 : 5; ?>" class="text-center text-muted" style="padding: 1.5rem;">No members found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <?php if ($isAdmin): ?>
                                    <td style="text-align: center;"><input type="checkbox" name="selected_members[]" value="<?php echo $m['member_id']; ?>" class="member-checkbox form-check-input"></td>
                                    <td data-label="Name"><strong><?php echo htmlspecialchars($m['member_name'] ?? ''); ?></strong></td>
                                <?php endif; ?>
                                <td data-label="Nickname"><strong><?php echo htmlspecialchars($m['nickname'] ?: 'Member'); ?></strong></td>
                                <td data-label="Teams"><?php echo htmlspecialchars($m['member_teams'] ?? '—'); ?></td>
                                <td data-label="Total Wins"><span class="badge badge--success"><?php echo (int)($m['total_wins'] ?? 0); ?> wins</span></td>
                                <td data-label="Trophies"><?php echo (int)($m['championships_count'] ?? 0) > 0 ? '🏆 ' . (int)$m['championships_count'] : '—'; ?></td>
                                <td data-label="Status">
                                    <?php if (($m['status'] ?? 'active') === 'active'): ?>
                                        <span class="badge badge--success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?>
                                    <td data-label="Actions">
                                        <div style="display:flex; gap:0.5rem; align-items:center;">
                                            <a href="edit_member.php?member_id=<?php echo $m['member_id']; ?>&club_id=<?php echo $clubId; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($isAdmin): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('.member-checkbox').forEach(cb => cb.checked = this.checked);
                });
            }
        });
        function executeBulkAction(selectEl) {
            const action = selectEl.value;
            if (!action) return;
            const checked = document.querySelectorAll('.member-checkbox:checked');
            if (checked.length === 0) {
                if (typeof showConfirmDialog === 'function') {
                    showConfirmDialog(null, {
                        title: 'Selection Required',
                        message: 'Please select at least one member to perform this action.',
                        confirmText: 'OK',
                        type: 'primary',
                        onConfirm: () => { selectEl.value = ''; },
                        onCancel: () => { selectEl.value = ''; }
                    });
                } else {
                    alert('Please select at least one member.');
                    selectEl.value = '';
                }
                return;
            }
            const submitBulkAction = () => {
                const form = document.getElementById('bulk-action-form');
                checked.forEach(cb => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'selected_members[]';
                    hidden.value = cb.value;
                    form.appendChild(hidden);
                });
                form.submit();
            };

            if (action === 'bulk_delete') {
                const count = checked.length;
                if (typeof showConfirmDialog === 'function') {
                    showConfirmDialog(null, {
                        title: '⚠️ Confirm Bulk Delete',
                        message: `Are you sure you want to delete ${count} selected member${count > 1 ? 's' : ''}?`,
                        warningMessage: 'This action is permanent and cannot be undone.',
                        confirmText: 'Delete Selected',
                        cancelText: 'Cancel',
                        type: 'danger',
                        onConfirm: () => {
                            submitBulkAction();
                        },
                        onCancel: () => {
                            selectEl.value = '';
                        }
                    });
                } else if (confirm('Are you sure you want to delete the selected member(s)?')) {
                    submitBulkAction();
                } else {
                    selectEl.value = '';
                }
            } else {
                submitBulkAction();
            }
        }
        </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Render Teams Table
     */
    public static function renderTeamsTable(array $teams, array $options = []): void {
        $isAdmin = !empty($options['is_admin']);
        $baseUrl = $options['base_url'] ?? '';
        $search = $options['search'] ?? '';
        $clubId = (int)($options['club_id'] ?? 0);
        ?>
        <div class="card-toolbar" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn--primary" id="toggle-add-team-btn" onclick="toggleAddTeamForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create_team') ? 'visibility:hidden;' : ''; ?>">
                    Add a Team
                </button>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-left: auto;">
                <form method="GET" class="toolbar-group" id="filter-form" action="<?php echo htmlspecialchars(parse_url($baseUrl, PHP_URL_PATH)); ?>" style="display: flex; gap: 0.5rem; margin: 0;">
                    <?php
                    $urlParts = parse_url($baseUrl);
                    if (!empty($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        foreach ($queryParams as $k => $v) {
                            if (!in_array($k, ['search'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
                            }
                        }
                    }
                    ?>
                    <div class="input-group" style="display: flex; gap: 0;">
                        <input type="text" name="search" placeholder="Search teams..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="width: 220px;" oninput="filterTableRows(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();filterTableRows(this);}" <?php echo $search !== '' ? 'autofocus onfocus="this.setSelectionRange(this.value.length, this.value.length)"' : ''; ?>>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="text-align:left;">Team Name</th>
                        <th style="text-align:left;">Members</th>
                        <th style="text-align:left;">Created</th>
                        <th style="text-align:left;">Total Plays</th>
                        <th style="text-align:left;">Wins</th>
                        <?php if ($isAdmin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($teams)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? 6 : 5; ?>" class="text-center text-muted" style="padding: 1.5rem;">No teams found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($teams as $team): ?>
                            <?php
                            $raw_list = $team['members_list'] ?? [
                                $team['member1_nickname'] ?? $team['m1_nick'] ?? null,
                                $team['member2_nickname'] ?? $team['m2_nick'] ?? null,
                                $team['member3_nickname'] ?? $team['m3_nick'] ?? null,
                                $team['member4_nickname'] ?? $team['m4_nick'] ?? null,
                            ];
                            $members_list = [];
                            foreach ($raw_list as $item) {
                                $nick = is_array($item) ? ($item['nickname'] ?? '') : (string)$item;
                                if ($nick !== '') {
                                    $members_list[] = $nick;
                                }
                            }
                            ?>
                            <tr>
                                <td data-label="Team Name"><strong><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                                <td data-label="Members">
                                    <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                                        <?php foreach ($members_list as $nickname): ?>
                                            <span class="club-stat-pill"><?php echo htmlspecialchars($nickname); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td data-label="Created" style="font-size:0.85rem;color:var(--color-text-muted);"><?php echo !empty($team['created_at']) ? date('Y/m/d', strtotime($team['created_at'])) : '—'; ?></td>
                                <td data-label="Total Plays"><?php echo (int)($team['total_plays'] ?? 0); ?></td>
                                <td data-label="Wins"><span class="badge badge--success"><?php echo (int)($team['wins'] ?? $team['win_count'] ?? 0); ?> wins</span></td>
                                <?php if ($isAdmin): ?>
                                    <td data-label="Actions">
                                        <div style="display:flex; gap:0.5rem; align-items:center;">
                                            <a href="edit_team.php?team_id=<?php echo $team['team_id']; ?>&club_id=<?php echo $clubId; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Champions Table
     */
    public static function renderChampionsTable(array $champions, array $options = []): void {
        $isAdmin = !empty($options['is_admin']);
        $baseUrl = $options['base_url'] ?? '';
        $sort = $options['sort'] ?? 'date';
        $order = strtolower($options['order'] ?? 'desc');
        $search = $options['search'] ?? '';
        $clubId = (int)($options['club_id'] ?? 0);

        $oppositeOrder = ($order === 'asc') ? 'desc' : 'asc';
        ?>
        <div class="card-toolbar" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn--primary" id="add-champion-btn" onclick="toggleAddChampionForm()" style="<?php echo (isset($_POST['action']) && $_POST['action'] === 'create') ? 'visibility:hidden;' : ''; ?>">
                    Add Champion
                </button>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-left: auto;">
                <form method="GET" class="toolbar-group" id="filter-form" action="<?php echo htmlspecialchars(parse_url($baseUrl, PHP_URL_PATH)); ?>" style="display: flex; gap: 0.5rem; margin: 0;">
                    <?php
                    $urlParts = parse_url($baseUrl);
                    if (!empty($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        foreach ($queryParams as $k => $v) {
                            if (!in_array($k, ['sort', 'order', 'search'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
                            }
                        }
                    }
                    ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                    <div class="input-group" style="display: flex; gap: 0;">
                        <input type="text" name="search" placeholder="Search champions..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="width: 220px;" oninput="filterTableRows(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();filterTableRows(this);}" <?php echo $search !== '' ? 'autofocus onfocus="this.setSelectionRange(this.value.length, this.value.length)"' : ''; ?>>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <?php $isMemberSort = ($sort === 'member_name' || $sort === 'nickname'); ?>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => ($isAdmin ? 'member_name' : 'nickname'), 'order' => ($isMemberSort ? $oppositeOrder : 'asc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Member</span>
                                <?php if ($isMemberSort): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'date', 'order' => ($sort === 'date' ? $oppositeOrder : 'desc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Date Awarded</span>
                                <?php if ($sort === 'date'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'champ_comments', 'order' => ($sort === 'champ_comments' ? $oppositeOrder : 'asc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Title / Award</span>
                                <?php if ($sort === 'champ_comments'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <?php if ($isAdmin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($champions)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? 5 : 4; ?>" class="text-center text-muted" style="padding: 1.5rem;">No champions recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($champions as $idx => $champion): ?>
                            <?php $isCurrent = ($idx === 0); ?>
                            <tr>
                                <td data-label="Status">
                                    <?php if (!empty($champion['is_current']) || $isCurrent): ?>
                                        <span class="badge badge--success">👑 Current Champion</span>
                                    <?php else: ?>
                                        <span class="badge badge--neutral">Former Champion</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Member">
                                    <strong>
                                        <?php
                                        if ($isAdmin) {
                                            $disp = htmlspecialchars($champion['member_name'] ?? 'Member');
                                            if (!empty($champion['nickname'])) {
                                                $disp .= ' (' . htmlspecialchars($champion['nickname']) . ')';
                                            }
                                            echo $disp;
                                        } else {
                                            echo htmlspecialchars($champion['nickname'] ?: 'Member');
                                        }
                                        ?>
                                    </strong>
                                </td>
                                <td data-label="Date Awarded"><?php echo date('Y/m/d', strtotime($champion['date'])); ?></td>
                                <td data-label="Title / Award"><?php echo htmlspecialchars($champion['champ_comments'] ?: 'Club Champion'); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td data-label="Actions">
                                        <div style="display:flex; gap:0.5rem; align-items:center;">
                                            <a href="edit_champion.php?club_id=<?php echo $clubId; ?>&champion_id=<?php echo $champion['ID']; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Games Table
     */
    public static function renderGamesTable(array $games, array $options = []): void {
        $isAdmin = !empty($options['is_admin']);
        $baseUrl = $options['base_url'] ?? '';
        $sort = $options['sort'] ?? 'game_name';
        $order = strtolower($options['order'] ?? 'asc');
        $search = $options['search'] ?? '';
        $clubId = (int)($options['club_id'] ?? 0);
        $imagePrefix = $isAdmin ? '../' : '';

        $oppositeOrder = ($order === 'asc') ? 'desc' : 'asc';
        ?>
        <div class="card-toolbar" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn--primary" id="add-game-btn" onclick="toggleAddGameForm()" style="<?php echo ((isset($_POST['action']) && $_POST['action'] === 'create') || (isset($_GET['action']) && $_GET['action'] === 'add')) ? 'visibility:hidden;' : ''; ?>">
                    Add a Game
                </button>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-left: auto;">
                <form method="GET" class="toolbar-group" id="filter-form" action="<?php echo htmlspecialchars(parse_url($baseUrl, PHP_URL_PATH)); ?>" style="display: flex; gap: 0.5rem; margin: 0;">
                    <?php
                    $urlParts = parse_url($baseUrl);
                    if (!empty($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        foreach ($queryParams as $k => $v) {
                            if (!in_array($k, ['sort', 'order', 'search'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
                            }
                        }
                    }
                    ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                    <div class="input-group" style="display: flex; gap: 0;">
                        <input type="text" name="search" placeholder="Search games..." value="<?php echo htmlspecialchars($search); ?>" class="form-control" style="width: 220px;" oninput="filterTableRows(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();filterTableRows(this);}" <?php echo $search !== '' ? 'autofocus onfocus="this.setSelectionRange(this.value.length, this.value.length)"' : ''; ?>>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: left;">Image</th>
                        <th style="text-align: left;">
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'game_name', 'order' => ($sort === 'game_name' ? $oppositeOrder : 'asc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Game Name</span>
                                <?php if ($sort === 'game_name'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Players</th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'game_type', 'order' => ($sort === 'game_type' ? $oppositeOrder : 'asc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Type</span>
                                <?php if ($sort === 'game_type'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'last_played', 'order' => ($sort === 'last_played' ? $oppositeOrder : 'desc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Last Played</span>
                                <?php if ($sort === 'last_played'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'total_plays', 'order' => ($sort === 'total_plays' ? $oppositeOrder : 'desc'), 'search' => $search]); ?>" class="table-sort-link sort-link">
                                <span>Total Plays</span>
                                <?php if ($sort === 'total_plays'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <?php if ($isAdmin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($games)): ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? 7 : 6; ?>" class="text-center text-muted" style="padding: 1.5rem;">No games found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($games as $game): ?>
                            <tr>
                                <td data-label="Image">
                                    <?php
                                    $isDemoMode = isset($_GET['demo']) || isset($_GET['preview']);
                                    if (!empty($game['game_image']) && !$isDemoMode):
                                    ?>
                                        <img src="<?php echo htmlspecialchars(get_game_image_url($game['game_image'], $imagePrefix)); ?>" alt="" class="game-thumbnail" loading="lazy" onerror="this.onerror=null; this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='inline-block';">
                                        <div class="game-thumbnail game-thumbnail--skeleton" style="display:none;" title="No image uploaded">🎲</div>
                                    <?php else: ?>
                                        <div class="game-thumbnail game-thumbnail--skeleton" title="Game">🎲</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Game Name" style="text-align: left;"><strong><?php echo htmlspecialchars($game['game_name']); ?></strong></td>
                                <td data-label="Players"><?php echo ($game['min_players'] ?? 1) . '-' . ($game['max_players'] ?? 4); ?></td>
                                <td data-label="Type"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $game['game_type'] ?? 'winner_losers'))); ?></td>
                                <td data-label="Last Played"><?php echo !empty($game['last_played']) ? date('Y/m/d', strtotime($game['last_played'])) : '—'; ?></td>
                                <td data-label="Total Plays"><?php echo (int)($game['total_plays'] ?? 0); ?></td>
                                <?php if ($isAdmin): ?>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit_game.php?club_id=<?php echo $game['club_id']; ?>&game_id=<?php echo $game['game_id']; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                            <a href="manage_results.php?club_id=<?php echo $game['club_id']; ?>&game_id=<?php echo $game['game_id']; ?>" class="btn btn--small btn--subtle">Results</a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Results Table
     */
    public static function renderResultsTable(array $results, array $all_games, array $options = []): void {
        $results = array_values(array_filter($results, function($r) {
            $w = trim(str_replace(' (Team)', '', $r['winner_name'] ?? ''));
            return !empty($w) && !in_array($w, ['Unknown', 'Unknown Member', 'Unknown Team', 'Member'], true);
        }));

        $isAdmin = !empty($options['is_admin']);
        $baseUrl = $options['base_url'] ?? '';
        $sort = $options['sort'] ?? 'played_at';
        $order = strtolower($options['order'] ?? 'desc');
        $gameId = $options['game_id'] ?? null;
        $clubId = (int)($options['club_id'] ?? 0);

        $oppositeOrder = ($order === 'asc') ? 'desc' : 'asc';
        ?>
        <div class="card-toolbar" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.25rem;">
            <?php if ($isAdmin): ?>
                <a href="<?php echo $gameId ? 'add_result.php?club_id=' . $clubId . '&game_id=' . $gameId : 'club_new_results.php?club_id=' . $clubId; ?>" class="btn btn--primary" style="white-space: nowrap; flex-shrink: 0;">
                    Add Result
                </a>
            <?php else: ?>
                <div></div>
            <?php endif; ?>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-left: auto;">
                <form method="GET" class="toolbar-group" id="filter-form" action="<?php echo htmlspecialchars(parse_url($baseUrl, PHP_URL_PATH)); ?>" style="display: flex; gap: 0.5rem; margin: 0;">
                    <?php
                    $urlParts = parse_url($baseUrl);
                    if (!empty($urlParts['query'])) {
                        parse_str($urlParts['query'], $queryParams);
                        foreach ($queryParams as $k => $v) {
                            if (!in_array($k, ['game_id', 'sort', 'order', 'search'])) {
                                echo '<input type="hidden" name="' . htmlspecialchars((string)$k) . '" value="' . htmlspecialchars((string)$v) . '">';
                            }
                        }
                    }
                    ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                    <div class="input-group" style="display: flex; gap: 0;">
                        <input type="text" name="search" placeholder="Search results..." class="form-control" style="width: 220px;" oninput="filterTableRows(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();filterTableRows(this);}">
                        <select name="game_id" class="form-control form-control--sm" onchange="this.form.submit()" style="width: 140px;">
                            <option value="">All Games</option>
                            <?php foreach ($all_games as $g_opt): ?>
                                <option value="<?php echo $g_opt['game_id']; ?>" <?php echo ($gameId == $g_opt['game_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($g_opt['game_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>

                <?php if ($isAdmin): ?>
                    <form method="POST" id="bulk-results-form" style="display: inline-block; margin: 0;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($options['csrf_token'] ?? ''); ?>">
                        <input type="hidden" name="club_id" value="<?php echo $clubId; ?>">
                        <select name="bulk_action" id="bulk-results-select" class="form-control form-control--sm" onchange="executeBulkResultsAction(this)" style="width: 140px;">
                            <option value="">Bulk Actions</option>
                            <option value="bulk_delete">Delete Selected</option>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if ($isAdmin): ?>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="select-all-results" class="form-check-input"></th>
                        <?php endif; ?>
                        <?php if (!$gameId): ?>
                            <th>
                                <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'game_name', 'order' => ($sort === 'game_name' ? $oppositeOrder : 'asc'), 'game_id' => $gameId]); ?>" class="table-sort-link sort-link">
                                    <span>Game</span>
                                    <?php if ($sort === 'game_name'): ?>
                                        <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                    <?php endif; ?>
                                </a>
                            </th>
                        <?php endif; ?>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'played_at', 'order' => ($sort === 'played_at' ? $oppositeOrder : 'asc'), 'game_id' => $gameId]); ?>" class="table-sort-link sort-link">
                                <span>Date Played</span>
                                <?php if ($sort === 'played_at'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'winner_name', 'order' => ($sort === 'winner_name' ? $oppositeOrder : 'asc'), 'game_id' => $gameId]); ?>" class="table-sort-link sort-link">
                                <span>Winner / Outcome</span>
                                <?php if ($sort === 'winner_name'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?php echo self::buildUrl($baseUrl, ['sort' => 'game_type', 'order' => ($sort === 'game_type' ? $oppositeOrder : 'asc'), 'game_id' => $gameId]); ?>" class="table-sort-link sort-link">
                                <span>Type</span>
                                <?php if ($sort === 'game_type'): ?>
                                    <span class="table-sort-link__icon"><?php echo $order === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Duration</th>
                        <?php if ($isAdmin): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="<?php echo ($gameId ? ($isAdmin ? 6 : 4) : ($isAdmin ? 7 : 5)); ?>" class="text-center text-muted" style="padding: 1.5rem;">No results found.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($results as $result): ?>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="selected_results[]" value="<?php echo ($result['game_type'] ?? 'individual') . ':' . $result['result_id']; ?>" class="result-checkbox form-check-input">
                                </td>
                            <?php endif; ?>
                            <?php if (!$gameId): ?>
                                <td data-label="Game">
                                    <a href="<?php echo self::buildUrl($baseUrl, ['game_id' => $result['game_id']]); ?>" style="font-weight: 600; text-decoration: none; color: var(--color-primary);">
                                        <?php echo htmlspecialchars($result['game_name']); ?>
                                    </a>
                                </td>
                            <?php endif; ?>
                            <td data-label="Date Played"><?php echo date('Y/m/d', strtotime($result['played_at'])); ?></td>
                            <td data-label="Winner / Outcome"><?php echo htmlspecialchars($result['winner_name'] ?? ''); ?></td>
                            <td data-label="Type">
                                <span class="club-stat-pill">
                                    <?php
                                    $gt_label = match($result['game_type'] ?? '') {
                                        'winner_losers' => 'Winner/Losers',
                                        'ranked' => 'Ranked',
                                        'teams', 'team' => 'Teams',
                                        'cooperative' => 'Cooperative',
                                        default => ucfirst(str_replace('_', ' ', (string)($result['game_type'] ?? '')))
                                    };
                                    echo htmlspecialchars($gt_label);
                                    ?>
                                </span>
                            </td>
                            <td data-label="Duration"><?php echo !empty($result['duration']) ? (int)$result['duration'] . ' mins' : '-'; ?></td>
                            <?php if ($isAdmin): ?>
                                <td data-label="Actions">
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <a href="add_result.php?result_id=<?php echo $result['result_id']; ?>&type=<?php echo $result['game_type']; ?>" class="btn btn--small btn--secondary">View/Edit</a>
                                    </div>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($isAdmin): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllResults = document.getElementById('select-all-results');
            if (selectAllResults) {
                selectAllResults.addEventListener('change', function() {
                    document.querySelectorAll('.result-checkbox').forEach(cb => cb.checked = this.checked);
                });
            }
        });
        function executeBulkResultsAction(selectEl) {
            const action = selectEl.value;
            if (!action) return;
            const checked = document.querySelectorAll('.result-checkbox:checked');
            if (checked.length === 0) {
                if (typeof showConfirmDialog === 'function') {
                    showConfirmDialog(null, {
                        title: 'Selection Required',
                        message: 'Please select at least one result to perform this action.',
                        confirmText: 'OK',
                        type: 'primary',
                        onConfirm: () => { selectEl.value = ''; },
                        onCancel: () => { selectEl.value = ''; }
                    });
                } else {
                    alert('Please select at least one result.');
                    selectEl.value = '';
                }
                return;
            }
            if (action === 'bulk_delete') {
                const count = checked.length;
                const processSubmit = (pwd) => {
                    const form = document.getElementById('bulk-results-form');
                    const pwdInput = document.createElement('input');
                    pwdInput.type = 'hidden';
                    pwdInput.name = 'password';
                    pwdInput.value = pwd || '';
                    form.appendChild(pwdInput);

                    checked.forEach(cb => {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'selected_results[]';
                        hidden.value = cb.value;
                        form.appendChild(hidden);
                    });
                    form.submit();
                };

                if (typeof showConfirmDialog === 'function') {
                    showConfirmDialog(null, {
                        title: '⚠️ Confirm Bulk Delete',
                        message: `Are you sure you want to delete ${count} selected result${count > 1 ? 's' : ''}?`,
                        warningMessage: 'This action is permanent and cannot be undone.',
                        confirmText: 'Delete Selected',
                        cancelText: 'Cancel',
                        type: 'danger',
                        requirePassword: true,
                        onConfirm: (pwd) => {
                            processSubmit(pwd);
                        },
                        onCancel: () => {
                            selectEl.value = '';
                        }
                    });
                } else {
                    const pwd = prompt('Enter admin password to confirm bulk delete:');
                    if (!pwd) {
                        selectEl.value = '';
                        return;
                    }
                    processSubmit(pwd);
                }
            }
        }
        </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Helper to build URL with query params
     */
    private static function buildUrl(string $baseUrl, array $params): string {
        $parsed = parse_url($baseUrl);
        $existing = [];
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $existing);
        }
        foreach ($params as $k => $v) {
            if ($v === null || $v === '') {
                unset($existing[$k]);
            } else {
                $existing[$k] = (string)$v;
            }
        }
        $path = $parsed['path'] ?? '';
        return $path . (!empty($existing) ? '?' . http_build_query($existing) : '');
    }
}
?>
<script>
if (typeof filterTableRows !== 'function') {
    function filterTableRows(input) {
        const query = input.value.toLowerCase().trim();
        const parent = input.closest('.card-toolbar')?.parentElement || input.closest('.card, .container, body') || document;
        const table = parent.querySelector('.data-table, table');
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            if (row.cells.length === 1 && (row.cells[0].getAttribute('colspan') || row.classList.contains('no-results-row'))) return;
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="search"]').forEach(input => {
        if (input.value) filterTableRows(input);
    });
});
</script>
