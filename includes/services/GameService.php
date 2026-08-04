<?php
/**
 * Game Service
 * Encapsulates data access and queries for game catalog and play statistics.
 */

class GameService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all games for a specific club
     */
    public function getGamesByClub($clubId, $sortBy = 'name') {
        $orderBy = 'game_name ASC';
        if ($sortBy === 'plays') {
            $orderBy = 'play_count DESC, game_name ASC';
        } elseif ($sortBy === 'recent') {
            $orderBy = 'game_id DESC';
        }

        $stmt = $this->pdo->prepare("
            SELECT g.*, 
                   (COALESCE((SELECT COUNT(*) FROM game_results WHERE game_id = g.game_id), 0) +
                    COALESCE((SELECT COUNT(*) FROM team_game_results WHERE game_id = g.game_id), 0)) as play_count
            FROM games g
            WHERE g.club_id = ?
            ORDER BY $orderBy
        ");
        $stmt->execute([$clubId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single game details by ID
     */
    public function getGameById($gameId) {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE game_id = ?");
        $stmt->execute([$gameId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
