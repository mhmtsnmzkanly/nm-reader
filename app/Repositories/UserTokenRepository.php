<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeInterface;
use PDO;

final class UserTokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createToken(
        string $type,
        string $tokenHash,
        DateTimeInterface $expiresAt,
        ?string $userId = null,
        ?string $email = null,
        ?string $sessionKey = null
    ): void {
        $sql = 'INSERT INTO user_tokens (user_id, session_key, type, email, token_hash, expires_at, used_at, created_at)
                VALUES (:user_id, :session_key, :type, :email, :token_hash, :expires_at, NULL, NOW())';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'session_key' => $sessionKey,
            'type' => $type,
            'email' => $email,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findValidToken(string $tokenHash, string $type): ?array
    {
        $sql = 'SELECT t.id, t.user_id, t.session_key, t.type, t.email, t.token_hash, t.expires_at, t.created_at,
                       u.username, u.email AS user_email, u.email_verified_at
                FROM user_tokens t
                LEFT JOIN users u ON u.id = t.user_id
                WHERE t.token_hash = :token_hash
                  AND t.type = :type
                  AND t.used_at IS NULL
                  AND t.expires_at > NOW()
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'token_hash' => $tokenHash,
            'type' => $type,
        ]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function consumeToken(string $tokenHash): void
    {
        $sql = 'UPDATE user_tokens SET used_at = NOW() WHERE token_hash = :token_hash AND used_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token_hash' => $tokenHash]);
    }

    public function revokeTokensBySessionKey(string $sessionKey): void
    {
        $sql = 'UPDATE user_tokens SET used_at = NOW() WHERE session_key = :session_key AND used_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['session_key' => $sessionKey]);
    }

    public function revokeActiveTokensForUser(string $userId, string $type): void
    {
        $sql = 'UPDATE user_tokens SET used_at = NOW() WHERE user_id = :user_id AND type = :type AND used_at IS NULL';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
        ]);
    }

    public function findValidRefreshToken(string $tokenHash): ?array
    {
        $sql = 'SELECT t.session_key, s.user_id, u.username, u.email
                FROM user_tokens t
                INNER JOIN user_sessions s ON s.session_key = t.session_key
                INNER JOIN users u ON u.id = s.user_id
                WHERE t.token_hash = :token_hash
                  AND t.type = "refresh"
                  AND t.used_at IS NULL
                  AND t.expires_at > NOW()
                  AND s.revoked_at IS NULL
                  AND s.expires_at > NOW()
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function cleanupExpired(int $daysOld = 7): int
    {
        $sql = 'DELETE FROM user_tokens WHERE (expires_at < DATE_SUB(NOW(), INTERVAL :days DAY)) OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL :days DAY))';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':days', $daysOld, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
