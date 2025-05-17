// ... existing code ...

// Build the query to fetch game details including winners from team_game_results
$query = "SELECT g.*, 
          gr.winner as individual_winner, 
          tgr.winner as team_winner 
          FROM games g
          LEFT JOIN game_results gr ON g.game_id = gr.game_id
          LEFT JOIN team_game_results tgr ON g.game_id = tgr.game_id
          WHERE g.game_id = ?"; // Assuming game_id is passed to this page

$stmt = $pdo->prepare($query);
$stmt->execute([$game_id]); // Assuming $game_id is defined and holds the current game ID
$game_details = $stmt->fetch(PDO::FETCH_ASSOC);

// ... existing code ...