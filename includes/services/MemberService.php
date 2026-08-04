<?php
/**
 * Member Service
 * Encapsulates database queries for club members and teams.
 */

class MemberService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Fetch active members for a club
     */
    public function getActiveMembers($clubId) {
        $stmt = $this->pdo->prepare("SELECT * FROM members WHERE club_id = ? AND status = 'active' ORDER BY nickname ASC");
        $stmt->execute([$clubId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all members (including inactive) for admin management
     */
    public function getAllMembers($clubId) {
        $stmt = $this->pdo->prepare("SELECT * FROM members WHERE club_id = ? ORDER BY nickname ASC");
        $stmt->execute([$clubId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
