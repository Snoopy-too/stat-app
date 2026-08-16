<?php

class SSOHelper {
    public static function checkAndSyncSession() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $rawCookie = $_COOKIE['session_id'] ?? '';

        // If currently logged in via SSO, verify TFD session is still active
        if (!empty($_SESSION['sso_tfd']) && !empty($_SESSION['admin_id'])) {
            if (empty($rawCookie)) {
                self::clearSession();
                return;
            }
            $tfdUser = self::getTfdUserFromCookie($rawCookie);
            if (!$tfdUser) {
                self::clearSession();
                return;
            }
            $currentUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
            if (!empty($tfdUser['username']) && strtolower($tfdUser['username']) !== strtolower($currentUsername)) {
                self::loginAdminByEmailOrUsername($tfdUser['email'] ?? '', $tfdUser['username'] ?? '');
            }
            return;
        }

        // If not logged in in StatApp, check if TFD cookie is present
        if (empty($_SESSION['admin_id']) && !empty($rawCookie)) {
            $tfdUser = self::getTfdUserFromCookie($rawCookie);
            if ($tfdUser) {
                self::loginAdminByEmailOrUsername($tfdUser['email'] ?? '', $tfdUser['username'] ?? '');
            }
        }
    }

    private static function getPdo() {
        static $pdo = null;
        if ($pdo === null) {
            try {
                require_once __DIR__ . '/../config/env.php';
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                    DB_USER,
                    DB_PASS,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );
            } catch (PDOException $e) {
                return null;
            }
        }
        return $pdo;
    }

    public static function getTfdUserFromCookie($rawCookie) {
        if (empty($rawCookie)) return null;

        $sessionId = $rawCookie;
        if (strpos($sessionId, 's:') === 0) {
            $sessionId = substr($sessionId, 2);
            $dotPos = strpos($sessionId, '.');
            if ($dotPos !== false) {
                $sessionId = substr($sessionId, 0, $dotPos);
            }
        }

        $pdo = self::getPdo();
        if (!$pdo) return null;

        try {
            $stmt = $pdo->prepare("SELECT data, expires FROM KRED.sessions WHERE session_id = ? AND expires > UNIX_TIMESTAMP()");
            $stmt->execute([$sessionId]);
            $row = $stmt->fetch();
            if (!$row || empty($row['data'])) return null;

            $sessData = json_decode($row['data'], true);
            $userId = $sessData['passport']['user'] ?? null;
            if (!$userId) return null;

            $uStmt = $pdo->prepare("SELECT id, username, email, role FROM KRED.users WHERE id = ?");
            $uStmt->execute([$userId]);
            return $uStmt->fetch() ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function loginAdminByEmailOrUsername($email, $username = null) {
        $pdo = self::getPdo();
        if (!$pdo) return false;

        try {
            $admin = null;
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT admin_id, username, admin_type, is_deactivated FROM statapp.admin_users WHERE email = ?");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();
            }
            if (!$admin && !empty($username)) {
                $stmt = $pdo->prepare("SELECT admin_id, username, admin_type, is_deactivated FROM statapp.admin_users WHERE username = ?");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();
            }

            if (!$admin && (!empty($email) || !empty($username))) {
                $insUser = !empty($username) ? $username : explode('@', $email)[0];
                $insEmail = !empty($email) ? $email : $insUser . '@theflyingdutchmen.games';
                $insStmt = $pdo->prepare("INSERT INTO statapp.admin_users (username, email, password_hash, admin_type) VALUES (?, ?, '', 'multi_club')");
                $insStmt->execute([$insUser, $insEmail]);
                $newId = $pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT admin_id, username, admin_type, is_deactivated FROM statapp.admin_users WHERE admin_id = ?");
                $stmt->execute([$newId]);
                $admin = $stmt->fetch();
            }

            if ($admin && empty($admin['is_deactivated'])) {
                $_SESSION['is_super_admin'] = true;
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_id'] = (int)$admin['admin_id'];
                $_SESSION['user_id'] = (int)$admin['admin_id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['username'] = $admin['username'];
                $_SESSION['admin_type'] = $admin['admin_type'] ?? 'multi_club';
                $_SESSION['login_time'] = time();
                $_SESSION['sso_tfd'] = true;

                // Determine default club context
                $userClubsStmt = $pdo->prepare("SELECT c.club_id, ca.is_default FROM statapp.clubs c JOIN statapp.club_admins ca ON c.club_id = ca.club_id WHERE ca.admin_id = ? ORDER BY c.club_name ASC");
                $userClubsStmt->execute([$admin['admin_id']]);
                $userClubs = $userClubsStmt->fetchAll(PDO::FETCH_ASSOC);

                $defaultClubs = array_values(array_filter($userClubs, function($c) { return !empty($c['is_default']); }));
                $defaultCount = count($defaultClubs);

                if ($defaultCount === 1) {
                    $defClubId = (int)$defaultClubs[0]['club_id'];
                    $_SESSION['current_club_id'] = $defClubId;
                    $_SESSION['club_id'] = $defClubId;
                } elseif (count($userClubs) === 1) {
                    $singleClubId = (int)$userClubs[0]['club_id'];
                    $_SESSION['current_club_id'] = $singleClubId;
                    $_SESSION['club_id'] = $singleClubId;
                } else {
                    unset($_SESSION['current_club_id'], $_SESSION['club_id']);
                }

                return true;
            }
        } catch (Exception $e) {
            error_log("SSO login failed: " . $e->getMessage());
        }

        return false;
    }

    public static function loginAdminByEmail($email) {
        return self::loginAdminByEmailOrUsername($email);
    }

    private static function clearSession() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
    }
}
