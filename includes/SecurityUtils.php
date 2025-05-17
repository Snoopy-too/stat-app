<?php
class SecurityUtils {
    private $pdo;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const MAX_REGISTRATION_ATTEMPTS = 3;
    private const ATTEMPT_WINDOW_MINUTES = 30;
    private const TOKEN_LENGTH = 64;
    private const TOKEN_EXPIRY_HOURS = 24;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function generateSecureToken(): string {
        return bin2hex(random_bytes(32));
    }

    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public function generateCSRFToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = $this->generateSecureToken();
        $sessionId = session_id();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+2 hours'));

        $stmt = $this->pdo->prepare(
            'INSERT INTO csrf_tokens (token, session_id, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$token, $sessionId, $expiresAt]);

        return $token;
    }

    public function verifyCSRFToken(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionId = session_id();
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM csrf_tokens 
            WHERE token = ? AND session_id = ? AND expires_at > NOW()'
        );
        $stmt->execute([$token, $sessionId]);

        return (bool)$stmt->fetchColumn();
    }

    public function cleanExpiredTokens(): void {
        $this->pdo->exec(
            'DELETE FROM csrf_tokens WHERE expires_at < NOW()'
        );
    }

    public function checkLoginAttempts(string $email, string $ipAddress): bool {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM login_attempts 
                WHERE (email = ? OR ip_address = ?) 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE) 
                AND is_successful = FALSE'
            );
            $stmt->execute([$email, $ipAddress, self::ATTEMPT_WINDOW_MINUTES]);

            return $stmt->fetchColumn() < self::MAX_LOGIN_ATTEMPTS;
        } catch (PDOException $e) {
            // If table doesn't exist, create it
            if ($e->getCode() == '42S02') {
                $this->createLoginAttemptsTable();
                return true;
            }
            throw $e;
        }
    }

    public function logLoginAttempt(string $email, string $ipAddress, bool $success): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_attempts (email, ip_address, is_successful) 
            VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $ipAddress, $success]);
    }

    public function checkRegistrationAttempts(string $ipAddress): bool {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) FROM registration_attempts 
                WHERE ip_address = ? 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)'
            );
            $stmt->execute([$ipAddress, self::ATTEMPT_WINDOW_MINUTES]);

            return $stmt->fetchColumn() < self::MAX_REGISTRATION_ATTEMPTS;
        } catch (PDOException $e) {
            // If table doesn't exist, create it
            if ($e->getCode() == '42S02') {
                $this->createRegistrationAttemptsTable();
                return true;
            }
            throw $e;
        }
    }

    public function logRegistrationAttempt(string $ipAddress, bool $success): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO registration_attempts (ip_address, is_successful) 
            VALUES (?, ?)'
        );
        $stmt->execute([$ipAddress, $success]);
    }

    public function generateEmailVerificationToken(): array {
        $token = $this->generateSecureToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRY_HOURS . ' hours'));

        return [
            'token' => $token,
            'expiry' => $expiry
        ];
    }

    public function sanitizeInput(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public function getClientIP(): string {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private function createLoginAttemptsTable(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                attempt_id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                email VARCHAR(255) NOT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_successful TINYINT(1) DEFAULT 0,
                INDEX idx_ip_email (ip_address, email),
                INDEX idx_attempt_time (attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function createRegistrationAttemptsTable(): void {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS registration_attempts (
                attempt_id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                is_successful TINYINT(1) DEFAULT 0,
                INDEX idx_ip_address (ip_address),
                INDEX idx_attempt_time (attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}