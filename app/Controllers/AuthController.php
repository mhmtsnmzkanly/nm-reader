<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for handling Authentication-related requests.
 *
 * Provides endpoints for user registration, login (with cookie management),
 * logout, session refreshing, and administrative session revocation.
 *
 * @package App\Controllers
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly \App\Services\SiteConfigService $siteConfig
    ) {
    }

    /**
     * Verifies the Cloudflare Turnstile token.
     */
    private function verifyTurnstile(array $payload, string $ip): void
    {
        $siteKey = $this->siteConfig->get('integrations')['cloudflare_turnstile_site_key'] ?? '';
        $secretKey = $this->siteConfig->get('integrations')['cloudflare_turnstile_secret_key'] ?? '';

        if (empty($siteKey) || empty($secretKey)) {
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

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            return; // Fail gracefully if API is down
        }

        $response = json_decode($result, true);
        if (!($response['success'] ?? false)) {
            throw new \InvalidArgumentException('Security check failed. Please try again.');
        }
    }

    /**
     * Handles user registration.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface 201 Created on success, or error JSON.
     */
    public function register(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $this->verifyTurnstile($payload, $ip);

            $user = $this->authService->register($payload);
            return ResponseHelper::created($user);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(409, $exception->getMessage());
        }
    }

    /**
     * Handles user login and optional 'remember-me' cookie issuance.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface User data JSON with Set-Cookie header if applicable.
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
                $res = $res->withHeader(
                    'Set-Cookie',
                    "nm_remember={$user['refresh_token']}; Expires=" . gmdate('D, d M Y H:i:s T', $expires) . "; Path=/; HttpOnly; SameSite=Lax"
                );
            }
            return $res;
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(401, $exception->getMessage());
        }
    }

    /**
     * Refreshes the session using a long-lived refresh token.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface Updated user/token JSON.
     */
    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $payload = (array) $request->getParsedBody();
            $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
            $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
            $ua = (string) ($request->getHeaderLine('User-Agent') ?: 'unknown');
            $user = $this->authService->refresh($refreshToken, $ip, $ua);
            return ResponseHelper::success($user);
        } catch (\InvalidArgumentException $exception) {
            return ResponseHelper::error(400, $exception->getMessage());
        } catch (\DomainException $exception) {
            return ResponseHelper::error(401, $exception->getMessage());
        }
    }

    /**
     * Destroys the current user session and clears authentication cookies.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface Redirect for GET requests, JSON for API calls.
     */
    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sessionKey = isset($_SESSION['session_key']) ? (string) $_SESSION['session_key'] : null;
        $this->authService->logout($sessionKey);
        
        // Thoroughly clear session data
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_destroy();
        }

        $res = ResponseHelper::success(['logged_out' => true]);
        
        // Expire remember-me cookie and session cookie again just in case
        $res = $res->withAddedHeader('Set-Cookie', 'nm_remember=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=Lax')
                   ->withAddedHeader('Set-Cookie', session_name() . '=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Path=/; HttpOnly; SameSite=Lax');

        if ($request->getMethod() === 'GET') {
            return $res->withStatus(302)->withHeader('Location', '/');
        }

        return $res;
    }

    /**
     * Lists all active sessions for the authenticated user.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface JSON list of sessions.
     */
    public function sessions(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id');
        return ResponseHelper::success($this->authService->listSessions($userId));
    }

    /**
     * Terminates a specific session belonging to the user.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $args Contains 'sessionKey'.
     * @return ResponseInterface JSON confirmation.
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
