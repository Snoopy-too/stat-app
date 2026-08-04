<?php
/**
 * Club Service
 * Encapsulates database operations and logic for club details, metrics, and leaderboards.
 */

class ClubService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetch club details by ID or Slug, including calculated counts
     */
    public function getClubDetails($clubId = 0, $slug = '') {
        if ($clubId <= 0 && empty($slug)) {
            $stmt = $this->pdo->query("SELECT club_id FROM clubs ORDER BY club_id ASC LIMIT 1");
            $first = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($first) {
                $clubId = (int)$first['club_id'];
            } else {
                return null;
            }
        }

        $sql = "SELECT * FROM clubs WHERE ";
        $params = [];

        if ($clubId > 0) {
            $sql .= "club_id = ?";
            $params[] = $clubId;
        } else {
            $sql .= "slug = ?";
            $params[] = $slug;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $club = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$club) {
            return null;
        }

        $cid = $club['club_id'];

        $countStmt = $this->pdo->prepare("
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
                ) as all_plays) as play_count,
                (SELECT COUNT(DISTINCT DATE(gr.played_at)) FROM game_results gr
                    INNER JOIN games g ON gr.game_id = g.game_id
                    WHERE g.club_id = ?) as game_days_count,
                (SELECT COUNT(*) FROM champions WHERE club_id = ?) as champions_count
        ");
        $countStmt->execute([$cid, $cid, $cid, $cid, $cid, $cid]);
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC);

        return array_merge($club, $counts ?: []);
    }

    /**
     * Get top players leaderboard for a club
     */
    public function getLeaderboard($clubId, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT m.member_id, m.nickname, 
                   COUNT(CASE WHEN gr.rank = 1 THEN 1 END) as wins,
                   COUNT(gr.result_id) as total_plays
            FROM members m
            LEFT JOIN game_results gr ON m.member_id = gr.member_id
            WHERE m.club_id = ? AND m.status = 'active'
            GROUP BY m.member_id, m.nickname
            ORDER BY wins DESC, total_plays DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $clubId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
