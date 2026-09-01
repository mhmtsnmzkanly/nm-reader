<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Validator;
use App\Repositories\UserRepository;
use App\Repositories\UserTokenRepository;
use App\Services\AnalyticsService;
use App\Services\AuthorizationService;
use App\Services\CacheService;
use App\Services\MailService;
use PDO;

/**
 * Service for handling Authentication, Registration, Password Resets, Email Verifications, and Sessions.
 *
 * @package App\Services
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserTokenRepository $userTokens,
        private readonly MailService $mailService,
        private readonly CacheService $cache,
        private readonly PDO $pdo,
        private readonly AnalyticsService $analytics,
        private readonly AuthorizationService $authorization,
        private readonly int $sessionLifetimeSeconds = 7200,
        private readonly int $refreshTokenDays = 30
    ) {
    }

    /**
     * Registers a new user.
     *
     * @param array $payload Input data (username, email, password).
     * @param string|null $appUrl
     * @return array The newly created user's basic info.
     */
    public function register(array $payload, ?string $appUrl = null): array
    {
        $error = Validator::requireFields($payload, ['username', 'email', 'password']);
        if ($error !== null) {
            throw new \InvalidArgumentException($error);
        }

        $username = Validator::sanitizeText((string) $payload['username']);
        $email = strtolower(trim((string) $payload['email']));
        $password = (string) $payload['password'];

        if (!Validator::validUsername($username)) {
            throw new \InvalidArgumentException('Username must be 3-30 chars and contain only letters, numbers, underscore');
        }

        if (!Validator::validEmail($email)) {
            throw new \InvalidArgumentException('Email format is invalid');
        }

        if (!Validator::validPassword($password)) {
            throw new \InvalidArgumentException('Password must be 8-128 chars and include upper, lower, number');
        }

        if ($this->users->findByEmail($email) !== null) {
            throw new \DomainException('Email already exists');
        }

        if ($this->users->findByUsername($username) !== null) {
            throw new \DomainException('Username already exists');
        }

        $id = null;
        for ($i = 0; $i < 10; $i++) {
            $candidateId = $this->generateUserId();
            if ($this->users->findById($candidateId) !== null) {
                continue;
            }

            $id = $this->users->create($candidateId, $username, $email, password_hash($password, PASSWORD_DEFAULT));
            break;
        }

        if ($id === null) {
            throw new \RuntimeException('Unable to generate user id');
        }

        // Send email verification if configured
        if ($this->mailService->isMailEnabled() && $this->mailService->isSendOnRegisterEnabled()) {
            try {
                $verificationToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $verificationToken);
                $expiresAt = (new \DateTimeImmutable())->modify('+24 hours');
                $this->userTokens->createToken('email_verification', $tokenHash, $expiresAt, $id, $email);
                $this->mailService->sendEmailVerification($email, $username, $verificationToken, $appUrl ?: 'http://localhost:3000');
            } catch (\Throwable) {
                // Email failure on register should not abort account creation
            }
        }

        return [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'email_verified' => false,
        ];
    }

    /**
     * Authenticates a user and establishes a session.
     */
    public function login(array $payload, string $ip, string $userAgent): array
    {
        $identity = strtolower(trim((string) ($payload['identity'] ?? $payload['email'] ?? $payload['username'] ?? '')));
        $password = (string) ($payload['password'] ?? '');
        $remember = (bool) ($payload['remember'] ?? false);

        if ($identity === '') {
            throw new \InvalidArgumentException('"email" is required');
        }

        if ($password === '') {
            throw new \InvalidArgumentException('"password" is required');
        }

        try {
            $this->assertLoginAllowed($identity, $ip);
        } catch (\DomainException $e) {
            $this->recordLoginEvent($identity, null, $ip, $userAgent, false, 'auth.rate_limited');
            throw new \DomainException('auth.rate_limited');
        }

        $user = str_contains($identity, '@')
            ? $this->users->findByEmail($identity)
            : ($this->users->findByUsername($identity) ?? $this->users->findByEmail($identity));

        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            $this->recordFailedLogin($identity, $ip);
            $this->recordLoginEvent($identity, null, $ip, $userAgent, false, 'auth.invalid_credentials');
            throw new \DomainException('auth.invalid_credentials');
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (string) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        $roles = $this->authorization->normalizeRoles($this->resolveRoles((string) $user['id']));
        $permissions = $this->authorization->resolveEffectivePermissions(
            $roles,
            [],
            (string) $user['id']
        );
        $_SESSION['roles'] = $roles;
        $_SESSION['permissions'] = $permissions;
        $_SESSION['is_admin'] = in_array('admin.panel.access', $permissions, true);

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        }

        $sessionKey = bin2hex(random_bytes(16));
        $sessionTtlSeconds = $remember
            ? $this->refreshTokenDays * 24 * 60 * 60
            : $this->sessionLifetimeSeconds;
        $expiresAt = (new \DateTimeImmutable())->modify('+' . $sessionTtlSeconds . ' seconds');

        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions (session_key, user_id, ip_hash, user_agent, expires_at)
             VALUES (:session_key, :user_id, :ip_hash, :user_agent, :expires_at)'
        );
        $stmt->execute([
            'session_key' => $sessionKey,
            'user_id' => (string) $user['id'],
            'ip_hash' => hash('sha256', $ip),
            'user_agent' => substr($userAgent, 0, 255),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        $_SESSION['session_key'] = $sessionKey;

        $refreshToken = null;
        if ($remember) {
            $refreshToken = bin2hex(random_bytes(48));
            $tokenHash = hash('sha256', $refreshToken);
            $this->userTokens->createToken('refresh', $tokenHash, $expiresAt, (string) $user['id'], null, $sessionKey);
        }

        $userEmail = (string) ($user['email'] ?? $identity);
        $this->clearFailedLogins($userEmail, $ip);
        $this->recordLoginEvent($userEmail, (string) $user['id'], $ip, $userAgent, true, null);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $token = bin2hex(random_bytes(32));
        $tokenExpiry = date('Y-m-d H:i:s', time() + (365 * 24 * 60 * 60)); // 1 year for mobile
        $stmt = $this->pdo->prepare('UPDATE users SET api_token = :token, api_token_expires_at = :expires WHERE id = :id');
        $stmt->execute(['token' => $token, 'expires' => $tokenExpiry, 'id' => $user['id']]);

        return [
            'id' => (string) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'email_verified' => !empty($user['email_verified_at']),
            'csrf_token' => (string) $_SESSION['csrf_token'],
            'refresh_token' => $refreshToken,
            'api_token' => $token,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    /**
     * Refreshes a session using a valid refresh token.
     */
    public function refresh(string $refreshToken, string $ip, string $userAgent): array
    {
        if ($refreshToken === '') {
            throw new \InvalidArgumentException('refresh_token is required');
        }

        $hash = hash('sha256', $refreshToken);
        $row = $this->userTokens->findValidRefreshToken($hash);
        if ($row === null) {
            throw new \DomainException('Invalid refresh token');
        }

        $sessionKey = (string) $row['session_key'];
        $newToken = bin2hex(random_bytes(48));
        $newHash = hash('sha256', $newToken);

        // Enforce simple device binding
        $stmt = $this->pdo->prepare(
            'SELECT ip_hash, user_agent FROM user_sessions WHERE session_key = :session_key LIMIT 1'
        );
        $stmt->execute(['session_key' => $sessionKey]);
        $sessionRow = $stmt->fetch();
        if ($sessionRow !== false) {
            $storedUa = (string) ($sessionRow['user_agent'] ?? '');
            $storedIpHash = (string) ($sessionRow['ip_hash'] ?? '');
            $uaPercent = 0.0;
            similar_text(substr($storedUa, 0, 64), substr($userAgent, 0, 64), $uaPercent);
            $ipMatches = hash_equals($storedIpHash, hash('sha256', $ip));
            if ($uaPercent < 30.0 && !$ipMatches) {
                throw new \DomainException('Refresh denied: device changed');
            }
        }

        $this->pdo->beginTransaction();
        try {
            $this->userTokens->consumeToken($hash);

            $expiresAt = (new \DateTimeImmutable())->modify('+' . $this->refreshTokenDays . ' days');
            $this->userTokens->createToken('refresh', $newHash, $expiresAt, (string) $row['user_id'], null, $sessionKey);

            $touchSession = $this->pdo->prepare(
                'UPDATE user_sessions
                 SET ip_hash = :ip_hash, user_agent = :user_agent, last_seen_at = NOW(), expires_at = DATE_ADD(NOW(), INTERVAL :days DAY)
                 WHERE session_key = :session_key'
            );
            $touchSession->execute([
                'ip_hash' => hash('sha256', $ip),
                'user_agent' => substr($userAgent, 0, 255),
                'session_key' => $sessionKey,
                'days' => $this->refreshTokenDays,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $roles = $this->authorization->normalizeRoles($this->resolveRoles((string) $row['user_id']));
        $permissions = $this->authorization->resolveEffectivePermissions(
            $roles,
            [],
            (string) $row['user_id']
        );

        return [
            'id' => (string) $row['user_id'],
            'username' => (string) $row['username'],
            'email' => (string) $row['email'],
            'refresh_token' => $newToken,
            'session_key' => $sessionKey,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    /**
     * Initiates a password reset request.
     */
    public function forgotPassword(string $email, ?string $appUrl = null): void
    {
        $email = strtolower(trim($email));
        if (!Validator::validEmail($email)) {
            throw new \InvalidArgumentException('Email format is invalid');
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            // Mitigate user enumeration: return silently
            return;
        }

        $userId = (string) $user['id'];
        $this->userTokens->revokeActiveTokensForUser($userId, 'password_reset');

        $resetToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $resetToken);
        $expiresAt = (new \DateTimeImmutable())->modify('+1 hour');

        $this->userTokens->createToken('password_reset', $tokenHash, $expiresAt, $userId, $email);
        $this->mailService->sendPasswordReset($email, (string) $user['username'], $resetToken, $appUrl ?: 'http://localhost:3000');
    }

    /**
     * Resets a user password using a verified token.
     */
    public function resetPassword(string $token, string $password): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Token is required');
        }

        if (!Validator::validPassword($password)) {
            throw new \InvalidArgumentException('Password must be 8-128 chars and include upper, lower, number');
        }

        $tokenHash = hash('sha256', $token);
        $tokenRow = $this->userTokens->findValidToken($tokenHash, 'password_reset');
        if ($tokenRow === null) {
            throw new \DomainException('auth.invalid_or_expired_reset_token');
        }

        $userId = (string) $tokenRow['user_id'];
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $this->users->updatePassword($userId, $newHash);
        $this->userTokens->consumeToken($tokenHash);

        // Invalidate active sessions on password reset
        $stmt = $this->pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);

        return [
            'id' => $userId,
            'message' => 'Password reset successfully',
        ];
    }

    /**
     * Verifies user email with a token.
     */
    public function verifyEmail(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            throw new \InvalidArgumentException('Token is required');
        }

        $tokenHash = hash('sha256', $token);
        $tokenRow = $this->userTokens->findValidToken($tokenHash, 'email_verification');
        if ($tokenRow === null) {
            throw new \DomainException('auth.invalid_or_expired_verification_token');
        }

        $userId = (string) $tokenRow['user_id'];
        $this->users->markEmailVerified($userId);
        $this->userTokens->consumeToken($tokenHash);

        return [
            'id' => $userId,
            'email_verified' => true,
        ];
    }

    /**
     * Resends email verification link for an authenticated user.
     */
    public function resendVerificationEmail(string $userId, ?string $appUrl = null): void
    {
        $user = $this->users->findById($userId);
        if ($user === null) {
            throw new \DomainException('User not found');
        }

        if (!empty($user['email_verified_at'])) {
            throw new \DomainException('auth.email_already_verified');
        }

        $email = (string) $user['email'];
        $username = (string) $user['username'];

        $this->userTokens->revokeActiveTokensForUser($userId, 'email_verification');

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = (new \DateTimeImmutable())->modify('+24 hours');

        $this->userTokens->createToken('email_verification', $tokenHash, $expiresAt, $userId, $email);
        $this->mailService->sendEmailVerification($email, $username, $token, $appUrl ?: 'http://localhost:3000');
    }

    /**
     * Revokes a specific session and its associated tokens.
     */
    public function logout(?string $sessionKey): void
    {
        if ($sessionKey === null || $sessionKey === '') {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions SET revoked_at = NOW() WHERE session_key = :session_key'
        );
        $stmt->execute(['session_key' => $sessionKey]);

        $this->userTokens->revokeTokensBySessionKey($sessionKey);
    }

    /**
     * Lists active sessions for a user.
     */
    public function listSessions(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT session_key, user_agent, last_seen_at, created_at, expires_at
             FROM user_sessions
             WHERE user_id = :user_id AND revoked_at IS NULL
             ORDER BY last_seen_at DESC'
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Forcefully revokes a session by ID.
     */
    public function revokeSession(string $userId, string $sessionKey, ?string $moderatorId = null): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE user_sessions
             SET revoked_at = NOW()
             WHERE user_id = :user_id AND session_key = :session_key'
        );
        $stmt->execute([
            'user_id' => $userId,
            'session_key' => $sessionKey,
        ]);

        $this->userTokens->revokeTokensBySessionKey($sessionKey);

        if ($moderatorId !== null) {
            try {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO admin_actions (moderator_user_id, target_type, target_id, action, reason, created_at)
                     VALUES (:mod, "user", :uid, "revoke_session", :reason, NOW())'
                );
                $stmt->execute([
                    'mod' => $moderatorId,
                    'uid' => $userId,
                    'reason' => json_encode(['session_key' => $sessionKey])
                ]);
            } catch (\Throwable) {
                // Ignore audit errors here
            }
        }
    }

    private function resolveRoles(string $userId): array
    {
        $roles = ['user'];

        try {
            $stmt = $this->pdo->prepare('SELECT roles FROM users WHERE id = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $userRolesRaw = (string) ($stmt->fetchColumn() ?: '');
            
            if ($userRolesRaw !== '') {
                $roleIds = explode(',', $userRolesRaw);
                
                $config = \App\Config::getSettings()['rbac'] ?? [];
                $idToSlug = array_flip((array) ($config['id_map'] ?? []));
                
                foreach ($roleIds as $id) {
                    $slug = $idToSlug[$id] ?? null;
                    if ($slug && !in_array($slug, $roles, true)) {
                        $roles[] = $slug;
                    }
                }
            }
        } catch (\Throwable) {
            // Fallback to basic 'user' role
        }

        return array_values($roles);
    }

    private function assertLoginAllowed(string $email, string $ip): void
    {
        $emailKey = 'auth_login_fail_email_' . hash('sha256', $email);
        $ipKey = 'auth_login_fail_ip_' . hash('sha256', $ip);
        $emailFail = (int) $this->cache->get($emailKey, 0);
        $ipFail = (int) $this->cache->get($ipKey, 0);
        if ($emailFail >= 10 || $ipFail >= 30) {
            throw new \DomainException('Too many failed login attempts');
        }
    }

    private function recordFailedLogin(string $email, string $ip): void
    {
        $emailKey = 'auth_login_fail_email_' . hash('sha256', $email);
        $ipKey = 'auth_login_fail_ip_' . hash('sha256', $ip);
        $this->cache->set($emailKey, ((int) $this->cache->get($emailKey, 0)) + 1, 900);
        $this->cache->set($ipKey, ((int) $this->cache->get($ipKey, 0)) + 1, 900);
    }

    private function clearFailedLogins(string $email, string $ip): void
    {
        $this->cache->delete('auth_login_fail_email_' . hash('sha256', $email));
        $this->cache->delete('auth_login_fail_ip_' . hash('sha256', $ip));
    }

    private function recordLoginEvent(
        string $email,
        ?string $userId,
        string $ip,
        string $userAgent,
        bool $success,
        ?string $failureReason
    ): void {
        $eventType = $success ? 'auth_login_success' : 'auth_login_failed';
        $this->analytics->track(
            $eventType,
            $userId,
            'auth',
            $userId,
            [
                'failure_reason' => $failureReason,
                'email_hash' => hash('sha256', strtolower($email)),
            ],
            $ip
        );

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO user_login_logs (
                    user_id, email, ip_hash, user_agent, success, failure_reason, attempted_at
                ) VALUES (
                    :user_id, :email, :ip_hash, :user_agent, :success, :failure_reason, NOW()
                )'
            );
            $stmt->execute([
                'user_id' => $userId,
                'email' => substr($email, 0, 150),
                'ip_hash' => hash('sha256', $ip),
                'user_agent' => substr($userAgent, 0, 255),
                'success' => $success ? 1 : 0,
                'failure_reason' => $failureReason,
            ]);
        } catch (\Throwable) {
            // Login event insert errors should not block authentication flow.
        }
    }

    private function generateUserId(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $maxIndex = strlen($alphabet) - 1;
        $id = '';

        for ($i = 0; $i < 8; $i++) {
            $id .= $alphabet[random_int(0, $maxIndex)];
        }

        return $id;
    }
}
