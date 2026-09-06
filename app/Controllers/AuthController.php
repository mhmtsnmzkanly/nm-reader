<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Helpers\ResponseHelper;
use App\Services\AuthService;
use App\Services\SiteConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for handling Authentication-related requests.
 *
 * Provides endpoints for user registration, login (with cookie management),
 * logout, password reset, email verification, session refreshing, and revocation.
 *
 * @package App\Controllers
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SiteConfigService $siteConfig
    ) {
    }

    /**
     * Verifies the Cloudflare Turnstile token.
     */
    private function verifyTurnstile(array $payload, string $ip): void
    {
        $siteKey = trim((string) ($_ENV['CLOUDFLARE_TURNSTILE_SITE_KEY'] ?? getenv('CLOUDFLARE_TURNSTILE_SITE_KEY') ?: ''));
        $secretKey = trim((string) ($_ENV['CLOUDFLARE_TURNSTILE_SECRET_KEY'] ?? getenv('CLOUDFLARE_TURNSTILE_SECRET_KEY') ?: ''));

        if ($siteKey === '' || $secretKey === '') {
            return;
        }

        $token = (string) ($payload['turnstile_token'] ?? '');
        if ($token === '') {
            throw new \InvalidArgumentException('Please complete the security check.');
        }

        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
        $data = [
            'secret' => $secretKey,
            'response' => $token,
            'remoteip' => $ip
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('Turnstile verification error: ' . $error);
            throw new \RuntimeException('Security verification is temporarily unavailable. Please try again.');
        }

        curl_close($ch);

        $response = json_decode((string) $result, true);
        if (!($response['success'] ?? false)) {
            throw new \InvalidArgumentException('Security check failed. Please try again.');
        }
    }

    private function resolveAppUrl(ServerRequestInterface $request): string
    {
        $siteAddress = trim($this->siteConfig->siteAddress());
        if ($siteAddress !== '') {
            return rtrim($siteAddress, '/');
        }

        $uri = $request->getUri();
        $scheme = $uri->getScheme() ?: 'http';
        $host = $uri->getHost() ?: 'localhost';
        $port = $uri->getPort();
        $portStr = ($port && !in_array($port, [80, 443], true)) ? ":{$port}" : '';

        return "{$scheme}://{$host}{$portStr}";
    }

    private function rememberCookie(ServerRequestInterface $request, string $value, int $expires): string
    {
        $settings = Config::getSettings()['app'];
        $sameSite = ucfirst(strtolower((string) ($settings['remember_cookie_same_site'] ?? 'Lax')));
        if (!in_array($sameSite, ['Lax', 'Strict', 'None'], true)) {
            $sameSite = 'Lax';
        }
        $forwardedProto = strtolower(trim($request->getHeaderLine('X-Forwarded-Proto')));
        $secure = (bool) ($settings['remember_cookie_secure'] ?? false)
            || $request->getUri()->getScheme() === 'https'
            || $forwardedProto === 'https';
        if ($sameSite === 'None') {
            $secure = true;
        }

        return sprintf(
            'nm_remember=%s; Expires=%s; Path=/; HttpOnly; SameSite=%s%s',
            $value,
            gmdate('D, d M Y H:i:s T', $expires),
            $sameSite,
            $secure ? '; Secure' : ''
        );
    }

    /**
     * Handles user registration.
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $this->verifyTurnstile($payload, $ip);

            $user = $this->authService->register($payload, $this->resolveAppUrl($request));
            return ResponseHelper::created($user);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\RuntimeException $exception) {
            return ResponseHelper::error(503, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(409, $exception->getMessage());
        }
    }

    /**
     * Handles user login and optional 'remember-me' cookie issuance.
     */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $ua = (string) ($request->getHeaderLine('User-Agent') ?: 'unknown');
            
            $this->verifyTurnstile($payload, $ip);
            
            $user = $this->authService->login($payload, $ip, $ua);

            $clientType = strtolower(trim((string) ($payload['client_type'] ?? 'browser')));
            $refreshToken = (string) ($user['refresh_token'] ?? '');
            $cookie = null;
            if ($clientType === 'browser' && $refreshToken !== '') {
                $days = (int) (Config::getSettings()['app']['refresh_token_days'] ?? 30);
                $cookie = $this->rememberCookie($request, $refreshToken, time() + ($days * 86400));
                unset($user['refresh_token']);
            }

            $res = ResponseHelper::success($user);
            if ($cookie !== null) $res = $res->withHeader('Set-Cookie', $cookie);
            return $res;
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\RuntimeException $exception) {
            return ResponseHelper::error(503, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(401, $exception->getMessage());
        }
    }

    /**
     * Refreshes the session using a long-lived refresh token.
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $clientType = strtolower(trim((string) ($payload['client_type'] ?? 'browser')));
            $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
            if ($refreshToken === '') {
                $refreshToken = trim((string) ($request->getCookieParams()['nm_remember'] ?? ''));
            }
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $ua = (string) ($request->getHeaderLine('User-Agent') ?: 'unknown');
            $user = $this->authService->refresh($refreshToken, $ip, $ua);

            $_SESSION['user_id'] = (string) $user['id'];
            $_SESSION['username'] = (string) $user['username'];
            $_SESSION['roles'] = $user['roles'];
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['is_admin'] = in_array('admin.panel.access', $user['permissions'], true);
            $_SESSION['session_key'] = (string) $user['session_key'];
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
            }
            $user['csrf_token'] = (string) $_SESSION['csrf_token'];

            $newRefreshToken = (string) ($user['refresh_token'] ?? '');
            $cookie = null;
            if ($clientType === 'browser' && $newRefreshToken !== '') {
                $days = (int) (Config::getSettings()['app']['refresh_token_days'] ?? 30);
                $cookie = $this->rememberCookie($request, $newRefreshToken, time() + ($days * 86400));
                unset($user['refresh_token']);
            }

            $res = ResponseHelper::success($user);
            return $cookie === null ? $res : $res->withHeader('Set-Cookie', $cookie);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(401, $exception->getMessage());
        }
    }

    public function csrf(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        }
        return ResponseHelper::success(['csrf_token' => (string) $_SESSION['csrf_token']]);
    }

    /**
     * Handles forgot password requests.
     */
    public function forgotPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $this->verifyTurnstile($payload, $ip);

            $email = (string) ($payload['email'] ?? '');
            $this->authService->forgotPassword($email, $this->resolveAppUrl($request));

            return ResponseHelper::success([
                'message' => 'Eğer e-posta adresi kayıtlı ise sıfırlama bağlantısı gönderilmiştir.'
            ]);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(500, 'Şifre sıfırlama işlemi sırasında bir hata oluştu.');
        }
    }

    /**
     * Resets password using token.
     */
    public function resetPassword(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $token = (string) ($payload['token'] ?? '');
            $password = (string) ($payload['password'] ?? '');

            $result = $this->authService->resetPassword($token, $password);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(500, 'Şifre güncellenirken bir hata oluştu.');
        }
    }

    /**
     * Verifies email address using token.
     */
    public function verifyEmail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $token = (string) ($payload['token'] ?? ($request->getQueryParams()['token'] ?? ''));

            $result = $this->authService->verifyEmail($token);
            return ResponseHelper::success($result);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(500, 'E-posta doğrulanırken bir hata oluştu.');
        }
    }

    /**
     * Resends verification email for authenticated user.
     */
    public function resendVerificationEmail(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $userId = (string) $request->getAttribute('user_id');
            if ($userId === '') {
                return ResponseHelper::error(401, 'Unauthorized');
            }

            $this->authService->resendVerificationEmail($userId, $this->resolveAppUrl($request));
            return ResponseHelper::success(['message' => 'Doğrulama e-postası tekrar gönderildi.']);
        } catch (\DomainException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\Throwable $exception) {
            return ResponseHelper::error(500, 'Doğrulama e-postası gönderilemedi.');
        }
    }

    /**
     * Destroys the current user session and clears authentication cookies.
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sessionKey = isset($_SESSION['session_key']) ? (string) $_SESSION['session_key'] : null;
        $this->authService->logout($sessionKey);
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
            session_destroy();
        }

        $res = ResponseHelper::success(['logged_out' => true]);
        $expiredRememberCookie = $this->rememberCookie($request, '', 0);
        $sessionSettings = Config::getSettings()['app'];
        $sessionSameSite = ucfirst(strtolower((string) ($sessionSettings['session_same_site'] ?? 'Lax')));
        if (!in_array($sessionSameSite, ['Lax', 'Strict', 'None'], true)) $sessionSameSite = 'Lax';
        $isSecure = (bool) ($sessionSettings['session_cookie_secure'] ?? false)
            || $request->getUri()->getScheme() === 'https'
            || strtolower(trim($request->getHeaderLine('X-Forwarded-Proto'))) === 'https';
        if ($sessionSameSite === 'None') $isSecure = true;
        $sessionCookie = sprintf(
            '%s=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=%s%s',
            session_name(),
            $sessionSameSite,
            $isSecure ? '; Secure' : ''
        );

        $res = $res->withAddedHeader('Set-Cookie', $expiredRememberCookie)
                   ->withAddedHeader('Set-Cookie', $sessionCookie);

        if ($request->getMethod() === 'GET') {
            return $res->withStatus(302)->withHeader('Location', '/');
        }

        return $res;
    }

    /**
     * Lists all active sessions for the authenticated user.
     */
    public function sessions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->authService->listSessions($userId));
    }

    /**
     * Terminates a specific session belonging to the user.
     */
    public function revokeSession(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $sessionKey = (string) ($args['sessionKey'] ?? '');
        if ($sessionKey === '') {
            return ResponseHelper::error(400, 'sessionKey is required');
        }

        $this->authService->revokeSession($userId, $sessionKey);
        return ResponseHelper::success(['revoked' => true]);
    }

    public function revokeOtherSessions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        $currentSessionKey = (string) ($_SESSION['session_key'] ?? '');
        if ($currentSessionKey === '') {
            return ResponseHelper::error(409, 'Current session could not be identified');
        }

        return ResponseHelper::success([
            'revoked_count' => $this->authService->revokeOtherSessions($userId, $currentSessionKey),
        ]);
    }
}
