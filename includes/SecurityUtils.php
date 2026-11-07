<?php
class SecurityUtils {
    private $pdo;
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
        $currentTime = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM csrf_tokens 
            WHERE token = ? AND session_id = ? AND expires_at > ?'
        );
        $stmt->execute([$token, $sessionId, $currentTime]);

        return (bool)$stmt->fetchColumn();
    }

    public function cleanExpiredTokens(): void {
        $this->pdo->exec(
            'DELETE FROM csrf_tokens WHERE expires_at < NOW()'
        );
    }

    public function checkLoginAttempts(string $email, string $ipAddress): bool {
        return true;
    }

    public function logLoginAttempt(string $email, string $ipAddress, bool $success): void {
        // No-op: Login attempts table dropped; authentication handled centrally
    }

    public function checkRegistrationAttempts(string $ipAddress): bool {
        return true;
    }

    public function logRegistrationAttempt(string $ipAddress, bool $success): void {
        // No-op: Registration attempts table dropped; registration handled centrally
    }

    public function clearLoginAttempts(string $email): void {
        // No-op
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
}