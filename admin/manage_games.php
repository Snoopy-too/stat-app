<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['is_super_admin']) || !$_SESSION['is_super_admin']) {
    header("Location: login.php");
    exit();
}

// Get club_id from URL
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : null;

// Validate club_id exists if provided
if ($club_id) {
    $stmt = $pdo->prepare("SELECT club_id FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Invalid club ID provided.";
        header("Location: dashboard.php");
        exit();
    }
}

// Handle game creation/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create' && !empty($_POST['game_name'])) {
            $stmt = $pdo->prepare("INSERT INTO games (club_id, game_name, min_players, max_players) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $club_id,
                trim($_POST['game_name']),
                $_POST['min_players'],
                $_POST['max_players']
            ]);
            $_SESSION['success'] = "Game added successfully!";
        }
        header("Location: manage_games.php" . ($club_id ? "?club_id=$club_id" : ""));
        exit();
    }
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'game_name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';
// Define valid sort columns
$valid_sort_columns = ['game_name', 'created_at', 'total_plays'];
$sort = in_array($sort, $valid_sort_columns) ? $sort : 'game_name';
$order = ($order === 'desc') ? 'desc' : 'asc';
// Build the query
$query = "SELECT g.*, c.club_name, 
          (COALESCE(gr.total, 0) + COALESCE(tgr.total, 0)) as total_plays 
          FROM games g 
          JOIN clubs c ON g.club_id = c.club_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM game_results 
              GROUP BY game_id
          ) gr ON g.game_id = gr.game_id
          LEFT JOIN (
              SELECT game_id, COUNT(result_id) as total 
              FROM team_game_results 
              GROUP BY game_id
          ) tgr ON g.game_id = tgr.game_id"; // Adjusted subqueries for accurate counting
$params = [];
if ($club_id) {
    $query .= " WHERE g.club_id = ?";
    $params[] = $club_id;
}
if ($search) {
    $query .= ($club_id ? " AND" : " WHERE") . " g.game_name LIKE ?";
    $params[] = "%$search%";
}
$query .= " GROUP BY g.game_id, c.club_id, c.club_name, g.game_name, g.min_players, g.max_players, g.created_at";
if ($sort === 'total_plays') {
    $query .= " ORDER BY total_plays $order, g.game_name ASC";
} else {
    $query .= " ORDER BY g.$sort $order";
}
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get club name if club_id is set
$club_name = '';
if ($club_id) {
    $stmt = $pdo->prepare("SELECT club_name FROM clubs WHERE club_id = ?");
    $stmt->execute([$club_id]);
    $club = $stmt->fetch(PDO::FETCH_ASSOC);
    $club_name = $club ? $club['club_name'] : '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games - Board Game Club StatApp</title>
    <link rel="stylesheet" href="../css/styles.css">
    <script src="../js/dark-mode.js"></script>
</head>
<body>
    <div class="header">
        <h1>Manage Games <?php echo $club_name ? "- $club_name" : ''; ?></h1>
        <a href="<?php echo $club_id ? 'manage_clubs.php' : 'dashboard.php'; ?>" class="btn">Back</a>
    </div>
    
    <div class="container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message message--success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message message--error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if ($club_id): ?>
        <div class="card">
            <h2>Add New Game</h2>
            <form method="POST" class="form">
                <div class="form-group">
                    <input type="text" name="game_name" placeholder="Game Name" required class="form-control">
                </div>
                <div class="form-group">
                    <input type="number" name="min_players" placeholder="Min Players" required min="1" class="form-control">
                </div>
                <div class="form-group">
                    <input type="number" name="max_players" placeholder="Max Players" required min="1" class="form-control">
                </div>
                <input type="hidden" name="action" value="create">
                <button type="submit" class="btn">Add Game</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="filters">
                <form method="GET" class="search-form">
                    <?php if ($club_id): ?>
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Search games..." 
                               value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                        <button type="submit" class="btn">Filter</button>
                        <a href="?<?php echo $club_id ? 'club_id=' . $club_id : ''; ?>" class="btn">Reset</a>
                    </div>
                </form>
            </div>

            <h2>Games List</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <?php if (!$club_id): ?><th>Club</th><?php endif; ?>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'game_name','order'=>$sort==='game_name'&&$order==='asc'?'desc':'asc'])); ?>" class="sort-link">
                                Game Name <?php echo $sort==='game_name'?($order==='asc'?'^':'v'):''; ?>
                            </a>
                        </th>
                        <th>Players</th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'created_at','order'=>$sort==='created_at'&&$order==='asc'?'desc':'asc'])); ?>" class="sort-link">
                                Added <?php echo $sort==='created_at'?($order==='asc'?'^':'v'):''; ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['sort'=>'total_plays','order'=>$sort==='total_plays'&&$order==='asc'?'desc':'asc'])); ?>" class="sort-link">
                                Total Plays <?php echo $sort==='total_plays'?($order==='asc'?'^':'v'):''; ?>
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($games as $game): ?>
                        <tr>
                            <?php if (!$club_id): ?><td data-label="Club"><?php echo htmlspecialchars($game['club_name']); ?></td><?php endif; ?>
                            <td data-label="Game Name"><?php echo htmlspecialchars($game['game_name']); ?></td>
                            <td data-label="Players"><?php echo $game['min_players'] . '-' . $game['max_players']; ?></td>
                            <td data-label="Added"><?php echo date('M j, Y', strtotime($game['created_at'])); ?></td>
                            <td data-label="Total Plays"><?php echo $game['total_plays']; ?></td>
                            <td>
                                <a href="edit_game.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                   class="btn">Edit</a>
                                <a href="results.php?club_id=<?php echo $club_id; ?>&game_id=<?php echo $game['game_id']; ?>" 
                                   class="btn">Results</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../js/mobile-menu.js"></script>
    <script src="../js/form-loading.js"></script>
    <script src="../js/confirmations.js"></script>
    <script src="../js/form-validation.js"></script>
    <script src="../js/empty-states.js"></script>
    <script src="../js/multi-step-form.js"></script>
    <script src="../js/breadcrumbs.js"></script>
</body>
<script>
(function() {
    // Save scroll position before navigating away (sort/filter links)
    function saveScrollPosition() {
        try {
            sessionStorage.setItem('manage_games_scroll', window.scrollY);
        } catch (e) {}
    }
    // Attach to all sort/filter links
    document.addEventListener('DOMContentLoaded', function() {
        var links = document.querySelectorAll('a.sort-link, .search-form button[type="submit"], .search-form .button');
        links.forEach(function(link) {
            link.addEventListener('click', saveScrollPosition);
        });
        // Restore scroll position
        var scroll = sessionStorage.getItem('manage_games_scroll');
        if (scroll !== null) {
            window.scrollTo(0, parseInt(scroll, 10));
            sessionStorage.removeItem('manage_games_scroll');
        }
    });
    // Also save on form submit (search/filter)
    var forms = document.querySelectorAll('.search-form');
    forms.forEach(function(form) {
        form.addEventListener('submit', saveScrollPosition);
    });
})();
</script>
</html>