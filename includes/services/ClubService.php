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
        try {
            if ($clubId <= 0 && empty($slug)) {
                $stmt = $this->pdo->query("SELECT club_id FROM clubs ORDER BY club_id ASC LIMIT 1");
                $first = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                if ($first) {
                    $clubId = (int)$first['club_id'];
                } else {
                    return null;
                }
            }

            // Inspect clubs table columns
            $colStmt = $this->pdo->query("SHOW COLUMNS FROM clubs");
            $clubCols = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $hasSlug = in_array('slug', $clubCols);

            if ($clubId > 0) {
                $stmt = $this->pdo->prepare("SELECT * FROM clubs WHERE club_id = ?");
                $stmt->execute([$clubId]);
            } else if ($hasSlug && !empty($slug)) {
                $stmt = $this->pdo->prepare("SELECT * FROM clubs WHERE slug = ?");
                $stmt->execute([$slug]);
            } else if (!empty($slug)) {
                $stmt = $this->pdo->prepare("SELECT * FROM clubs WHERE LOWER(club_name) = LOWER(?)");
                $stmt->execute([$slug]);
            } else {
                return null;
            }

            $club = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$club) {
                return null;
            }

            $cid = (int)$club['club_id'];

            // Inspect members table for status column
            $mColStmt = $this->pdo->query("SHOW COLUMNS FROM members");
            $memCols = $mColStmt ? $mColStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $hasMemberStatus = in_array('status', $memCols);

            $memberWhere = $hasMemberStatus ? "club_id = ? AND status = 'active'" : "club_id = ?";

            $memberCount = 0;
            $gameCount = 0;
            $playCount = 0;
            $gameDaysCount = 0;
            $championsCount = 0;

            // Member count
            try {
                $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE {$memberWhere}");
                $cStmt->execute([$cid]);
                $memberCount = (int)$cStmt->fetchColumn();
            } catch (Throwable $e) {}

            // Game count
            try {
                $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM games WHERE club_id = ?");
                $cStmt->execute([$cid]);
                $gameCount = (int)$cStmt->fetchColumn();
            } catch (Throwable $e) {}

            // Play count
            try {
                $cStmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM (
                        SELECT g.game_id FROM games g
                        INNER JOIN game_results gr ON g.game_id = gr.game_id
                        WHERE g.club_id = ?
                        UNION ALL
                        SELECT g.game_id FROM games g
                        INNER JOIN team_game_results tgr ON g.game_id = tgr.game_id
                        WHERE g.club_id = ?
                        UNION ALL
                        SELECT g.game_id FROM games g
                        INNER JOIN cooperative_game_results cgr ON g.game_id = cgr.game_id
                        WHERE g.club_id = ?
                    ) as all_plays
                ");
                $cStmt->execute([$cid, $cid, $cid]);
                $playCount = (int)$cStmt->fetchColumn();
            } catch (Throwable $e) {
                try {
                    $cStmt = $this->pdo->prepare("
                        SELECT COUNT(*) FROM games g
                        INNER JOIN game_results gr ON g.game_id = gr.game_id
                        WHERE g.club_id = ?
                    ");
                    $cStmt->execute([$cid]);
                    $playCount = (int)$cStmt->fetchColumn();
                } catch (Throwable $e2) {}
            }

            // Game days count
            try {
                $cStmt = $this->pdo->prepare("
                    SELECT COUNT(DISTINCT play_date) FROM (
                        SELECT DATE(gr.played_at) as play_date FROM game_results gr INNER JOIN games g ON gr.game_id = g.game_id WHERE g.club_id = ?
                        UNION ALL
                        SELECT DATE(tgr.played_at) as play_date FROM team_game_results tgr INNER JOIN games g ON tgr.game_id = g.game_id WHERE g.club_id = ?
                        UNION ALL
                        SELECT DATE(cgr.played_at) as play_date FROM cooperative_game_results cgr INNER JOIN games g ON cgr.game_id = g.game_id WHERE g.club_id = ?
                    ) as all_dates
                ");
                $cStmt->execute([$cid, $cid, $cid]);
                $gameDaysCount = (int)$cStmt->fetchColumn();
            } catch (Throwable $e) {}

            // Champions count
            try {
                $cStmt = $this->pdo->prepare("SELECT COUNT(*) FROM champions WHERE club_id = ?");
                $cStmt->execute([$cid]);
                $championsCount = (int)$cStmt->fetchColumn();
            } catch (Throwable $e) {}

            return array_merge($club, [
                'member_count' => $memberCount,
                'game_count' => $gameCount,
                'play_count' => $playCount,
                'game_days_count' => $gameDaysCount,
                'champions_count' => $championsCount
            ]);

        } catch (Throwable $e) {
            error_log("ClubService::getClubDetails Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get top players leaderboard for a club
     */
    public function getLeaderboard($clubId, $limit = 5) {
        try {
            $mColStmt = $this->pdo->query("SHOW COLUMNS FROM members");
            $memCols = $mColStmt ? $mColStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $hasMemberStatus = in_array('status', $memCols);

            $memberWhere = $hasMemberStatus ? "m.club_id = ? AND m.status = 'active'" : "m.club_id = ?";

            $stmt = $this->pdo->prepare("
                SELECT m.member_id, m.nickname, 
                       COUNT(CASE WHEN gr.rank = 1 THEN 1 END) as wins,
                       COUNT(gr.result_id) as total_plays
                FROM members m
                LEFT JOIN game_results gr ON m.member_id = gr.member_id
                WHERE {$memberWhere}
                GROUP BY m.member_id, m.nickname
                ORDER BY wins DESC, total_plays DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, (int)$clubId, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log("ClubService::getLeaderboard Error: " . $e->getMessage());
            return [];
        }
    }
}
