<?php

declare(strict_types=1);

namespace App\Controllers;

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

            $res = ResponseHelper::success($user);
            if (!empty($user['refresh_token'])) {
                $expires = time() + (30 * 24 * 60 * 60);
                $isSecure = ($request->getUri()->getScheme() === 'https') || $this->siteConfig->enforceHttps();
                $res = $res->withHeader(
                    'Set-Cookie',
                    "nm_remember={$user['refresh_token']}; Expires=" . gmdate('D, d M Y H:i:s T', $expires) . "; Path=/; HttpOnly; SameSite=Lax" . ($isSecure ? '; Secure' : '')
                );
            }
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
            $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $ua = (string) ($request->getHeaderLine('User-Agent') ?: 'unknown');
            $user = $this->authService->refresh($refreshToken, $ip, $ua);
            if (!isset($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
            }
            $user['csrf_token'] = (string) $_SESSION['csrf_token'];
            return ResponseHelper::success($user);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(401, $exception->getMessage());
        }
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
        $isSecure = ($request->getUri()->getScheme() === 'https') || $this->siteConfig->enforceHttps();
        $secureSuffix = $isSecure ? '; Secure' : '';
        
        $res = $res->withAddedHeader('Set-Cookie', 'nm_remember=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=Lax' . $secureSuffix)
                   ->withAddedHeader('Set-Cookie', session_name() . '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=Lax' . $secureSuffix);

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
}
