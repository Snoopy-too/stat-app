<?php
/**
 * Game Service
 * Encapsulates data access and queries for game catalog and play statistics.
 */

require_once __DIR__ . '/../helpers.php';

class GameService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get all games for a specific club
     */
    public function getGamesByClub($clubId, $sortBy = 'name') {
        ensure_results_tables_exist($this->pdo);

        $orderBy = 'game_name ASC';
        if ($sortBy === 'plays') {
            $orderBy = 'plays DESC, game_name ASC';
        } elseif ($sortBy === 'recent') {
            $orderBy = 'last_played DESC, game_name ASC';
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT g.*, 
                       (
                           COALESCE((SELECT COUNT(DISTINCT COALESCE(session_id, CAST(result_id AS CHAR))) FROM game_results WHERE game_id = g.game_id), 0) +
                           COALESCE((SELECT COUNT(DISTINCT COALESCE(session_id, CAST(result_id AS CHAR))) FROM team_game_results WHERE game_id = g.game_id), 0) +
                           COALESCE((SELECT COUNT(DISTINCT COALESCE(session_id, CAST(result_id AS CHAR))) FROM cooperative_game_results WHERE game_id = g.game_id), 0)
                       ) as plays,
                       (
                           COALESCE((SELECT COUNT(DISTINCT COALESCE(session_id, CAST(result_id AS CHAR))) FROM game_results WHERE game_id = g.game_id), 0) +
                           COALESCE((SELECT COUNT(DISTINCT COALESCE(session_id, CAST(result_id AS CHAR))) FROM team_game_results WHERE game_id = g.game_id), 0) +
                           COALESCE((SELECT COUNT(DISTINCT COALESCE(session_id, CAST(result_id AS CHAR))) FROM cooperative_game_results WHERE game_id = g.game_id), 0)
                       ) as play_count,
                       NULLIF(
                           GREATEST(
                               COALESCE((SELECT MAX(played_at) FROM game_results WHERE game_id = g.game_id), '1970-01-01 00:00:00'),
                               COALESCE((SELECT MAX(played_at) FROM team_game_results WHERE game_id = g.game_id), '1970-01-01 00:00:00'),
                               COALESCE((SELECT MAX(played_at) FROM cooperative_game_results WHERE game_id = g.game_id), '1970-01-01 00:00:00')
                           ),
                           '1970-01-01 00:00:00'
                       ) as last_played
                FROM games g
                WHERE g.club_id = ?
                ORDER BY $orderBy
            ");
            $stmt->execute([$clubId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log("getGamesByClub failed: " . $e->getMessage());
            try {
                $stmt = $this->pdo->prepare("SELECT g.*, 0 as plays, 0 as play_count, NULL as last_played FROM games g WHERE g.club_id = ? ORDER BY game_name ASC");
                $stmt->execute([$clubId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable $ex) {
                return [];
            }
        }
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
